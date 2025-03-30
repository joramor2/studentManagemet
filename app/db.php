<?php
session_start(); // Start the session at the top of the script
$host = "localhost"; 
$db_name = "schoolmanagement";  // Change if your database name is different
$username = "root";  // Default for local MySQL (change if needed)
$password = "";  // Default for local MySQL (change if needed)

// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>

