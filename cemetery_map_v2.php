<?php
require_once 'Database/db_connector.php';

$db   = new db_connector();
$conn = $db->connect();

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterPhase = isset($_GET['phase']) ? (int)$_GET['phase'] : 0;

try {
    $phasesStmt = $conn->prepare("SELECT * FROM phases ORDER BY phase_number ASC");
    $phasesStmt->execute();
    $phases = $phasesStmt->fetchAll();

    $plotsQuery = "
        SELECT
            p.plot_id, p.plot_code, p.lot_number, p.section_number,
            b.block_id, b.block_name,
            ph.phase_id, ph.phase_number, ph.phase_name,
            COUNT(d.deceased_id) as occupant_count,
            GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names,
            COALESCE(MAX(d.payment_status), 'unpaid') as payment_status,
            p.status as plot_status
        FROM plots p
        LEFT JOIN blocks b   ON p.block_id  = b.block_id
        LEFT JOIN phases ph  ON b.phase_id  = ph.phase_id
        LEFT JOIN deceased d ON p.plot_id   = d.plot_id
        GROUP BY p.plot_id
    ";

    if ($filterPhase > 0) {
        $plotsQuery .= " HAVING phase_number = " . (int)$filterPhase;
    }

    if (!empty($searchQuery)) {
        $plotsQuery = "
            SELECT
                p.plot_id, p.plot_code, p.lot_number, p.section_number,
                b.block_id, b.block_name,
                ph.phase_id, ph.phase_number, ph.phase_name,
                COUNT(d.deceased_id) as occupant_count,
                GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names,
                COALESCE(MAX(d.payment_status), 'unpaid') as payment_status,
                p.status as plot_status
            FROM plots p
            LEFT JOIN blocks b   ON p.block_id  = b.block_id
            LEFT JOIN phases ph  ON b.phase_id  = ph.phase_id
            LEFT JOIN deceased d ON p.plot_id   = d.plot_id
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

    function findPlot($plotsData, $blockName, $lotNumber, $phaseNumber = null) {
        foreach ($plotsData as $p) {
            if ($p['block_name'] === $blockName && $p['lot_number'] == $lotNumber) {
                if ($phaseNumber === null || $p['phase_number'] == $phaseNumber) return $p;
            }
        }
        return null;
    }

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

/*
 * LAYOUT — matches the photo exactly (left to right):
 *
 * PHASE 3:  AA (single) | [unnamed red cluster beside AA]
 * divider line
 * PHASE 2:  Z Y | X W | V U | T (single)
 * divider line
 * PHASE 1:  I H | G F | E D | C B | A (single)
 *
 * All named blocks = 20 lots each
 * Fence runs the full width at the bottom
 * Phase labels appear BELOW the fence
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GraveTrack Cemetery Map</title>
    <link rel="stylesheet" href="styles/cemetery_map_style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;         /* NO scroll at all */
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
        }

        /* ── PAGE SHELL ── */
        .page {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* ── HEADER ── */
        .site-header {
            flex-shrink: 0;
            background: linear-gradient(135deg, #3d6b4f, #254d36);
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .site-header h1 { font-size: 18px; white-space: nowrap; }

        /* Search toolbar inline in header */
        .toolbar {
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        }
        .search-input  { padding:6px 10px; border:1px solid #ccc; border-radius:5px; font-size:12px; min-width:160px; }
        .filter-select { padding:6px 10px; border:1px solid #ccc; border-radius:5px; font-size:12px; }
        .btn-search    { padding:6px 14px; background:#fff; color:#254d36; border:none; border-radius:5px; cursor:pointer; font-weight:700; font-size:12px; }
        .btn-search:hover { background:#e0ffe0; }
        .btn-clear     { padding:6px 12px; background:rgba(255,255,255,.2); color:#fff; border:1px solid rgba(255,255,255,.4); border-radius:5px; cursor:pointer; font-weight:600; font-size:12px; text-decoration:none; }

        /* ── LEGEND BAR ── */
        .legend {
            flex-shrink: 0;
            display: flex; gap: 18px; align-items: center;
            padding: 5px 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        .legend-item { display:flex; align-items:center; gap:5px; font-weight:600; color:#444; }
        .lbox { width:22px; height:11px; border-radius:3px; border:2px solid; }
        .lbox.vacant   { background:#5cb85c; border-color:#3a8a3a; }
        .lbox.occupied { background:#d9534f; border-color:#a02020; }
        .lbox.unnamed  { background:#d9534f; border-color:#a02020; }

        /* ── MAP OUTER CONTAINER ── fills remaining vertical space ── */
        .map-outer {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f5f5f5;
            padding: 10px 16px 8px;
        }

        /* ── SCALE WRAPPER ── shrinks the inner map to fit the viewport ── */
        .map-scaler {
            transform-origin: center center;
            /* JS will set transform: scale(N) */
            display: inline-block;
            white-space: nowrap;
        }

        /* ── INNER MAP ── white background, matches the photo ── */
        .map-inner {
            background: #fff;
            padding: 16px 24px 0;
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
        }

        /* ── ALL COLUMNS IN ONE ROW ── */
        .all-blocks {
            display: flex;
            align-items: flex-end;   /* grows upward from fence baseline */
            gap: 0;
        }

        /* Thick divider line between phases (matching the photo) */
        .phase-divider {
            width: 4px;
            background: #111;
            align-self: stretch;
            flex-shrink: 0;
            margin: 0 6px;
        }

        /* Gap between column pairs within a phase */
        .pair-gap { width: 10px; flex-shrink: 0; }

        /* Single block column */
        .block-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 3px;
        }
        .block-label {
            font-size: 13px;
            font-weight: 900;
            color: #111;
            margin-bottom: 6px;
            letter-spacing: .02em;
        }
        .plots-stack {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        /* Plot rectangle — green/red rounded box */
        .lot-box {
            width: 52px;
            height: 19px;
            border-radius: 6px;
            border: 2px solid;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: 700;
            color: rgba(0,0,0,.45);
            transition: transform .1s, box-shadow .1s;
            position: relative;
        }
        .lot-box:hover {
            transform: scale(1.25);
            box-shadow: 0 3px 10px rgba(0,0,0,.3);
            z-index: 100;
        }
        .lot-box.vacant   { background:#5cb85c; border-color:#3a8a3a; }
        .lot-box.occupied { background:#d9534f; border-color:#a02020; color:#fff; }

        /* Unnamed red cluster (Phase 3, beside AA) */
        .unnamed-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            padding: 0 3px;
        }
        .unnamed-stack {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .lot-box.unnamed {
            background: #d9534f;
            border-color: #a02020;
            width: 48px;
            height: 19px;
            cursor: default;
            color: transparent;
        }
        .lot-box.unnamed:hover { transform: none; box-shadow: none; }

        /* ── FENCE (full-width SVG below all blocks) ── */
        .fence-row {
            width: 100%;
            display: block;
            height: 60px;
            margin-top: 8px;
        }

        /* ── PHASE LABELS below fence ── */
        .phase-labels-row {
            display: flex;
            width: 100%;
            margin-top: 6px;
            padding-bottom: 10px;
            font-size: 13px;
            font-weight: 900;
            color: #111;
            letter-spacing: .08em;
        }
        .phase-label-cell {
            text-align: center;
            text-transform: uppercase;
        }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 1000;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #fff; border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,.3);
            width: 90%; max-width: 540px; max-height: 80vh; overflow-y: auto;
        }
        .modal-header {
            display:flex; justify-content:space-between; align-items:center;
            padding:14px 18px; border-bottom:2px solid #eee;
        }
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
        .modal-actions {
            display:flex; gap:8px; justify-content:flex-end;
            padding:12px 18px; border-top:1px solid #eee; background:#f9f9f9;
        }
        .btn-edit {
            padding:8px 16px; background:#28a745; color:#fff;
            border:none; border-radius:6px; cursor:pointer; font-weight:600;
            text-decoration:none; display:inline-block; font-size:13px;
        }
        .btn-edit:hover { background:#218838; }
        .btn-close-modal {
            padding:8px 16px; background:#6c757d; color:#fff;
            border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px;
        }
        .btn-close-modal:hover { background:#545b62; }
    </style>
</head>
<body>
<div class="page">

    <!-- HEADER + SEARCH -->
    <div class="site-header">
        <h1>🪦 GraveTrack Cemetery Map</h1>
        <form method="GET" class="toolbar">
            <input type="text" name="search" class="search-input"
                   placeholder="Search name or plot code…"
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
            <button type="submit" class="btn-search">Search</button>
            <?php if (!empty($searchQuery) || $filterPhase > 0): ?>
                <a href="cemetery_map_v2.php" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LEGEND -->
    <div class="legend">
        <div class="legend-item"><div class="lbox vacant"></div>Vacant</div>
        <div class="legend-item"><div class="lbox occupied"></div>Occupied</div>
        <div class="legend-item"><div class="lbox unnamed"></div>Unnamed / Reserved</div>
    </div>

    <!-- MAP -->
    <div class="map-outer" id="mapOuter">
        <div class="map-scaler" id="mapScaler">
            <div class="map-inner" id="mapInner">

                <!-- ═══════════════════════════════════════════════
                     ALL BLOCKS IN ONE HORIZONTAL ROW
                     Left → Right:  PHASE 3 | PHASE 2 | PHASE 1
                     ═══════════════════════════════════════════════ -->
                <div class="all-blocks" id="allBlocks">

                    <?php
                    $LOTS = 20;   // 20 lots per named block

                    /* ── PHASE 3: AA (single col) + unnamed red cluster ── */
                    ?>

                    <!-- AA column -->
                    <div class="block-col">
                        <div class="block-label">AA</div>
                        <div class="plots-stack">
                            <?php for ($lot = 1; $lot <= $LOTS; $lot++):
                                $plotData   = findPlot($plotsData, 'AA', $lot, 3);
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

                    <!-- Unnamed red cluster column (beside AA, bottom-aligned) -->
                    <div class="unnamed-col">
                        <div class="unnamed-stack">
                            <?php for ($r = 0; $r < 5; $r++): ?>
                                <div class="lot-box unnamed" title="Unnamed / Reserved"></div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Phase 3 → Phase 2 divider -->
                    <div class="phase-divider"></div>

                    <?php
                    /* ── PHASE 2: Z Y | X W | V U | T ── */
                    $phase2Groups = [['Z','Y'], ['X','W'], ['V','U'], ['T']];
                    foreach ($phase2Groups as $gi => $group):
                        if ($gi > 0): ?><div class="pair-gap"></div><?php endif;
                        foreach ($group as $blockName):
                            $phaseNum = 2;
                    ?>
                    <div class="block-col">
                        <div class="block-label"><?php echo $blockName; ?></div>
                        <div class="plots-stack">
                            <?php for ($lot = 1; $lot <= $LOTS; $lot++):
                                $plotData   = findPlot($plotsData, $blockName, $lot, $phaseNum);
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
                    <?php endforeach; /* blocks in group */
                    endforeach; /* phase2 groups */ ?>

                    <!-- Phase 2 → Phase 1 divider -->
                    <div class="phase-divider"></div>

                    <?php
                    /* ── PHASE 1: I H | G F | E D | C B | A ── */
                    $phase1Groups = [['I','H'], ['G','F'], ['E','D'], ['C','B'], ['A']];
                    foreach ($phase1Groups as $gi => $group):
                        if ($gi > 0): ?><div class="pair-gap"></div><?php endif;
                        foreach ($group as $blockName):
                            $phaseNum = 1;
                    ?>
                    <div class="block-col">
                        <div class="block-label"><?php echo $blockName; ?></div>
                        <div class="plots-stack">
                            <?php for ($lot = 1; $lot <= $LOTS; $lot++):
                                $plotData   = findPlot($plotsData, $blockName, $lot, $phaseNum);
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
                    <?php endforeach;
                    endforeach; ?>

                </div><!-- .all-blocks -->

                <!-- ══════════════════════════════════
                     FENCE — full width, single strip
                     ══════════════════════════════════ -->
                <svg class="fence-row" id="fenceSvg" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                    <defs>
                        <!-- Picket pattern -->
                        <pattern id="picket" x="0" y="0" width="13" height="60" patternUnits="userSpaceOnUse">
                            <polygon points="6.5,1 3.5,12 9.5,12" fill="#1a1008"/>
                            <rect x="3.5" y="11" width="6" height="42" rx="1.5" fill="#1a1008"/>
                        </pattern>
                    </defs>
                    <!-- Rails -->
                    <rect x="0" y="34" width="100%" height="6" fill="#1a1008"/>
                    <rect x="0" y="46" width="100%" height="6" fill="#1a1008"/>
                    <!-- Pickets overlay -->
                    <rect x="0" y="0"  width="100%" height="60" fill="url(#picket)"/>

                    <!-- Gate 1 — Phase 3 section (~10% from left) -->
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
                        <!-- Gate post balls -->
                        <circle cx="70"  cy="2" r="4" fill="#5c3010"/>
                        <circle cx="124" cy="2" r="4" fill="#5c3010"/>
                    </g>

                    <!-- Gate 2 — Phase 2 section (~38% from left) -->
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

                    <!-- Gate 3 — Phase 1 section (~72% from left) -->
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

                <!-- ══════════════════════════════════
                     PHASE LABELS below the fence
                     (widths set by JS to match columns)
                     ══════════════════════════════════ -->
                <div class="phase-labels-row" id="phaseLabelsRow">
                    <!-- filled by JS -->
                </div>

            </div><!-- .map-inner -->
        </div><!-- .map-scaler -->
    </div><!-- .map-outer -->

</div><!-- .page -->

<!-- MODAL -->
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
/* ── SCALE TO FIT ────────────────────────────────────────────────── */
function scaleMap() {
    const outer  = document.getElementById('mapOuter');
    const scaler = document.getElementById('mapScaler');
    const inner  = document.getElementById('mapInner');

    // Reset scale to measure natural size
    scaler.style.transform = 'scale(1)';

    const availW = outer.clientWidth  - 32;
    const availH = outer.clientHeight - 16;
    const natW   = inner.scrollWidth;
    const natH   = inner.scrollHeight;

    const scale  = Math.min(availW / natW, availH / natH, 1);
    scaler.style.transform = `scale(${scale})`;
}

/* ── PHASE LABELS (positioned by measuring block columns) ── */
function positionPhaseLabels() {
    const allBlocks = document.getElementById('allBlocks');
    const labelsRow = document.getElementById('phaseLabelsRow');

    // Find dividers
    const dividers = allBlocks.querySelectorAll('.phase-divider');
    const allRect  = allBlocks.getBoundingClientRect();

    // We have 2 dividers: [0]=between ph3 and ph2, [1]=between ph2 and ph1
    if (dividers.length < 2) return;

    const d1 = dividers[0].getBoundingClientRect();
    const d2 = dividers[1].getBoundingClientRect();

    const ph3W = d1.left  - allRect.left;
    const ph2W = d2.left  - d1.right;
    const ph1W = allRect.right - d2.right;
    const divW = d1.width + 12; // divider + margins

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

/* ── MODAL ── */
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
        editBtn.href        = 'edit_burial_record.php?action=add&plot_code=' + plotCode;
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