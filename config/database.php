<?php
$servername = "localhost";      //Hostname
$username = "root";         //Username
$password = "";         //Password
$dbname = "hoangtoph";      //Database Name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>