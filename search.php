<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$query = trim($_GET['q'] ?? '');
$selectedFloor = trim($_GET['floor'] ?? '');
$selectedWing = trim($_GET['wing'] ?? '');
$selectedCategory = trim($_GET['category'] ?? '');

$rooms = [];
$emptyMessage = null;

$floorOptions = [];
$wingOptions = [];
$categoryOptions = [];

$stmt = $pdo->query(
    "SELECT DISTINCT f.floor_name FROM nodes n LEFT JOIN floors f ON f.floor_id = n.floor_id WHERE n.node_type IN ('room', 'entrance') ORDER BY f.floor_name ASC"
);
$floorOptions = array_values(array_filter(array_map(fn($row) => $row['floor_name'], $stmt->fetchAll())));

$stmt = $pdo->query(
    "SELECT DISTINCT f.wing FROM nodes n LEFT JOIN floors f ON f.floor_id = n.floor_id WHERE n.node_type IN ('room', 'entrance') AND f.wing IS NOT NULL AND f.wing != '' ORDER BY f.wing ASC"
);
$wingOptions = array_values(array_filter(array_map(fn($row) => $row['wing'], $stmt->fetchAll())));

$stmt = $pdo->query(
    "SELECT DISTINCT category FROM nodes WHERE node_type IN ('room', 'entrance') AND category IS NOT NULL AND category != '' ORDER BY category ASC"
);
$categoryOptions = array_values(array_filter(array_map(fn($row) => $row['category'], $stmt->fetchAll())));

if ($query !== '' || $selectedCategory !== '') {
    // Log the search query
    $userId = $_SESSION['user_id'] ?? null;
    $logStmt = $pdo->prepare("INSERT INTO search_logs (query, user_id) VALUES (?, ?)");
    $logStmt->execute([$query, $userId]);

    // Strip hyphens and spaces from the query for robust matching
    $cleanQuery = str_replace(['-', ' '], '', $query);
    $likeParam = '%' . $cleanQuery . '%';

    $sql = "SELECT n.node_id, n.room_code, n.node_name, n.description, n.category, f.floor_name, f.wing " .
        "FROM nodes n " .
        "LEFT JOIN floors f ON f.floor_id = n.floor_id " .
        "WHERE n.node_type IN ('room', 'entrance')";
    
    $params = [];

    if ($query !== '') {
        $sql .= " AND (REPLACE(REPLACE(n.room_code, '-', ''), ' ', '') LIKE ? OR " .
            "REPLACE(REPLACE(n.node_name, '-', ''), ' ', '') LIKE ? OR " .
            "n.category LIKE ? OR n.category LIKE ? OR " .
            "n.description LIKE ?)";
        $params[] = $likeParam;
        $params[] = $likeParam;
        $params[] = $query . '%';
        $params[] = '% ' . $query . '%';
        $params[] = '%' . $query . '%';
    }

    if ($selectedFloor !== '') {
        $sql .= " AND f.floor_name = ?";
        $params[] = $selectedFloor;
    }

    if ($selectedWing !== '') {
        $sql .= " AND f.wing = ?";
        $params[] = $selectedWing;
    }

    if ($selectedCategory !== '') {
        $sql .= " AND n.category = ?";
        $params[] = $selectedCategory;
    }

    $sql .= " ORDER BY n.node_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();
} else {
    $emptyMessage = 'Type a room name to search';
}
?>

<section class="search-page">
    <div class="search-page__shell">
        <h1>Search Rooms</h1>
        <form method="GET" class="search-form">
            <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search by room name or description">
            <button type="submit">Search</button>

            <div class="search-filters" role="group" aria-label="Filter rooms">
                <label class="search-filter">
                    <span>Floor</span>
                    <select name="floor" onchange="this.form.submit()">
                        <option value="">All floors</option>
                        <?php foreach ($floorOptions as $floor): ?>
                            <option value="<?php echo htmlspecialchars($floor); ?>" <?php echo $selectedFloor === $floor ? 'selected' : ''; ?>><?php echo htmlspecialchars($floor); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="search-filter">
                    <span>Wing</span>
                    <select name="wing" onchange="this.form.submit()">
                        <option value="">All wings</option>
                        <?php foreach ($wingOptions as $wing): ?>
                            <option value="<?php echo htmlspecialchars($wing); ?>" <?php echo $selectedWing === $wing ? 'selected' : ''; ?>><?php echo htmlspecialchars($wing); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="search-filter">
                    <span>Category (Tag)</span>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        <?php foreach ($categoryOptions as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selectedCategory === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </form>

        <div class="search-results">
            <?php if ($rooms): ?>
                <ul class="room-list">
                    <?php foreach ($rooms as $room): ?>
                        <li class="room-card">
                            <a href="navigate.php?to=<?php echo (int) $room['node_id']; ?>" class="room-card__link">
                                <span class="room-card__main">
                                    <span class="room-card__title">
                                        <?php if (!empty($room['room_code'])): ?>
                                            <span style="background: var(--accent-primary); color: var(--text-main); padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; margin-right: 6px;"><?php echo htmlspecialchars($room['room_code']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($room['category'])): ?>
                                            <span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-right: 6px; font-weight: bold;"><?php echo htmlspecialchars($room['category']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($room['node_name'] ?: 'Unnamed Room'); ?>
                                    </span>
                                    <span class="room-card__meta"><?php echo htmlspecialchars($room['floor_name'] ?? 'Unknown floor'); ?></span>
                                    <span class="room-card__desc"><?php echo htmlspecialchars($room['description'] ?? 'No description available.'); ?></span>
                                </span>
                                <span class="room-card__action">Select</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif ($emptyMessage): ?>
                <p class="empty-state"><?php echo htmlspecialchars($emptyMessage); ?></p>
            <?php else: ?>
                <p class="empty-state">No rooms matched your search.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
