<?php
session_start();
require_once '../config/db.php';

// Auth check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

require_once '../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 2.5rem; color: var(--text-main); margin-bottom: 10px;">Admin Hub</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem;">Manage maps, nodes, alerts, and user reports from one central location.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
        
        <!-- Map & Floor Manager -->
        <a href="manage_maps.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">🗺️</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Map Manager</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Upload new floor plan images and register new building floors into the system.</p>
        </a>

        <!-- Map Editor -->
        <a href="map_editor.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">✏️</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Map Editor</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Drag and drop nodes to adjust coordinates, and edit room names and types interactively.</p>
        </a>

        <!-- Coordinate Picker -->
        <a href="/coordinate_picker.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">📍</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Coordinate Picker</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Click on maps to capture exact pixel coordinates and generate SQL for new nodes (rooms, stairs, etc).</p>
        </a>

        <!-- Edge Linker -->
        <a href="/edge_linker.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">🔗</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Edge Linker</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Draw connections (edges) between nodes to enable pathfinding and navigation routing.</p>
        </a>

        <!-- Issue Reports -->
        <a href="admin_reports.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">🚩</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Issue Reports</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Review and resolve facility issues (broken lifts, closed paths) reported by students.</p>
        </a>

        <!-- Global Alerts -->
        <a href="/admin_alerts.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">📢</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Global Alerts</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Broadcast campus-wide notifications to all active users regarding navigation changes or emergencies.</p>
        </a>

        <!-- Search Analytics -->
        <a href="admin_analytics.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">📊</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Search Analytics</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">Track search queries and view popular locations to optimize map navigation.</p>
        </a>

        <!-- User Management -->
        <a href="manage_users.php" style="text-decoration: none; display: flex; flex-direction: column; background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); transition: transform 0.2s, box-shadow 0.2s; color: var(--text-main);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
            <div style="font-size: 2.5rem; margin-bottom: 15px;">👥</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">User Management</h3>
            <p style="color: var(--text-muted); margin: 0; line-height: 1.5;">View all registered users and manage their access roles and permissions.</p>
        </a>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
