<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/pathfinder.php';

$loadLeafletCss = false;

// Removed campusnavExtractRoomCode as it's no longer needed with the database room_code column.

function campusnavWingClass(?string $wing): string
{
    $wing = strtolower(trim((string) $wing));
    if (strpos($wing, 'left') !== false) {
        return 'wing-tag--left';
    }
    if (strpos($wing, 'central') !== false) {
        return 'wing-tag--central';
    }
    if (strpos($wing, 'right') !== false) {
        return 'wing-tag--right';
    }
    return 'wing-tag--default';
}

$toId = isset($_GET['to']) ? (int) $_GET['to'] : 0;
$fromId = isset($_GET['from']) ? (int) $_GET['from'] : 0;

$destination = null;
$pathResult = null;
$pathSteps = [];
$errorMessage = null;

if ($toId > 0) {
    $stmt = $pdo->prepare(
        "SELECT n.node_id, n.room_code, n.node_name, n.description, n.x_coord, n.y_coord, f.floor_name, f.wing " .
        "FROM nodes n " .
        "LEFT JOIN floors f ON f.floor_id = n.floor_id " .
        "WHERE n.node_id = ?"
    );
    $stmt->execute([$toId]);
    $destination = $stmt->fetch();
}

// Search-based "from" picker: user types a room name/description, we show
// matching rooms as clickable results (mirrors search.php's query logic).
$routeSegmentsJson = null;
$routeStepItems = [];
$fromCandidates = [];
$fromQuery = trim($_GET['from_query'] ?? '');

if ($toId > 0 && $fromId === 0 && $fromQuery !== '') {
    // Log the search query
    $userId = $_SESSION['user_id'] ?? null;
    $logStmt = $pdo->prepare("INSERT INTO search_logs (query, user_id) VALUES (?, ?)");
    $logStmt->execute([$fromQuery, $userId]);

    $cleanQuery = str_replace(['-', ' '], '', $fromQuery);
    $likeQuery = '%' . $cleanQuery . '%';

    $stmt = $pdo->prepare(
        "SELECT n.node_id, n.room_code, n.node_name, n.description, n.category, f.floor_name, f.wing " .
        "FROM nodes n " .
        "LEFT JOIN floors f ON f.floor_id = n.floor_id " .
        "WHERE n.node_type = 'room' AND (" .
        "REPLACE(REPLACE(n.room_code, '-', ''), ' ', '') LIKE ? OR " .
        "REPLACE(REPLACE(n.node_name, '-', ''), ' ', '') LIKE ? OR " .
        "n.category LIKE ? OR n.category LIKE ? OR " .
        "n.description LIKE ?) " .
        "ORDER BY n.node_name ASC LIMIT 20"
    );
    $stmt->execute([
        $likeQuery, 
        $likeQuery, 
        $fromQuery . '%', 
        '% ' . $fromQuery . '%', 
        '%' . $fromQuery . '%'
    ]);
    $fromCandidates = $stmt->fetchAll();
}

if ($fromId > 0 && $toId > 0) {
    $pathResult = findShortestPath($pdo, $fromId, $toId);
    if ($pathResult === null) {
        $errorMessage = "No route could be found between those two points. " .
            "This can happen if a node isn't linked into the graph yet — check edge_linker.php.";
    } else {
        $pathSteps = describePath($pathResult['path']);

        $lastFloor = null;
        foreach ($pathResult['path'] as $index => $node) {
            $floorChanged = $lastFloor !== null && (int) $node['floor_id'] !== (int) $lastFloor;
            $kind = 'continue';
            $title = 'Continue to';

            if ($index === 0) {
                $kind = 'start';
                $title = 'Start at';
            } elseif ($floorChanged) {
                $kind = 'transition';
                $title = 'Take ' . htmlspecialchars($node['node_type']);
            }

            $routeStepItems[] = [
                'kind' => $kind,
                'title' => $title,
                'nodeName' => $node['node_name'],
                'roomCode' => $node['room_code'],
                'floorName' => $node['floor_name'] ?? 'Unknown',
                'wing' => $node['wing'] ?? '',
                'wingClass' => campusnavWingClass($node['wing'] ?? ''),
            ];

            $lastFloor = $node['floor_id'];
        }

        // Group the path into per-floor segments (path can cross floors via
        // stairs/lifts, so each floor gets its own map image + point list).
        $segments = [];
        $currentFloorId = null;
        $mapsDir = __DIR__ . '/assets/maps/';

        foreach ($pathResult['path'] as $node) {
            if ($node['floor_id'] !== $currentFloorId) {
                $imagePath = $mapsDir . $node['map_image'];
                $dimensions = @getimagesize($imagePath);

                $segments[] = [
                    'floorLabel' => $node['floor_name'] . ' (' . $node['wing'] . ')',
                    'imageUrl' => '/assets/maps/' . $node['map_image'],
                    'imageWidth' => $dimensions ? $dimensions[0] : 0,
                    'imageHeight' => $dimensions ? $dimensions[1] : 0,
                    'points' => [],
                ];
                $currentFloorId = $node['floor_id'];
            }

            $lastIndex = count($segments) - 1;
            $roomCode = $node['room_code'] ? '[' . $node['room_code'] . '] ' : '';
            $segments[$lastIndex]['points'][] = [
                'nodeId' => (int) $node['node_id'],
                'name' => $roomCode . ($node['node_name'] ?: 'Unnamed'),
                'type' => $node['node_type'],
                'x' => (float) $node['x_coord'],
                'y' => (float) $node['y_coord'],
            ];
        }

        $routeSegmentsJson = json_encode(['segments' => $segments], JSON_UNESCAPED_SLASHES);
        $loadLeafletCss = true;
    }
}

require_once 'includes/header.php';
?>

<section class="navigate-page">
    <h1>Navigation</h1>

    <?php if (!$toId): ?>
        <p class="empty-state">Select a room to start navigation.</p>

    <?php elseif (!$destination): ?>
        <p class="empty-state">That room couldn't be found.</p>

    <?php else: ?>
        <div class="room-card">
            <div class="room-card__header">
                <div class="room-card__title-block">
                    <h2 class="room-card__name">
                        <?php if (!empty($destination['room_code'])): ?>
                            <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; margin-right: 6px;"><?php echo htmlspecialchars($destination['room_code']); ?></span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($destination['node_name'] ?: 'Unnamed Room'); ?>
                    </h2>
                    <?php $roomCode = $destination['room_code']; ?>
                    <?php if ($roomCode): ?>
                        <span class="room-code-badge <?php echo campusnavWingClass($destination['wing'] ?? ''); ?>"><?php echo htmlspecialchars($roomCode); ?></span>
                    <?php endif; ?>
                </div>
                <div class="room-card__meta">
                    <span class="signage-tag signage-tag--floor">Floor <?php echo htmlspecialchars($destination['floor_name'] ?? 'Unknown'); ?></span>
                    <span class="signage-tag <?php echo campusnavWingClass($destination['wing'] ?? ''); ?>"><?php echo htmlspecialchars($destination['wing'] ?? 'Unknown Wing'); ?></span>
                </div>
            </div>
            <p class="room-card__description"><?php echo htmlspecialchars($destination['description'] ?? 'No description available.'); ?></p>
        </div>

        <?php if (!$fromId): ?>
            <div class="from-picker">
                <form method="get" action="navigate.php" class="search-form">
                    <input type="hidden" name="to" value="<?php echo (int) $toId; ?>">
                    <input type="text" name="from_query" value="<?php echo htmlspecialchars($fromQuery); ?>" placeholder="Where are you starting from? (e.g. F-G-28)" autofocus>
                    <button type="submit">Search</button>
                </form>

                <?php if ($fromQuery !== '' && empty($fromCandidates)): ?>
                    <p class="empty-state">No rooms matched "<?php echo htmlspecialchars($fromQuery); ?>". Try a different room name.</p>

                <?php elseif (!empty($fromCandidates)): ?>
                    <ul class="room-list">
                        <?php foreach ($fromCandidates as $room): ?>
                            <?php $candidateCode = $room['room_code']; ?>
                            <li class="room-card">
                                <div class="room-card__header">
                                    <div class="room-card__title-block">
                                        <h3 class="room-card__name">
                                            <?php if (!empty($room['room_code'])): ?>
                                                <span style="background: var(--accent-primary); color: var(--text-main); padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; margin-right: 6px;"><?php echo htmlspecialchars($room['room_code']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($room['category'])): ?>
                                                <span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-right: 6px; font-weight: bold;"><?php echo htmlspecialchars($room['category']); ?></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($room['node_name'] ?: 'Unnamed Room'); ?>
                                        </h3>
                                        <?php if ($candidateCode): ?>
                                            <span class="room-code-badge room-code-badge--compact <?php echo campusnavWingClass($room['wing'] ?? ''); ?>"><?php echo htmlspecialchars($candidateCode); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="signage-tag signage-tag--floor"><?php echo htmlspecialchars($room['floor_name'] ?? 'Unknown floor'); ?></span>
                                </div>
                                <a href="navigate.php?to=<?php echo (int) $toId; ?>&from=<?php echo (int) $room['node_id']; ?>" class="cta-btn">Start route from here</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        <?php elseif ($errorMessage): ?>
            <p class="empty-state"><?php echo htmlspecialchars($errorMessage); ?></p>

        <?php else: ?>
            <div class="route-summary">
                <div class="route-summary__header">
                    <div>
                        <p class="route-summary__eyebrow">Wayfinding directory</p>
                        <h3>Route (<?php echo round($pathResult['distance'], 1); ?> units)</h3>
                    </div>
                    <span class="signage-tag signage-tag--accent">Live route</span>
                </div>
                <p><a href="navigate.php?to=<?php echo (int) $toId; ?>">Change starting point</a></p>

                <div class="route-map-controls">
                    <button type="button" id="route-prev-floor" class="route-map-control-btn">&larr; Previous Floor</button>
                    <span id="route-floor-label" class="route-map-floor-label"></span>
                    <button type="button" id="route-next-floor" class="route-map-control-btn">Next Floor &rarr;</button>
                </div>
                <div id="route-map"></div>

                <details class="route-steps-detail">
                    <summary>Step-by-step text directions</summary>
                    <ol class="route-steps-list">
                        <?php foreach ($routeStepItems as $index => $step): ?>
                            <li class="route-step route-step--<?php echo htmlspecialchars($step['kind']); ?>">
                                <span class="route-step__marker">
                                    <?php if ($step['kind'] === 'transition'): ?>
                                        ⇅
                                    <?php else: ?>
                                        <?php echo (int) $index + 1; ?>
                                    <?php endif; ?>
                                </span>
                                <div class="route-step__content">
                                    <div class="route-step__title">
                                        <?php echo htmlspecialchars($step['title'] . ' ' . $step['nodeName']); ?>
                                    </div>
                                    <div class="route-step__meta">
                                        <?php if ($step['roomCode']): ?>
                                            <span class="room-code-badge room-code-badge--compact <?php echo $step['wingClass']; ?>"><?php echo htmlspecialchars($step['roomCode']); ?></span>
                                        <?php endif; ?>
                                        <span class="signage-tag signage-tag--floor">Floor <?php echo htmlspecialchars($step['floorName']); ?></span>
                                        <span class="signage-tag <?php echo $step['wingClass']; ?>"><?php echo htmlspecialchars($step['wing'] ?: 'Unknown Wing'); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </details>

                <script src="/assets/vendor/leaflet/leaflet.js"></script>
                <script>
                    const routeData = <?php echo $routeSegmentsJson; ?>;
                </script>
                <script src="/assets/js/map-viewer.js"></script>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
