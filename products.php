<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  

require_once "../db-connect.php";
require_once "../auth.php";

check_api_key($env);


$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT * FROM p.id, p.name, p.base_price FROM products p";
}

if (isset($_GET['category'])) {
    $category = $connection->real_escape_string($_GET['category']); //real escape string cleans up input
    $query .= " JOIN product_categories pc ON p.id = pc.product_id
               JOIN categories c ON pc.category_id = c.id
               WHERE c.name = '$category' 
               "; // should generally use a prepared statement here 
    }
$result = $connection->query($query);
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = $row; 
}


echo json_encode(['success' => true, 'data' => $products]);

elseif ($method === 'POST') { 
    $data = json_decode(file_get_contents('php://input'), true); 

    if (!isset($data['name']) || !isset($data['base_price'])) {
        http_response_code(400); 
        echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required fields']);
        exit;
    }

    $name = $connection->real_escape_string($data['name']); 
    $price = floatval($data['base_price']);

    $stmt = $connection->prepare("INSERT INTO products (name, base_price) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $price);
    
    if ($stmt->execute()){
        http_response_code(201); 
        echo_jason_encode(['success' => true, 'id' => $connection->insert_id]);
    } else {
        http_response_code(500); 
        echo json_encode(['error' => ' Server Error']);
    }
}

else
{
    http_response_code(405); 
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>






