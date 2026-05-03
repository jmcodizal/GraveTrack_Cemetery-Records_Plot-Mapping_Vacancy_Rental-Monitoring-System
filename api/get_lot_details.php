<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../Database/db_connector.php';

$plotId = isset($_GET['plot_id']) ? (int)$_GET['plot_id'] : 0;

if (!$plotId) {
    echo json_encode(['success' => false, 'message' => 'Invalid plot ID']);
    exit;
}

try {
    $db = new db_connector();
    $conn = $db->connect();

    // Get plot info
    $plotStmt = $conn->prepare("SELECT * FROM plots WHERE plot_id = :id");
    $plotStmt->execute([':id' => $plotId]);
    $plot = $plotStmt->fetch();

    if (!$plot) {
        echo json_encode(['success' => false, 'message' => 'Plot not found']);
        exit;
    }

    // Get all deceased records for this plot
    $deceasedStmt = $conn->prepare("
        SELECT * FROM deceased 
        WHERE plot_id = :plot_id 
        ORDER BY deceased_id ASC
    ");
    $deceasedStmt->execute([':plot_id' => $plotId]);
    $deceasedRecords = $deceasedStmt->fetchAll();

    // Generate HTML for modal
    ob_start();
    ?>
    <div class="record-item">
        <div class="record-meta">
            <strong>Block:</strong> <?= htmlspecialchars($plot['block'] ?: 'N/A') ?> |
            <strong>Section:</strong> <?= htmlspecialchars($plot['section'] ?: 'N/A') ?> |
            <strong>Lot:</strong> <?= htmlspecialchars($plot['lot']) ?> |
            <strong>Type:</strong> <?= htmlspecialchars($plot['type'] ?: 'N/A') ?>
        </div>
    </div>

    <?php if (empty($deceasedRecords)): ?>
        <div class="record-item">
            <p class="record-name">Vacant</p>
            <p class="record-meta">This lot is currently vacant.</p>
            <div class="badge-container"><span class="badge badge-vacant">Vacant</span></div>
        </div>
    <?php else: ?>
        <?php foreach ($deceasedRecords as $record): ?>
            <div class="record-item">
                <p class="record-name"><?= htmlspecialchars($record['full_name']) ?></p>
                <p class="record-meta">
                    DOB: <?= htmlspecialchars($record['birth_date'] ?: 'Not listed') ?> | 
                    DOD: <?= htmlspecialchars($record['date_of_death'] ?: 'Not listed') ?>
                    <?php if ($record['gender']): ?>| Gender: <?= htmlspecialchars($record['gender']) ?><?php endif; ?>
                </p>
                <?php if ($record['date_of_burial']): ?>
                    <p class="record-meta">Burial: <?= htmlspecialchars($record['date_of_burial']) ?></p>
                <?php endif; ?>
                <?php if ($record['burial_type']): ?>
                    <p class="record-meta">Type: <?= htmlspecialchars($record['burial_type']) ?></p>
                <?php endif; ?>
                <?php if ($record['address']): ?>
                    <p class="record-meta">Address: <?= htmlspecialchars($record['address']) ?></p>
                <?php endif; ?>
                <div class="badge-container">
                    <span class="badge <?= $plot['status'] == 'Occupied' ? 'badge-occupied' : 'badge-vacant' ?>">
                        <?= htmlspecialchars($plot['status']) ?>
                    </span>
                    <?php if ($record['burial_type']): ?>
                        <span class="badge"><?= htmlspecialchars($record['burial_type']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'plot' => $plot,
        'deceased' => $deceasedRecords
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
