<?php
require_once './log.php';

function getConnection(){
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "crbtPortal";
    	$conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
	var_dump($conn->connect_error);
        die("Connection failed: " . $conn->connect_error);
        
    }

    return $conn;
}