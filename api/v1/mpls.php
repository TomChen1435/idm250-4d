<?php


define('API_REQUEST', true);
require_once '../../db_connect.php';
require_once '../../auth.php';

// Load environment config
$env = parse_ini_file(__DIR__ . '/../../.env');

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

// Validate required fields
$required_fields = ['mpl_number', 'items'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'details' => "Missing required field: $field"]);
        exit;
    }
}

if (!is_array($data['items']) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Items array is required and must not be empty']);
    exit;
}

// Check for duplicate MPL
$mpl_number = $mysqli->real_escape_string($data['mpl_number']);
$check_stmt = $mysqli->prepare("SELECT id FROM packing_list WHERE mpl_number = ?");
$check_stmt->bind_param('s', $mpl_number);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Conflict', 'details' => "MPL with reference number $mpl_number already exists"]);
    exit;
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Create MPL header
    $stmt = $mysqli->prepare("INSERT INTO packing_list (mpl_number, status, created_at) VALUES (?, 'pending', NOW())");
    $stmt->bind_param('s', $mpl_number);
    $stmt->execute();
    $mpl_id = $mysqli->insert_id;
    
    // Process items
    $missing_skus = [];
    
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
            // SKU doesn't exist - check if we have details to auto-create
            if (isset($item['sku_details'])) {
                $details = $item['sku_details'];
                
                // Auto-create SKU
                $insert_sku = $mysqli->prepare("INSERT INTO sku (sku, description, uom, pieces, length, width, height, weight, ficha) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_sku->bind_param('ssidddddi',
                    $sku,
                    $details['description'] ?? '',
                    $details['uom'] ?? '',
                    $details['pieces'] ?? 0,
                    $details['length'] ?? 0,
                    $details['width'] ?? 0,
                    $details['height'] ?? 0,
                    $details['weight'] ?? 0,
                    $details['ficha'] ?? 0
                );
                $insert_sku->execute();
                $sku_id = $mysqli->insert_id;
            } else {
                $missing_skus[] = $sku;
                continue;
            }
        } else {
            $sku_row = $sku_result->fetch_assoc();
            $sku_id = $sku_row['id'];
        }
        
        // Insert packing list item
        $item_stmt = $mysqli->prepare("INSERT INTO packing_list_items (mpl_id, sku_id, quantity_expected, quantity_received, status, created_at) 
                                       VALUES (?, ?, ?, 0, 'pending', NOW())");
        $item_stmt->bind_param('iii', $mpl_id, $sku_id, $quantity);
        $item_stmt->execute();
    }
    
    // If there are missing SKUs, rollback and return error
    if (!empty($missing_skus)) {
        $mysqli->rollback();
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'details' => 'Missing SKUs in WMS: ' . implode(', ', $missing_skus) . '. Provide full SKU details to auto-create.'
        ]);
        exit;
    }
    
    $mysqli->commit();
    
    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'MPL received successfully',
        'mpl_id' => $mpl_id,
        'mpl_number' => $data['mpl_number'],
        'items_count' => count($data['items'])
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['error' => 'Failed to process MPL', 'details' => $e->getMessage()]);
}
