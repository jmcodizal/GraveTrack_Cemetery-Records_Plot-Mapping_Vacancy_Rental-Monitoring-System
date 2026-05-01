<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Log In</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= base_url('styles/login_style.css') ?>"/>
</head>
<body>

<nav>
  <div class="brand">GR<span>A</span>VE<span>T</span>RACK</div>
  <div class="nav-right">
    <button class="theme-toggle" id="themeToggle">
      <span class="knob"></span>
    </button>
  </div>
</nav>

<main>
  <div class="auth-card">
    <div class="panel-subtitle">Login to access your account</div>

    <div class="form-group">
      <label class="field-label">Name</label>
      <input class="field-input" type="text" id="login-name" placeholder="Enter your username"/>
    </div>

    <div class="form-group">
      <label class="field-label">Password</label>
      <div class="pass-wrap">
        <input class="field-input" type="password" id="login-password" placeholder="Enter your password"/>
        <button type="button" class="eye-btn" onclick="togglePass()">👁</button>
      </div>
    </div>

    <div class="forgot-row">
      <a href="#" class="forgot-link">Forgot Password?</a>
    </div>

    <button class="btn-primary-custom" onclick="handleLogin()">Log In</button>
  </div>
</main>

<div class="toast-wrap" id="toastWrap"></div>
<script src="<?= base_url('js/login.js') ?>"></script>
<script>
async function handleLogin() {
  const username = document.getElementById('login-name').value.trim();
  const password = document.getElementById('login-password').value;
  
  if (!username || !password) {
    toast('Please enter username and password', 'error');
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    
    const response = await fetch('<?= base_url('/login/auth') ?>', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      toast(result.message, 'success');
      setTimeout(() => {
        window.location.href = '<?= base_url('/dashboard') ?>';
      }, 1000);
    } else {
      toast(result.message, 'error');
    }
  } catch (error) {
    toast('Login failed. Please try again.', 'error');
  }
}

function togglePass() {
  const passInput = document.getElementById('login-password');
  passInput.type = passInput.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
