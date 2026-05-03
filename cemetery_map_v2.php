<?php
require_once 'Database/db_connector.php';

$db   = new db_connector();
$conn = $db->connect();

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterPhase = isset($_GET['phase']) ? (int)$_GET['phase'] : 0;

function calculatePhase($block) {
    if (preg_match('/^[A-I]/i', $block)) return 'Phase 1';
    if (preg_match('/^[T-Z]/i', $block)) return 'Phase 2';
    if (strtoupper($block) === 'AA') return 'Phase 3';
    return 'Unassigned';
}

try {
    // Get distinct phases from data (not from a phases table)
    $phases = [];
    $phasesStmt = $conn->query("
        SELECT 
            CASE 
                WHEN block REGEXP '^[A-I]' THEN 1
                WHEN block REGEXP '^[T-Z]' THEN 2
                WHEN UPPER(block) = 'AA' THEN 3
                ELSE 0
            END as phase_number,
            CASE 
                WHEN block REGEXP '^[A-I]' THEN 'Phase 1'
                WHEN block REGEXP '^[T-Z]' THEN 'Phase 2'
                WHEN UPPER(block) = 'AA' THEN 'Phase 3'
                ELSE 'Unassigned'
            END as phase_name
        FROM plots
        GROUP BY phase_number, phase_name
        HAVING phase_number > 0
        ORDER BY phase_number ASC
    ");
    $phases = $phasesStmt->fetchAll();

    $plotsQuery = "
        SELECT
            p.plot_id, p.block, p.section, p.lot, p.type, p.status,
            COUNT(d.deceased_id) as occupant_count,
            GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names
        FROM plots p
        LEFT JOIN deceased d ON p.plot_id = d.plot_id
        GROUP BY p.plot_id
    ";

    if ($filterPhase > 0) {
        $plotsQuery .= " HAVING CASE 
            WHEN block REGEXP '^[A-I]' THEN 1
            WHEN block REGEXP '^[T-Z]' THEN 2
            WHEN UPPER(block) = 'AA' THEN 3
            ELSE 0
        END = " . (int)$filterPhase;
    }

    if (!empty($searchQuery)) {
        $plotsQuery = "
            SELECT
                p.plot_id, p.block, p.section, p.lot, p.type, p.status,
                COUNT(d.deceased_id) as occupant_count,
                GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names
            FROM plots p
            LEFT JOIN deceased d ON p.plot_id = d.plot_id
            WHERE d.full_name LIKE :search OR p.block LIKE :search OR p.lot LIKE :search
            GROUP BY p.plot_id
        ";
        if ($filterPhase > 0) {
            $plotsQuery .= " HAVING CASE 
                WHEN block REGEXP '^[A-I]' THEN 1
                WHEN block REGEXP '^[T-Z]' THEN 2
                WHEN UPPER(block) = 'AA' THEN 3
                ELSE 0
            END = " . (int)$filterPhase;
        }
        $plotsStmt = $conn->prepare($plotsQuery);
        $plotsStmt->execute([':search' => '%' . $searchQuery . '%']);
    } else {
        $plotsStmt = $conn->prepare($plotsQuery);
        $plotsStmt->execute();
    }

    $plotsData = $plotsStmt->fetchAll();

    function findPlot($plotsData, $blockName, $lotNumber, $phaseNumber = null) {
        foreach ($plotsData as $p) {
            if ($p['block'] === $blockName && $p['lot'] == $lotNumber) {
                if ($phaseNumber === null || calculatePhase($p['block']) === 'Phase ' . $phaseNumber || 
                    (strpos($p['block'], $blockName) === 0)) return $p;
            }
        }
        return null;
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/cemetery_map_style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Syne:wght@700;800&display=swap');
        html, body { width: 100%; height: 100%; overflow: hidden; font-family: 'DM Sans', sans-serif; background: #e8f0fb; }
        .page { display: flex; flex-direction: column; height: 100vh; width: 100vw; overflow: hidden; }

        /* ── NAVBAR (vacancy_style) ── */
        .site-header {
          flex-shrink: 0;
          background: #1a3a6e;
          padding: .55rem 1.2rem;
          display: flex;
          align-items: center;
          justify-content: space-between;
          box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .brand {
          font-family: 'Syne', sans-serif;
          font-size: 1.2rem;
          font-weight: 800;
          letter-spacing: .03em;
          color: #ffffff;
          user-select: none;
        }
        .brand span { color: #60a5fa; }
        .nav-actions { display: flex; align-items: center; gap: .55rem; }
        .nav-btn {
          font-family: 'DM Sans', sans-serif;
          font-size: .82rem;
          font-weight: 600;
          padding: .38rem 1.1rem;
          border-radius: 20px;
          border: none;
          cursor: pointer;
          transition: opacity .2s, transform .15s;
          text-align: center;
          line-height: 1.3;
          text-decoration: none;
          display: inline-block;
        }
        .nav-btn:hover { opacity: .85; transform: translateY(-1px); }
        .nav-btn:active { transform: translateY(0); }
        .btn-search  { background: #2563eb; color: #fff; }
        .btn-clear   { background: #dbeafe; color: #1e3a8a; }
        .search-input {
          background: #f0f6ff;
          border: 1.5px solid #cbd5e1;
          border-radius: 20px;
          padding: .32rem .9rem;
          font-family: 'DM Sans', sans-serif;
          font-size: .82rem;
          color: #1e293b;
          outline: none;
          transition: border-color .2s, box-shadow .2s;
          min-width: 160px;
        }
        .search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .search-input::placeholder { color: #94a3b8; }
        .filter-select {
          background: #f0f6ff;
          border: 1.5px solid #cbd5e1;
          border-radius: 20px;
          padding: .32rem .9rem;
          font-family: 'DM Sans', sans-serif;
          font-size: .82rem;
          color: #1e293b;
          outline: none;
          cursor: pointer;
        }
        .btn-power {
          width: 36px; height: 36px;
          border-radius: 50%;
          border: none;
          background: #ef4444;
          color: #fff;
          display: flex; align-items: center; justify-content: center;
          cursor: pointer;
          transition: background .2s, transform .15s;
          flex-shrink: 0;
        }
        .btn-power:hover { background: #dc2626; transform: scale(1.08); }
        .btn-power svg { width: 16px; height: 16px; }

        /* ── LEGEND BAR ── */
        .legend {
          flex-shrink: 0;
          display: flex;
          gap: 18px;
          align-items: center;
          padding: 5px 20px;
          background: #dbeafe;
          border-bottom: 1px solid #bfdbfe;
          font-size: 12px;
          font-family: 'DM Sans', sans-serif;
        }
        .legend-item { display:flex; align-items:center; gap:5px; font-weight:600; color:#1e3a8a; }
        .lbox { width:22px; height:11px; border-radius:3px; border:2px solid; }
        .lbox.vacant   { background:#5cb85c; border-color:#3a8a3a; }
        .lbox.occupied { background:#d9534f; border-color:#a02020; }
        .lbox.unnamed  { background:#d9534f; border-color:#a02020; }

        .map-outer { flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #e8f0fb; padding: 10px 16px 8px; }
        .map-scaler { transform-origin: center center; display: inline-block; white-space: nowrap; }
        .map-inner { background: #fff; padding: 16px 24px 0; display: inline-flex; flex-direction: column; align-items: flex-start; }
        .all-blocks { display: flex; align-items: flex-end; gap: 0; }
        .phase-divider { width: 4px; background: #111; align-self: stretch; flex-shrink: 0; margin: 0 6px; }
        .pair-gap { width: 10px; flex-shrink: 0; }
        .block-col { display: flex; flex-direction: column; align-items: center; padding: 0 3px; }
        .block-label { font-size: 13px; font-weight: 900; color: #111; margin-bottom: 6px; letter-spacing: .02em; }
        .plots-stack { display: flex; flex-direction: column; gap: 3px; }
        .lot-box { width: 52px; height: 19px; border-radius: 6px; border: 2px solid; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: 700; color: rgba(0,0,0,.45); transition: transform .1s, box-shadow .1s; position: relative; }
        .lot-box:hover { transform: scale(1.25); box-shadow: 0 3px 10px rgba(0,0,0,.3); z-index: 100; }
        .lot-box.vacant   { background:#5cb85c; border-color:#3a8a3a; }
        .lot-box.occupied { background:#d9534f; border-color:#a02020; color:#fff; }
        .unnamed-col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding: 0 3px; }
        .unnamed-stack { display: flex; flex-direction: column; gap: 3px; }
        .lot-box.unnamed { background: #d9534f; border-color: #a02020; width: 48px; height: 19px; cursor: default; color: transparent; }
        .lot-box.unnamed:hover { transform: none; box-shadow: none; }
        .fence-row { width: 100%; display: block; height: 60px; margin-top: 8px; }
        .phase-labels-row { display: flex; width: 100%; margin-top: 6px; padding-bottom: 10px; font-size: 13px; font-weight: 900; color: #111; letter-spacing: .08em; }
        .phase-label-cell { text-align: center; text-transform: uppercase; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #fff; border-radius: 10px; box-shadow: 0 4px 24px rgba(0,0,0,.3); width: 90%; max-width: 540px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:2px solid #eee; }
        .modal-title { font-size:17px; font-weight:700; color:#333; }
        .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#666; line-height:1; }
        .modal-body  { padding:16px 18px; }
        .record-item { padding:12px; border:1px solid #eee; border-radius:7px; margin-bottom:8px; background:#f9f9f9; }
        .record-name { font-weight:700; color:#333; margin-bottom:4px; }
        .record-meta { color:#666; font-size:13px; margin:2px 0; }
        .badge-container { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; }
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .badge-paid     { background:#d4edda; color:#155724; }
        .badge-unpaid   { background:#f8d7da; color:#721c24; }
        .badge-occupied { background:#cfe2ff; color:#084298; }
        .badge-vacant   { background:#d1e7dd; color:#0f5132; }
        .modal-actions { display:flex; gap:8px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eee; background:#f9f9f9; }
        .btn-edit { padding:8px 16px; background:#28a745; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-block; font-size:13px; }
        .btn-edit:hover { background:#218838; }
        .btn-close-modal { padding:8px 16px; background:#6c757d; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; }
        .btn-close-modal:hover { background:#545b62; }
    </style>
</head>
<body>
<div class="page">
    <div class="site-header">
        <div class="brand">GRAVE<span>TRACK</span> <span style="font-size:.75rem;font-weight:600;color:#bfdbfe;margin-left:.3rem;">Cemetery Map</span></div>
        <form method="GET" class="nav-actions">
            <input type="text" name="search" class="search-input"
                   placeholder="Search name or block…"
                   value="<?php echo htmlspecialchars($searchQuery); ?>">
            <select name="phase" class="filter-select">
                <option value="0">All Phases</option>
                <?php foreach ($phases as $ph): ?>
                    <option value="<?php echo $ph['phase_number']; ?>"
                            <?php echo $filterPhase == $ph['phase_number'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ph['phase_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="nav-btn btn-search">Search</button>
            <?php if (!empty($searchQuery) || $filterPhase > 0): ?>
                <a href="cemetery_map_v2.php" class="nav-btn btn-clear">Clear</a>
            <?php endif; ?>
            <button type="button" class="btn-power" onclick="window.history.back()" title="Go back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/>
                </svg>
            </button>
        </form>
    </div>

    <div class="legend">
        <div class="legend-item"><div class="lbox vacant"></div>Vacant</div>
        <div class="legend-item"><div class="lbox occupied"></div>Occupied</div>
        <div class="legend-item"><div class="lbox unnamed"></div>Unnamed / Reserved</div>
    </div>

    <div class="map-outer" id="mapOuter">
        <div class="map-scaler" id="mapScaler">
            <div class="map-inner" id="mapInner">
                <div class="all-blocks" id="allBlocks">
                    <?php
                    $LOTS = 20;
                    ?>
                    <!-- AA column -->
                    <div class="block-col">
                        <div class="block-label">AA</div>
                        <div class="plots-stack">
                            <?php for ($lot = $LOTS; $lot >= 1; $lot--):
                                $plotData   = findPlot($plotsData, 'AA', $lot);
                                $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                                $status     = $isOccupied ? 'occupied' : 'vacant';
                                $plotCode   = 'AA-' . $lot;
                                $pid        = $plotData ? $plotData['plot_id'] : 'null';
                                $tip        = $plotCode . ($isOccupied ? ' — ' . htmlspecialchars($plotData['deceased_names']) : ' (Vacant)');
                            ?>
                            <div class="lot-box <?php echo $status; ?>"
                                 title="<?php echo $tip; ?>"
                                 onclick="showLotDetails('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $pid; ?>)">
                                <?php echo $lot; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Unnamed red cluster column -->
                    <div class="unnamed-col">
                        <div class="unnamed-stack">
                            <?php for ($r = 0; $r < 5; $r++): ?>
                                <div class="lot-box unnamed" title="Unnamed / Reserved"></div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="phase-divider"></div>

                    <?php
                    $phase2Groups = [['Z','Y'], ['X','W'], ['V','U'], ['T']];
                    foreach ($phase2Groups as $gi => $group):
                        if ($gi > 0): ?><div class="pair-gap"></div><?php endif;
                        foreach ($group as $blockName):
                            $phaseNum = 2;
                    ?>
                    <div class="block-col">
                        <div class="block-label"><?php echo $blockName; ?></div>
                        <div class="plots-stack">
                            <?php for ($lot = $LOTS; $lot >= 1; $lot--):
                                $plotData   = findPlot($plotsData, $blockName, $lot);
                                $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                                $status     = $isOccupied ? 'occupied' : 'vacant';
                                $plotCode   = $blockName . '-' . $lot;
                                $pid        = $plotData ? $plotData['plot_id'] : 'null';
                                $tip        = $plotCode . ($isOccupied ? ' — ' . htmlspecialchars($plotData['deceased_names']) : ' (Vacant)');
                            ?>
                            <div class="lot-box <?php echo $status; ?>"
                                 title="<?php echo $tip; ?>"
                                 onclick="showLotDetails('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $pid; ?>)">
                                <?php echo $lot; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; endforeach; ?>

                    <div class="phase-divider"></div>

                    <?php
                    $phase1Groups = [['I','H'], ['G','F'], ['E','D'], ['C','B'], ['A']];
                    foreach ($phase1Groups as $gi => $group):
                        if ($gi > 0): ?><div class="pair-gap"></div><?php endif;
                        foreach ($group as $blockName):
                            $phaseNum = 1;
                    ?>
                    <div class="block-col">
                        <div class="block-label"><?php echo $blockName; ?></div>
                        <div class="plots-stack">
                            <?php for ($lot = $LOTS; $lot >= 1; $lot--):
                                $plotData   = findPlot($plotsData, $blockName, $lot);
                                $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                                $status     = $isOccupied ? 'occupied' : 'vacant';
                                $plotCode   = $blockName . '-' . $lot;
                                $pid        = $plotData ? $plotData['plot_id'] : 'null';
                                $tip        = $plotCode . ($isOccupied ? ' — ' . htmlspecialchars($plotData['deceased_names']) : ' (Vacant)');
                            ?>
                            <div class="lot-box <?php echo $status; ?>"
                                 title="<?php echo $tip; ?>"
                                 onclick="showLotDetails('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $pid; ?>)">
                                <?php echo $lot; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; endforeach; ?>
                </div>

                <svg class="fence-row" id="fenceSvg" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                    <defs>
                        <pattern id="picket" x="0" y="0" width="13" height="60" patternUnits="userSpaceOnUse">
                            <polygon points="6.5,1 3.5,12 9.5,12" fill="#1a1008"/>
                            <rect x="3.5" y="11" width="6" height="42" rx="1.5" fill="#1a1008"/>
                        </pattern>
                    </defs>
                    <rect x="0" y="34" width="100%" height="6" fill="#1a1008"/>
                    <rect x="0" y="46" width="100%" height="6" fill="#1a1008"/>
                    <rect x="0" y="0"  width="100%" height="60" fill="url(#picket)"/>
                    <g id="gate1">
                        <rect x="70" y="2" width="54" height="56" rx="5" fill="#3d2008"/>
                        <rect x="73" y="5" width="22" height="50" rx="3" fill="#5c3010"/>
                        <rect x="99" y="5" width="22" height="50" rx="3" fill="#5c3010"/>
                        <path d="M70,16 Q97,-2 124,16" stroke="#3d2008" stroke-width="3" fill="#3d2008"/>
                        <line x1="74"  y1="16" x2="74"  y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="84"  y1="9"  x2="84"  y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="97"  y1="5"  x2="97"  y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="110" y1="9"  x2="110" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="122" y1="16" x2="122" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <circle cx="70"  cy="2" r="4" fill="#5c3010"/>
                        <circle cx="124" cy="2" r="4" fill="#5c3010"/>
                    </g>
                    <g id="gate2" transform="translate(37.5%,0)">
                        <rect x="0"  y="2" width="54" height="56" rx="5" fill="#3d2008"/>
                        <rect x="3"  y="5" width="22" height="50" rx="3" fill="#5c3010"/>
                        <rect x="29" y="5" width="22" height="50" rx="3" fill="#5c3010"/>
                        <path d="M0,16 Q27,-2 54,16" stroke="#3d2008" stroke-width="3" fill="#3d2008"/>
                        <line x1="4"  y1="16" x2="4"  y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="14" y1="9"  x2="14" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="27" y1="5"  x2="27" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="40" y1="9"  x2="40" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="52" y1="16" x2="52" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <circle cx="0"  cy="2" r="4" fill="#5c3010"/>
                        <circle cx="54" cy="2" r="4" fill="#5c3010"/>
                    </g>
                    <g id="gate3" transform="translate(71.5%,0)">
                        <rect x="0"  y="2" width="54" height="56" rx="5" fill="#3d2008"/>
                        <rect x="3"  y="5" width="22" height="50" rx="3" fill="#5c3010"/>
                        <rect x="29" y="5" width="22" height="50" rx="3" fill="#5c3010"/>
                        <path d="M0,16 Q27,-2 54,16" stroke="#3d2008" stroke-width="3" fill="#3d2008"/>
                        <line x1="4"  y1="16" x2="4"  y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="14" y1="9"  x2="14" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="27" y1="5"  x2="27" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="40" y1="9"  x2="40" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <line x1="52" y1="16" x2="52" y2="58" stroke="#1a1008" stroke-width="2"/>
                        <circle cx="0"  cy="2" r="4" fill="#5c3010"/>
                        <circle cx="54" cy="2" r="4" fill="#5c3010"/>
                    </g>
                </svg>

                <div class="phase-labels-row" id="phaseLabelsRow"></div>

            </div><!-- .map-inner -->
        </div><!-- .map-scaler -->
    </div><!-- .map-outer -->

</div><!-- .page -->

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
function scaleMap() {
    const outer  = document.getElementById('mapOuter');
    const scaler = document.getElementById('mapScaler');
    const inner  = document.getElementById('mapInner');
    scaler.style.transform = 'scale(1)';
    const availW = outer.clientWidth  - 32;
    const availH = outer.clientHeight - 16;
    const natW   = inner.scrollWidth;
    const natH   = inner.scrollHeight;
    const scale  = Math.min(availW / natW, availH / natH, 1);
    scaler.style.transform = `scale(${scale})`;
}

function positionPhaseLabels() {
    const allBlocks = document.getElementById('allBlocks');
    const labelsRow = document.getElementById('phaseLabelsRow');
    const dividers = allBlocks.querySelectorAll('.phase-divider');
    const allRect  = allBlocks.getBoundingClientRect();
    if (dividers.length < 2) return;
    const d1 = dividers[0].getBoundingClientRect();
    const d2 = dividers[1].getBoundingClientRect();
    const ph3W = d1.left  - allRect.left;
    const ph2W = d2.left  - d1.right;
    const ph1W = allRect.right - d2.right;
    const divW = d1.width + 12;
    labelsRow.innerHTML = `
        <div class="phase-label-cell" style="width:${ph3W}px">PHASE 3</div>
        <div style="width:${divW}px"></div>
        <div class="phase-label-cell" style="width:${ph2W}px">PHASE 2</div>
        <div style="width:${divW}px"></div>
        <div class="phase-label-cell" style="width:${ph1W}px">PHASE 1</div>
    `;
}

window.addEventListener('load', () => {
    positionPhaseLabels();
    scaleMap();
});
window.addEventListener('resize', scaleMap);

let currentPlotId = null, currentPlotCode = null;

function showLotDetails(plotCode, plotId) {
    currentPlotId   = plotId;
    currentPlotCode = plotCode;
    const modalTitle = document.getElementById('modalTitle');
    const modalBody  = document.getElementById('modalBody');
    const editBtn    = document.getElementById('editLotBtn');
    modalTitle.textContent = 'Plot: ' + plotCode;
    if (plotId === null) {
        modalBody.innerHTML = `
            <div class="record-item">
                <p class="record-name">Vacant</p>
                <p class="record-meta">This lot is currently vacant and has no burial records.</p>
                <div class="badge-container"><span class="badge badge-vacant">Vacant</span></div>
            </div>`;
        editBtn.href        = 'edit_burial_record.php?action=add&block=' + plotCode.split('-')[0] + '&lot=' + plotCode.split('-')[1];
        editBtn.textContent = 'Add Burial Record';
    } else {
        fetch('api/get_lot_details.php?plot_id=' + plotId)
            .then(r => r.json())
            .then(data => { if (data.success) modalBody.innerHTML = data.html; })
            .catch(err => console.error('Error:', err));
        editBtn.href        = 'edit_burial_record.php?action=edit&plot_id=' + plotId;
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
document.getElementById('detailsModal').addEventListener('click', e => {
    if (e.target.id === 'detailsModal') closeModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('detailsModal').classList.contains('active')) closeModal();
});
</script>

</body>
</html>