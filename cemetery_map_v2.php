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

    $plotsData = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedPlots = [];
    foreach ($plotsData as $plot) {
        $blockKey = $plot['block'];
        $groupedPlots[$blockKey][$plot['lot']] = $plot;
    }
    ksort($groupedPlots);

    function getPlotData($groupedPlots, $blockName, $lotNumber) {
        return $groupedPlots[$blockName][$lotNumber] ?? null;
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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%;
            height: 100dvh;
            overflow: hidden;
            font-family: 'DM Sans', sans-serif;
            background: #e8f0fb;
        }

        .page {
            display: flex;
            flex-direction: column;
            height: 100dvh;
            width: 100%;
            overflow: hidden;
        }

        .shell {
            display: flex;
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: #1a3a6e;
            display: flex;
            flex-direction: column;
            border-right: 2px solid rgba(96,165,250,.25);
            overflow: hidden;
            height: 100%;
        }

        .sidebar-header {
            padding: 14px 16px 10px;
            border-bottom: 1px solid rgba(96,165,250,.2);
        }

        .brand {
            font-family: 'Syne', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: .04em;
            color: #fff;
            user-select: none;
        }
        .brand span { color: #60a5fa; }
        .brand-sub {
            font-size: .7rem;
            color: #93c5fd;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .search-wrap { padding: 12px 16px; }
        .search-input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1.5px solid rgba(96,165,250,.3);
            background: rgba(255,255,255,.08);
            color: #e2e8f0;
            font-family: 'DM Sans', sans-serif;
            font-size: .82rem;
            outline: none;
        }
        .search-input::placeholder { color: #64748b; }
        .search-input:focus { border-color: #60a5fa; background: rgba(255,255,255,.12); }

        .filter-section {
            padding: 0 16px 12px;
            border-bottom: 1px solid rgba(96,165,250,.15);
        }
        .filter-label {
            font-size: .7rem;
            font-weight: 700;
            color: #93c5fd;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .phase-btns { display: flex; flex-direction: column; gap: 6px; }
        .phase-btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1.5px solid rgba(96,165,250,.3);
            background: rgba(37,99,235,.15);
            color: #bfdbfe;
            font-family: 'DM Sans', sans-serif;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: all .15s;
        }
        .phase-btn:hover, .phase-btn.active {
            background: #2563eb;
            border-color: #60a5fa;
            color: #fff;
        }

        .legend-section {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(96,165,250,.15);
        }
        .leg-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: .75rem;
            font-weight: 600;
            color: #bfdbfe;
        }
        .lbox { width: 20px; height: 10px; border-radius: 3px; border: 2px solid; flex-shrink: 0; }
        .lbox.v { background: #22c55e; border-color: #16a34a; }
        .lbox.o { background: #ef4444; border-color: #dc2626; }
        .lbox.u { background: #f87171; border-color: #dc2626; }

        .detail-panel {
            flex: 1;
            overflow-y: auto;
            padding: 14px 16px;
        }
        .detail-panel::-webkit-scrollbar { width: 4px; }
        .detail-panel::-webkit-scrollbar-track { background: transparent; }
        .detail-panel::-webkit-scrollbar-thumb { background: rgba(96,165,250,.4); border-radius: 4px; }

        .detail-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            gap: 8px;
        }
        .detail-empty-icon { font-size: 2rem; opacity: .4; }

        .detail-title {
            font-size: .7rem;
            font-weight: 700;
            color: #93c5fd;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .detail-card {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(96,165,250,.2);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
        }
        .detail-plot-code { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 4px; }
        .detail-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: .7rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .badge-vacant  { background: rgba(34,197,94,.2); color: #86efac; border: 1px solid #22c55e; }
        .badge-occupied { background: rgba(239,68,68,.2); color: #fca5a5; border: 1px solid #ef4444; }
        .detail-row { font-size: .78rem; color: #93c5fd; margin: 3px 0; }
        .detail-row strong { color: #e2e8f0; font-weight: 600; }
        .detail-name { font-size: .9rem; font-weight: 700; color: #fff; margin-bottom: 2px; }
        .detail-meta { font-size: .75rem; color: #93c5fd; margin: 2px 0; }

        .occupant-item {
            background: rgba(37,99,235,.15);
            border: 1px solid rgba(96,165,250,.2);
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 6px;
        }

        .btn-action {
            width: 100%;
            padding: 9px;
            border-radius: 7px;
            border: none;
            background: #2563eb;
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: .8rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 4px;
            transition: background .15s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .btn-action:hover { background: #1d4ed8; }
        .btn-back {
            width: 100%;
            padding: 7px;
            border-radius: 7px;
            border: 1.5px solid rgba(96,165,250,.3);
            background: transparent;
            color: #93c5fd;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
            transition: all .15s;
        }
        .btn-back:hover { background: rgba(37,99,235,.2); color: #bfdbfe; }

        /* ── MAP PANE ── */
        .map-pane {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            background: #e8f0fb;
            padding: 8px;
            overflow: hidden;
        }

        .map-inner {
            flex: 1;
            min-height: 0;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
            padding: 10px 10px 4px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            overflow: hidden;
        }

        .all-blocks {
            flex: 1;
            min-height: 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            width: 100%;
        }

        .phase-divider {
            width: 5px;
            background: #111;
            align-self: stretch;
            flex-shrink: 0;
            margin: 0 4px;
            border-radius: 2px;
        }
        .pair-gap { width: 8px; flex-shrink: 0; }

        .block-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 var(--col-pad, 3px);
            flex-shrink: 0;
            min-width: 0;
        }
        .block-label {
            font-size: var(--label-font, 11px);
            font-weight: 900;
            color: #111;
            margin-bottom: 4px;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .plots-stack {
            display: flex;
            flex-direction: column;
            gap: var(--lot-gap, 2px);
            width: 100%;
            align-items: center;
        }

        .lot-box {
            width: var(--lot-w, 36px);
            height: var(--lot-h, 18px);
            border-radius: 4px;
            border: 2px solid;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--lot-font, 9px);
            font-weight: 700;
            color: rgba(0,0,0,.45);
            transition: transform .1s, box-shadow .1s;
            position: relative;
            flex-shrink: 0;
        }
        .lot-box:hover {
            transform: scale(1.3);
            box-shadow: 0 4px 12px rgba(0,0,0,.35);
            z-index: 100;
        }
        .lot-box.vacant   { background: #22c55e; border-color: #16a34a; }
        .lot-box.occupied { background: #ef4444; border-color: #dc2626; color: #fff; }
        .lot-box.highlight {
            box-shadow: 0 0 0 3px #fbbf24, 0 4px 12px rgba(0,0,0,.3) !important;
            transform: scale(1.15);
            z-index: 50;
        }

        .unnamed-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            padding: 0 3px;
            flex-shrink: 0;
        }
        .unnamed-stack { display: flex; flex-direction: column; gap: 3px; }
        .lot-box.unnamed {
            background: #f87171;
            border-color: #dc2626;
            width: calc(var(--lot-w, 36px) * 0.65);
            height: calc(var(--lot-h, 18px) * 0.75);
            cursor: default;
            color: transparent;
            opacity: .7;
        }
        .lot-box.unnamed:hover { transform: none; box-shadow: none; }

        .fence-row {
            width: 100%;
            display: block;
            height: 50px;
            flex-shrink: 0;
            margin-top: 6px;
        }

        .phase-labels-row {
            display: flex;
            width: 100%;
            flex-shrink: 0;
            padding-bottom: 2px;
            font-size: 11px;
            font-weight: 900;
            color: #111;
            letter-spacing: .08em;
        }
        .phase-label-cell { text-align: center; text-transform: uppercase; }

        /* ── NAVBAR ── */
        .site-header {
            flex-shrink: 0;
            background: #1a3a6e;
            padding: 0.4rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
            z-index: 200;
        }
        .nav-brand {
            font-family: 'Syne', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: .03em;
            color: #ffffff;
            user-select: none;
            white-space: nowrap;
        }
        .nav-brand span { color: #60a5fa; }
        .nav-brand-sub {
            font-size: .65rem;
            font-weight: 600;
            color: #bfdbfe;
            margin-left: .4rem;
            letter-spacing: .04em;
        }
        .nav-actions { display: flex; align-items: center; gap: .5rem; }
        .btn-power {
            width: 34px; height: 34px;
            border-radius: 50%;
            border: none;
            background: #ef4444;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .15s;
        }
        .btn-power:hover { background: #dc2626; }
        .btn-power svg { width: 15px; height: 15px; }
    </style>
</head>
<body>
<div class="page">

    <!-- ── NAVBAR ── -->
    <div class="site-header">
        <div class="nav-brand">
            GRAVE<span>TRACK</span>
            <span class="nav-brand-sub">Cemetery Map</span>
        </div>
        <div class="nav-actions">
            <button type="button" class="btn-power" onclick="window.history.back()" title="Go back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/>
                </svg>
            </button>
        </div>
    </div>

<div class="shell">

    <!-- ── SIDEBAR ── -->
    <div class="sidebar">
        <div class="sidebar-header" style="padding:10px 16px 8px;border-bottom:1px solid rgba(96,165,250,.2)">
            <div style="font-size:.7rem;font-weight:700;color:#93c5fd;letter-spacing:.1em;text-transform:uppercase">Map Controls</div>
        </div>

        <div class="search-wrap">
            <input type="text" class="search-input" id="searchInput"
                   placeholder="Search name or block…"
                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                   oninput="doSearch(this.value)">
        </div>

        <div class="filter-section">
            <div class="filter-label">Filter by Phase</div>
            <div class="phase-btns">
                <button class="phase-btn <?php echo $filterPhase==0?'active':''; ?>"
                        onclick="filterPhase(0,this)">All Phases</button>
                <?php foreach ($phases as $ph): ?>
                <button class="phase-btn <?php echo $filterPhase==$ph['phase_number']?'active':''; ?>"
                        onclick="filterPhase(<?php echo $ph['phase_number']; ?>,this)">
                    <?php echo htmlspecialchars($ph['phase_name']); ?>
                    <?php
                    $labels = [1=>'(A–I)', 2=>'(T–Z)', 3=>'(AA)'];
                    echo isset($labels[$ph['phase_number']]) ? ' '.$labels[$ph['phase_number']] : '';
                    ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="legend-section">
            <div class="filter-label">Legend</div>
            <div class="leg-item"><div class="lbox v"></div>Vacant</div>
            <div class="leg-item"><div class="lbox o"></div>Occupied</div>
            <div class="leg-item"><div class="lbox u"></div>Unnamed / Reserved</div>
        </div>

        <div class="detail-panel" id="detailPanel">
            <div class="detail-empty" id="detailEmpty">
                <div class="detail-empty-icon">🪦</div>
                <div style="color:#93c5fd;font-weight:600;font-size:.85rem">Select a lot</div>
                <div style="color:#475569;font-size:.75rem;margin-top:4px">Click any lot on the map<br>to view details here</div>
            </div>
            <div id="detailContent" style="display:none"></div>
        </div>
    </div>

    <!-- ── MAP PANE ── -->
    <div class="map-pane">
        <div class="map-inner" id="mapInner">
            <div class="all-blocks" id="allBlocks">

                <?php
                /* ── Phase 3: AA ── */
                $phase3Blocks = array_filter($groupedPlots, fn($b) => strtoupper($b)=='AA', ARRAY_FILTER_USE_KEY);
                foreach ($phase3Blocks as $blockName => $lotsData):
                ?>
                <div class="block-col" data-phase="3">
                    <div class="block-label"><?php echo htmlspecialchars($blockName); ?></div>
                    <div class="plots-stack">
                        <?php
                        ksort($lotsData);
                        for ($lot = 1; $lot <= max(array_keys($lotsData)); $lot++):
                            $plotData  = getPlotData($groupedPlots, $blockName, $lot);
                            $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                            $status    = $isOccupied ? 'occupied' : 'vacant';
                            $plotCode  = $blockName . '-' . $lot;
                            $pid       = $plotData ? $plotData['plot_id'] : 'null';
                            $names_js  = $plotData && $isOccupied ? htmlspecialchars($plotData['deceased_names'], ENT_QUOTES) : '';
                        ?>
                        <div class="lot-box <?php echo $status; ?>"
                             data-block="<?php echo htmlspecialchars($blockName); ?>"
                             data-lot="<?php echo $lot; ?>"
                             data-pid="<?php echo $pid; ?>"
                             data-names="<?php echo $names_js; ?>"
                             data-phase="3"
                             onclick="showDetail('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $pid; ?>, '<?php echo $status; ?>', '<?php echo $names_js; ?>', 3, '<?php echo htmlspecialchars($blockName); ?>', <?php echo $lot; ?>)">
                            <?php echo $lot; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="unnamed-col" data-phase="3">
                    <div class="unnamed-stack">
                        <?php for ($r = 0; $r < 5; $r++): ?>
                        <div class="lot-box unnamed" title="Unnamed / Reserved"></div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="phase-divider" id="div1"></div>

                <?php
                /* ── Phase 2: Z,Y | X,W | V,U | T ── */
                $phase2Groups = [['Z','Y'], ['X','W'], ['V','U'], ['T']];
                foreach ($phase2Groups as $gi => $group):
                    if ($gi > 0): ?><div class="pair-gap" data-phase="2"></div><?php endif;
                    foreach ($group as $blockName):
                        $blockLots = $groupedPlots[$blockName] ?? [];
                        ksort($blockLots);
                        $maxLot = !empty($blockLots) ? max(array_keys($blockLots)) : 20;
                ?>
                <div class="block-col" data-phase="2">
                    <div class="block-label"><?php echo htmlspecialchars($blockName); ?></div>
                    <div class="plots-stack">
                        <?php for ($lot = 1; $lot <= $maxLot; $lot++):
                            $plotData  = getPlotData($groupedPlots, $blockName, $lot);
                            $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                            $status    = $isOccupied ? 'occupied' : 'vacant';
                            $plotCode  = $blockName . '-' . $lot;
                            $pid       = $plotData ? $plotData['plot_id'] : 'null';
                            $names_js  = $plotData && $isOccupied ? htmlspecialchars($plotData['deceased_names'], ENT_QUOTES) : '';
                        ?>
                        <div class="lot-box <?php echo $status; ?>"
                             data-block="<?php echo htmlspecialchars($blockName); ?>"
                             data-lot="<?php echo $lot; ?>"
                             data-pid="<?php echo $pid; ?>"
                             data-names="<?php echo $names_js; ?>"
                             data-phase="2"
                             onclick="showDetail('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $pid; ?>, '<?php echo $status; ?>', '<?php echo $names_js; ?>', 2, '<?php echo htmlspecialchars($blockName); ?>', <?php echo $lot; ?>)">
                            <?php echo $lot; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; endforeach; ?>

                <div class="phase-divider" id="div2"></div>

                <?php
                /* ── Phase 1: I,H | G,F | E,D | C,B | A ── */
                $phase1Groups = [['I','H'], ['G','F'], ['E','D'], ['C','B'], ['A']];
                foreach ($phase1Groups as $gi => $group):
                    if ($gi > 0): ?><div class="pair-gap" data-phase="1"></div><?php endif;
                    foreach ($group as $blockName):
                        $blockLots = $groupedPlots[$blockName] ?? [];
                        ksort($blockLots);
                        $maxLot = !empty($blockLots) ? max(array_keys($blockLots)) : 20;
                ?>
                <div class="block-col" data-phase="1">
                    <div class="block-label"><?php echo htmlspecialchars($blockName); ?></div>
                    <div class="plots-stack">
                        <?php for ($lot = 1; $lot <= $maxLot; $lot++):
                            $plotData  = getPlotData($groupedPlots, $blockName, $lot);
                            $isOccupied = $plotData && $plotData['occupant_count'] > 0;
                            $status    = $isOccupied ? 'occupied' : 'vacant';
                            $plotCode  = $blockName . '-' . $lot;
                            $pid       = $plotData ? $plotData['plot_id'] : 'null';
                            $names_js  = $plotData && $isOccupied ? htmlspecialchars($plotData['deceased_names'], ENT_QUOTES) : '';
                        ?>
                        <div class="lot-box <?php echo $status; ?>"
                             data-block="<?php echo htmlspecialchars($blockName); ?>"
                             data-lot="<?php echo $lot; ?>"
                             data-pid="<?php echo $pid; ?>"
                             data-names="<?php echo $names_js; ?>"
                             data-phase="1"
                             onclick="showDetail('<?php echo htmlspecialchars($plotCode); ?>', <?php echo $pid; ?>, '<?php echo $status; ?>', '<?php echo $names_js; ?>', 1, '<?php echo htmlspecialchars($blockName); ?>', <?php echo $lot; ?>)">
                            <?php echo $lot; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; endforeach; ?>

            </div><!-- .all-blocks -->

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
            </svg>

            <div class="phase-labels-row" id="phaseLabelsRow"></div>

        </div><!-- .map-inner -->
    </div><!-- .map-pane -->

</div><!-- .shell -->
</div><!-- .page -->

<script>
// ── Detail panel ──
function showDetail(plotCode, plotId, status, names, phase, block, lot) {
    // Remove previous highlight
    document.querySelectorAll('.lot-box.highlight').forEach(el => el.classList.remove('highlight'));

    // Highlight selected lot
    const sel = document.querySelector(`.lot-box[data-block="${block}"][data-lot="${lot}"]`);
    if (sel) sel.classList.add('highlight');

    document.getElementById('detailEmpty').style.display = 'none';
    const panel = document.getElementById('detailContent');
    panel.style.display = 'block';

    const isOccupied = status === 'occupied';
    const phaseNames = {1:'Phase 1 (A–I)', 2:'Phase 2 (T–Z)', 3:'Phase 3 (AA)'};

    let occupantsHTML = '';
    if (isOccupied && names) {
        const nameList = names.split(', ');
        occupantsHTML = `
            <div class="detail-title" style="margin-top:12px">Occupants</div>
            ${nameList.map(n => `
                <div class="occupant-item">
                    <div class="detail-name">${n}</div>
                    <div class="detail-meta">Plot ${plotCode}</div>
                </div>`).join('')}`;

        // Also fetch full details from API if plotId is set
        if (plotId !== null && plotId !== 'null') {
            fetch('api/get_lot_details.php?plot_id=' + plotId)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.html) {
                        const occDiv = panel.querySelector('#occupants-dynamic');
                        if (occDiv) occDiv.innerHTML = data.html;
                    }
                })
                .catch(() => {});
        }
    }

    const editHref = isOccupied
        ? `edit_burial_record.php?action=edit&plot_id=${plotId}`
        : `edit_burial_record.php?action=add&block=${block}&lot=${lot}`;

    panel.innerHTML = `
        <div class="detail-title">Lot Details</div>
        <div class="detail-card">
            <div class="detail-plot-code">${plotCode}</div>
            <span class="detail-badge ${isOccupied ? 'badge-occupied' : 'badge-vacant'}">
                ${isOccupied ? 'Occupied' : 'Vacant'}
            </span>
            <div class="detail-row"><strong>Block:</strong> ${block}</div>
            <div class="detail-row"><strong>Lot #:</strong> ${lot}</div>
            <div class="detail-row"><strong>Phase:</strong> ${phaseNames[phase] || 'Unknown'}</div>
        </div>
        ${isOccupied
            ? `<div id="occupants-dynamic">${occupantsHTML}</div>`
            : `<div style="font-size:.78rem;color:#64748b;padding:4px 0 8px">This lot is currently vacant.</div>`
        }
        <a href="${editHref}" class="btn-action">
            ${isOccupied ? 'Edit Record' : 'Add Burial Record'}
        </a>
        <button class="btn-back" onclick="clearDetail()">← Back</button>
    `;
}

function clearDetail() {
    document.querySelectorAll('.lot-box.highlight').forEach(el => el.classList.remove('highlight'));
    document.getElementById('detailEmpty').style.display = 'flex';
    document.getElementById('detailContent').style.display = 'none';
}

// ── Search (live filter) ──
function doSearch(val) {
    const v = val.toLowerCase().trim();
    document.querySelectorAll('.lot-box:not(.unnamed)').forEach(el => {
        if (!v) { el.style.opacity = '1'; el.classList.remove('highlight'); return; }
        const b = el.dataset.block, l = el.dataset.lot;
        const code = (b + '-' + l).toLowerCase();
        const names2 = (el.dataset.names || '').toLowerCase();
        const match = code.includes(v) || names2.includes(v) || b.toLowerCase().includes(v);
        el.style.opacity = match ? '1' : '0.12';
        if (match) el.classList.add('highlight'); else el.classList.remove('highlight');
    });
}

// ── Phase filter ──
let activePhase = <?php echo $filterPhase; ?>;

function filterPhase(ph, btn) {
    activePhase = ph;
    document.querySelectorAll('.phase-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.block-col, .unnamed-col').forEach(col => {
        const cp = parseInt(col.dataset.phase || 0);
        col.style.display = (ph === 0 || cp === ph) ? '' : 'none';
    });
    document.querySelectorAll('.pair-gap').forEach(el => {
        const cp = parseInt(el.dataset.phase || 0);
        el.style.display = (ph === 0 || cp === ph) ? '' : 'none';
    });
    document.querySelectorAll('.phase-divider').forEach(el => {
        el.style.display = ph === 0 ? '' : 'none';
    });

    clearDetail();
    requestAnimationFrame(() => { fitMap(); positionPhaseLabels(); positionGates(); });
}

// ── Gate SVG builder ──
function gateHTML(cx) {
    const gw = 48, x = cx - gw / 2;
    return `<g transform="translate(${x},0)">
        <rect x="0"  y="2"  width="${gw}" height="54" rx="4" fill="#3d2008"/>
        <rect x="2"  y="5"  width="${gw/2-2}" height="46" rx="2" fill="#5c3010"/>
        <rect x="${gw/2+1}" y="5" width="${gw/2-2}" height="46" rx="2" fill="#5c3010"/>
        <path d="M0,15 Q${gw/2},-2 ${gw},15" stroke="#3d2008" stroke-width="2.5" fill="#3d2008"/>
        <line x1="3"           y1="15" x2="3"           y2="56" stroke="#1a1008" stroke-width="1.5"/>
        <line x1="${gw/2}"     y1="5"  x2="${gw/2}"     y2="56" stroke="#1a1008" stroke-width="2"/>
        <line x1="${gw-3}"     y1="15" x2="${gw-3}"     y2="56" stroke="#1a1008" stroke-width="1.5"/>
        <circle cx="0"   cy="2" r="3.5" fill="#5c3010"/>
        <circle cx="${gw}" cy="2" r="3.5" fill="#5c3010"/>
    </g>`;
}

function positionGates() {
    const svg       = document.getElementById('fenceSvg');
    const allBlocks = document.getElementById('allBlocks');
    const dividers  = Array.from(allBlocks.querySelectorAll('.phase-divider')).filter(d => d.style.display !== 'none');
    const svgRect   = svg.getBoundingClientRect();
    const allRect   = allBlocks.getBoundingClientRect();
    svg.querySelectorAll('.js-gate').forEach(g => g.remove());
    const ns = 'http://www.w3.org/2000/svg';

    let centers = [];
    if (dividers.length >= 2) {
        const d1 = dividers[0].getBoundingClientRect();
        const d2 = dividers[1].getBoundingClientRect();
        centers = [
            (allRect.left  + d1.left) / 2 - svgRect.left,
            (d1.right + d2.left)      / 2 - svgRect.left,
            (d2.right + allRect.right)/ 2 - svgRect.left,
        ];
    } else {
        centers = [(allRect.left + allRect.right) / 2 - svgRect.left];
    }

    centers.forEach(cx => {
        const g = document.createElementNS(ns, 'g');
        g.classList.add('js-gate');
        g.innerHTML = gateHTML(cx);
        svg.appendChild(g);
    });
}

function fitMap() {
    const outer     = document.getElementById('mapInner');
    const allBlocks = document.getElementById('allBlocks');
    const root      = document.documentElement;

    const fenceH = 50, labH = 20, padV = 14;
    const availH = outer.clientHeight - fenceH - labH - padV;
    const availW = outer.clientWidth  - 20;

    const blockCols = Array.from(allBlocks.querySelectorAll('.block-col')).filter(c => c.style.display !== 'none');
    const dividers  = Array.from(allBlocks.querySelectorAll('.phase-divider')).filter(d => d.style.display !== 'none');
    const pairGaps  = Array.from(allBlocks.querySelectorAll('.pair-gap')).filter(g => g.style.display !== 'none');

    let maxLots = 0;
    blockCols.forEach(col => {
        const n = col.querySelectorAll('.lot-box:not(.unnamed)').length;
        if (n > maxLots) maxLots = n;
    });
    if (maxLots === 0) maxLots = 20;

    const divTotalW = dividers.length * (5 + 8);
    const gapTotalW = pairGaps.length * 8;
    const lotGap    = 2;
    const labelH2   = 20;
    const lotH      = Math.max(12, Math.floor((availH - labelH2) / maxLots) - lotGap);
    const usableW   = availW - divTotalW - gapTotalW;
    const lotWFull  = Math.max(24, Math.floor(usableW / blockCols.length) - 6);
    const lotWCap   = Math.round(lotH * 2.8);
    const lotW      = Math.min(lotWFull, lotWCap);
    const lotFont   = Math.max(7,  Math.min(11, Math.floor(lotH  * 0.46)));
    const labelFont = Math.max(8,  Math.min(13, Math.floor(lotW  * 0.20)));

    root.style.setProperty('--lot-w',      lotW      + 'px');
    root.style.setProperty('--lot-h',      lotH      + 'px');
    root.style.setProperty('--lot-font',   lotFont   + 'px');
    root.style.setProperty('--lot-gap',    lotGap    + 'px');
    root.style.setProperty('--label-font', labelFont + 'px');
}

function positionPhaseLabels() {
    const allBlocks = document.getElementById('allBlocks');
    const labelsRow = document.getElementById('phaseLabelsRow');
    const dividers  = Array.from(allBlocks.querySelectorAll('.phase-divider')).filter(d => d.style.display !== 'none');
    const allRect   = allBlocks.getBoundingClientRect();
    if (dividers.length < 2) { labelsRow.innerHTML = ''; return; }
    const d1   = dividers[0].getBoundingClientRect();
    const d2   = dividers[1].getBoundingClientRect();
    const ph3W = d1.left - allRect.left;
    const ph2W = d2.left - d1.right;
    const ph1W = allRect.right - d2.right;
    const divW = d1.width + 8;
    labelsRow.innerHTML = `
        <div class="phase-label-cell" style="flex:0 0 ${ph3W}px">PHASE 3</div>
        <div style="flex:0 0 ${divW}px"></div>
        <div class="phase-label-cell" style="flex:0 0 ${ph2W}px">PHASE 2</div>
        <div style="flex:0 0 ${divW}px"></div>
        <div class="phase-label-cell" style="flex:0 0 ${ph1W}px">PHASE 1</div>`;
}

function init() {
    fitMap();
    requestAnimationFrame(() => { positionPhaseLabels(); positionGates(); });
}

window.addEventListener('load', init);

// Apply initial phase filter if set from URL
<?php if ($filterPhase > 0): ?>
window.addEventListener('load', () => {
    const btn = document.querySelector(`.phase-btn:nth-child(<?php echo $filterPhase + 1; ?>)`);
    if (btn) filterPhase(<?php echo $filterPhase; ?>, btn);
});
<?php endif; ?>

let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(init, 60);
});
</script>
</body>
</html>