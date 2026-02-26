<?php
/**
 * Mock CMS - Receive Order Shipment from WMS
 * This simulates the CMS receiving callbacks from the WMS
 */

header('Content-Type: application/json');

// Read the incoming JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate the request
if (empty($data['action']) || $data['action'] !== 'ship') {
    http_response_code(400);
    echo json_encode([
        'error' => 'Bad Request',
        'details' => 'Invalid action. Expected: ship'
    ]);
    exit;
}

if (empty($data['order_number'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Bad Request',
        'details' => 'Missing order_number'
    ]);
    exit;
}

if (empty($data['shipped_at'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Bad Request',
        'details' => 'Missing shipped_at'
    ]);
    exit;
}

// Log the callback for testing
$log_entry = sprintf(
    "[%s] Order Shipment Received\n  Order Number: %s\n  Action: %s\n  Shipped At: %s\n\n",
    date('Y-m-d H:i:s'),
    $data['order_number'],
    $data['action'],
    $data['shipped_at']
);

$log_file = __DIR__ . '/order_callbacks.log';
file_put_contents($log_file, $log_entry, FILE_APPEND);

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Order shipment received',
    'order_number' => $data['order_number'],
    'shipped_at' => $data['shipped_at']
]);
