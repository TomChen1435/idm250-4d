<?php
    header('Content-Type: application/json');

    include("../include/index.php");
        
    $json = json_encode($s);
    echo $json;
?>
