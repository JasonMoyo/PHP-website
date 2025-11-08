<?php
require_once("cors-headers.php");
require_once("env-loader.php"); // Load environment variables
header('Content-Type: application/json');

$servername = $_ENV['DB_HOST'] ?? 'localhost';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';
$database = $_ENV['DB_NAME'] ?? 'abc_medicos';

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Set charset to utf8mb4 for proper encoding
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
$conn->set_charset($charset);
?>