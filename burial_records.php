<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "gravetrack_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Debug: Check if view exists and get info
$viewCheck = $conn->query("SHOW CREATE TABLE burial_records_view");
$viewInfo = $viewCheck ? $viewCheck->fetch_assoc() : null;

// Search filter
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch burial records from view - simple query first to debug
$sql = "SELECT * FROM burial_records_view";
$result = $conn->query($sql);

// Debug info
$queryError = $conn->error;
$numRows = $result ? $result->num_rows : 0;

// Get first row to see column names
$firstRow = null;
$columns = [];
if ($result && $result->num_rows > 0) {
    $firstRow = $result->fetch_assoc();
    $columns = array_keys($firstRow);
    // Reset pointer for later display
    $result->data_seek(0);
}

// If search is applied
$searchApplied = false;
if (!empty($searchFilter) && $result && $result->num_rows > 0) {
    $searchFilter = $conn->real_escape_string($searchFilter);
    $searchSql = "SELECT * FROM burial_records_view 
            WHERE full_name LIKE '%$searchFilter%' 
            OR contact_person LIKE '%$searchFilter%' 
            OR plot_location LIKE '%$searchFilter%'";
    $searchResult = $conn->query($searchSql);
    if ($searchResult) {
        $result = $searchResult;
        $searchApplied = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Burial Records</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles/burial_records_style.css">
</head>
<body>

  <!-- Top navbar -->
  <div class="top-nav">
    <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
    <div class="nav-actions">
      <a href="adding_burial_records.php" class="nav-btn btn-save">Add New</a>
      <button class="btn-power" aria-label="Power off" onclick="confirmExit()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
        </svg>
      </button>
    </div>
  </div>

<!-- Content area -->
  <div class="content-area">

    <!-- Search -->
    <div class="search-box">
      <div class="search-label">Search Burial Record</div>
      <form method="GET">
        <input
          class="search-input"
          type="text"
          name="search"
          id="searchInput"
          placeholder="Search by name or ID..."
          value="<?php echo htmlspecialchars($searchFilter); ?>"
        />
        <button type="submit" style="display:none;">Search</button>
      </form>
    </div>

    <!-- Table -->
    <div class="table-box">
      <div class="table-header">
        <div class="table-title">Burial Record Management</div>
        <div class="table-subtitle">Burial Records List (<?php echo $result->num_rows; ?>)</div>
      </div>
      <div class="table-wrap">
        <table id="recordsTable">
          <thead>
            <tr>
              <th>Full Name</th>
              <th>Date of Death</th>
              <th>Date of Burial</th>
              <th>Gender</th>
              <th>Contact Person</th>
              <th>Contact Number</th>
              <th>Address</th>
              <th>Plot Location</th>
              </th>
              <th>Burial Type</th>
            </tr>
          </thead>
          <tbody id="recordsBody">
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
                  <td><?php echo isset($row['date_of_death']) ? date('M d, Y', strtotime($row['date_of_death'])) : '-'; ?></td>
                  <td><?php echo isset($row['date_of_burial']) ? date('M d, Y', strtotime($row['date_of_burial'])) : '-'; ?></td>
                  <td><?php echo htmlspecialchars($row['gender'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['contact_person'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['plot_location'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['burial_type'] ?? ''); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" style="text-align:center;">No burial records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <div class="empty-state" id="emptyState" style="display:<?php echo ($result && $result->num_rows === 0) ? 'flex' : 'none'; ?>;">No burial records found.</div>
      </div>
    </div>

  </div>

  <!-- Bottom navigation -->
  <div class="bottom-nav">
    <button class="bottom-btn bb-default" onclick="window.location.href='dashboard.html'; setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-active" onclick="window.location.href='burial_records.php'; setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='cemetery_map.html'; setActive(this)">Plot Mapping</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='vacancy.php'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='payment_monitoring.php'; setActive(this)">Payment<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='reports.html'; setActive(this)">Reports</button>
  </div>

  <div id="toastWrap"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function setActive(el) {
      document.querySelectorAll('.bottom-btn').forEach(b => {
        b.classList.remove('bb-active');
        b.classList.add('bb-default');
      });
      el.classList.remove('bb-default');
      el.classList.add('bb-active');
    }

    function confirmExit() {
      if (confirm('Are you sure you want to exit?')) window.close();
    }

    function toast(msg, type = 'info') {
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div');
      const colors = {
        error: '#ef4444',
        success: '#22c55e',
        info: '#60a5fa'
      };
      el.style.cssText = `
        background: #1a3a6e; color:#fff;
        padding:.55rem 1rem; border-radius:8px;
        font-size:.82rem; font-family:'DM Sans',sans-serif;
        box-shadow:0 4px 16px rgba(0,0,0,.25);
        border-left:3px solid ${colors[type] || colors.info};
        animation: slideIn .3s ease both;
      `;
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(() => el.remove(), 3000);
    }

    // ── CLEAR ──
    function handleClear() {
      window.location.href = 'burial_records.php';
      toast('Search cleared.', 'info');
    }

    // ── CANCEL ──
    function handleCancel() {
      window.location.href = 'dashboard.html';
    }
  </script>

</body>
</html>
