<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  

require_once "../db-connect.php";
require_once "../auth.php";

check_api_key($env);



