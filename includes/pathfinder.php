<?php
/**
 * pathfinder.php
 *
 * Dijkstra shortest-path logic for CampusNav.
 * Expects $pdo (PDO connection) to already be available — include this
 * AFTER require_once 'config/db.php'.
 *
 * Usage:
 *   require_once 'includes/pathfinder.php';
 *   $result = findShortestPath($pdo, $fromNodeId, $toNodeId);
 *   if ($result === null) {
 *       // no path exists between these two nodes
 *   } else {
 *       $result['path'];     // ordered array of node rows (start -> end)
 *       $result['distance']; // total weight of the path
 *   }
 */

/**
 * Loads the whole graph (nodes + edges) from MySQL into memory once.
 * Returns [
 *   'nodes' => [node_id => node_row, ...],
 *   'adjacency' => [node_id => [ [neighbor_id, weight], ... ], ...]
 * ]
 */
function loadGraph(PDO $pdo): array
{
    $nodes = [];
    $stmt = $pdo->query(
        "SELECT n.node_id, n.room_code, n.node_name, n.node_type, n.floor_id, n.x_coord, n.y_coord, " .
        "n.description, n.category, f.floor_name, f.wing, f.map_image " .
        "FROM nodes n " .
        "LEFT JOIN floors f ON f.floor_id = n.floor_id"
    );
    foreach ($stmt->fetchAll() as $row) {
        $nodes[(int) $row['node_id']] = $row;
    }

    $adjacency = [];
    foreach (array_keys($nodes) as $nodeId) {
        $adjacency[$nodeId] = [];
    }

    $edgeStmt = $pdo->query("SELECT node_a, node_b, weight FROM edges");
    foreach ($edgeStmt->fetchAll() as $edge) {
        $a = (int) $edge['node_a'];
        $b = (int) $edge['node_b'];
        $w = (float) $edge['weight'];

        // edges are undirected — add both directions
        $adjacency[$a][] = [$b, $w];
        $adjacency[$b][] = [$a, $w];
    }

    return ['nodes' => $nodes, 'adjacency' => $adjacency];
}

/**
 * Runs Dijkstra's algorithm over the graph from $fromId to $toId.
 * Returns null if either node doesn't exist or no path connects them.
 * Otherwise returns ['path' => [node_row, ...], 'distance' => float].
 */
function findShortestPath(PDO $pdo, int $fromId, int $toId): ?array
{
    $graph = loadGraph($pdo);
    $nodes = $graph['nodes'];
    $adjacency = $graph['adjacency'];

    if (!isset($nodes[$fromId]) || !isset($nodes[$toId])) {
        return null;
    }

    if ($fromId === $toId) {
        return ['path' => [$nodes[$fromId]], 'distance' => 0.0];
    }

    // distances start at infinity for everyone except the start node
    $dist = [];
    $prev = [];
    foreach (array_keys($nodes) as $nodeId) {
        $dist[$nodeId] = INF;
    }
    $dist[$fromId] = 0.0;

    // simple min-priority queue using SplPriorityQueue
    // (SplPriorityQueue is a max-heap, so we negate distances)
    $queue = new SplPriorityQueue();
    $queue->insert($fromId, 0);
    $visited = [];

    while (!$queue->isEmpty()) {
        $current = $queue->extract();

        if (isset($visited[$current])) {
            continue; // already finalized, skip stale queue entry
        }
        $visited[$current] = true;

        if ($current === $toId) {
            break; // shortest path to target found
        }

        foreach ($adjacency[$current] as [$neighbor, $weight]) {
            if (isset($visited[$neighbor])) {
                continue;
            }
            $newDist = $dist[$current] + $weight;
            if ($newDist < $dist[$neighbor]) {
                $dist[$neighbor] = $newDist;
                $prev[$neighbor] = $current;
                $queue->insert($neighbor, -$newDist); // negate: max-heap acts as min-heap
            }
        }
    }

    if ($dist[$toId] === INF) {
        return null; // no path exists (graph disconnected between these nodes)
    }

    // walk back through $prev to reconstruct the path, then reverse it
    $pathIds = [$toId];
    $step = $toId;
    while ($step !== $fromId) {
        $step = $prev[$step];
        $pathIds[] = $step;
    }
    $pathIds = array_reverse($pathIds);

    $path = array_map(fn($id) => $nodes[$id], $pathIds);

    return ['path' => $path, 'distance' => $dist[$toId]];
}

/**
 * Turns a path (array of node rows) into a simple list of human-readable
 * step descriptions, calling out floor changes at stairs/lift nodes.
 * Useful for a plain-text route summary before the Leaflet map view exists.
 */
function describePath(array $path): array
{
    $steps = [];
    $lastFloor = null;

    foreach ($path as $i => $node) {
        $displayName = (!empty($node['room_code']) ? '[' . $node['room_code'] . '] ' : '') . ($node['node_name'] ?: 'Unnamed');
        if ($i === 0) {
            $steps[] = "Start at " . $displayName . " (" . $node['floor_name'] . ")";
        } elseif ($node['floor_id'] !== $path[$i - 1]['floor_id']) {
            $steps[] = "Take " . $node['node_type'] . " to " . $displayName .
                " on " . $node['floor_name'];
        } else {
            $steps[] = "Continue to " . $displayName;
        }
        $lastFloor = $node['floor_id'];
    }

    return $steps;
}
