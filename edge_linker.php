<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

// Handle AJAX request FIRST, before any HTML output (header.php prints
// <!DOCTYPE html> immediately, which broke the JSON response before)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['floor_id'])) {
    $floorId = (int) $_GET['floor_id'];
    $stmt = $pdo->prepare(
        "SELECT n.node_id, n.room_code, n.node_name, n.node_type, n.x_coord, n.y_coord, n.floor_id, f.floor_name, f.wing " .
        "FROM nodes n " .
        "LEFT JOIN floors f ON f.floor_id = n.floor_id " .
        "WHERE n.floor_id = ? ORDER BY n.node_name ASC"
    );
    $stmt->execute([$floorId]);
    $nodes = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['nodes' => $nodes]);
    exit;
}

require_once 'includes/header.php';

function formatNodeLabel($node)
{
    $label = '';

    if (!empty($node['room_code']) || !empty($node['node_name'])) {
        $label = trim(($node['room_code'] ? '[' . $node['room_code'] . '] ' : '') . ($node['node_name'] ?: ''));
    } else {
        $label = 'Unnamed node';
    }

    $floorName = trim((string) ($node['floor_name'] ?? ''));
    $wing = trim((string) ($node['wing'] ?? ''));
    $location = '';

    if ($floorName !== '' || $wing !== '') {
        $location = $floorName;
        if ($wing !== '') {
            $location .= $wing !== '' ? ' - ' . $wing : '';
        }
    }

    return $label . ($location !== '' ? ' (' . $location . ')' : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentFloorId = (int) ($_POST['current_floor'] ?? 0);

    if (isset($_POST['delete_edge']) && !empty($_POST['edge_id'])) {
        $stmt = $pdo->prepare('DELETE FROM edges WHERE edge_id = ?');
        $stmt->execute([(int) $_POST['edge_id']]);
        header('Location: edge_linker.php?floor_id=' . $currentFloorId);
        exit;
    }

    if (isset($_POST['quick_link'])) {
        $nodeA = (int) ($_POST['quick_node_a'] ?? 0);
        $nodeB = (int) ($_POST['quick_node_b'] ?? 0);
        $weight = (float) ($_POST['quick_weight'] ?? 25);
        $currentFloorId = (int) ($_POST['current_floor'] ?? 0);

        if ($nodeA > 0 && $nodeB > 0 && $nodeA !== $nodeB) {
            $stmt = $pdo->prepare('INSERT INTO edges (node_a, node_b, weight) VALUES (?, ?, ?)');
            $stmt->execute([$nodeA, $nodeB, $weight]);
        }

        header('Location: edge_linker.php?floor_id=' . $currentFloorId);
        exit;
    }
    if (isset($_POST['add_edge'])) {
        $nodeA = (int) ($_POST['node_a'] ?? 0);
        $nodeB = (int) ($_POST['node_b'] ?? 0);
        $weight = (float) ($_POST['weight'] ?? 10);

        if ($nodeA > 0 && $nodeB > 0 && $nodeA !== $nodeB) {
            $stmt = $pdo->prepare('INSERT INTO edges (node_a, node_b, weight) VALUES (?, ?, ?)');
            $stmt->execute([$nodeA, $nodeB, $weight]);
        }

        header('Location: edge_linker.php?floor_id=' . $currentFloorId);
        exit;
    }

    if (isset($_POST['edit_node']) && !empty($_POST['edit_node_id'])) {
        $nodeId = (int) $_POST['edit_node_id'];
        $newName = trim((string) ($_POST['edit_node_name'] ?? ''));
        $newCode = trim((string) ($_POST['edit_room_code'] ?? ''));
        $newType = (string) ($_POST['edit_node_type'] ?? 'room');
        $newX = (float) ($_POST['edit_x_coord'] ?? 0);
        $newY = (float) ($_POST['edit_y_coord'] ?? 0);
        $validTypes = ['room', 'junction', 'stairs', 'lift', 'entrance'];

        if ($nodeId > 0 && $newName !== '' && in_array($newType, $validTypes, true)) {
            $stmt = $pdo->prepare('UPDATE nodes SET room_code = ?, node_name = ?, node_type = ?, x_coord = ?, y_coord = ? WHERE node_id = ?');
            $stmt->execute([$newCode, $newName, $newType, $newX, $newY, $nodeId]);
        }

        $redirectSearch = isset($_POST['search_query']) ? '&search=' . urlencode($_POST['search_query']) : '';
        $redirectSearch .= isset($_POST['type_filter_query']) && $_POST['type_filter_query'] !== '' ? '&type_filter=' . urlencode($_POST['type_filter_query']) : '';
        header('Location: edge_linker.php?floor_id=' . $currentFloorId . $redirectSearch . '#nodeManager');
        exit;
    }

    if (isset($_POST['delete_node']) && !empty($_POST['delete_node_id'])) {
        $nodeId = (int) $_POST['delete_node_id'];

        // Remove any edges touching this node first (foreign key safety),
        // then remove the node itself
        $stmt = $pdo->prepare('DELETE FROM edges WHERE node_a = ? OR node_b = ?');
        $stmt->execute([$nodeId, $nodeId]);

        $stmt = $pdo->prepare('DELETE FROM nodes WHERE node_id = ?');
        $stmt->execute([$nodeId]);

        $redirectSearch = isset($_POST['search_query']) ? '&search=' . urlencode($_POST['search_query']) : '';
        $redirectSearch .= isset($_POST['type_filter_query']) && $_POST['type_filter_query'] !== '' ? '&type_filter=' . urlencode($_POST['type_filter_query']) : '';
        header('Location: edge_linker.php?floor_id=' . $currentFloorId . $redirectSearch . '#nodeManager');
        exit;
    }
}

$floorsStmt = $pdo->query("SELECT floor_id, floor_name, building, wing, map_image, floor_order FROM floors ORDER BY floor_order, floor_name, wing");
$floors = $floorsStmt->fetchAll();

$requestedFloorId = isset($_GET['floor_id']) ? (int) $_GET['floor_id'] : 0;
$defaultFloor = null;
foreach ($floors as $floor) {
    if ((int) $floor['floor_id'] === $requestedFloorId) {
        $defaultFloor = $floor;
        break;
    }
}
if (!$defaultFloor) {
    $defaultFloor = $floors[0] ?? null;
}
$defaultMap = $defaultFloor && !empty($defaultFloor['map_image']) ? 'assets/maps/' . $defaultFloor['map_image'] : '';

$stmt = $pdo->query(
    "SELECT n.node_id, n.room_code, n.node_name, n.node_type, f.floor_name, f.wing, n.x_coord, n.y_coord, n.floor_id " .
    "FROM nodes n " .
    "LEFT JOIN floors f ON f.floor_id = n.floor_id " .
    "ORDER BY f.floor_order, f.floor_name, f.wing, n.node_name"
);
$nodes = $stmt->fetchAll();

$edgesStmt = $pdo->query(
    "SELECT e.edge_id, e.weight, " .
    "n1.node_id AS node_a_id, n1.room_code AS node_a_code, n1.node_name AS node_a_name, n1.floor_id AS floor_a_id, f1.floor_name AS floor_a_name, f1.wing AS wing_a, " .
    "n2.node_id AS node_b_id, n2.room_code AS node_b_code, n2.node_name AS node_b_name, n2.floor_id AS floor_b_id, f2.floor_name AS floor_b_name, f2.wing AS wing_b " .
    "FROM edges e " .
    "JOIN nodes n1 ON n1.node_id = e.node_a " .
    "JOIN nodes n2 ON n2.node_id = e.node_b " .
    "LEFT JOIN floors f1 ON f1.floor_id = n1.floor_id " .
    "LEFT JOIN floors f2 ON f2.floor_id = n2.floor_id " .
    "ORDER BY e.edge_id DESC"
);
$edges = $edgesStmt->fetchAll();

$totalNodeCount = (int) $pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
$nodeCountByType = $pdo->query("SELECT node_type, COUNT(*) AS cnt FROM nodes GROUP BY node_type")->fetchAll();

// Node search for the management panel below
$searchQuery = trim((string) ($_GET['search'] ?? ''));
$typeFilter = trim((string) ($_GET['type_filter'] ?? ''));
$validNodeTypes = ['room', 'junction', 'stairs', 'lift', 'entrance'];
if (!in_array($typeFilter, $validNodeTypes, true)) {
    $typeFilter = '';
}

$searchResults = [];
$hasSearchCriteria = ($searchQuery !== '' || $typeFilter !== '');

if ($hasSearchCriteria) {
    $conditions = [];
    $params = [];

    if ($searchQuery !== '') {
        $cleanSearch = str_replace(['-', ' '], '', $searchQuery);
        $likeSearch = '%' . $cleanSearch . '%';
        
        $conditions[] = "(REPLACE(REPLACE(n.room_code, '-', ''), ' ', '') LIKE ? OR REPLACE(REPLACE(n.node_name, '-', ''), ' ', '') LIKE ?)";
        $params[] = $likeSearch;
        $params[] = $likeSearch;
    }
    if ($typeFilter !== '') {
        $conditions[] = 'n.node_type = ?';
        $params[] = $typeFilter;
    }

    $whereClause = implode(' AND ', $conditions);

    $searchStmt = $pdo->prepare(
        "SELECT n.node_id, n.room_code, n.node_name, n.node_type, n.x_coord, n.y_coord, n.floor_id, " .
        "f.floor_name, f.wing, " .
        "(SELECT COUNT(*) FROM edges WHERE node_a = n.node_id OR node_b = n.node_id) AS edge_count " .
        "FROM nodes n LEFT JOIN floors f ON f.floor_id = n.floor_id " .
        "WHERE " . $whereClause . " " .
        "ORDER BY f.floor_order, f.wing, n.node_name " .
        "LIMIT 100"
    );
    $searchStmt->execute($params);
    $searchResults = $searchStmt->fetchAll();
}

// Find suggested cross-floor matches for stairs/lifts: same wing, on
// different (adjacent) floors, with coordinates close enough on the image
// to plausibly be the same physical stairwell/shaft, and not already linked.
$existingPairSet = [];
foreach ($edges as $edge) {
    $key1 = $edge['node_a_id'] . '-' . $edge['node_b_id'];
    $key2 = $edge['node_b_id'] . '-' . $edge['node_a_id'];
    $existingPairSet[$key1] = true;
    $existingPairSet[$key2] = true;
}

$candidateStmt = $pdo->query(
    "SELECT n.node_id, n.room_code, n.node_name, n.node_type, n.x_coord, n.y_coord, n.floor_id, " .
    "f.floor_name, f.wing, f.floor_order, f.building " .
    "FROM nodes n LEFT JOIN floors f ON f.floor_id = n.floor_id " .
    "WHERE n.node_type IN ('stairs', 'lift') " .
    "ORDER BY f.wing, f.floor_order"
);
$candidates = $candidateStmt->fetchAll();

// Group by wing (same physical column of floors stacked on top of each other)
$groupedByWing = [];
foreach ($candidates as $candidate) {
    $wingKey = ($candidate['building'] ?? '') . '|' . ($candidate['wing'] ?? '');
    $groupedByWing[$wingKey][] = $candidate;
}

// Coordinates within this many pixels are considered "close enough" to be
// the same physical stairwell across floors. Tweak if suggestions look off.
$coordThreshold = 80;

$suggestedMatches = [];
foreach ($groupedByWing as $wingKey => $wingNodes) {
    // Sort by floor order so we only suggest ADJACENT floors (Ground->First,
    // First->Second), never skipping a floor
    usort($wingNodes, function ($a, $b) {
        return $a['floor_order'] <=> $b['floor_order'];
    });

    // Group further by floor_id within this wing
    $byFloor = [];
    foreach ($wingNodes as $node) {
        $byFloor[$node['floor_id']][] = $node;
    }
    $floorIds = array_keys($byFloor);

    for ($f = 0; $f < count($floorIds) - 1; $f++) {
        $floorA = $byFloor[$floorIds[$f]];
        $floorB = $byFloor[$floorIds[$f + 1]];

        foreach ($floorA as $nodeA) {
            $bestMatch = null;
            $bestDistance = null;

            foreach ($floorB as $nodeB) {
                $dx = $nodeA['x_coord'] - $nodeB['x_coord'];
                $dy = $nodeA['y_coord'] - $nodeB['y_coord'];
                $dist = sqrt($dx * $dx + $dy * $dy);

                if ($dist <= $coordThreshold && ($bestDistance === null || $dist < $bestDistance)) {
                    $bestDistance = $dist;
                    $bestMatch = $nodeB;
                }
            }

            if ($bestMatch) {
                $key = $nodeA['node_id'] . '-' . $bestMatch['node_id'];
                if (!isset($existingPairSet[$key])) {
                    $suggestedMatches[] = [
                        'a' => $nodeA,
                        'b' => $bestMatch,
                        'distance' => round($bestDistance)
                    ];
                }
            }
        }
    }
}
?>

<style>
.node-locate-btn {
    background: none;
    border: none;
    color: #1e3a5f;
    text-decoration: underline;
    cursor: pointer;
    font-size: inherit;
    padding: 0;
    text-align: left;
}
.node-locate-btn:hover {
    color: #16304d;
}
.edge-linker-dot-highlight {
    background: #ffd60a !important;
    box-shadow: 0 0 0 6px rgba(255, 214, 10, 0.4) !important;
    animation: edge-linker-pulse 1s ease-in-out 3;
}
.edge-linker-dot-unlinked {
    box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.5);
    border-color: #d32f2f;
}
.edge-linker-dot-unlinked.edge-linker-dot-selected {
    box-shadow: 0 0 0 4px rgba(243, 156, 18, 0.5);
}
.edge-linker-dot-stairs {
    width: 20px !important;
    height: 20px !important;
    transform: translate(-50%, -50%) rotate(45deg) !important;
    border-radius: 4px !important;
    background: #6a4c93;
    z-index: 5;
}
.edge-linker-dot-stairs.edge-linker-dot-selected {
    background: #f39c12;
}
.edge-linker-dot-stairs.edge-linker-dot-unlinked {
    box-shadow: 0 0 0 5px rgba(211, 47, 47, 0.6);
}
.edge-linker-floor-picker {
    width: 150px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.floor-picker-group {
    background: #fff;
    border: 1px solid #dde5ef;
    border-radius: 10px;
    padding: 10px;
}
.floor-picker-level-label {
    font-weight: 700;
    color: #1e3a5f;
    margin-bottom: 8px;
    font-size: 0.85rem;
}
.floor-picker-wings {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.floor-picker-wings-row {
    flex-direction: row;
    gap: 6px;
}
.floor-picker-btn {
    padding: 8px 10px;
    border: 1px solid #cdd7e3;
    border-radius: 6px;
    background: #f7f9fc;
    cursor: pointer;
    text-align: left;
    font-size: 0.9rem;
    color: #333;
}
.floor-picker-btn-short {
    flex: 1;
    text-align: center;
    padding: 8px 0;
    font-weight: 600;
}
.floor-picker-btn:hover {
    background: #e8f1fb;
}
.floor-picker-btn-active {
    background: #1e3a5f;
    color: #fff;
    border-color: #1e3a5f;
    font-weight: 600;
}

/* Widen this specific page so the map has more room to breathe */
.site-main:has(.edge-linker-page) {
    max-width: 1500px;
}
.edge-linker-sidebar {
    width: 280px;
}
@keyframes edge-linker-pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.6); }
}
</style>

<section class="edge-linker-page">
    <h1>Edge Linker</h1>
    <p class="edge-linker-help">Pick nodes visually from the map, then connect them into edges.</p>
    <p class="edge-linker-help">
        <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#2e86de;box-shadow:0 0 0 3px rgba(211,47,47,0.5);vertical-align:middle;margin-right:6px;"></span>
        Red ring = no edges yet on this node.
        <span id="unlinkedCount" style="font-weight:600;margin-left:8px;"></span>
    </p>

    <div class="edge-linker-controls">
        <select id="floorSelect" name="floor_id" style="display:none;">
            <?php foreach ($floors as $floor): ?>
                <?php $label = trim($floor['floor_name']); ?>
                <?php if (!empty($floor['wing'])): ?>
                    <?php $label .= ' - ' . $floor['wing']; ?>
                <?php endif; ?>
                <option value="<?php echo (int) $floor['floor_id']; ?>"
                    data-map="<?php echo htmlspecialchars(!empty($floor['map_image']) ? 'assets/maps/' . $floor['map_image'] : ''); ?>"
                    <?php echo ($defaultFloor && (int) $floor['floor_id'] === (int) $defaultFloor['floor_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label style="display:flex;align-items:center;gap:6px;margin-top:8px;font-weight:normal;">
            <input type="checkbox" id="stairsOnlyToggle">
            Show only stairs / lifts
        </label>
    </div>

    <?php
    // Group floors by wing (Computing/PADU/Central/etc) preserving floor
    // order within each group, so each group shows its G/1/2 floor buttons
    $wingGroups = [];
    foreach ($floors as $floor) {
        $groupKey = $floor['wing'] ?: $floor['floor_name'];
        $wingGroups[$groupKey][] = $floor;
    }

    function shortFloorLabel($floorName)
    {
        $floorName = strtolower(trim($floorName));
        if (strpos($floorName, 'ground') !== false) {
            return 'G';
        }
        if (strpos($floorName, 'first') !== false) {
            return '1';
        }
        if (strpos($floorName, 'second') !== false) {
            return '2';
        }
        return substr($floorName, 0, 1);
    }
    ?>

    <div class="edge-linker-layout">
        <div class="edge-linker-floor-picker">
            <?php foreach ($wingGroups as $wingName => $floorsInWing): ?>
                <?php
                usort($floorsInWing, function ($a, $b) {
                    return $a['floor_order'] <=> $b['floor_order'];
                });
                ?>
                <div class="floor-picker-group">
                    <div class="floor-picker-level-label"><?php echo htmlspecialchars($wingName); ?></div>
                    <div class="floor-picker-wings floor-picker-wings-row">
                        <?php foreach ($floorsInWing as $floor): ?>
                            <button type="button" class="floor-picker-btn floor-picker-btn-short"
                                data-floor-id="<?php echo (int) $floor['floor_id']; ?>"
                                data-map="<?php echo htmlspecialchars(!empty($floor['map_image']) ? 'assets/maps/' . $floor['map_image'] : ''); ?>"
                                title="<?php echo htmlspecialchars($floor['floor_name']); ?>">
                                <?php echo htmlspecialchars(shortFloorLabel($floor['floor_name'])); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="edge-linker-map-wrap">
            <?php if ($defaultMap !== ''): ?>
                <div class="edge-linker-image-stage">
                    <img id="floorImage" src="<?php echo htmlspecialchars($defaultMap); ?>" alt="Floor plan" class="edge-linker-image">
                    <div id="overlayLayer" class="edge-linker-overlay-layer"></div>
                </div>
            <?php else: ?>
                <div class="edge-linker-empty">No map image found for this floor.</div>
            <?php endif; ?>
        </div>

        <div class="edge-linker-sidebar">
            <div class="edge-linker-panel">
                <h2>Selected Nodes</h2>
                <div id="selectedNodesSummary" class="edge-linker-selected-list">
                    <p class="edge-linker-empty">No nodes selected yet.</p>
                </div>
            </div>

            <div class="edge-linker-panel" id="edgeFormPanel" style="display:none;">
                <h2>Create Edge</h2>
                <form method="POST" class="edge-linker-form">
                    <input type="hidden" id="node_a" name="node_a" value="">
                    <input type="hidden" id="node_b" name="node_b" value="">
                    <input type="hidden" id="currentFloorField" name="current_floor" value="<?php echo (int) ($defaultFloor['floor_id'] ?? 0); ?>">
                    <div class="edge-linker-field">
                        <label>Node A</label>
                        <div id="selectedNodeA" class="edge-linker-chip">Select a node</div>
                    </div>
                    <div class="edge-linker-field">
                        <label>Node B</label>
                        <div id="selectedNodeB" class="edge-linker-chip">Select a node</div>
                    </div>
                    <div class="edge-linker-field">
                        <label for="weight">Weight (auto-suggested from distance — edit if needed)</label>
                        <input type="number" id="weight" name="weight" value="10" min="1" step="1">
                    </div>
                    <div id="duplicateWarning" class="error-msg" style="display:none;">
                        This connection already exists between these two nodes.
                    </div>
                    <button type="submit" name="add_edge" class="edge-linker-btn" id="addEdgeBtn">Add Edge</button>
                </form>
            </div>

            <div class="edge-linker-panel">
                <h2>Fallback Dropdowns</h2>
                <div class="edge-linker-row">
                    <div class="edge-linker-field">
                        <label for="node_a_dropdown">Node A</label>
                        <select id="node_a_dropdown" name="node_a_dropdown">
                            <?php
                            $currentGroup = '';
                            foreach ($nodes as $node):
                                $groupLabel = trim((string) ($node['floor_name'] ?? ''));
                                if (!empty($node['wing'])) {
                                    $groupLabel .= ' - ' . $node['wing'];
                                }
                                if ($groupLabel !== $currentGroup) {
                                    if ($currentGroup !== '') {
                                        echo '</optgroup>';
                                    }
                                    echo '<optgroup label="' . htmlspecialchars($groupLabel) . '">';
                                    $currentGroup = $groupLabel;
                                }
                                echo '<option value="' . (int) $node['node_id'] . '">' . htmlspecialchars(formatNodeLabel($node)) . '</option>';
                            endforeach;
                            if ($currentGroup !== '') {
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="edge-linker-row">
                    <div class="edge-linker-field">
                        <label for="node_b_dropdown">Node B</label>
                        <select id="node_b_dropdown" name="node_b_dropdown">
                            <?php
                            $currentGroup = '';
                            foreach ($nodes as $node):
                                $groupLabel = trim((string) ($node['floor_name'] ?? ''));
                                if (!empty($node['wing'])) {
                                    $groupLabel .= ' - ' . $node['wing'];
                                }
                                if ($groupLabel !== $currentGroup) {
                                    if ($currentGroup !== '') {
                                        echo '</optgroup>';
                                    }
                                    echo '<optgroup label="' . htmlspecialchars($groupLabel) . '">';
                                    $currentGroup = $groupLabel;
                                }
                                echo '<option value="' . (int) $node['node_id'] . '">' . htmlspecialchars(formatNodeLabel($node)) . '</option>';
                            endforeach;
                            if ($currentGroup !== '') {
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="edge-linker-table-wrap" id="nodeManager">
        <h2>Manage Nodes</h2>
        <p class="edge-linker-help" style="font-weight:600;color:#1e3a5f;">
            <?php echo $totalNodeCount; ?> total nodes
            <span style="font-weight:normal;color:#666;">
                (<?php
                    $typeStrings = array_map(function ($row) {
                        return (int) $row['cnt'] . ' ' . ucfirst($row['node_type']) . (($row['cnt'] == 1) ? '' : 's');
                    }, $nodeCountByType);
                    echo htmlspecialchars(implode(', ', $typeStrings));
                ?>)
            </span>
        </p>
        <p class="edge-linker-help">
            Search any node by name across all floors/wings. Rename it, change
            its type, adjust coordinates, or delete it entirely (deleting also
            removes any edges connected to it).
        </p>
        <form method="GET" class="search-form" style="margin:12px 0;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search node name..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            <select name="type_filter">
                <option value="">All types</option>
                <?php foreach ($validNodeTypes as $typeOption): ?>
                    <option value="<?php echo $typeOption; ?>" <?php echo $typeFilter === $typeOption ? 'selected' : ''; ?>><?php echo ucfirst($typeOption); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Search</button>
            <?php if ($hasSearchCriteria): ?>
                <a href="edge_linker.php#nodeManager" class="edge-linker-btn" style="text-decoration:none;display:inline-flex;align-items:center;">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($hasSearchCriteria): ?>
            <?php if ($searchResults): ?>
                <p class="edge-linker-help"><?php echo count($searchResults); ?> result<?php echo count($searchResults) === 1 ? '' : 's'; ?></p>
                <table class="edge-linker-table">
                    <thead>
                        <tr>
                            <th>Node</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Edges</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults as $result): ?>
                            <tr>
                                <td>
                                    <button type="button" class="node-locate-btn"
                                        data-floor-id="<?php echo (int) $result['floor_id']; ?>"
                                        data-node-id="<?php echo (int) $result['node_id']; ?>">
                                        <?php echo htmlspecialchars(($result['room_code'] ? '['.$result['room_code'].'] ' : '') . ($result['node_name'] ?: 'Unnamed')); ?>
                                    </button>
                                </td>
                                <td><?php echo htmlspecialchars($result['node_type']); ?></td>
                                <td><?php echo htmlspecialchars(trim(($result['floor_name'] ?? '') . ' - ' . ($result['wing'] ?? ''), ' -')); ?></td>
                                <td><?php echo (int) $result['edge_count']; ?></td>
                                <td>
                                    <button type="button" class="edge-linker-btn node-edit-toggle" data-target="edit-row-<?php echo (int) $result['node_id']; ?>">Edit</button>
                                    <form method="POST" class="inline-delete-form" onsubmit="return confirm('Delete this node? This also removes <?php echo (int) $result['edge_count']; ?> connected edge(s). This cannot be undone.');">
                                        <input type="hidden" name="delete_node_id" value="<?php echo (int) $result['node_id']; ?>">
                                        <input type="hidden" name="current_floor" value="<?php echo (int) ($defaultFloor['floor_id'] ?? 0); ?>">
                                        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                        <input type="hidden" name="type_filter_query" value="<?php echo htmlspecialchars($typeFilter); ?>">
                                        <button type="submit" name="delete_node" class="edge-linker-delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-row-<?php echo (int) $result['node_id']; ?>" style="display:none;">
                                <td colspan="5">
                                    <form method="POST" class="edge-linker-form" style="flex-direction:row;flex-wrap:wrap;align-items:end;gap:10px;">
                                        <input type="hidden" name="edit_node_id" value="<?php echo (int) $result['node_id']; ?>">
                                        <input type="hidden" name="current_floor" value="<?php echo (int) ($defaultFloor['floor_id'] ?? 0); ?>">
                                        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                        <input type="hidden" name="type_filter_query" value="<?php echo htmlspecialchars($typeFilter); ?>">
                                        <div class="edge-linker-field">
                                            <label>Room Code</label>
                                            <input type="text" name="edit_room_code" value="<?php echo htmlspecialchars($result['room_code'] ?? ''); ?>">
                                        </div>
                                        <div class="edge-linker-field">
                                            <label>Name</label>
                                            <input type="text" name="edit_node_name" value="<?php echo htmlspecialchars($result['node_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="edge-linker-field">
                                            <label>Type</label>
                                            <select name="edit_node_type">
                                                <?php foreach (['room', 'junction', 'stairs', 'lift', 'entrance'] as $typeOption): ?>
                                                    <option value="<?php echo $typeOption; ?>" <?php echo $result['node_type'] === $typeOption ? 'selected' : ''; ?>><?php echo ucfirst($typeOption); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="edge-linker-field">
                                            <label>X</label>
                                            <input type="number" name="edit_x_coord" value="<?php echo (int) $result['x_coord']; ?>" style="width:90px;">
                                        </div>
                                        <div class="edge-linker-field">
                                            <label>Y</label>
                                            <input type="number" name="edit_y_coord" value="<?php echo (int) $result['y_coord']; ?>" style="width:90px;">
                                        </div>
                                        <button type="submit" name="edit_node" class="edge-linker-btn">Save Changes</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="edge-linker-empty">No nodes found<?php echo $searchQuery !== '' ? ' matching "' . htmlspecialchars($searchQuery) . '"' : ''; ?><?php echo $typeFilter !== '' ? ' of type "' . htmlspecialchars($typeFilter) . '"' : ''; ?>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($suggestedMatches)): ?>
        <div class="edge-linker-table-wrap">
            <h2>Suggested Cross-Floor Links</h2>
            <p class="edge-linker-help">
                These stairs/lifts sit at nearly the same spot on adjacent floors
                within the same wing — likely the same physical staircase. Review
                each before confirming; this is a suggestion, not automatic.
            </p>
            <table class="edge-linker-table">
                <thead>
                    <tr>
                        <th>Node A</th>
                        <th>Node B</th>
                        <th>Coordinate Gap</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestedMatches as $match): ?>
                        <tr>
                            <td>
                                <button type="button" class="node-locate-btn"
                                    data-floor-id="<?php echo (int) $match['a']['floor_id']; ?>"
                                    data-node-id="<?php echo (int) $match['a']['node_id']; ?>">
                                    <?php echo htmlspecialchars(formatNodeLabel([
                                        'node_name' => ($match['a']['room_code'] ? '['.$match['a']['room_code'].'] ' : '') . $match['a']['node_name'],
                                        'floor_name' => $match['a']['floor_name'],
                                        'wing' => $match['a']['wing']
                                    ])); ?>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="node-locate-btn"
                                    data-floor-id="<?php echo (int) $match['b']['floor_id']; ?>"
                                    data-node-id="<?php echo (int) $match['b']['node_id']; ?>">
                                    <?php echo htmlspecialchars(formatNodeLabel([
                                        'node_name' => ($match['b']['room_code'] ? '['.$match['b']['room_code'].'] ' : '') . $match['b']['node_name'],
                                        'floor_name' => $match['b']['floor_name'],
                                        'wing' => $match['b']['wing']
                                    ])); ?>
                                </button>
                            </td>
                            <td><?php echo (int) $match['distance']; ?>px</td>
                            <td>
                                <form method="POST" class="inline-delete-form">
                                    <input type="hidden" name="quick_node_a" value="<?php echo (int) $match['a']['node_id']; ?>">
                                    <input type="hidden" name="quick_node_b" value="<?php echo (int) $match['b']['node_id']; ?>">
                                    <input type="hidden" name="quick_weight" value="25">
                                    <input type="hidden" name="current_floor" value="<?php echo (int) ($defaultFloor['floor_id'] ?? 0); ?>">
                                    <button type="submit" name="quick_link" class="edge-linker-btn">Confirm Link</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="edge-linker-table-wrap">
        <h2>Existing Edges</h2>
        <?php if ($edges): ?>
            <table class="edge-linker-table">
                <thead>
                    <tr>
                        <th>Node A</th>
                        <th>Node B</th>
                        <th>Weight</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edges as $edge): ?>
                        <tr>
                            <td>
                                <button type="button" class="node-locate-btn"
                                    data-floor-id="<?php echo (int) $edge['floor_a_id']; ?>"
                                    data-node-id="<?php echo (int) $edge['node_a_id']; ?>">
                                    <?php echo htmlspecialchars(formatNodeLabel([
                                        'node_name' => ($edge['node_a_code'] ? '['.$edge['node_a_code'].'] ' : '') . $edge['node_a_name'],
                                        'floor_name' => $edge['floor_a_name'],
                                        'wing' => $edge['wing_a']
                                    ])); ?>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="node-locate-btn"
                                    data-floor-id="<?php echo (int) $edge['floor_b_id']; ?>"
                                    data-node-id="<?php echo (int) $edge['node_b_id']; ?>">
                                    <?php echo htmlspecialchars(formatNodeLabel([
                                        'node_name' => ($edge['node_b_code'] ? '['.$edge['node_b_code'].'] ' : '') . $edge['node_b_name'],
                                        'floor_name' => $edge['floor_b_name'],
                                        'wing' => $edge['wing_b']
                                    ])); ?>
                                </button>
                            </td>
                            <td><?php echo htmlspecialchars((string) $edge['weight']); ?></td>
                            <td>
                                <form method="POST" class="inline-delete-form">
                                    <input type="hidden" name="edge_id" value="<?php echo (int) $edge['edge_id']; ?>">
                                    <input type="hidden" name="current_floor" value="<?php echo (int) ($defaultFloor['floor_id'] ?? 0); ?>">
                                    <button type="submit" name="delete_edge" class="edge-linker-delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="edge-linker-empty">No edges created yet.</p>
        <?php endif; ?>
    </div>
</section>

<script>
    const existingEdgePairs = <?php
        $pairs = array_map(function ($edge) {
            return [(int) $edge['node_a_id'], (int) $edge['node_b_id']];
        }, $edges);
        echo json_encode($pairs);
    ?>;

    const linkedNodeIds = new Set();
    existingEdgePairs.forEach(function (pair) {
        linkedNodeIds.add(String(pair[0]));
        linkedNodeIds.add(String(pair[1]));
    });
</script>
<script>
(function () {
    const floorSelect = document.getElementById('floorSelect');
    const floorImage = document.getElementById('floorImage');
    const overlayLayer = document.getElementById('overlayLayer');
    const selectedNodesSummary = document.getElementById('selectedNodesSummary');
    const edgeFormPanel = document.getElementById('edgeFormPanel');
    const selectedNodeA = document.getElementById('selectedNodeA');
    const selectedNodeB = document.getElementById('selectedNodeB');
    const nodeAInput = document.getElementById('node_a');
    const nodeBInput = document.getElementById('node_b');
    const nodeADropdown = document.getElementById('node_a_dropdown');
    const nodeBDropdown = document.getElementById('node_b_dropdown');

    let currentNodes = [];
    let selectedNodes = [];

    function getLocationLabel(node) {
        const parts = [];
        if (node.floor_name) {
            parts.push(node.floor_name);
        }
        if (node.wing) {
            parts.push(node.wing);
        }
        return parts.length ? parts.join(' - ') : 'Unknown floor';
    }

    function renderSelectedNodes() {
        if (!selectedNodesSummary) {
            return;
        }

        if (selectedNodes.length === 0) {
            selectedNodesSummary.innerHTML = '<p class="edge-linker-empty">No nodes selected yet.</p>';
            return;
        }

        selectedNodesSummary.innerHTML = '';
        selectedNodes.forEach(function (node) {
            const item = document.createElement('div');
            item.className = 'edge-linker-chip';
            var codeStr = node.room_code ? '[' + node.room_code + '] ' : '';
            item.textContent = codeStr + (node.node_name || 'Unnamed') + ' (' + getLocationLabel(node) + ')';
            selectedNodesSummary.appendChild(item);
        });
    }

    function isDuplicateEdge(idA, idB) {
        return existingEdgePairs.some(function (pair) {
            return (String(pair[0]) === String(idA) && String(pair[1]) === String(idB)) ||
                   (String(pair[0]) === String(idB) && String(pair[1]) === String(idA));
        });
    }

    function updateFormPanel() {
        if (!edgeFormPanel) {
            return;
        }

        const hasA = !!selectedNodes[0];
        const hasB = !!selectedNodes[1];
        edgeFormPanel.style.display = hasA && hasB ? 'block' : 'none';

        if (selectedNodeA) {
            selectedNodeA.textContent = hasA ? selectedNodes[0].node_name + ' (' + getLocationLabel(selectedNodes[0]) + ')' : 'Select a node';
        }

        if (selectedNodeB) {
            selectedNodeB.textContent = hasB ? selectedNodes[1].node_name + ' (' + getLocationLabel(selectedNodes[1]) + ')' : 'Select a node';
        }

        if (nodeAInput) {
            nodeAInput.value = hasA ? selectedNodes[0].node_id : '';
        }
        if (nodeBInput) {
            nodeBInput.value = hasB ? selectedNodes[1].node_id : '';
        }

        const weightInput = document.getElementById('weight');
        if (hasA && hasB && weightInput) {
            const nodeA = selectedNodes[0];
            const nodeB = selectedNodes[1];
            if (String(nodeA.floor_id) === String(nodeB.floor_id)) {
                const dx = nodeA.x_coord - nodeB.x_coord;
                const dy = nodeA.y_coord - nodeB.y_coord;
                const pixelDistance = Math.sqrt(dx * dx + dy * dy);
                // Scale factor: tweak the divisor if suggested weights feel
                // too high/low compared to how the routing "feels" in testing
                const suggestedWeight = Math.max(5, Math.round(pixelDistance / 20));
                weightInput.value = suggestedWeight;
            } else {
                // Different floors - no shared coordinate space, can't calculate
                weightInput.value = 25;
            }
        }

        const duplicateWarning = document.getElementById('duplicateWarning');
        const addEdgeBtn = document.getElementById('addEdgeBtn');
        if (hasA && hasB && isDuplicateEdge(selectedNodes[0].node_id, selectedNodes[1].node_id)) {
            if (duplicateWarning) duplicateWarning.style.display = 'block';
            if (addEdgeBtn) addEdgeBtn.disabled = true;
        } else {
            if (duplicateWarning) duplicateWarning.style.display = 'none';
            if (addEdgeBtn) addEdgeBtn.disabled = false;
        }
    }

    function renderOverlay() {
        if (!overlayLayer || !floorImage) {
            return;
        }

        overlayLayer.innerHTML = '';
        const rect = floorImage.getBoundingClientRect();
        const scaleX = floorImage.naturalWidth ? (floorImage.naturalWidth / rect.width) : 1;
        const scaleY = floorImage.naturalHeight ? (floorImage.naturalHeight / rect.height) : 1;
        let unlinkedCountThisFloor = 0;

        currentNodes.forEach(function (node) {
            if (String(node.floor_id) !== String(floorSelect.value)) {
                return;
            }

            const stairsOnlyToggle = document.getElementById('stairsOnlyToggle');
            const isStairsOrLift = node.node_type === 'stairs' || node.node_type === 'lift';
            if (stairsOnlyToggle && stairsOnlyToggle.checked && !isStairsOrLift) {
                return;
            }

            if (!linkedNodeIds.has(String(node.node_id))) {
                unlinkedCountThisFloor++;
            }

            const left = Math.round(node.x_coord / scaleX);
            const top = Math.round(node.y_coord / scaleY);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'edge-linker-dot' +
                (selectedNodes.some(function (selected) { return String(selected.node_id) === String(node.node_id); }) ? ' edge-linker-dot-selected' : '') +
                (!linkedNodeIds.has(String(node.node_id)) ? ' edge-linker-dot-unlinked' : '') +
                (isStairsOrLift ? ' edge-linker-dot-stairs' : '');
            button.setAttribute('data-node-id', node.node_id);
            button.style.left = left + 'px';
            button.style.top = top + 'px';
            button.title = node.node_name + ' — ' + (node.node_type || 'node');
            button.addEventListener('click', function () {
                const existingIndex = selectedNodes.findIndex(function (selected) {
                    return String(selected.node_id) === String(node.node_id);
                });

                if (existingIndex >= 0) {
                    selectedNodes.splice(existingIndex, 1);
                } else if (selectedNodes.length < 2) {
                    selectedNodes.push(node);
                } else {
                    selectedNodes = [selectedNodes[1], node];
                }

                renderSelectedNodes();
                updateFormPanel();
                renderOverlay();
            });
            overlayLayer.appendChild(button);
        });

        const unlinkedCountEl = document.getElementById('unlinkedCount');
        if (unlinkedCountEl) {
            unlinkedCountEl.textContent = unlinkedCountThisFloor > 0
                ? '(' + unlinkedCountThisFloor + ' unlinked on this wing)'
                : '(all nodes on this wing linked ✓)';
        }
    }

    let pendingHighlightNodeId = null;

    function highlightNode(nodeId) {
        if (!overlayLayer) {
            return;
        }
        const dots = overlayLayer.querySelectorAll('.edge-linker-dot');
        dots.forEach(function (dot) {
            dot.classList.remove('edge-linker-dot-highlight');
        });
        const target = overlayLayer.querySelector('[data-node-id="' + nodeId + '"]');
        if (target) {
            target.classList.add('edge-linker-dot-highlight');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    document.querySelectorAll('.node-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const row = document.getElementById(targetId);
            if (row) {
                row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
            }
        });
    });

    document.querySelectorAll('.node-locate-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetFloorId = btn.getAttribute('data-floor-id');
            const targetNodeId = btn.getAttribute('data-node-id');

            if (String(floorSelect.value) === String(targetFloorId)) {
                // Already on the right floor, just highlight immediately
                highlightNode(targetNodeId);
            } else {
                pendingHighlightNodeId = targetNodeId;
                floorSelect.value = targetFloorId;

                const currentFloorField = document.getElementById('currentFloorField');
                if (currentFloorField) {
                    currentFloorField.value = targetFloorId;
                }

                setActiveFloorButton();

                const selectedOption = floorSelect.options[floorSelect.selectedIndex];
                const mapPath = selectedOption.getAttribute('data-map');
                if (floorImage && mapPath) {
                    floorImage.src = mapPath;
                    floorImage.onload = function () {
                        renderOverlay();
                        loadNodesForFloor();
                    };
                }
            }

            // Scroll the map into view too, so the person can actually see it
            const mapWrap = document.querySelector('.edge-linker-map-wrap');
            if (mapWrap) {
                mapWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    function loadNodesForFloor() {
        fetch('edge_linker.php?ajax=1&floor_id=' + encodeURIComponent(floorSelect.value))
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                currentNodes = data.nodes || [];
                renderOverlay();
                if (pendingHighlightNodeId) {
                    setTimeout(function () {
                        highlightNode(pendingHighlightNodeId);
                        pendingHighlightNodeId = null;
                    }, 100);
                }
            });
    }

    floorSelect.addEventListener('change', function () {
        const selectedOption = floorSelect.options[floorSelect.selectedIndex];
        const mapPath = selectedOption.getAttribute('data-map');

        const currentFloorField = document.getElementById('currentFloorField');
        if (currentFloorField) {
            currentFloorField.value = floorSelect.value;
        }

        setActiveFloorButton();

        if (floorImage && mapPath) {
            floorImage.src = mapPath;
            floorImage.onload = function () {
                renderOverlay();
                loadNodesForFloor();
            };
        }
    });

    if (nodeADropdown) {
        nodeADropdown.addEventListener('change', function () {
            const selectedNode = currentNodes.find(function (candidate) {
                return String(candidate.node_id) === String(nodeADropdown.value);
            });
            if (selectedNode) {
                selectedNodes = [selectedNode].concat(selectedNodes.filter(function (candidate) {
                    return String(candidate.node_id) !== String(selectedNode.node_id);
                }));
                renderSelectedNodes();
                updateFormPanel();
                renderOverlay();
            }
        });
    }

    if (nodeBDropdown) {
        nodeBDropdown.addEventListener('change', function () {
            const selectedNode = currentNodes.find(function (candidate) {
                return String(candidate.node_id) === String(nodeBDropdown.value);
            });
            if (selectedNode) {
                selectedNodes = selectedNodes.filter(function (candidate) {
                    return String(candidate.node_id) !== String(selectedNode.node_id);
                });
                selectedNodes.push(selectedNode);
                renderSelectedNodes();
                updateFormPanel();
                renderOverlay();
            }
        });
    }

    function setActiveFloorButton() {
        document.querySelectorAll('.floor-picker-btn').forEach(function (btn) {
            btn.classList.toggle('floor-picker-btn-active', String(btn.getAttribute('data-floor-id')) === String(floorSelect.value));
        });
    }

    document.querySelectorAll('.floor-picker-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetFloorId = btn.getAttribute('data-floor-id');
            if (String(floorSelect.value) === String(targetFloorId)) {
                return;
            }
            floorSelect.value = targetFloorId;
            floorSelect.dispatchEvent(new Event('change'));
            setActiveFloorButton();
        });
    });

    setActiveFloorButton();

    const stairsOnlyToggleEl = document.getElementById('stairsOnlyToggle');
    if (stairsOnlyToggleEl) {
        // Restore saved state from before the page reloaded
        if (localStorage.getItem('edgeLinkerStairsOnly') === 'true') {
            stairsOnlyToggleEl.checked = true;
        }
        stairsOnlyToggleEl.addEventListener('change', function () {
            localStorage.setItem('edgeLinkerStairsOnly', stairsOnlyToggleEl.checked ? 'true' : 'false');
            renderOverlay();
        });
    }

    renderSelectedNodes();
    updateFormPanel();

    if (floorImage.complete && floorImage.naturalWidth > 0) {
        loadNodesForFloor();
    } else {
        floorImage.addEventListener('load', loadNodesForFloor, { once: true });
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>