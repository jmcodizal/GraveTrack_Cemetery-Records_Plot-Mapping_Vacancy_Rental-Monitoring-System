<?php
require_once 'Database/db_connector.php';
session_start();

$db = new db_connector();
$conn = $db->connect();

$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$plotId = isset($_GET['plot_id']) ? (int)$_GET['plot_id'] : null;
$block = isset($_GET['block']) ? trim($_GET['block']) : null;
$section = isset($_GET['section']) ? trim($_GET['section']) : null;
$lot = isset($_GET['lot']) ? trim($_GET['lot']) : null;
$deceasedId = isset($_GET['deceased_id']) ? (int)$_GET['deceased_id'] : null;

$message = '';
$messageType = '';
$plotInfo = null;
$deceasedRecords = [];

function calculatePhase($block) {
    if (preg_match('/^[A-I]/i', $block)) return 'Phase 1';
    if (preg_match('/^[T-Z]/i', $block)) return 'Phase 2';
    if (strtoupper($block) === 'AA') return 'Phase 3';
    return 'Unassigned';
}

try {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add') {
            $block = isset($_POST['block']) ? trim($_POST['block']) : '';
            $section = isset($_POST['section']) ? trim($_POST['section']) : '';
            $lot = isset($_POST['lot']) ? trim($_POST['lot']) : '';
            $fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
            $birthDate = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : null;
            $dateOfDeath = isset($_POST['date_of_death']) ? trim($_POST['date_of_death']) : null;
            $dateOfBurial = isset($_POST['date_of_burial']) ? trim($_POST['date_of_burial']) : null;
            $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';
            $burialType = isset($_POST['burial_type']) ? trim($_POST['burial_type']) : '';
            
            if (empty($fullName) || empty($block) || empty($lot)) {
                $message = 'Name, block, and lot are required.';
                $messageType = 'error';
            } else {
                // Find or create plot
                $plotStmt = $conn->prepare("SELECT plot_id FROM plots WHERE block = :block AND lot = :lot AND section = :section");
                $plotStmt->execute([
                    ':block' => $block,
                    ':lot' => $lot,
                    ':section' => $section ?: null
                ]);
                $plotResult = $plotStmt->fetch();
                
                if (!$plotResult) {
                    // Create new plot
                    $createPlotStmt = $conn->prepare("
                        INSERT INTO plots (block, section, lot, type, status)
                        VALUES (:block, :section, :lot, :type, 'Occupied')
                    ");
                    $createPlotStmt->execute([
                        ':block' => $block,
                        ':section' => $section ?: null,
                        ':lot' => $lot,
                        ':type' => $burialType ?: null
                    ]);
                    $plotId = $conn->lastInsertId();
                } else {
                    $plotId = $plotResult['plot_id'];
                    // Update status to Occupied
                    $updatePlotStmt = $conn->prepare("UPDATE plots SET status = 'Occupied' WHERE plot_id = :id");
                    $updatePlotStmt->execute([':id' => $plotId]);
                }
                
                // Insert deceased record
                $deceasedStmt = $conn->prepare("
                    INSERT INTO deceased (full_name, plot_id, birth_date, date_of_death, date_of_burial, gender, address, burial_type)
                    VALUES (:name, :plot_id, :birth_date, :dod, :doburial, :gender, :address, :burial_type)
                ");
                $deceasedStmt->execute([
                    ':name' => $fullName,
                    ':plot_id' => $plotId,
                    ':birth_date' => !empty($birthDate) ? $birthDate : null,
                    ':dod' => !empty($dateOfDeath) ? $dateOfDeath : null,
                    ':doburial' => !empty($dateOfBurial) ? $dateOfBurial : null,
                    ':gender' => $gender,
                    ':address' => $address,
                    ':burial_type' => $burialType
                ]);
                
                $message = 'Burial record added successfully!';
                $messageType = 'success';
                $deceasedId = $conn->lastInsertId();
            }
        } elseif ($action === 'edit') {
            $fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
            $birthDate = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : null;
            $dateOfDeath = isset($_POST['date_of_death']) ? trim($_POST['date_of_death']) : null;
            $dateOfBurial = isset($_POST['date_of_burial']) ? trim($_POST['date_of_burial']) : null;
            $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';
            $burialType = isset($_POST['burial_type']) ? trim($_POST['burial_type']) : '';
            
            if (empty($fullName) || !$deceasedId) {
                $message = 'Name is required and deceased ID is missing.';
                $messageType = 'error';
            } else {
                $updateStmt = $conn->prepare("
                    UPDATE deceased 
                    SET full_name = :name, birth_date = :birth_date, date_of_death = :dod, 
                        date_of_burial = :doburial, gender = :gender, address = :address, burial_type = :burial_type
                    WHERE deceased_id = :id
                ");
                $updateStmt->execute([
                    ':name' => $fullName,
                    ':birth_date' => !empty($birthDate) ? $birthDate : null,
                    ':dod' => !empty($dateOfDeath) ? $dateOfDeath : null,
                    ':doburial' => !empty($dateOfBurial) ? $dateOfBurial : null,
                    ':gender' => $gender,
                    ':address' => $address,
                    ':burial_type' => $burialType,
                    ':id' => $deceasedId
                ]);
                
                $message = 'Burial record updated successfully!';
                $messageType = 'success';
            }
        } elseif ($action === 'delete') {
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
        $plotStmt = $conn->prepare("SELECT * FROM plots WHERE plot_id = :id");
        $plotStmt->execute([':id' => $plotId]);
        $plotInfo = $plotStmt->fetch();
        
        if ($plotInfo) {
            $block = $plotInfo['block'];
            $section = $plotInfo['section'];
            $lot = $plotInfo['lot'];
        }
        
        // Fetch all deceased records for this plot
        $deceasedStmt = $conn->prepare("
            SELECT * FROM deceased WHERE plot_id = :plot_id ORDER BY deceased_id ASC
        ");
        $deceasedStmt->execute([':plot_id' => $plotId]);
        $deceasedRecords = $deceasedStmt->fetchAll();
    } elseif (!empty($block) && !empty($lot)) {
        // Try to find plot by block/section/lot
        $plotStmt = $conn->prepare("SELECT * FROM plots WHERE block = :block AND lot = :lot AND section = :section");
        $plotStmt->execute([
            ':block' => $block,
            ':lot' => $lot,
            ':section' => $section ?: null
        ]);
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; font-size: 28px; }
        .header p { margin: 0; opacity: 0.9; }
        .breadcrumb { margin-bottom: 20px; padding: 10px 0; font-size: 14px; }
        .breadcrumb a { color: #667eea; text-decoration: none; margin: 0 5px; }
        .breadcrumb a:hover { text-decoration: underline; }
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid; }
        .alert-success { background: #d4edda; color: #155724; border-color: #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; overflow: hidden; }
        .card-header { background: #f9f9f9; padding: 20px; border-bottom: 1px solid #eee; }
        .card-title { font-size: 18px; font-weight: 600; color: #333; margin: 0; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
        textarea { resize: vertical; min-height: 100px; }
        .button-group { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        button { padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .record-list { list-style: none; }
        .record-list-item { padding: 15px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .record-list-item.header { background: #f9f9f9; font-weight: 600; border-color: #ddd; }
        .record-info { flex: 1; }
        .record-name { font-weight: 600; font-size: 16px; color: #333; margin-bottom: 5px; }
        .record-meta { font-size: 13px; color: #666; }
        .record-actions { display: flex; gap: 10px; }
        .record-actions a, .record-actions button { padding: 8px 16px; font-size: 13px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-right: 8px; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-unpaid { background: #f8d7da; color: #721c24; }
        .plot-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 6px; margin-bottom: 20px; }
        .plot-info-item { text-align: center; }
        .plot-info-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .plot-info-value { font-size: 16px; font-weight: 600; color: #333; }
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
                        <div class="plot-info-label">Block</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['block']); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Section</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['section'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Lot</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['lot']); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Type</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['type'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Phase</div>
                        <div class="plot-info-value"><?php echo calculatePhase($plotInfo['block']); ?></div>
                    </div>
                    <div class="plot-info-item">
                        <div class="plot-info-label">Status</div>
                        <div class="plot-info-value"><?php echo htmlspecialchars($plotInfo['status']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ADD/EDIT FORM -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo ($action === 'add') ? 'Add New Burial Record' : (($action === 'edit' && $currentDeceased) ? 'Edit Burial Record: ' . htmlspecialchars($currentDeceased['full_name']) : 'Burial Record'); ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Deceased Name *</label>
                        <input type="text" id="full_name" name="full_name" required
                               value="<?php echo htmlspecialchars($currentDeceased['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_date">Date of Birth</label>
                            <input type="date" id="birth_date" name="birth_date"
                                   value="<?php echo htmlspecialchars($currentDeceased['birth_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_of_death">Date of Death</label>
                            <input type="date" id="date_of_death" name="date_of_death"
                                   value="<?php echo htmlspecialchars($currentDeceased['date_of_death'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_burial">Date of Burial</label>
                            <input type="date" id="date_of_burial" name="date_of_burial"
                                   value="<?php echo htmlspecialchars($currentDeceased['date_of_burial'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">-- Select --</option>
                                <option value="Male" <?php echo ($currentDeceased['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($currentDeceased['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($currentDeceased['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"><?php echo htmlspecialchars($currentDeceased['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="burial_type">Burial Type</label>
                        <select id="burial_type" name="burial_type">
                            <option value="">-- Select --</option>
                            <option value="Ground" <?php echo ($currentDeceased['burial_type'] ?? '') === 'Ground' ? 'selected' : ''; ?>>Ground</option>
                            <option value="Family Burial" <?php echo ($currentDeceased['burial_type'] ?? '') === 'Family Burial' ? 'selected' : ''; ?>>Family Burial</option>
                            <option value="Apartment" <?php echo ($currentDeceased['burial_type'] ?? '') === 'Apartment' ? 'selected' : ''; ?>>Apartment</option>
                            <option value="Columbarium" <?php echo ($currentDeceased['burial_type'] ?? '') === 'Columbarium' ? 'selected' : ''; ?>>Columbarium</option>
                        </select>
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
                                    <div class="record-name"><?php echo htmlspecialchars($record['full_name']); ?></div>
                                    <div class="record-meta">
                                        DOB: <?php echo htmlspecialchars($record['birth_date'] ?: 'Not listed'); ?> | 
                                        DOD: <?php echo htmlspecialchars($record['date_of_death'] ?: 'Not listed'); ?>
                                        <?php if ($record['gender']): ?>
                                            | Gender: <?php echo htmlspecialchars($record['gender']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <span class="badge">
                                            <?php echo htmlspecialchars($record['burial_type'] ?: 'Unspecified'); ?>
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
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="block">Block *</label>
                            <input type="text" id="block" name="block" required placeholder="e.g., A, B, AA, Z"
                                   value="<?php echo htmlspecialchars($block ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="section">Section</label>
                            <input type="text" id="section" name="section" placeholder="e.g., 1, 2"
                                   value="<?php echo htmlspecialchars($section ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="lot">Lot # *</label>
                            <input type="text" id="lot" name="lot" required placeholder="e.g., 1, 2, 10"
                                   value="<?php echo htmlspecialchars($lot ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Deceased Name *</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_date">Date of Birth</label>
                            <input type="date" id="birth_date" name="birth_date">
                        </div>
                        <div class="form-group">
                            <label for="date_of_death">Date of Death</label>
                            <input type="date" id="date_of_death" name="date_of_death">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_burial">Date of Burial</label>
                            <input type="date" id="date_of_burial" name="date_of_burial">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="burial_type">Burial Type</label>
                        <select id="burial_type" name="burial_type">
                            <option value="">-- Select --</option>
                            <option value="Ground">Ground</option>
                            <option value="Family Burial">Family Burial</option>
                            <option value="Apartment">Apartment</option>
                            <option value="Columbarium">Columbarium</option>
                        </select>
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
