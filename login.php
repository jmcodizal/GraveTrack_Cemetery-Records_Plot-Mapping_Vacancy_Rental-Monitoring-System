<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/Database/db_connector.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db = new db_connector();
            $conn = $db->connect();

            $stmt = $conn->prepare("SELECT * FROM users WHERE name = :name LIMIT 1");
            $stmt->bindParam(':name', $username);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user && (password_verify($password, $user['password']) || hash_equals($user['password'], $password))) {
                $_SESSION['user'] = $user['name'];
                header('Location: dashboard.html');
                exit;
            } else {
                $error = 'Invalid credentials.';
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Server error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GraveTrack – Log In</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles/login_style.css"/>
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
    <img src="images/images.jpg" class="mx-auto d-block">
    <div class="panel-subtitle">Login to access your account</div>

    <script>window.addEventListener('load', () => { <?php if (isset($error)): ?>toast('<?php echo addslashes($error); ?>');<?php endif; ?> });</script>

    <form method="POST">
      <div class="form-group">
        <label class="field-label">Username</label>
        <input class="field-input" type="text" name="username" id="login-name" placeholder="Enter your username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" />
      </div>

      <div class="form-group">
        <label class="field-label">Password</label>
        <div class="pass-wrap">
          <input class="field-input" type="password" name="password" id="login-password" placeholder="Enter your password" />
          <button type="button" class="eye-btn" onclick="togglePass()">👁</button>
        </div>
      </div>

      <div class="forgot-row">
        <a href="reset_password.html" class="forgot-link">Forgot Password?</a>
      </div>

      <button type="submit" class="btn-primary-custom">Log In</button>
    </form>

    <script>
      document.querySelector('form').addEventListener('submit', function(e) {
        const username = document.getElementById('login-name').value.trim();
        const password = document.getElementById('login-password').value;
        if (!username || !password) {
          e.preventDefault();
          toast('Please fill in all fields.');
          return false;
        }
      });
    </script>
  </div>
</main>

<div class="toast-wrap" id="toastWrap"></div>
<script>
// Theme toggle
const body = document.body;
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('theme') === 'dark') body.classList.add('dark');
themeToggle.onclick = () => {
  body.classList.toggle('dark');
  localStorage.setItem('theme', body.classList.contains('dark') ? 'dark' : 'light');
};

// Password toggle
function togglePass() {
  const input = document.getElementById('login-password');
  input.type = input.type === 'password' ? 'text' : 'password';
}

function toast(msg) {
  const wrap = document.getElementById('toastWrap');
  const el = document.createElement('div');
  el.className = 'toast-item';
  el.textContent = msg;
  wrap.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}
</script>

</body>
</html>
