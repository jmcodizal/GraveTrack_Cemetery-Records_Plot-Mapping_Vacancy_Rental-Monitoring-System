<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Add New Records</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= base_url('styles/abr_style.css') ?>">
</head>
<body>

  <!-- Top navbar -->
  <div class="top-nav">
    <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
    <div class="nav-actions">
      <button class="nav-btn btn-save"   onclick="handleSave()">Save</button>
      <button class="nav-btn btn-clear"  onclick="handleClear()">Clear</button>
      <button class="nav-btn btn-cancel" onclick="handleCancel()">Cancel</button>
      <button class="btn-power" aria-label="Power off">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Content area -->
  <div class="content-area">
    <div class="section-title">Burial Record Management</div>
    <div class="section-subtitle">Add New Burial Record</div>

    <div class="form-grid">

      <div class="field-group">
        <label class="field-label" for="fullName">Full Name <span style="color:red;">*</span></label>
        <input class="field-input" type="text" id="fullName" placeholder="Enter full name"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="dateOfBirth">Date of Birth</label>
        <input class="field-input" type="date" id="dateOfBirth"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="dateOfDeath">Date of Death <span style="color:red;">*</span></label>
        <input class="field-input" type="date" id="dateOfDeath"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="dateOfBurial">Date of Burial <span style="color:red;">*</span></label>
        <input class="field-input" type="date" id="dateOfBurial"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="gender">Gender</label>
        <select class="field-input" id="gender">
          <option value="">Select gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </select>
      </div>

      <div class="field-group">
        <label class="field-label" for="contactPerson">Contact Person</label>
        <input class="field-input" type="text" id="contactPerson" placeholder="Enter contact person"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="contactNumber">Contact Number</label>
        <input class="field-input" type="tel" id="contactNumber" placeholder="Enter contact number"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="address">Address</label>
        <input class="field-input" type="text" id="address" placeholder="Enter address"/>
      </div>

      <div class="field-group">
        <label class="field-label" for="plotId">Select Plot <span style="color:red;">*</span></label>
        <select class="field-input" id="plotId" onchange="updatePlotInfo()">
          <option value="">-- Select Vacant Plot --</option>
        </select>
        <div id="plotInfo" style="margin-top:5px;font-size:0.75rem;color:#666;"></div>
      </div>

      <div class="field-group">
        <label class="field-label" for="burialType">Burial Type</label>
        <select class="field-input" id="burialType">
          <option value="">Select type</option>
          <option value="Ground">Ground Burial</option>
          <option value="Apartment">Apartment/BCrypt</option>
          <option value="Mausoleum">Mausoleum</option>
        </select>
      </div>

    </div>
  </div>

  <!-- Bottom navigation -->
  <div class="bottom-nav">
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/dashboard') ?>';setActive(this)">Dashboard</button>
    <button class="bottom-btn bb-active"  onclick="window.location.href='<?= base_url('/burial-records') ?>'; setActive(this)">Burial Records</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/vacancy') ?>'; setActive(this)">Vacancy<br>Monitoring</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/payment') ?>'; setActive(this)">Rental &amp;<br>Payment</button>
    <button class="bottom-btn bb-default" onclick="window.location.href='<?= base_url('/reports') ?>'; setActive(this)">Reports</button>
  </div>

  <!-- Toast -->
  <div id="toastWrap" style="position:fixed;bottom:5rem;right:1rem;z-index:999;display:flex;flex-direction:column;gap:.4rem;"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let vacantPlots = [];
    
    // Load vacant plots on page load
    async function loadVacantPlots() {
      try {
        const response = await fetch('<?= base_url('/add-burial/getVacantPlots') ?>');
        const result = await response.json();
        
        if (result.success && result.plots.length > 0) {
          vacantPlots = result.plots;
          const select = document.getElementById('plotId');
          
          result.plots.forEach(plot => {
            const option = document.createElement('option');
            option.value = plot.plot_id;
            option.textContent = plot.label;
            select.appendChild(option);
          });
        } else {
          document.getElementById('plotInfo').textContent = 'No vacant plots available';
        }
      } catch (error) {
        console.error('Error loading plots:', error);
        document.getElementById('plotInfo').textContent = 'Error loading plots';
      }
    }
    
    function updatePlotInfo() {
      const plotId = document.getElementById('plotId').value;
      const infoDiv = document.getElementById('plotInfo');
      
      if (plotId) {
        const plot = vacantPlots.find(p => p.plot_id == plotId);
        if (plot) {
          infoDiv.textContent = plot.plot_type || '';
        }
      } else {
        infoDiv.textContent = '';
      }
    }
    
    // Load plots when page loads
    window.addEventListener('DOMContentLoaded', loadVacantPlots);
    
    // ── BOTTOM NAV ACTIVE ──
    function setActive(el) {
      document.querySelectorAll('.bottom-btn').forEach(b => {
        b.classList.remove('bb-active');
        b.classList.add('bb-default');
      });
      el.classList.remove('bb-default');
      el.classList.add('bb-active');
    }

    // ── TOAST ──
    function toast(msg, type = 'info') {
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div');
      el.style.cssText = `
        background: #1a3a6e; color:#fff;
        padding:.55rem 1rem; border-radius:8px;
        font-size:.82rem; font-family:'DM Sans',sans-serif;
        box-shadow:0 4px 16px rgba(0,0,0,.25);
        border-left:3px solid ${type==='error'?'#ef4444':type==='success'?'#22c55e':'#60a5fa'};
        animation: fadeInRight .3s ease both;
      `;
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(() => el.remove(), 3000);
    }

// ── SAVE ──
    async function handleSave() {
      const fullName = document.getElementById('fullName').value.trim();
      const dateOfDeath = document.getElementById('dateOfDeath').value.trim();
      const dateOfBurial = document.getElementById('dateOfBurial').value.trim();
      const plotId = document.getElementById('plotId').value.trim();
      
      // Validate required fields
      if (!fullName) {
        document.getElementById('fullName').style.borderColor = '#ef4444';
        toast('Please fill in Full Name.', 'error');
        return;
      }
      if (!dateOfDeath) {
        document.getElementById('dateOfDeath').style.borderColor = '#ef4444';
        toast('Please fill in Date of Death.', 'error');
        return;
      }
      if (!dateOfBurial) {
        document.getElementById('dateOfBurial').style.borderColor = '#ef4444';
        toast('Please fill in Date of Burial.', 'error');
        return;
      }
      if (!plotId) {
        document.getElementById('plotId').style.borderColor = '#ef4444';
        toast('Please select a vacant plot.', 'error');
        return;
      }
      
      // Collect form data
      const formData = new FormData();
      formData.append('fullName', document.getElementById('fullName').value);
      formData.append('dateOfBirth', document.getElementById('dateOfBirth').value);
      formData.append('dateOfDeath', document.getElementById('dateOfDeath').value);
      formData.append('dateOfBurial', document.getElementById('dateOfBurial').value);
      formData.append('gender', document.getElementById('gender').value);
      formData.append('contactPerson', document.getElementById('contactPerson').value);
      formData.append('contactNumber', document.getElementById('contactNumber').value);
      formData.append('address', document.getElementById('address').value);
      formData.append('plotId', document.getElementById('plotId').value);
      formData.append('burialType', document.getElementById('burialType').value);
      
      try {
        const response = await fetch('<?= base_url('/add-burial/save') ?>', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        
        if (result.success) {
          toast(result.message, 'success');
          // Clear form after successful save
          handleClear();
          // Redirect to burial records page after short delay
          setTimeout(() => {
            window.location.href = '<?= base_url('/burial-records') ?>';
          }, 1500);
        } else {
          toast(result.message, 'error');
        }
      } catch (error) {
        toast('Failed to save record. Please try again.', 'error');
      }
    }

    // ── CLEAR ──
    function handleClear() {
      document.querySelectorAll('.field-input').forEach(f => {
        f.value = '';
        f.style.borderColor = '#cbd5e1';
      });
      document.getElementById('plotInfo').textContent = '';
      toast('Form cleared.', 'info');
    }

    // ── CANCEL ──
    function handleCancel() {
      handleClear();
      history.back();
      toast('Action cancelled.', 'info');
    }

    // ── POWER ──
    document.querySelector('.btn-power').addEventListener('click', () => {
      if (confirm('Are you sure you want to exit?')) window.close();
    });
  </script>
</body>
</html>
