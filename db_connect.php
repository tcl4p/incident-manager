<?php
// db_connect.php
$host = 'localhost';
$username = 'root';
$password = 'root';   // MAMP default is often 'root' (but yours could be blank)
$dbname = 'fdchecklist'; // <-- must match your actual DB name in phpMyAdmin

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
