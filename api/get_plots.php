<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gravetrack_db";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query
$sql = "SELECT plot_id, block, section, lot, type, status FROM plots";
$result = $conn->query($sql);

$plots = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $plots[] = $row;
    }
}

// Return JSON
header('Content-Type: application/json');
echo json_encode($plots);

$conn->close();
?>