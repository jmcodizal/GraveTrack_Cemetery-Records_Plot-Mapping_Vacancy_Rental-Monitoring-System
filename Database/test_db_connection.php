<?php
require_once 'db_connector.php';

$db = new Database();
$conn = $db->connect();

if ($conn) {
    echo "✅ Connected to GraveTrack database successfully!";
} else {
    echo "❌ Connection failed.";
}
?>