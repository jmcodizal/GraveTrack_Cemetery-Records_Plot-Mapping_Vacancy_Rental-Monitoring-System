<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../Database/db_connector.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = new db_connector();
    $conn = $db->connect();
    
$stmt = $conn->prepare("
        SELECT 
            `Deceased Name` as name,
            `Plot Location` as plot,
            DATE_FORMAT(`Date of Transaction`, '%M %d, %Y') as period,
            `Contact Person` as contact_person,
            `Contact Number` as contact_num,
            `Amount` as amount,
            `Status` as status
        FROM transaction_summary_view 
        ORDER BY `Date of Transaction` DESC
    ");

    $stmt->execute();
    $records = $stmt->fetchAll();
    
    echo json_encode($records);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

