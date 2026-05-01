<?php
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "gravetrack_db");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get only vacant plots
$sql = "SELECT plot_id, block, section, lot, type
        FROM plots 
        WHERE status = 'Vacant' 
        ORDER BY block, section, lot";

$result = $conn->query($sql);

$plots = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $plots[] = [
            'plot_id' => $row['plot_id'],
            'label' => 'Block ' . $row['block'] . ', Section ' . $row['section'] . ', Lot ' . $row['lot'] . ' (' . $row['type'] . ')',
            'block' => $row['block'],
            'section' => $row['section'],
            'lot' => $row['lot'],
            'type' => $row['type']
        ];
    }
}

echo json_encode([
    'success' => true,
    'plots' => $plots,
    'count' => count($plots)
]);

$conn->close();
?>
