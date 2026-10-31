<?php
// ============================================
// Smart Attendance - Login Panel Administrator TU
// ============================================
require_once '../api/koneksi.php';
startAppSession();
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

// Jika sudah login sebagai admin, langsung redirect ke dashboard
if (getAdminSession() !== null) {
    header('Location: index.php');
    exit();
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');

    if (empty($username) || empty($password)) {
        $errorMsg = 'Username/NIP dan kata sandi wajib diisi.';
    } else {
        $conn = getConnection();
        $passMd5 = md5($password);

        $stmt = $conn->prepare(
            "SELECT id, nip, nomor_induk, nama, role, jabatan, no_hp 
             FROM users 
             WHERE (nip = ? OR nomor_induk = ?) AND password = ?"
        );
        $stmt->bind_param('sss', $username, $username, $passMd5);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if ($user['role'] !== 'admin') {
                $errorMsg = 'Akses ditolak: Akun ini bukan Administrator TU.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_user'] = [
                    'id'           => (int)$user['id'],
                    'nip'          => !empty($user['nip']) ? $user['nip'] : (isset($user['nomor_induk']) ? $user['nomor_induk'] : 'admin'),
                    'nama'         => $user['nama'],
                    'role'         => 'admin',
                    'jabatan'      => !empty($user['jabatan']) ? $user['jabatan'] : 'Administrator TU',
                    'no_hp'        => isset($user['no_hp']) ? $user['no_hp'] : '',
                    'logged_in_at' => time()
                ];
                $conn->close();
                header('Location: index.php');
                exit();
            }
        } else {
            $errorMsg = 'Username atau kata sandi salah.';
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Administrator TU – Global Nusantara</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    :root {
      --bg: #060d17;
      --navy: #0a192f;
      --navy-light: #0f2444;
      --card: #0e1f38;
      --border: rgba(197, 155, 39, 0.22);
      --border-subtle: rgba(255, 255, 255, 0.08);
      --accent: #d4af37;
      --accent2: #c59b27;
      --blue: #2563eb;
      --red: #f43f5e;
      --muted: rgba(255, 255, 255, 0.6);
      --text: #f8fafc;
      --radius: 14px;
    }

    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background-image: 
        radial-gradient(circle at 15% 20%, rgba(197, 155, 39, 0.07) 0%, transparent 40%),
        radial-gradient(circle at 85% 80%, rgba(37, 99, 235, 0.07) 0%, transparent 40%);
    }

    .login-container {
      width: 100%;
      max-width: 420px;
    }

    .brand-header {
      text-align: center;
      margin-bottom: 26px;
    }
    .brand-icon {
      width: 52px;
      height: 52px;
      margin: 0 auto 14px;
      background: linear-gradient(135deg, var(--navy), var(--navy-light));
      border: 1.5px solid var(--accent);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    }
    .brand-icon svg { width: 24px; height: 24px; }
    .brand-header h1 {
      font-family: 'Cinzel', serif;
      font-size: 1.25rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: 0.8px;
    }
    .brand-header p {
      font-size: 0.72rem;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      font-weight: 700;
      margin-top: 4px;
    }

    .login-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 30px 24px;
      box-shadow: 0 16px 40px rgba(0,0,0,0.5);
    }
    .login-card-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .login-card-title svg { color: var(--accent); }

    .alert-error {
      background: rgba(244, 63, 94, 0.12);
      border: 1px solid rgba(244, 63, 94, 0.35);
      color: #fda4af;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 0.82rem;
      font-weight: 600;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .form-group {
      margin-bottom: 16px;
    }
    .form-group label {
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 6px;
    }
    .form-input-wrap {
      position: relative;
    }
    .form-group input {
      width: 100%;
      padding: 12px 14px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--border-subtle);
      border-radius: 10px;
      color: var(--text);
      font-size: 0.88rem;
      font-family: inherit;
      outline: none;
      transition: all 0.2s;
    }
    .form-group input:focus {
      border-color: var(--accent);
      background: rgba(255, 255, 255, 0.08);
      box-shadow: 0 0 0 3px rgba(197, 155, 39, 0.15);
    }
    .btn-eye {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 4px;
    }
    .btn-eye:hover { color: #fff; }

    .btn-submit {
      width: 100%;
      padding: 13px;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--accent2), var(--accent));
      color: #060d17;
      font-size: 0.88rem;
      font-weight: 800;
      font-family: inherit;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(197, 155, 39, 0.3);
      transition: all 0.2s;
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-submit:hover {
      box-shadow: 0 6px 20px rgba(197, 155, 39, 0.45);
      transform: translateY(-1px);
    }

    .login-footer {
      text-align: center;
      margin-top: 22px;
    }
    .login-footer a {
      color: var(--muted);
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: color 0.2s;
    }
    .login-footer a:hover {
      color: var(--accent);
    }

    .demo-hint {
      margin-top: 14px;
      padding: 10px;
      background: rgba(255,255,255,0.03);
      border: 1px dashed var(--border-subtle);
      border-radius: 8px;
      font-size: 0.72rem;
      color: var(--muted);
      text-align: center;
    }
  </style>
</head>
<body>

  <div class="login-container">
    <div class="brand-header">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
          <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
        </svg>
      </div>
      <h1>Global Nusantara</h1>
      <p>Panel Manajemen Administrator TU</p>
    </div>

    <div class="login-card">
      <div class="login-card-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <span>Masuk Administrator</span>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert-error">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?php echo htmlspecialchars($errorMsg); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label for="username">Username / NIP Admin</label>
          <div class="form-input-wrap">
            <input type="text" id="username" name="username" placeholder="Masukkan username admin" value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : ''); ?>" required autofocus autocomplete="username" />
          </div>
        </div>

        <div class="form-group">
          <label for="password">Kata Sandi</label>
          <div class="form-input-wrap">
            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password" />
            <button type="button" class="btn-eye" onclick="togglePass()" aria-label="Toggle kata sandi">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <span>Masuk ke Dashboard TU</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </button>
      </form>

      <div class="demo-hint">
        Kredensial Default: <b>admin</b> / <b>admin123</b>
      </div>
    </div>

    <div class="login-footer">
      <a href="../index.html">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
        </svg>
        <span>Kembali ke Portal Utama</span>
      </a>
    </div>
  </div>

  <script>
    function togglePass() {
      const p = document.getElementById('password');
      p.type = p.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
