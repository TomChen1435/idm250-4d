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
$required_fields = ['items'];
$mpl_number = null;

// Accept either 'mpl_number' OR 'reference_number'
if (!empty($data['mpl_number'])) {
    $mpl_number = $data['mpl_number'];
} elseif (!empty($data['reference_number'])) {
    $mpl_number = $data['reference_number'];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required field: mpl_number or reference_number']);
    exit;
}

// Validate items array
if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Items array is required and must not be empty']);
    exit;
}

// Check for duplicate MPL - use the already-set $mpl_number variable
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
    // Create MPL header with trailer_number and expected_arrival
    $trailer_number = isset($data['trailer_number']) ? $data['trailer_number'] : null;
    $expected_arrival = isset($data['expected_arrival']) ? $data['expected_arrival'] : null;
    
    $stmt = $mysqli->prepare("INSERT INTO packing_list (mpl_number, trailer_number, expected_arrival, status, created_at) 
                             VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->bind_param('sss', $mpl_number, $trailer_number, $expected_arrival);
    $stmt->execute();
    $mpl_id = $mysqli->insert_id;
    
    // Process items - aggregate by SKU
    $missing_skus = [];
    $sku_aggregation = [];  // Group items by SKU
    
    // First pass: aggregate items by SKU and collect SKU details
    foreach ($data['items'] as $item) {
        if (empty($item['sku'])) {
            throw new Exception('Each item must have sku');
        }
        
        $sku = $mysqli->real_escape_string($item['sku']);
        
        // Initialize SKU entry if not exists
        if (!isset($sku_aggregation[$sku])) {
            $sku_aggregation[$sku] = [
                'quantity' => 0,
                'sku_details' => isset($item['sku_details']) ? $item['sku_details'] : null
            ];
        }
        
        // If quantity is provided, use it; otherwise count as 1 unit
        if (isset($item['quantity'])) {
            $sku_aggregation[$sku]['quantity'] += intval($item['quantity']);
        } else {
            $sku_aggregation[$sku]['quantity'] += 1;  // Each item = 1 unit
        }
        
        // Keep sku_details if provided (use first occurrence)
        if (!$sku_aggregation[$sku]['sku_details'] && isset($item['sku_details'])) {
            $sku_aggregation[$sku]['sku_details'] = $item['sku_details'];
        }
    }
    
    // Second pass: process aggregated SKUs
    foreach ($sku_aggregation as $sku => $item_data) {
        $quantity = $item_data['quantity'];
        
        // Check if SKU exists
        $sku_stmt = $mysqli->prepare("SELECT id FROM sku WHERE sku = ?");
        $sku_stmt->bind_param('s', $sku);
        $sku_stmt->execute();
        $sku_result = $sku_stmt->get_result();
        
        if ($sku_result->num_rows === 0) {
            // SKU doesn't exist - create it if we have details
            if ($item_data['sku_details']) {
                $details = $item_data['sku_details'];
                
                // Map CMS field names to WMS field names
                $desc = $details['description'] ?? '';
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
                $insert_sku->bind_param('ssidddddi', $sku, $desc, $uom, $pieces, $length, $width, $height, $weight, $ficha);
                $insert_sku->execute();
            } else {
                $missing_skus[] = $sku;
                continue;
            }
        } else {
            // SKU exists - update it if we have new details
            if ($item_data['sku_details']) {
                $details = $item_data['sku_details'];
                
                // Map CMS field names to WMS field names
                $desc = $details['description'] ?? '';
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
                $update_sku->bind_param('sidddddis', $desc, $pieces, $length, $width, $height, $weight, $ficha, $uom, $sku);
                $update_sku->execute();
            }
        }
        
        // Insert packing list item with SKU code (not sku_id)
        // Store quantity_received in a variable
        $qty_received = 0;
        $item_stmt = $mysqli->prepare("INSERT INTO packing_list_items (mpl_id, sku, quantity_expected, quantity_received, status, created_at) 
                                       VALUES (?, ?, ?, ?, 'pending', NOW())");
        $item_stmt->bind_param('isii', $mpl_id, $sku, $quantity, $qty_received);
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
