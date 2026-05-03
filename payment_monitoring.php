<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Payment Monitoring</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles/payment_monitoring.css"/>
</head>
<body>

  <!-- Top navbar -->
  <div class="top-nav">
    <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
    <div class="nav-actions">
      <button class="nav-btn btn-financial" onclick="generateFinancial()">Generate<br>Financial<br>Report</button>
      <button class="nav-btn btn-receipt"   onclick="printReceipt()">Print Receipt</button>
      <button class="btn-power" aria-label="Power off" onclick="confirmExit()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Content area -->
  <div class="content-area">

    <div class="section-title">Payment Monitoring Dashboard</div>

    <!-- Filters (Vacancy style) -->
    <form class="filters-form">
      <div class="filter-row">
        <div class="filter-card">
          <div class="filter-label">Status</div>
          <select id="statusFilter" class="filter-select" onchange="applyFilters()">
            <option value="all">All Status</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Unpaid</option>
          </select>
        </div>
        <div class="filter-card">
          <div class="filter-label">Deceased Name</div>
          <input id="nameSearch" class="filter-input" placeholder="Search name..." oninput="applyFilters()">
        </div>
      </div>
    </form>

    <!-- Table (Vacancy Style) -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Deceased Name</th>
              <th>Plot Location</th>
              <th>Contact Person</th>
              <th>Contact Number</th>
              <th>Date of Transaction</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="tableBody"></tbody>
        </table>
        <div class="empty-state" id="emptyState">No records found.</div>
      </div>
    </div>

  </div>

  <!-- Bottom navigation -->
  <div class="bottom-nav">
    <button class="bottom-btn bb-default" onclick="window.location.href='dashboard.html';setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='burial_records.php'; setActive(this);">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='cemetery_map.html'; setActive(this)">Plot Mapping</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='vacancy.php'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='payment_monitoring.php'; setActive(this)">Payment<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='reports.html'; setActive(this)">Reports</button>
  </div>

  <div id="toastWrap"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let records = [];

    async function loadRecords() {
      try {
        const response = await fetch('api/get_payment_summary.php');
        if (!response.ok) throw new Error('API error: ' + response.status);
        records = await response.json();
        renderTable(records);
      } catch (error) {
        toast('Failed to load payments: ' + error.message, '#ef4444');
      }
    }

    const statusClass = {
      'Paid':     'badge-paid',
      'Pending':  'badge-pending',
      'Overdue':  'badge-overdue',
      'Upcoming': 'badge-upcoming',
    };

    function renderTable(data) {
      const body = document.getElementById('tableBody');
      const empty = document.getElementById('emptyState');
      body.innerHTML = '';
      if (!data.length) { empty.style.display = 'flex'; return; }
      empty.style.display = 'none';
      data.forEach((r, i) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.name}</td>
          <td>${r.plot}</td>
          <td>${r.contact_person || ''}</td>
          <td>${r.contact_num || ''}</td>
          <td>${r.period}</td>
          <td>₱${parseFloat(r.amount || 0).toLocaleString()}</td>
          <td><span class="badge-status ${statusClass[r.status] || 'badge-pending'}">${r.status || 'Pending'}</span></td>
          <td>
            <div class="action-btns">
              <button class="action-btn btn-view-rec" onclick="viewRecord(${i})">View</button>
            </div>
          </td>
        `;
        body.appendChild(tr);
      });
    }

    let currentFilters = { status: 'all', search: '' };

    function applyFilters() {
      const statusFilter = document.getElementById('statusFilter').value;
      const searchTerm = document.getElementById('nameSearch').value.toLowerCase();
      
      currentFilters.status = statusFilter;
      currentFilters.search = searchTerm;
      
      let filtered = records.filter(r => {
        // Status filter
        if (statusFilter === 'paid') {
          return r.status === 'Paid';
        } else if (statusFilter === 'unpaid') {
          return r.status === 'Pending' || r.status === 'Overdue';
        }
        
        // Name search
        return r.name.toLowerCase().includes(searchTerm);
      });
      
      renderTable(filtered);
    }

function viewRecord(i) {
  const r = records[i];
  // Show modal with transactions
  let modalHtml = `
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5>Transactions for ${r.name}</h5>
            <button type="button" class="btn-close" onclick="closeViewModal()"></button>
          </div>
          <div class="modal-body">
            <div id="transactions-loading">Loading transactions...</div>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  fetchTransactions(r.name);
}
    function sendOne(name)       { toast(`Reminder sent to contact of ${name}`, '#22c55e'); }
    function generateFinancial() { toast('Generating Financial Report…', '#2563eb'); }
    function printReceipt()      { window.print(); }
    function confirmExit()       { if (confirm('Are you sure you want to exit?')) window.close(); }

    function setActive(el) {
      document.querySelectorAll('.bottom-btn').forEach(b => {
        b.classList.remove('bb-active');
        b.classList.add('bb-default');
      });
      el.classList.remove('bb-default');
      el.classList.add('bb-active');
    }

    function toast(msg, color = '#2563eb') {
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div');
      el.className = 'toast-item';
      el.style.borderLeft = `3px solid ${color}`;
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(() => el.remove(), 3000);
    }

    loadRecords();

  async function fetchTransactions(name) {
    try {
      const response = await fetch('api/get_deceased_transactions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name: name})
      });
      const transactions = await response.json();
      const loading = document.getElementById('transactions-loading');
      if (transactions.error) {
        loading.innerHTML = '<p class="text-danger">Error: ' + transactions.error + '</p>';
      } else {
        let html = transactions.length ? 
          transactions.map(t => `
            <div class="transaction-item mb-3 p-3 border rounded">

              <strong>Payment ID:</strong> ${t.payment_id}<br>

              <strong>Amount:</strong> ₱${parseFloat(t.amount || 0).toLocaleString()}<br>

              <strong>Date Paid:</strong> ${t.payment_date || 'N/A'}<br>

              <strong>Status:</strong> ${t.status}<br>

              <strong>Timeliness:</strong> 
              <span class="badge ${t.timeliness === 'Late' ? 'bg-danger' : 'bg-success'}">
                ${t.timeliness}
              </span>

            </div>
          `).join('') : 
          '<p>No transactions found.</p>';
        loading.innerHTML = html;
      }
    } catch (error) {
      document.getElementById('transactions-loading').innerHTML = '<p class="text-danger">Failed to load: ' + error.message + '</p>';
    }
  }

  function closeViewModal() {
    document.querySelector('.modal').remove();
  }
  </script>

</body>
</html>