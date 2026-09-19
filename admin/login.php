<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../api/config/database.php';

if (!empty($_SESSION['local_admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT au.id,au.name,au.email,au.password_hash,ar.name AS role
             FROM local_admin_users au
             INNER JOIN local_admin_roles ar ON ar.id=au.role_id
             WHERE au.email=? AND au.status="active" AND au.deleted_at IS NULL AND ar.is_active=1
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            $_SESSION['local_admin_id'] = (int)$admin['id'];
            $_SESSION['local_admin_name'] = (string)$admin['name'];
            $_SESSION['local_admin_email'] = (string)$admin['email'];
            $_SESSION['local_admin_role'] = (string)$admin['role'];

            $pdo->prepare('UPDATE local_admin_users SET last_login_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')
                ->execute([(int)$admin['id']]);

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1f4d">
<title>MWH Local Admin Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,system-ui,sans-serif;background:linear-gradient(135deg,#f7f9ff 0%,#edf3ff 55%,#fff8ef 100%);display:grid;place-items:center;padding:22px;color:#172033}
.login-shell{width:min(440px,100%)}.brand{display:flex;justify-content:center;margin-bottom:22px}.brand-mark{width:74px;height:74px;border-radius:22px;background:linear-gradient(145deg,#0b1f4d,#1e5eff);color:#fff;display:grid;place-items:center;font-weight:900;letter-spacing:.08em;box-shadow:0 20px 45px rgba(11,31,77,.18)}.card{border:1px solid #e4eaf5;border-radius:24px;box-shadow:0 22px 70px rgba(20,38,80,.12);overflow:hidden}.card-body{padding:30px}.kicker{text-transform:uppercase;letter-spacing:.12em;font-size:10px;font-weight:800;color:#4169e1}.card h1{font-size:28px;font-weight:800;margin:7px 0}.sub{color:#7b879b;font-size:12px}.form-label{font-size:11px;font-weight:800;color:#526078}.form-control{height:46px;border-radius:12px;border-color:#dfe6f1;background:#fbfcfe}.btn-primary{height:46px;border-radius:12px;background:#1e5eff;border:0;font-weight:800}.alert{font-size:12px;border-radius:12px}.foot{text-align:center;color:#8994a7;font-size:10px;margin-top:14px}
</style>
</head>
<body>
<div class="login-shell">
  <div class="brand"><div class="brand-mark">MWH</div></div>
  <div class="card">
    <div class="card-body">
      <div class="kicker"><i class="bi bi-grid-1x2-fill"></i> MWH Local</div>
      <h1>Admin Panel</h1>
      <div class="sub mb-4">Contractors, staff, requirements and local operations.</div>
      <?php if ($error !== ''): ?><div class="alert alert-danger"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required autocomplete="username"></div>
        <div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i>&nbsp; Sign in</button>
      </form>
    </div>
  </div>
  <div class="foot">Maan World Local · Standalone administration</div>
</div>
</body>
</html>
