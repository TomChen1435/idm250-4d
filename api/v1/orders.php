<?php


define('API_REQUEST', true);
require_once '../../db_connect.php';
require_once '../../auth.php';

// Load environment config
$env_path = __DIR__ . '/../../.env';
if (!file_exists($env_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error', 'details' => '.env file not found']);
    exit;
}
$env = parse_ini_file($env_path);

if (!isset($env['X-API-KEY'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error', 'details' => 'X-API-KEY not found in .env']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Validate API key
$headers = getallheaders();
$headers = array_change_key_case($headers, CASE_LOWER);

if (!isset($headers['x-api-key']) || $headers['x-api-key'] !== $env['X-API-KEY']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'details' => 'Invalid or missing API key']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'details' => 'Only POST requests are accepted']);
    exit;
}

// Read JSON body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON', 'details' => json_last_error_msg()]);
    exit;
}

// Validate required fields - accept BOTH formats
$order_number = null;
$customer_name = null;
$address = '';

// Accept either 'order_number' from both
if (!empty($data['order_number'])) {
    $order_number = $data['order_number'];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required field: order_number']);
    exit;
}

// Accept either 'customer_name' OR 'ship_to_company'
if (!empty($data['customer_name'])) {
    $customer_name = $data['customer_name'];
} elseif (!empty($data['ship_to_company'])) {
    $customer_name = $data['ship_to_company'];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required field: customer_name or ship_to_company']);
    exit;
}

// Build address from either single field OR separate fields
if (!empty($data['address'])) {
    $address = $data['address'];
} else {
    // Build from separate CMS fields
    $parts = [];
    if (!empty($data['ship_to_street'])) $parts[] = $data['ship_to_street'];
    if (!empty($data['ship_to_city'])) $parts[] = $data['ship_to_city'];
    if (!empty($data['ship_to_state'])) $parts[] = $data['ship_to_state'];
    if (!empty($data['ship_to_zip'])) $parts[] = $data['ship_to_zip'];
    $address = implode(', ', $parts);
}

// Validate items
if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Items array is required and must not be empty']);
    exit;
}

// Check for duplicate order
$order_number = $mysqli->real_escape_string($data['order_number']);
$check_stmt = $mysqli->prepare("SELECT id FROM orders WHERE order_number = ?");
$check_stmt->bind_param('s', $order_number);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Conflict', 'details' => "Order with number $order_number already exists"]);
    exit;
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Create order header - using mapped fields
    $order_number_safe = $mysqli->real_escape_string($order_number);
    $customer_name_safe = $mysqli->real_escape_string($customer_name);
    $address_safe = $mysqli->real_escape_string($address);
    
    $stmt = $mysqli->prepare("INSERT INTO orders (order_number, customer_name, address, status, time_created) 
                             VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->bind_param('sss', $order_number_safe, $customer_name_safe, $address_safe);
    $stmt->execute();
    $order_id = $mysqli->insert_id;
    
    // Process items
    $missing_skus = [];
    $insufficient_inventory = [];
    
    foreach ($data['items'] as $item) {
        if (empty($item['sku']) || !isset($item['quantity'])) {
            throw new Exception('Each item must have sku and quantity');
        }
        
        $sku = $mysqli->real_escape_string($item['sku']);
        $quantity = intval($item['quantity']);
        
        // Check if SKU exists
        $sku_stmt = $mysqli->prepare("SELECT id FROM sku WHERE sku = ?");
        $sku_stmt->bind_param('s', $sku);
        $sku_stmt->execute();
        $sku_result = $sku_stmt->get_result();
        
        if ($sku_result->num_rows === 0) {
            $missing_skus[] = $sku;
            continue;
        }
        
        // Check inventory availability (warning only)
        $inv_stmt = $mysqli->prepare("SELECT quantity_available FROM inventory WHERE sku = ?");
        $inv_stmt->bind_param('s', $sku);
        $inv_stmt->execute();
        $inv_result = $inv_stmt->get_result();
        
        if ($inv_result->num_rows > 0) {
            $inv_row = $inv_result->fetch_assoc();
            if ($inv_row['quantity_available'] < $quantity) {
                $insufficient_inventory[] = "$sku (need $quantity, have {$inv_row['quantity_available']})";
            }
        }
        
        // Insert order item with SKU code - using 'ordered' column
        $item_stmt = $mysqli->prepare("INSERT INTO order_items (order_id, sku, ordered, shipped, created_at) 
                                       VALUES (?, ?, ?, 0, NOW())");
        $item_stmt->bind_param('isi', $order_id, $sku, $quantity);
        $item_stmt->execute();
    }
    
    // If there are missing SKUs, rollback and return error
    if (!empty($missing_skus)) {
        $mysqli->rollback();
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'details' => 'SKUs not found in WMS: ' . implode(', ', $missing_skus)
        ]);
        exit;
    }
    
    $mysqli->commit();
    
    // Build response with warnings if needed
    $response = [
        'success' => true,
        'message' => 'Order received successfully',
        'order_id' => $order_id,
        'order_number' => $order_number,
        'customer_name' => $customer_name,
        'items_count' => count($data['items'])
    ];
    
    if (!empty($insufficient_inventory)) {
        $response['warnings'] = [
            'insufficient_inventory' => $insufficient_inventory
        ];
    }
    
    http_response_code(201);
    echo json_encode($response);
    
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['error' => 'Failed to process order', 'details' => $e->getMessage()]);
}
