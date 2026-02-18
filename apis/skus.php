<?php
    header('Content-Type: application/json');

    include("../index.php");
        
    $json = json_encode($s);
    echo $json;
?>
