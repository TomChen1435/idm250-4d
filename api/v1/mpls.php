<?php
/**
 * WMS API - Receive MPL from CMS
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
$reference_number = null;

// Accept either 'mpl_number' OR 'reference_number'
if (!empty($data['mpl_number'])) {
    $reference_number = $data['mpl_number'];
} elseif (!empty($data['reference_number'])) {
    $reference_number = $data['reference_number'];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required field: reference_number']);
    exit;
}

// Validate items array
if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'details' => 'Items array is required and must not be empty']);
    exit;
}

// Check for duplicate MPL
$check_stmt = $mysqli->prepare("SELECT id FROM packing_list WHERE reference_number = ?");
$check_stmt->bind_param('s', $reference_number);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Conflict', 'details' => "MPL with reference number $reference_number already exists"]);
    exit;
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Create MPL header
    $trailer_number = isset($data['trailer_number']) ? $data['trailer_number'] : null;
    $expected_arrival = isset($data['expected_arrival']) ? $data['expected_arrival'] : null;
    
    $stmt = $mysqli->prepare("INSERT INTO packing_list (reference_number, trailer_number, expected_arrival, status, created_at) 
                             VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->bind_param('sss', $reference_number, $trailer_number, $expected_arrival);
    $stmt->execute();
    $mpl_id = $mysqli->insert_id;
    
    // Process each individual unit
    $missing_skus = [];
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
                $insert_sku->bind_param('sssiddddi', $sku, $description, $uom, $pieces, $length, $width, $height, $weight, $ficha);
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
                $update_sku->bind_param('ssiddddis', $description, $uom, $pieces, $length, $width, $height, $weight, $ficha, $sku);
                $update_sku->execute();
            }
        }
        
        // Insert individual unit into packing_list_items
        $insert_item = $mysqli->prepare("INSERT INTO packing_list_items (mpl_id, unit_id, sku, status) 
                                         VALUES (?, ?, ?, 'pending')");
        $insert_item->bind_param('iss', $mpl_id, $unit_id, $sku);
        $insert_item->execute();
        
        $unit_count++;
    }
    
    // If any SKUs were missing and couldn't be created, rollback
    if (!empty($missing_skus)) {
        $mysqli->rollback();
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'details' => 'Missing SKUs in WMS: ' . implode(', ', $missing_skus) . '. Provide full SKU details to auto-create.'
        ]);
        exit;
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'MPL received successfully',
        'mpl_id' => $mpl_id,
        'reference_number' => $reference_number,
        'units_count' => $unit_count
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'details' => 'Failed to process MPL: ' . $e->getMessage()
    ]);
}
