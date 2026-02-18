<?php
    function check_api_key($env) {
        $valid_api_key = $env['X_API_KEY'];
        $headers = getallheaders();
    
        foreach ($headers as $name => $value) {
            if(strtolower($name) === 'x-api-key') {
                $provided_key = $value;
                break;
            }
        } 
        if ($provided_key !== $valid_api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized:', 'details' => 'Invalid API Key']);
            exit();
        }
    }
?>
