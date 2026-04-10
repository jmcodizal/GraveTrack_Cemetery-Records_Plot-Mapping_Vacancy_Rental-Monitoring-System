<?php
require_once 'db_connector.php';

$db = new db_connector();
try {
    $records = $db->getBurialRecords('');
    echo "<h3>burial_records_view test</h3>";
    if (empty($records)) {
        echo "No records returned.";
    } else {
        echo "<pre>";
        print_r($records[0]); // First row keys/values
        echo "</pre>";
        echo "<p>Total: " . count($records) . "</p>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

