<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Burial Records</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= base_url('styles/burial_records_style.css') ?>">
</head>
<body>

  <!-- Top navbar -->
  <div class="top-nav">
    <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
    <div class="nav-actions">
      <a href="<?= base_url('/add-burial') ?>" class="nav-btn btn-save">Add New</a>
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
      <form method="GET" action="<?= base_url('/burial-records') ?>">
        <input
          class="search-input"
          type="text"
          name="search"
          id="searchInput"
          placeholder="Search by name or ID..."
          value="<?= htmlspecialchars($searchFilter) ?>"
        />
        <button type="submit" style="display:none;">Search</button>
      </form>
    </div>

    <!-- Table -->
    <div class="table-box">
      <div class="table-header">
        <div class="table-title">Burial Record Management</div>
        <div class="table-subtitle">Burial Records List (<?= count($records) ?>)</div>
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
              <th>Burial Type</th>
            </tr>
          </thead>
          <tbody id="recordsBody">
            <?php if (count($records) > 0): ?>
              <?php foreach($records as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
                  <td><?= isset($row['date_of_death']) ? date('M d, Y', strtotime($row['date_of_death'])) : '-' ?></td>
                  <td><?= isset($row['date_of_burial']) ? date('M d, Y', strtotime($row['date_of_burial'])) : '-' ?></td>
                  <td><?= htmlspecialchars($row['gender'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['contact_person'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['contact_number'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['address'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['plot_location'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['burial_type'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" style="text-align:center;">No burial records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <div class="empty-state" id="emptyState" style="display:<?= (count($records) === 0) ? 'flex' : 'none' ?>;">No burial records found.</div>
      </div>
    </div>

  </div>

  <!-- Bottom navigation -->
  <div class="bottom-nav">
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/dashboard') ?>'; setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-active" onclick="window.location.href='<?= base_url('/burial-records') ?>'; setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/vacancy') ?>'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/payment') ?>'; setActive(this)">Rental &amp;<br>Payment</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/reports') ?>'; setActive(this)">Reports</button>
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
      window.location.href = '<?= base_url('/burial-records') ?>';
      toast('Search cleared.', 'info');
    }

    // ── CANCEL ──
    function handleCancel() {
      window.location.href = '<?= base_url('/dashboard') ?>';
    }
  </script>

</body>
</html>
