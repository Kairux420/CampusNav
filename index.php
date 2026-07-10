<?php
session_start();
require_once 'config/db.php';

// If already logged in, skip straight to home
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error = '';
$mode = $_GET['mode'] ?? 'login'; // 'login' or 'register'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guest'])) {
        $_SESSION['guest'] = true;
        $_SESSION['name'] = 'Guest';
        $_SESSION['role'] = 'guest';
        header('Location: home.php');
        exit;
    }

    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            header('Location: home.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }

    if (isset($_POST['register'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt->execute([$name, $email, $hashed]);

                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['name'] = $name;
                $_SESSION['role'] = 'student';
                header('Location: home.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusNav &mdash; <?php echo $mode === 'register' ? 'Register' : 'Log In'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>CampusNav</h1>
        <p class="auth-subtitle">Indoor navigation for FCVAC</p>

        <?php if ($error): ?>
            <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($mode === 'register'): ?>
            <form method="POST" class="auth-form">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

                <button type="submit" name="register" class="btn-primary" style="margin-top:10px;">Create Account</button>
                <a href="google_login.php" class="btn-primary google-btn" style="text-align: center; text-decoration: none; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; background: #fff; color: #444; border: 1px solid #ddd; box-shadow: var(--shadow-sm);">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google G" width="18" height="18">
                    Sign up with Google
                </a>
                <div class="auth-separator">OR</div>
                <button type="submit" name="guest" class="btn-guest">Continue as Guest (Quick Map Access)</button>
            </form>
            <p class="auth-switch">Already have an account? <a href="index.php?mode=login">Log in here</a></p>
        <?php else: ?>
            <form method="POST" class="auth-form">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit" name="login" class="btn-primary" style="margin-top:10px;">Log In</button>
                <a href="google_login.php" class="btn-primary google-btn" style="text-align: center; text-decoration: none; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; background: #fff; color: #444; border: 1px solid #ddd; box-shadow: var(--shadow-sm);">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google G" width="18" height="18">
                    Log in with Google
                </a>
                <div class="auth-separator">OR</div>
                <button type="submit" name="guest" class="btn-guest">Continue as Guest (Quick Map Access)</button>
            </form>
            <p class="auth-switch">Don't have an account? <a href="index.php?mode=register">Register here</a></p>
        <?php endif; ?>
        <p class="guest-notice">Guest access provides basic navigation features. Log in for personalized experience and saved routes.</p>
    </div>
</body>
</html>
