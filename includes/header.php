<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['guest']);
$isGuest = isset($_SESSION['guest']) && $_SESSION['guest'] === true;

// Force refresh role from DB so you don't have to log out, and auto-upgrade Kyle's account
if ($isLoggedIn && !$isGuest && isset($pdo)) {
    $pdo->query("UPDATE users SET role = 'admin' WHERE email = 'kylebradley05@gmail.com'");
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $dbRole = $stmt->fetchColumn();
    if ($dbRole) {
        $_SESSION['role'] = $dbRole;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusNav</title>
    <link rel="stylesheet" href="/campusnav/assets/css/style.css">
    <?php if (!empty($loadLeafletCss)): ?>
        <link rel="stylesheet" href="/campusnav/assets/vendor/leaflet/leaflet.css">
    <?php endif; ?>
    <script>
        // Init theme immediately to prevent flash
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
</head>
<body>
    <?php
    $globalAlert = null;
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT * FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC LIMIT 1");
            if ($stmt) {
                $globalAlert = $stmt->fetch();
            }
        } catch (PDOException $e) {
            // Notifications table might not be migrated yet, ignore silently
        }
    }
    ?>
    <?php if ($globalAlert): ?>
        <div class="global-alert-banner" style="background: var(--accent-secondary); color: var(--ink); padding: 10px 20px; text-align: center; font-weight: 600; font-size: 0.95rem; z-index: 1001; position: relative;">
            🚨 <?php echo htmlspecialchars($globalAlert['title'] ?? 'Campus Alert'); ?>: <?php echo htmlspecialchars($globalAlert['message']); ?>
        </div>
    <?php endif; ?>
    <header class="site-header">
        <div class="header-inner">
            <a href="/campusnav/home.php" class="brand">CampusNav</a>
            <?php if ($isLoggedIn): ?>
                <nav class="main-nav">
                    <a href="/campusnav/home.php">Home</a>
                    <a href="/campusnav/about.php">About</a>
                    <a href="/campusnav/search.php">Search</a>
                    <a href="/campusnav/chat.php">AI Assistant</a>
                    <a href="/campusnav/notifications.php">Notifications</a>
                    <?php if (!$isGuest && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')): ?>
                        <a href="/campusnav/report_issue.php">Report Issue</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="/campusnav/admin/index.php">Admin Hub</a>
                    <?php endif; ?>
                    <a href="/campusnav/profile.php">Profile</a>
                    <a href="/campusnav/logout.php">Log Out</a>
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle Dark Mode">🌓</button>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('themeToggle');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    const current = document.documentElement.getAttribute('data-theme');
                    const next = current === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('theme', next);
                });
            }
        });
    </script>
    <main class="site-main">
