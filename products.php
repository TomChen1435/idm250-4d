<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  // allows everyone access to this route

require_once "../db-connect.php";
require_once "../auth.php";

check_api_key($env); // checks api key 

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT * FROM sku.id, sku.ficha, sku.description, sku.sku, sku.uom, sku.pieces,sku.length, sku.width, sku.height, sku.weight FROM sku";
}

if (isset($_GET['category'])) {
    $category = $connection->real_escape_string($_GET['category']); //real escape string cleans up input
    $query .= " JOIN product_categories pc ON sku.id = pc.product_id
               JOIN categories c ON pc.category_id = c.id
               WHERE c.name = '$category' 
               "; // should generally use a prepared statement here 
    }
$result = $connection->query($query);
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = $row; // brackets means stick this row at the end of the array
}
//send it back

echo json_encode(['success' => true, 'data' => $products]);

elseif ($method === 'POST') { //check what data they are trying to send into our system, it is coming in as JSON
    $data = json_decode(file_get_contents('php://input'), true); // file get contents is a php method that will get the contents of any file

    if (!isset($data['name']) || !isset($data['base_price'])) {
        http_response_code(400); // bad request
        echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required fields']);
        exit;
    }

    // clean up the values before insert to database
    $name = $connection->real_escape_string($data['name']); 
    $price = floatval($data['base_price']);

    $stmt = $connection->prepare("INSERT INTO products (name, base_price) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $price);
    
    if ($stmt->execute()){
        http_response_code(201); // created
        echo_jason_encode(['success' => true, 'id' => $connection->insert_id]);
    } else {
        http_response_code(500); // server error
        echo json_encode(['error' => ' Server Error']);
    }
}

else
{
    http_response_code(405); // method not allowed
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>