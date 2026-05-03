<?php
$conn = new mysqli("localhost", "root", "", "gravetrack_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FILTERS
$blockFilter = isset($_GET['block']) ? $_GET['block'] : 'all';
$typeFilter  = isset($_GET['type']) ? $_GET['type'] : 'all';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get distinct blocks
$blocksResult = $conn->query("SELECT DISTINCT block FROM plots ORDER BY block ASC");
$blocks = [];
if ($blocksResult) {
    while ($row = $blocksResult->fetch_assoc()) {
        $blocks[] = $row['block'];
    }
}

// Build SQL
$sql = "SELECT * FROM plots WHERE 1=1";
if ($blockFilter !== 'all') {
    $sql .= " AND block = '" . $conn->real_escape_string($blockFilter) . "'";
}
if ($typeFilter !== 'all') {
    $sql .= " AND type LIKE '%" . $conn->real_escape_string($typeFilter) . "%'";
}
if ($statusFilter !== 'all') {
    $sql .= " AND status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$result = $conn->query($sql);

// STATS
$vacant = $conn->query("SELECT COUNT(*) as total FROM plots WHERE status='Vacant'")->fetch_assoc()['total'];
$occupied = $conn->query("SELECT COUNT(*) as total FROM plots WHERE status='Occupied'")->fetch_assoc()['total'];
$total_plot = $conn->query("SELECT COUNT(*) as total FROM plots")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>GraveTrack – Vacancy Monitoring</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="styles/vacancy_style.css">

</head>
<body>

<!-- TOP NAV -->
<div class="top-nav">
  <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
  <div class="nav-actions">
    <button class="nav-btn btn-generate">Generate<br>Vacancy Report</button>
    <button class="nav-btn btn-export">Export to PDF</button>
    <a href="vacancy.php" class="nav-btn btn-refresh">Refresh</a>
  </div>
</div>

<!-- CONTENT -->
<div class="content-area">

  <!-- STATS -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-label">Total Vacant Plots</div>
      <div class="stat-value"><?= $vacant ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Occupied Plots</div>
      <div class="stat-value"><?= $occupied ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Plots</div>
      <div class="stat-value"><?= $total_plot ?></div>
    </div>
    
  </div>

  <!-- FILTERS (PHP FORM) -->
  <form method="GET">
    <div class="filter-row">
      <div class="filter-card">
        <div class="filter-label">Filter by Block</div>
        <select class="filter-select" name="block">
          <option value="all" <?= ($blockFilter === 'all') ? 'selected' : '' ?>>All Blocks</option>
          <?php foreach ($blocks as $block): ?>
          <option value="<?= htmlspecialchars($block) ?>" <?= ($blockFilter === $block) ? 'selected' : '' ?>>
            Block <?= htmlspecialchars($block) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-card">
        <div class="filter-label">Filter by Plot Type</div>
        <select class="filter-select" name="type">
          <option value="all" <?= ($typeFilter === 'all') ? 'selected' : '' ?>>All Types</option>
          <option value="Apartment" <?= ($typeFilter === 'Apartment') ? 'selected' : '' ?>>Apartment</option>
          <option value="Single" <?= ($typeFilter === 'Single') ? 'selected' : '' ?>>Single</option>
        </select>
      </div>

      <div class="filter-card">
        <div class="filter-label">Filter by Status</div>
        <select class="filter-select" name="status">
          <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>All Status</option>
          <option value="Vacant" <?= ($statusFilter === 'Vacant') ? 'selected' : '' ?>>Vacant</option>
          <option value="Occupied" <?= ($statusFilter === 'Occupied') ? 'selected' : '' ?>>Occupied</option>
        </select>
      </div>

      <div class="filter-card" style="display:flex; align-items:end;">
        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
      </div>
    </div>
  </form>

  <!-- TABLE -->
  <div class="table-card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Plot ID</th>
            <th>Block</th>
            <th>Section</th>
            <th>Lot</th>
            <th>Type</th>
            <th>Status</th>
            <th>Date Added</th>
          </tr>
        </thead>

        <tbody>
        <?php if ($result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['plot_id'] ?></td>
              <td><?= $row['block'] ?></td>
              <td><?= $row['section'] ?></td>
              <td><?= $row['lot'] ?></td>
              <td><?= $row['type'] ?></td>
              <td>
                <span class="badge-status 
                  <?= $row['status'] == 'Vacant' ? 'badge-vacant' : 
                      ($row['status'] == 'Occupied' ? 'badge-occupied' : 'badge-reserved') ?>">
                  <?= $row['status'] ?>
                </span>
              </td>
              <td><?= date('F d, Y', strtotime($row['date_added'])) ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align:center;">No plots found</td>
          </tr>
        <?php endif; ?>
        </tbody>

      </table>
    </div>
  </div>

</div>

<!-- BOTTOM NAV -->
<div class="bottom-nav">
    <button class="bottom-btn bb-default" onclick="window.location.href='dashboard.html'; setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='burial_records.php'; setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='cemetery_map.html'; setActive(this)">Plot Mapping</button>
    <button class="bottom-btn bb-active" onclick="window.location.href='vacancy.php'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='payment_monitoring.php'; setActive(this)">Payment<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='reports.html'; setActive(this)">Reports</button>
  </div>

</body>
</html>