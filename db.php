<?php
$conn = new mysqli("localhost", "root", "", "Database");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>