const body = document.body;
const themeToggle = document.getElementById('themeToggle');

if (localStorage.getItem('theme') === 'dark') {
  body.classList.add('dark');
}

themeToggle.onclick = () => {
  body.classList.toggle('dark');
  localStorage.setItem(
    'theme',
    body.classList.contains('dark') ? 'dark' : 'light'
  );
};

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

function handleLogin() {
  const username = document.getElementById('login-name').value.trim();
  const password = document.getElementById('login-password').value;

  if (window.location.protocol === "file:") {
    toast("Cannot log in from file://. Run this project on localhost (Apache/PHP).");
    return;
  }

  if (!username || !password) {
    toast("Please fill in all fields.");
    return;
  }

  fetch("auth.php", {
    method: "POST",
    headers: {
        "Content-Type": "application/json"
    },
    body: JSON.stringify({
        username: username,
        password: password
    })
  })
  .then(async (res) => {
    const contentType = res.headers.get("content-type") || "";
    let data;
    try {
      data = await res.json();
    } catch (parseErr) {
      const text = await res.text();
      throw new Error(text || `Server returned ${res.status} with invalid JSON.`);
    }
    if (!res.ok) {
      throw new Error(data.message || `Login failed (${res.status})`);
    }
    return data;
  })
  .then(data => {
    if (data.success) {
      toast("Login successful!");
      setTimeout(() => {
        window.location.href = "dashboard.html";
      }, 800);
    } else {
      toast(data.message || "Invalid credentials.");
    }
  })
  .catch(err => {
    console.error(err);
    if (err instanceof TypeError && /fetch/i.test(err.message)) {
      toast("Failed to reach auth.php. Check Apache/PHP server and URL path.");
      return;
    }
    toast(err.message || "Server error.");
  });
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Enter") {
    handleLogin();
  }
});

