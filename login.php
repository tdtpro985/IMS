<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error   = '';
$warning = '';

if (isset($_GET['timeout'])) {
    $warning = 'Your session expired due to inactivity. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, name, email, password, role, is_locked, fail_count FROM users WHERE email = ?"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['is_locked']) {
            $error = 'This account is locked. Please contact an Administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $newFail = $user['fail_count'] + 1;
            $locked  = $newFail >= 5 ? 1 : 0;
            $upd = $db->prepare("UPDATE users SET fail_count=?, is_locked=? WHERE id=?");
            $upd->bind_param('iii', $newFail, $locked, $user['id']);
            $upd->execute(); $upd->close();
            if ($locked) {
                $error = 'Too many failed attempts. Account locked. Contact an Admin.';
                logAudit('LOCK', 'Users', $user['id'], "Account locked: {$user['email']}");
            } else {
                $remaining = 5 - $newFail;
                $error = "Invalid email or password. {$remaining} attempt(s) remaining.";
            }
        } else {
            $upd = $db->prepare("UPDATE users SET fail_count=0 WHERE id=?");
            $upd->bind_param('i', $user['id']);
            $upd->execute(); $upd->close();

            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['last_activity'] = time();

            logAudit('LOGIN', 'Auth', $user['id'], "{$user['name']} logged in.");
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — TDT Powersteel IMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-page">

    <!-- Background decorations -->
    <div class="login-bg-glow"></div>
    <div class="login-bg-arc login-bg-arc-1"></div>
    <div class="login-bg-arc login-bg-arc-2"></div>

    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo-wrap">
            <img src="/uploads/photos/logo-dark.png" alt="TDT Powersteel">
        </div>

        <!-- Title -->
        <div class="login-title">
            <h1>Admin <span>Access</span></h1>
            <p class="subtitle">Intern Management System</p>
        </div>

        <!-- Alerts -->
        <?php if ($warning): ?>
        <div class="alert-warning">
            <i class="fas fa-clock"></i>
            <?= htmlspecialchars($warning) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle" style="margin-top:1px;flex-shrink:0"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="/login.php" class="login-form" novalidate>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="admin"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="password-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" id="togglePwd" aria-label="Toggle password">
                        <i class="fas fa-eye" id="togglePwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Log In</button>

        </form>

        <p class="login-note">For Authorized Personnel Only</p>

    </div>

    <p class="login-page-footer">
        &copy; <?= date('Y') ?> TDT Powersteel Corporation. All rights reserved.
    </p>

<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('togglePwdIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>

</body>
</html>
