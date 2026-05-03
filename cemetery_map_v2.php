<?php
require_once 'Database/db_connector.php';

// Create database connection
$db = new db_connector();
$conn = $db->connect();

// Handle search
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterPhase = isset($_GET['phase']) ? (int)$_GET['phase'] : 0;

try {
    // Fetch all phases
    $phasesStmt = $conn->prepare("SELECT * FROM phases ORDER BY phase_number ASC");
    $phasesStmt->execute();
    $phases = $phasesStmt->fetchAll();

    // Fetch all plots with deceased information for the cemetery map
    $plotsQuery = "
        SELECT 
            p.plot_id,
            p.plot_code,
            p.lot_number,
            p.section_number,
            b.block_id,
            b.block_name,
            ph.phase_id,
            ph.phase_number,
            ph.phase_name,
            COUNT(d.deceased_id) as occupant_count,
            GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names,
            COALESCE(MAX(d.payment_status), 'unpaid') as payment_status,
            p.status as plot_status
        FROM plots p
        LEFT JOIN blocks b ON p.block_id = b.block_id
        LEFT JOIN phases ph ON b.phase_id = ph.phase_id
        LEFT JOIN deceased d ON p.plot_id = d.plot_id
        GROUP BY p.plot_id
    ";
    
    // Add phase filter if specified
    if ($filterPhase > 0) {
        $plotsQuery .= " HAVING phase_number = " . (int)$filterPhase;
    }
    
    // Add search filter if specified
    if (!empty($searchQuery)) {
        $plotsQuery = "
            SELECT 
                p.plot_id,
                p.plot_code,
                p.lot_number,
                p.section_number,
                b.block_id,
                b.block_name,
                ph.phase_id,
                ph.phase_number,
                ph.phase_name,
                COUNT(d.deceased_id) as occupant_count,
                GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names,
                COALESCE(MAX(d.payment_status), 'unpaid') as payment_status,
                p.status as plot_status
            FROM plots p
            LEFT JOIN blocks b ON p.block_id = b.block_id
            LEFT JOIN phases ph ON b.phase_id = ph.phase_id
            LEFT JOIN deceased d ON p.plot_id = d.plot_id
            WHERE d.full_name LIKE :search OR p.plot_code LIKE :search OR b.block_name LIKE :search
            GROUP BY p.plot_id
        ";
        if ($filterPhase > 0) {
            $plotsQuery .= " HAVING phase_number = " . (int)$filterPhase;
        }
        $plotsStmt = $conn->prepare($plotsQuery);
        $plotsStmt->execute([':search' => '%' . $searchQuery . '%']);
    } else {
        $plotsStmt = $conn->prepare($plotsQuery);
        $plotsStmt->execute();
    }
    
    $plotsData = $plotsStmt->fetchAll();
    
    // Group plots by phase and block
    $plotsByPhaseBlock = [];
    foreach ($plotsData as $plot) {
        if (!isset($plotsByPhaseBlock[$plot['phase_number']])) {
            $plotsByPhaseBlock[$plot['phase_number']] = [];
        }
        if (!isset($plotsByPhaseBlock[$plot['phase_number']][$plot['block_name']])) {
            $plotsByPhaseBlock[$plot['phase_number']][$plot['block_name']] = [];
        }
        $plotsByPhaseBlock[$plot['phase_number']][$plot['block_name']][] = $plot;
    }

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GraveTrack Cemetery Map</title>
    <link rel="stylesheet" href="styles/cemetery_map_style.css">
    <style>
        .phase-container {
            margin-bottom: 40px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .phase-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            text-transform: uppercase;
        }
        
        .phase-grid {
            display: grid;
            gap: 20px;
        }
        
        .block-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .block-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #555;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .lots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .lot-box {
            aspect-ratio: 1;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px;
            text-align: center;
            font-size: 12px;
            position: relative;
        }
        
        .lot-box.vacant {
            background: #90EE90;
            border-color: #228B22;
        }
        
        .lot-box.occupied {
            background: #FF6B6B;
            border-color: #CC0000;
            color: white;
        }
        
        .lot-box:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .lot-label {
            font-weight: 600;
            font-size: 11px;
            word-break: break-word;
        }
        
        .lot-info {
            font-size: 10px;
            margin-top: 2px;
            opacity: 0.8;
        }
        
        .search-section {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-header {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .btn-search {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .btn-search:hover {
            background: #0056b3;
        }
        
        .btn-clear {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .btn-clear:hover {
            background: #545b62;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .record-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .record-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        
        .record-name {
            font-weight: 600;
            color: #333;
            margin: 0 0 8px 0;
        }
        
        .record-meta {
            color: #666;
            font-size: 13px;
            margin: 4px 0;
        }
        
        .badge-container {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-occupied {
            background: #cfe2ff;
            color: #084298;
        }
        
        .badge-vacant {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 20px;
            border-top: 1px solid #eee;
            background: #f9f9f9;
        }
        
        .btn-edit {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s ease;
            display: inline-block;
        }
        
        .btn-edit:hover {
            background: #218838;
        }
        
        .btn-close-modal {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .btn-close-modal:hover {
            background: #545b62;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        
        .header p {
            margin: 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>GraveTrack Cemetery Map</h1>
    <p>View and manage cemetery plots and burial records</p>
</div>

<!-- SEARCH SECTION -->
<div class="search-section">
    <form method="GET" class="search-header">
        <input type="text" name="search" class="search-input" placeholder="Search by deceased name or plot code..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        <select name="phase" class="filter-select">
            <option value="0">All Phases</option>
            <?php foreach ($phases as $phase): ?>
                <option value="<?php echo $phase['phase_number']; ?>" <?php echo $filterPhase == $phase['phase_number'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($phase['phase_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-search">Search</button>
        <?php if (!empty($searchQuery) || $filterPhase > 0): ?>
            <a href="cemetery_map.php" class="btn-clear">Clear Filters</a>
        <?php endif; ?>
    </form>
</div>

<!-- PHASES AND PLOTS -->
<div class="phase-container">
    <?php foreach ($phases as $phase): ?>
        <?php $phaseNum = $phase['phase_number']; ?>
        <?php if (!$filterPhase || $filterPhase == $phaseNum): ?>
            <div>
                <h2 class="phase-title"><?php echo htmlspecialchars($phase['phase_name']); ?></h2>
                
                <div class="phase-grid">
                    <?php if ($phaseNum == 3): ?>
                        <!-- PHASE 3: APARTMENT COURT (50 lots per section) -->
                        <?php for ($section = 1; $section <= 4; $section++): ?>
                            <div class="block-section">
                                <div class="block-title">Block AA - Section <?php echo $section; ?></div>
                                <div class="lots-grid">
                                    <?php for ($lot = 1; $lot <= 50; $lot++): ?>
                                        <?php 
                                        $plotCode = "AA-S" . $section . "-L" . $lot;
                                        // Find plot data
                                        $plotData = null;
                                        foreach ($plotsData as $p) {
                                            if ($p['block_name'] === 'AA' && $p['section_number'] == $section && $p['lot_number'] == $lot) {
                                                $plotData = $p;
                                                break;
                                            }
                                        }
                                        
                                        $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                                        $status = $isOccupied ? 'occupied' : 'vacant';
                                        ?>
                                        <div class="lot-box <?php echo $status; ?>" onclick="showLotDetails('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $plotData ? $plotData['plot_id'] : 'null'; ?>)">
                                            <div class="lot-label"><?php echo $lot; ?></div>
                                            <?php if ($isOccupied): ?>
                                                <div class="lot-info"><?php echo substr($plotData['deceased_names'], 0, 15); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <!-- PHASE 1 & 2: BLOCKS A-I and T-Z -->
                        <?php 
                        $blocksToShow = ($phaseNum == 1) ? range('A', 'I') : array_merge(range('T', 'Z'), ['AA']);
                        ?>
                        <?php foreach ($blocksToShow as $blockLetter): ?>
                            <div class="block-section">
                                <div class="block-title">Block <?php echo $blockLetter; ?></div>
                                <div class="lots-grid">
                                    <?php 
                                    // Get all plots for this block
                                    $blockPlots = [];
                                    foreach ($plotsData as $p) {
                                        if ($p['block_name'] === $blockLetter && $p['phase_number'] == $phaseNum) {
                                            $blockPlots[] = $p;
                                        }
                                    }
                                    
                                    // Generate lots (1-10 per block for Phase 1 & 2)
                                    for ($lot = 1; $lot <= 10; $lot++): 
                                        // Find plot data
                                        $plotData = null;
                                        foreach ($blockPlots as $p) {
                                            if ($p['lot_number'] == $lot) {
                                                $plotData = $p;
                                                break;
                                            }
                                        }
                                        
                                        $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                                        $status = $isOccupied ? 'occupied' : 'vacant';
                                        $plotCode = $blockLetter . "-" . $lot;
                                    ?>
                                        <div class="lot-box <?php echo $status; ?>" onclick="showLotDetails('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $plotData ? $plotData['plot_id'] : 'null'; ?>)">
                                            <div class="lot-label">Lot <?php echo $lot; ?></div>
                                            <?php if ($isOccupied): ?>
                                                <div class="lot-info"><?php echo substr($plotData['deceased_names'], 0, 15); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- MODAL FOR LOT DETAILS -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Lot Details</h2>
            <button class="modal-close" id="modalCloseBtn">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-actions">
            <button class="btn-close-modal" id="closeModalBtn">Close</button>
            <a href="#" class="btn-edit" id="editLotBtn">Edit Record</a>
        </div>
    </div>
</div>

<script>
let currentPlotId = null;
let currentPlotCode = null;

function showLotDetails(plotCode, plotId) {
    currentPlotId = plotId;
    currentPlotCode = plotCode;
    
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const editBtn = document.getElementById('editLotBtn');
    
    modalTitle.textContent = 'Plot: ' + plotCode;
    
    if (plotId === null) {
        // Vacant lot
        modalBody.innerHTML = `
            <div class="record-item">
                <p class="record-name">Vacant</p>
                <p class="record-meta">This lot is currently vacant and has no burial records.</p>
                <div class="badge-container">
                    <span class="badge badge-vacant">Vacant</span>
                </div>
            </div>
        `;
        editBtn.href = 'edit_burial_record.php?action=add&plot_code=' + plotCode;
        editBtn.textContent = 'Add Burial Record';
    } else {
        // Fetch lot details via AJAX
        fetch('api/get_lot_details.php?plot_id=' + plotId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalBody.innerHTML = data.html;
                }
            })
            .catch(error => console.error('Error:', error));
        
        editBtn.href = 'edit_burial_record.php?action=edit&plot_id=' + plotId;
        editBtn.textContent = 'Edit Record';
    }
    
    document.getElementById('detailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('detailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('modalCloseBtn').addEventListener('click', closeModal);
document.getElementById('closeModalBtn').addEventListener('click', closeModal);
document.getElementById('detailsModal').addEventListener('click', (e) => {
    if (e.target.id === 'detailsModal') closeModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('detailsModal').classList.contains('active')) {
        closeModal();
    }
});
</script>

</body>
</html>
