<?php
$servername = "localhost";      //Hostname
$username = "root";         //Username
$password = "";         //Password
$dbname = "truyengg";      //Database Name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>