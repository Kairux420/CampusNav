<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/header.php';
?>

<div class="home-dashboard">
    <div class="dashboard-header">
        <h1>About <em>CampusNav</em></h1>
        <p>Your intelligent indoor navigation assistant.</p>
    </div>

    <div class="search-card" style="line-height: 1.8;">
        <h2 style="color: var(--text-main); margin-bottom: 16px;">Welcome to CampusNav</h2>
        <p style="color: var(--text-muted); margin-bottom: 16px;">
            CampusNav is a state-of-the-art web application designed to help students, faculty, and visitors effortlessly navigate our complex campus buildings. Finding the right room, restroom, or cafeteria has never been easier.
        </p>

        <h3 style="color: var(--text-main); margin-top: 24px; margin-bottom: 12px;">Features</h3>
        <ul style="color: var(--text-muted); padding-left: 20px; margin-bottom: 20px;">
            <li style="margin-bottom: 8px;"><strong>Interactive Maps:</strong> Beautiful, high-resolution floor plans with interactive nodes.</li>
            <li style="margin-bottom: 8px;"><strong>Intelligent Search:</strong> Quickly filter by building wings, floors, and categories.</li>
            <li style="margin-bottom: 8px;"><strong>AI Assistant:</strong> Chat directly with our integrated AI to find exactly what you're looking for using natural language.</li>
            <li style="margin-bottom: 8px;"><strong>Step-by-Step Routing:</strong> Get clear, concise directions with our custom timeline routing system.</li>
        </ul>

        <h3 style="color: var(--text-main); margin-top: 24px; margin-bottom: 12px;">Contact Us</h3>
        <p style="color: var(--text-muted);">
            If you encounter any issues with the maps or have suggestions for improvements, please use the <strong>Report Issue</strong> link in the navigation menu.
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
