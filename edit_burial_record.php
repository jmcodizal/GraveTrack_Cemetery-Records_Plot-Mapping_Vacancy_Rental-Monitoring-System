<?php
require_once 'Database/db_connector.php';
session_start();

$db = new db_connector();
$conn = $db->connect();

$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$plotId = isset($_GET['plot_id']) ? (int)$_GET['plot_id'] : null;
$plotCode = isset($_GET['plot_code']) ? trim($_GET['plot_code']) : null;
$deceasedId = isset($_GET['deceased_id']) ? (int)$_GET['deceased_id'] : null;

$message = '';
$messageType = '';
$plotInfo = null;
$deceasedRecords = [];

try {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add') {
            // Add new burial record
            $plotCode = isset($_POST['plot_code']) ? trim($_POST['plot_code']) : '';
            $deceasedName = isset($_POST['deceased_name']) ? trim($_POST['deceased_name']) : '';
            $dateOfBirth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
            $dateOfDeath = isset($_POST['date_of_death']) ? trim($_POST['date_of_death']) : null;
            $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';
            $paymentStatus = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'unpaid';
            
            if (empty($deceasedName) || empty($plotCode)) {
                $message = 'Name and plot code are required.';
                $messageType = 'error';
            } else {
                // Find or create plot
                $plotStmt = $conn->prepare("SELECT plot_id FROM plots WHERE plot_code = :code");
                $plotStmt->execute([':code' => $plotCode]);
                $plotResult = $plotStmt->fetch();
                
                if (!$plotResult) {
                    // Create new plot
                    // Parse plot code to extract block, lot, section info
                    $blockName = substr($plotCode, 0, 2);
                    $blockStmt = $conn->prepare("SELECT block_id FROM blocks WHERE block_name = :name");
                    $blockStmt->execute([':name' => $blockName]);
                    $blockResult = $blockStmt->fetch();
                    
                    if ($blockResult) {
                        $blockId = $blockResult['block_id'];
                    } else {
                        // Create block if it doesn't exist
                        $createBlockStmt = $conn->prepare("INSERT INTO blocks (block_name) VALUES (:name)");
                        $createBlockStmt->execute([':name' => $blockName]);
                        $blockId = $conn->lastInsertId();
                    }
                    
                    // Parse lot number from plot code
                    $lotNumber = 1; // Default
                    if (preg_match('/L(\d+)/', $plotCode, $matches)) {
                        $lotNumber = (int)$matches[1];
                    } elseif (preg_match('/-(\d+)$/', $plotCode, $matches)) {
                        $lotNumber = (int)$matches[1];
                    }
                    
                    // Parse section for Phase 3
                    $sectionNumber = null;
                    if (preg_match('/S(\d+)/', $plotCode, $matches)) {
                        $sectionNumber = (int)$matches[1];
                    }
                    
                    $createPlotStmt = $conn->prepare("
                        INSERT INTO plots (plot_code, block_id, lot_number, section_number, status)
                        VALUES (:code, :block_id, :lot_number, :section_number, 'occupied')
                    ");
                    $createPlotStmt->execute([
                        ':code' => $plotCode,
                        ':block_id' => $blockId,
                        ':lot_number' => $lotNumber,
                        ':section_number' => $sectionNumber
                    ]);
                    $plotId = $conn->lastInsertId();
                } else {
                    $plotId = $plotResult['plot_id'];
                }
                
                // Insert deceased record
                $deceasedStmt = $conn->prepare("
                    INSERT INTO deceased (deceased_name, plot_id, date_of_birth, date_of_death, gender, address, payment_status)
                    VALUES (:name, :plot_id, :dob, :dod, :gender, :address, :payment_status)
                ");
                $deceasedStmt->execute([
                    ':name' => $deceasedName,
                    ':plot_id' => $plotId,
                    ':dob' => !empty($dateOfBirth) ? $dateOfBirth : null,
                    ':dod' => !empty($dateOfDeath) ? $dateOfDeath : null,
                    ':gender' => $gender,
                    ':address' => $address,
                    ':payment_status' => $paymentStatus
                ]);
                
                $message = 'Burial record added successfully!';
                $messageType = 'success';
                $deceasedId = $conn->lastInsertId();
            }
        } elseif ($action === 'edit') {
            // Edit deceased record
            $deceasedName = isset($_POST['deceased_name']) ? trim($_POST['deceased_name']) : '';
            $dateOfBirth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
            $dateOfDeath = isset($_POST['date_of_death']) ? trim($_POST['date_of_death']) : null;
            $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';
            $paymentStatus = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'unpaid';
            
            if (empty($deceasedName) || !$deceasedId) {
                $message = 'Name is required and deceased ID is missing.';
                $messageType = 'error';
            } else {
                $updateStmt = $conn->prepare("
                    UPDATE deceased 
                    SET deceased_name = :name, date_of_birth = :dob, date_of_death = :dod, 
                        gender = :gender, address = :address, payment_status = :payment_status
                    WHERE deceased_id = :id
                ");
                $updateStmt->execute([
                    ':name' => $deceasedName,
                    ':dob' => !empty($dateOfBirth) ? $dateOfBirth : null,
                    ':dod' => !empty($dateOfDeath) ? $dateOfDeath : null,
                    ':gender' => $gender,
                    ':address' => $address,
                    ':payment_status' => $paymentStatus,
                    ':id' => $deceasedId
                ]);
                
                $message = 'Burial record updated successfully!';
                $messageType = 'success';
            }
        } elseif ($action === 'delete') {
            // Delete deceased record
            if ($deceasedId) {
                $deleteStmt = $conn->prepare("DELETE FROM deceased WHERE deceased_id = :id");
                $deleteStmt->execute([':id' => $deceasedId]);
                
                $message = 'Burial record deleted successfully!';
                $messageType = 'success';
                $deceasedId = null;
            }
        }
    }

    // Fetch plot info
    if ($plotId) {
        $plotStmt = $conn->prepare("
            SELECT p.*, b.block_name, ph.phase_name, ph.phase_number
            FROM plots p
            LEFT JOIN blocks b ON p.block_id = b.block_id
            LEFT JOIN phases ph ON b.phase_id = ph.phase_id
            WHERE p.plot_id = :id
        ");
        $plotStmt->execute([':id' => $plotId]);
        $plotInfo = $plotStmt->fetch();
        
        if ($plotInfo) {
            $plotCode = $plotInfo['plot_code'];
        }
        
        // Fetch all deceased records for this plot
        $deceasedStmt = $conn->prepare("
            SELECT * FROM deceased WHERE plot_id = :plot_id ORDER BY deceased_id ASC
        ");
        $deceasedStmt->execute([':plot_id' => $plotId]);
        $deceasedRecords = $deceasedStmt->fetchAll();
    } elseif (!empty($plotCode)) {
        // Try to find plot by code
        $plotStmt = $conn->prepare("
            SELECT p.*, b.block_name, ph.phase_name, ph.phase_number
            FROM plots p
            LEFT JOIN blocks b ON p.block_id = b.block_id
            LEFT JOIN phases ph ON b.phase_id = ph.phase_id
            WHERE p.plot_code = :code
        ");
        $plotStmt->execute([':code' => $plotCode]);
        $plotInfo = $plotStmt->fetch();
        
        if ($plotInfo) {
            $plotId = $plotInfo['plot_id'];
            // Fetch deceased records
            $deceasedStmt = $conn->prepare("
                SELECT * FROM deceased WHERE plot_id = :plot_id ORDER BY deceased_id ASC
            ");
            $deceasedStmt->execute([':plot_id' => $plotId]);
            $deceasedRecords = $deceasedStmt->fetchAll();
        }
    }

    // Fetch specific deceased record if editing
    $currentDeceased = null;
    if ($deceasedId && $action === 'edit') {
        $deceasedStmt = $conn->prepare("SELECT * FROM deceased WHERE deceased_id = :id");
        $deceasedStmt->execute([':id' => $deceasedId]);
        $currentDeceased = $deceasedStmt->fetch();
    }

} catch (Exception $e) {
    $message = 'Error: ' . htmlspecialchars($e->getMessage());
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Burial Record - GraveTrack</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .breadcrumb {
            margin-bottom: 20px;
            padding: 10px 0;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: #f9f9f9;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row.three-col {
            grid-template-columns: 1fr 1fr 1fr;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .record-list {
            list-style: none;
        }
        
        .record-list-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .record-list-item.header {
            background: #f9f9f9;
            font-weight: 600;
            border-color: #ddd;
        }
        
        .record-info {
            flex: 1;
        }
        
        .record-name {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .record-meta {
            font-size: 13px;
            color: #666;
        }
        
        .record-actions {
            display: flex;
            gap: 10px;
        }
        
        .record-actions a,
        .record-actions button {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .plot-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .plot-info-item {
            text-align: center;
        }
        
        .plot-info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .plot-info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="breadcrumb">
        <a href="cemetery_map_v2.php">← Back to Cemetery Map</a>
    </div>
    
    <div class="header">
        <h1>Edit Burial Record</h1>
        <p>Add, edit, or delete burial records for cemetery plots</p>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($plotInfo): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Plot Information</h3>
            </div>
            <div class="card-body">
                <div class="plot-info">
                    <div class="plot-info-item">
                        <div class="plot-info-label">Plot Code</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['plot_code']); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Block</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['block_name'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Phase</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['phase_name'] ?: 'N/A'); ?></div>
                    </div>
                    <?php if ($plotInfo['section_number']): ?>
                        <div class="plot-info-item">
                            <div class="plot-info-label">Section</div>
                            <div class="plot-info-value"><?php echo $plotInfo['section_number']; ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($plotInfo['lot_number']): ?>
                        <div class="plot-info-item">
                            <div class="plot-info-label">Lot Number</div>
                            <div class="plot-info-value"><?php echo $plotInfo['lot_number']; ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ADD/EDIT FORM -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo ($action === 'add') ? 'Add New Burial Record' : (($action === 'edit' && $currentDeceased) ? 'Edit Burial Record: ' . htmlspecialchars($currentDeceased['deceased_name']) : 'Burial Record'); ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="deceased_name">Deceased Name *</label>
                        <input type="text" id="deceased_name" name="deceased_name" required
                               value="<?php echo htmlspecialchars($currentDeceased['deceased_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo htmlspecialchars($currentDeceased['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_of_death">Date of Death</label>
                            <input type="date" id="date_of_death" name="date_of_death"
                                   value="<?php echo htmlspecialchars($currentDeceased['date_of_death'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">-- Select --</option>
                                <option value="Male" <?php echo ($currentDeceased['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($currentDeceased['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($currentDeceased['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_status">Payment Status *</label>
                            <select id="payment_status" name="payment_status" required>
                                <option value="unpaid" <?php echo ($currentDeceased['payment_status'] ?? 'unpaid') === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="paid" <?php echo ($currentDeceased['payment_status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="partial" <?php echo ($currentDeceased['payment_status'] ?? '') === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"><?php echo htmlspecialchars($currentDeceased['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="button-group">
                        <a href="cemetery_map_v2.php" class="btn-secondary" style="text-decoration: none; display: inline-block;">Cancel</a>
                        <button type="submit" class="btn-primary">
                            <?php echo ($action === 'add') ? 'Add Record' : 'Update Record'; ?>
                        </button>
                        <?php if ($action === 'edit' && $currentDeceased): ?>
                            <button type="submit" name="action" value="delete" class="btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this record? This action cannot be undone.');">
                                Delete Record
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- EXISTING RECORDS LIST -->
        <?php if (!empty($deceasedRecords)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Burial Records (<?php echo count($deceasedRecords); ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="record-list">
                        <?php foreach ($deceasedRecords as $record): ?>
                            <div class="record-list-item">
                                <div class="record-info">
                                    <div class="record-name"><?php echo htmlspecialchars($record['deceased_name']); ?></div>
                                    <div class="record-meta">
                                        DOB: <?php echo htmlspecialchars($record['date_of_birth'] ?: 'Not listed'); ?> | 
                                        DOD: <?php echo htmlspecialchars($record['date_of_death'] ?: 'Not listed'); ?>
                                        <?php if ($record['gender']): ?>
                                            | Gender: <?php echo htmlspecialchars($record['gender']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <span class="badge <?php echo ($record['payment_status'] === 'paid') ? 'badge-paid' : 'badge-unpaid'; ?>">
                                            <?php echo htmlspecialchars($record['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="record-actions">
                                    <a href="?action=edit&plot_id=<?php echo $plotId; ?>&deceased_id=<?php echo $record['deceased_id']; ?>" 
                                       class="btn-primary" style="text-decoration: none;">Edit</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- NEW PLOT FORM -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Add New Burial Record</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="plot_code">Plot Code *</label>
                        <input type="text" id="plot_code" name="plot_code" required placeholder="e.g., A-1, AA-S1-L1"
                               value="<?php echo htmlspecialchars($plotCode ?? ''); ?>">
                        <small style="color: #666; margin-top: 5px; display: block;">
                            Format: Block-Lot (e.g., A-1) or Block-Section-Lot (e.g., AA-S1-L1 for apartment)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="deceased_name">Deceased Name *</label>
                        <input type="text" id="deceased_name" name="deceased_name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                        <div class="form-group">
                            <label for="date_of_death">Date of Death</label>
                            <input type="date" id="date_of_death" name="date_of_death">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_status">Payment Status *</label>
                            <select id="payment_status" name="payment_status" required>
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"></textarea>
                    </div>
                    
                    <div class="button-group">
                        <a href="cemetery_map_v2.php" class="btn-secondary" style="text-decoration: none; display: inline-block;">Cancel</a>
                        <button type="submit" class="btn-primary">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
