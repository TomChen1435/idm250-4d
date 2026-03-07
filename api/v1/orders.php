<?php
/**
 * WMS API - Receive Order from CMS
 * Unit-Based Model (Workbook Structure)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connect.php';

// Read environment variables
$env = parse_ini_file(__DIR__ . '/../../.env');

// Validate API Key
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
    echo json_encode(['error' => 'Method Not Allowed', 'details' => 'Use POST method']);
    exit;
}

// Read and parse JSON body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON', 'details' => json_last_error_msg()]);
    exit;
}

// Validate required fields
$order_number = null;
$customer_name = null;
$address = '';

// Order number
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

// Build address from separate fields
$ship_to_street = isset($data['ship_to_street']) ? $data['ship_to_street'] : '';
$ship_to_city = isset($data['ship_to_city']) ? $data['ship_to_city'] : '';
$ship_to_state = isset($data['ship_to_state']) ? $data['ship_to_state'] : '';
$ship_to_zip = isset($data['ship_to_zip']) ? $data['ship_to_zip'] : '';

// Validate items
if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Items array is required and must not be empty']);
    exit;
}

// Check for duplicate order
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
    // Create order header
    $stmt = $mysqli->prepare("INSERT INTO orders (order_number, ship_to_company, ship_to_street, ship_to_city, ship_to_state, ship_to_zip, status, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param('ssssss', $order_number, $customer_name, $ship_to_street, $ship_to_city, $ship_to_state, $ship_to_zip);
    $stmt->execute();
    $order_id = $mysqli->insert_id;
    
    // Process each individual unit
    $missing_skus = [];
    $missing_units = [];
    $unit_count = 0;
    
    foreach ($data['items'] as $item) {
        // Validate unit has required fields
        if (empty($item['sku'])) {
            throw new Exception('Each item must have sku');
        }
        
        if (empty($item['unit_id'])) {
            throw new Exception('Each item must have unit_id');
        }
        
        $sku = $mysqli->real_escape_string($item['sku']);
        $unit_id = $mysqli->real_escape_string($item['unit_id']);
        
        // Check if SKU exists
        $sku_stmt = $mysqli->prepare("SELECT id FROM sku WHERE sku = ?");
        $sku_stmt->bind_param('s', $sku);
        $sku_stmt->execute();
        $sku_result = $sku_stmt->get_result();
        
        if ($sku_result->num_rows === 0) {
            // SKU doesn't exist - create it if we have details
            if (isset($item['sku_details'])) {
                $details = $item['sku_details'];
                
                // Map CMS field names to WMS field names
                $description = $details['description'] ?? '';
                $uom = $details['uom'] ?? $details['uom_primary'] ?? '';
                $pieces = intval($details['pieces'] ?? 0);
                $length = floatval($details['length'] ?? $details['length_inches'] ?? 0);
                $width = floatval($details['width'] ?? $details['width_inches'] ?? 0);
                $height = floatval($details['height'] ?? $details['height_inches'] ?? 0);
                $weight = floatval($details['weight'] ?? $details['weight_lbs'] ?? 0);
                $ficha = intval($details['ficha'] ?? 0);
                
                // Auto-create SKU
                $insert_sku = $mysqli->prepare("INSERT INTO sku (sku, description, uom, pieces, length, width, height, weight, ficha) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_sku->bind_param('ssidddddi', $sku, $description, $uom, $pieces, $length, $width, $height, $weight, $ficha);
                $insert_sku->execute();
            } else {
                $missing_skus[] = $sku;
                continue;
            }
        } else {
            // SKU exists - update it if we have new details
            if (isset($item['sku_details'])) {
                $details = $item['sku_details'];
                
                // Map CMS field names to WMS field names
                $description = $details['description'] ?? '';
                $uom = $details['uom'] ?? $details['uom_primary'] ?? '';
                $pieces = intval($details['pieces'] ?? 0);
                $length = floatval($details['length'] ?? $details['length_inches'] ?? 0);
                $width = floatval($details['width'] ?? $details['width_inches'] ?? 0);
                $height = floatval($details['height'] ?? $details['height_inches'] ?? 0);
                $weight = floatval($details['weight'] ?? $details['weight_lbs'] ?? 0);
                $ficha = intval($details['ficha'] ?? 0);
                
                // Update existing SKU with new details
                $update_sku = $mysqli->prepare("UPDATE sku 
                                                SET description = ?, 
                                                    uom = ?, 
                                                    pieces = ?, 
                                                    length = ?, 
                                                    width = ?, 
                                                    height = ?, 
                                                    weight = ?, 
                                                    ficha = ?
                                                WHERE sku = ?");
                $update_sku->bind_param('sidddddis', $description, $pieces, $length, $width, $height, $weight, $ficha, $uom, $sku);
                $update_sku->execute();
            }
        }
        
        // Validate that unit_id exists in inventory
        $inv_stmt = $mysqli->prepare("SELECT id FROM inventory WHERE unit_id = ? AND sku = ? AND status = 'available'");
        $inv_stmt->bind_param('ss', $unit_id, $sku);
        $inv_stmt->execute();
        $inv_result = $inv_stmt->get_result();
        
        if ($inv_result->num_rows === 0) {
            $missing_units[] = $unit_id;
            continue;
        }
        
        // Insert individual unit into order_items
        $insert_item = $mysqli->prepare("INSERT INTO order_items (order_id, unit_id, sku) 
                                         VALUES (?, ?, ?)");
        $insert_item->bind_param('iss', $order_id, $unit_id, $sku);
        $insert_item->execute();
        
        $unit_count++;
    }
    
    // If any SKUs were missing and couldn't be created, rollback
    if (!empty($missing_skus)) {
        $mysqli->rollback();
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'details' => 'Missing SKUs in WMS: ' . implode(', ', $missing_skus)
        ]);
        exit;
    }
    
    // If any units were not found in inventory, rollback
    if (!empty($missing_units)) {
        $mysqli->rollback();
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'details' => 'Units not in WMS inventory: ' . implode(', ', $missing_units)
        ]);
        exit;
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Order received successfully',
        'order_id' => $order_id,
        'order_number' => $order_number,
        'customer_name' => $customer_name,
        'units_count' => $unit_count
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'details' => 'Failed to process order: ' . $e->getMessage()
    ]);
}
