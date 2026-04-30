<?php
$conn = new mysqli("localhost", "root", "", "gravetrack_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FILTERS
$sectionFilter = isset($_GET['section']) ? $_GET['section'] : '';
$typeFilter    = isset($_GET['type']) ? $_GET['type'] : '';

// SQL with filters
$sql = "SELECT * FROM plots WHERE 1";

if (!empty($sectionFilter)) {
    $sql .= " AND section LIKE '%$sectionFilter%'";
}
if (!empty($typeFilter)) {
    $sql .= " AND type LIKE '%$typeFilter%'";
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
        <div class="filter-label">Filter by Section</div>
        <input class="filter-input" type="text" name="section"
          value="<?= htmlspecialchars($sectionFilter) ?>"
          placeholder="e.g. Section 1"/>
      </div>

      <div class="filter-card">
        <div class="filter-label">Filter by Plot Type</div>
        <input class="filter-input" type="text" name="type"
          value="<?= htmlspecialchars($typeFilter) ?>"
          placeholder="e.g. Ground, Apartment"/>
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
    <button class="bottom-btn bb-default" onclick="window.location.href='burial_records.html'; setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='cemetery_map.html'; setActive(this)">Plot Mapping</button>
    <button class="bottom-btn bb-active" onclick="window.location.href='vacancy.php'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='payment_monitoring.html'; setActive(this)">Rental &amp;<br>Payment</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='reports.html'; setActive(this)">Reports</button>
  </div>

</body>
</html>