<?php
// Define database connection parameters
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // UPDATED
define('DB_PASSWORD', ''); // UPDATED (empty password)
define('DB_NAME', 'cloud'); // UPDATED

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Start a session for user authentication
session_start();
?>