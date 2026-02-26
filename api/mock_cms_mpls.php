<?php
/**
 * Mock CMS - Receive MPL Confirmation from WMS
 * This simulates the CMS receiving callbacks from the WMS
 */

header('Content-Type: application/json');

// Read the incoming JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate the request
if (empty($data['action']) || $data['action'] !== 'confirm') {
    http_response_code(400);
    echo json_encode([
        'error' => 'Bad Request',
        'details' => 'Invalid action. Expected: confirm'
    ]);
    exit;
}

if (empty($data['reference_number'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Bad Request',
        'details' => 'Missing reference_number'
    ]);
    exit;
}

// Log the callback for testing
$log_entry = sprintf(
    "[%s] MPL Confirmation Received\n  Reference: %s\n  Action: %s\n\n",
    date('Y-m-d H:i:s'),
    $data['reference_number'],
    $data['action']
);

$log_file = __DIR__ . '/mpl_callbacks.log';
file_put_contents($log_file, $log_entry, FILE_APPEND);

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'MPL confirmation received',
    'reference_number' => $data['reference_number']
]);
