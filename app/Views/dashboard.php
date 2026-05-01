<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= base_url('styles/dashboard_style.css') ?>"/>
</head>
<body>

  <!-- Top navbar -->
  <div class="top-nav">
    <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
    <div class="nav-actions">
      <div class="user-info">Welcome, <?= session()->get('username') ?></div>
      <a href="<?= base_url('/login/logout') ?>" class="nav-btn btn-logout">Logout</a>
    </div>
  </div>

  <!-- Main content -->
  <div class="content-area">
    <div class="section-title">Dashboard</div>
    <div class="section-subtitle">Welcome to GraveTrack Cemetery Management System</div>

    <div class="dashboard-grid">
      <div class="dash-card" onclick="window.location.href='<?= base_url('/burial-records') ?>'">
        <div class="dash-icon">📚</div>
        <div class="dash-label">Burial Records</div>
      </div>

      <div class="dash-card" onclick="window.location.href='<?= base_url('/vacancy') ?>'">
        <div class="dash-icon">🗺️</div>
        <div class="dash-label">Vacancy Monitoring</div>
      </div>

      <div class="dash-card" onclick="window.location.href='<?= base_url('/add-burial') ?>'">
        <div class="dash-icon">➕</div>
        <div class="dash-label">Add New Burial</div>
      </div>
    </div>
  </div>

  <!-- Bottom navigation -->
  <div class="bottom-nav">
    <button class="bottom-btn bb-active" onclick="window.location.href='<?= base_url('/dashboard') ?>'; setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/burial-records') ?>'; setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/vacancy') ?>'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/payment') ?>'; setActive(this)">Rental &amp;<br>Payment</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/reports') ?>'; setActive(this)">Reports</button>
  </div>

  <script>
    function setActive(el) {
      document.querySelectorAll('.bottom-btn').forEach(b => {
        b.classList.remove('bb-active');
        b.classList.add('bb-default');
      });
      el.classList.remove('bb-default');
      el.classList.add('bb-active');
    }
  </script>

</body>
</html>
