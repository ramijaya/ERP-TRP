<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/'));
    exit;
}

// Load BASE_URL from config if available
if (file_exists(__DIR__ . '/config/app.php')) {
    // Only need the constant, not the session/auth stuff
    $configContent = file_get_contents(__DIR__ . '/config/app.php');
    if (preg_match("/define\('BASE_URL',\s*'([^']+)'\)/", $configContent, $m)) {
        if (!defined('BASE_URL')) define('BASE_URL', $m[1]);
    }
}
if (!defined('BASE_URL')) define('BASE_URL', '/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            header('Location: ' . BASE_URL);
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP-TRP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .login-card {
            background: #fff; border-radius: 16px; padding: 40px;
            width: 100%; max-width: 420px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .login-brand { text-align: center; margin-bottom: 30px; }
        .login-brand h2 { font-weight: 700; color: #1e293b; }
        .login-brand h2 span { color: #3b82f6; }
        .login-brand p { color: #94a3b8; font-size: 0.9rem; }
        .form-control { border-radius: 10px; padding: 12px 16px; border-color: #e2e8f0; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .btn-login {
            background: #3b82f6; border: none; border-radius: 10px;
            padding: 12px; font-weight: 600; width: 100%;
            font-size: 1rem; color: #fff;
        }
        .btn-login:hover { background: #2563eb; color: #fff; }
        .input-group-text { background: #f8fafc; border-color: #e2e8f0; border-radius: 10px 0 0 10px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <h2><span>ERP</span>-TRP</h2>
            <p>Enterprise Resource Planning System</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2" style="border-radius:10px;font-size:0.875rem">
                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label" style="font-weight:500;font-size:0.85rem;color:#475569">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" style="font-weight:500;font-size:0.85rem;color:#475569">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>
        <div class="text-center mt-3">
            <small class="text-muted">ERP-TRP v1.0.0</small>
        </div>
    </div>
</body>
</html>
