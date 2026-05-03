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
    $data = json_decode(file_get_contents("php://input"), true);
    $deceased_name = $data['name'] ?? '';

    $db = new db_connector();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("
        SELECT 
            t.transaction_id,
            d.full_name AS deceased_name,
            t.transaction_date,
            t.amount,
            t.type AS transaction_type,
            
            p.status AS payment_status,
            p.payment_date,
            
            c.contact_person,
            c.contact_number,
            
            CONCAT(pl.block, ' - ', pl.section, ' - ', pl.lot) AS plot_location

        FROM transactions t
        
        LEFT JOIN deceased d ON t.deceased_id = d.deceased_id
        LEFT JOIN contacts c ON d.deceased_id = c.deceased_id
        LEFT JOIN plots pl ON d.plot_id = pl.plot_id
        
        LEFT JOIN rentals r ON d.deceased_id = r.deceased_id
        LEFT JOIN payments p ON r.rental_id = p.rental_id

        WHERE d.full_name LIKE ?
        
        ORDER BY t.transaction_date DESC
    ");

    $stmt->bindValue(1, "%$deceased_name%");
    $stmt->execute();
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($transactions);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>