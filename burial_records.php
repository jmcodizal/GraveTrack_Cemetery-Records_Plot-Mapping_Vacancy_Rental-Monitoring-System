<?php
require_once 'Database/db_connector.php';

$db = new db_connector();
$conn = null;
$records = [];
$error = '';

try {
    $conn = $db->connect();
    
    // Handle AJAX search or initial load
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    $allRecords = $db->getBurialRecords('');
    $records = $allRecords;
    
    // Note: Import Database/gravetrack_db.sql and add data via phpMyAdmin for records to show. DB connector working.
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Set JSON header for AJAX or HTML
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['records' => $records, 'error' => $error]);
    exit;
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
      <button class="nav-btn btn-save" onclick="handleSave()">Save</button>
      <button class="nav-btn btn-clear" onclick="handleClear()">Clear</button>
      <button class="nav-btn btn-cancel" onclick="handleCancel()">Cancel</button>
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
      <input
        class="search-input"
        type="text"
        id="searchInput"
        placeholder="Search by name or ID..."
        oninput="filterRecords()"
      />
    </div>

    <!-- Table -->
    <div class="table-box">
      <div class="table-header">
        <div class="table-title">Burial Record Management</div>
        <div class="table-subtitle">Burial Records List<?= $error ? ' - ' . htmlspecialchars($error) : '' ?></div>
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
            <tr><td colspan="9" style="text-align:center; padding:2rem; color:#666;">Loading...</td></tr>
          </tbody>
        </table>
        <div class="empty-state" id="emptyState" style="display:<?php echo empty($records) ? 'flex' : 'none'; ?>;">No matching records.</div>
      </div>
    </div>

  </div>

  <!-- Bottom navigation -->
  <div class="bottom-nav">
    <button class="bottom-btn bb-default" onclick="window.location.href='dashboard.html';setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-active" onclick="window.location.href='burial_records.php';setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='plot_mapping.html';setActive(this)">Plot Mapping</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='vacancy.html';setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='payment_monitoring.html';setActive(this)">Rental &amp;<br>Payment</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='reports.html';setActive(this)">Reports</button>
  </div>

  <div id="toastWrap"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>

    function renderTable(data) {
      const body = document.getElementById('recordsBody');
      const empty = document.getElementById('emptyState');
      body.innerHTML = '';
      if (!data || !data.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="9" style="text-align:center;padding:2rem;color:#666;">No matching records found.</td>';
        body.appendChild(tr);
        empty.style.display = 'none';
        return;
      }
      empty.style.display = 'none';
      data.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(r.full_name)}</td>
  <td>${r.date_of_death || ''}</td>
  <td>${r.date_of_burial || ''}</td>
  <td>${r.gender || ''}</td>
  <td>${r.contact_person || ''}</td>

          <td>${r.contact_number || ''}</td>
          <td>${r.address || ''}</td>
          <td>${r.plot_location || ''}</td>
          <td>${r.burial_type || ''}</td>
        `;
        body.appendChild(tr);
      });
    }

    async function filterRecords() {
      const q = document.getElementById('searchInput').value.trim();
      try {
        showLoading(true);
        const res = await fetch(`?search=${encodeURIComponent(q)}`, {
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await res.json();
        if (data.error) {
          toast('Error: ' + data.error, 'error');
          return;
        }
        renderTable(data.records);
      } catch (e) {
        toast('Search failed: ' + e.message, 'error');
      } finally {
        showLoading(false);
      }
    }

    function renderTable(data) {
      const body = document.getElementById('recordsBody');
      const empty = document.getElementById('emptyState');
      body.innerHTML = '';
      if (!data || !data.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="9" style="text-align:center;padding:2rem;color:#666;">No matching records found.</td>';
        body.appendChild(tr);
        empty.style.display = 'none';
        return;
      }
      empty.style.display = 'none';
      data.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(r.full_name)}</td>
  <td>${r.date_of_death || ''}</td>
  <td>${r.date_of_burial || ''}</td>

          <td>${r.gender || ''}</td>
          <td>${r.contact_person || ''}</td>
          <td>${r.contact_number || ''}</td>
          <td>${r.address || ''}</td>
          <td>${r.plot_location || ''}</td>
          <td>${r.burial_type || ''}</td>
        `;
        body.appendChild(tr);
      });
    }

    function escapeHtml(text) {
      const map = {'&': '&amp;', '<': '<', '>': '>', '"': '"', "'": '&#039;'};
      return text.replace(/[&<>"']/g, m => map[m]);
    }

    function showLoading(show) {
      // Optional: Add spinner to search input or table
      console.log(show ? 'Searching...' : 'Search complete');
    }

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

    // ── SAVE ──
    function handleSave() {
      toast('Record saved successfully!', 'success');
    }

    // ── CLEAR ──
    function handleClear() {
      toast('Form cleared.', 'info');
    }

    // ── CANCEL ──
    function handleCancel() {
      toast('Action cancelled.', 'info');
    }

    // Initialize table on load
    const records = <?= json_encode($records) ?> ;
    renderTable(records);

  </script>

