<?php
session_start();
require_once '../config/db.php';

// Auth check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

// Analytics Queries
$totalSearchesStmt = $pdo->query("SELECT COUNT(*) FROM search_logs");
$totalSearches = $totalSearchesStmt->fetchColumn();

// Top 10 Searches
$topSearchesStmt = $pdo->query("
    SELECT query, COUNT(*) as count 
    FROM search_logs 
    GROUP BY query 
    ORDER BY count DESC 
    LIMIT 10
");
$topSearches = $topSearchesStmt->fetchAll();

// Recent Searches
$recentSearchesStmt = $pdo->query("
    SELECT s.query, s.created_at, u.name 
    FROM search_logs s
    LEFT JOIN users u ON s.user_id = u.user_id
    ORDER BY s.created_at DESC 
    LIMIT 20
");
$recentSearches = $recentSearchesStmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2rem; color: var(--text-main); margin: 0 0 10px 0;">Search Analytics</h1>
            <p style="color: var(--text-muted); margin: 0;">Track search queries and view popular locations.</p>
        </div>
        <a href="index.php" class="btn-secondary" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; border: 1px solid var(--border-color); color: var(--text-main);">← Back to Hub</a>
    </div>

    <!-- Stats Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div style="background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); text-align: center;">
            <h3 style="margin: 0 0 10px 0; color: var(--text-muted); font-weight: 500;">Total Searches</h3>
            <div style="font-size: 3rem; font-weight: bold; color: var(--accent-primary);"><?php echo number_format($totalSearches); ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        
        <!-- Top Searches -->
        <div style="background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <h2 style="margin-top: 0; color: var(--text-main); font-size: 1.3rem; margin-bottom: 20px;">Top 10 Searched Rooms</h2>
            <?php if (empty($topSearches)): ?>
                <p style="color: var(--text-muted);">No search data available yet.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                            <th style="padding: 10px; color: var(--text-muted);">Query</th>
                            <th style="padding: 10px; color: var(--text-muted); width: 100px;">Searches</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topSearches as $row): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 10px; font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($row['query']); ?></td>
                                <td style="padding: 12px 10px; color: var(--accent-primary); font-weight: bold;"><?php echo (int)$row['count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Searches -->
        <div style="background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <h2 style="margin-top: 0; color: var(--text-main); font-size: 1.3rem; margin-bottom: 20px;">Recent Searches</h2>
            <?php if (empty($recentSearches)): ?>
                <p style="color: var(--text-muted);">No search data available yet.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                            <th style="padding: 10px; color: var(--text-muted);">Query</th>
                            <th style="padding: 10px; color: var(--text-muted);">User</th>
                            <th style="padding: 10px; color: var(--text-muted);">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSearches as $row): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 10px; color: var(--text-main);"><?php echo htmlspecialchars($row['query']); ?></td>
                                <td style="padding: 12px 10px; color: var(--text-muted);"><?php echo htmlspecialchars($row['name'] ?: 'Guest'); ?></td>
                                <td style="padding: 12px 10px; color: var(--text-muted); font-size: 0.9em;"><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
