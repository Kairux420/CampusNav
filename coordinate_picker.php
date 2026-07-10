<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}
require_once 'includes/header.php';

if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['floor_id'])) {
    $floorId = (int) $_GET['floor_id'];
    $stmt = $pdo->prepare("SELECT node_id, room_code, node_name, node_type, x_coord, y_coord, floor_id FROM nodes WHERE floor_id = ? ORDER BY node_name ASC");
    $stmt->execute([$floorId]);
    $nodes = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['nodes' => $nodes]);
    exit;
}

$stmt = $pdo->query("SELECT floor_id, floor_name, building, wing, map_image FROM floors ORDER BY floor_order, floor_name, wing");
$floors = $stmt->fetchAll();
$defaultFloor = $floors[0] ?? null;
$defaultMap = $defaultFloor && !empty($defaultFloor['map_image']) ? 'assets/maps/' . $defaultFloor['map_image'] : '';
?>

<section class="coord-picker-page">
    <h1>Coordinate Picker</h1>
    <p class="coord-picker-help">Select a floor map, click to capture pixel coordinates, and copy the SQL-ready rows when you're done.</p>

    <div class="coord-picker-controls">
        <label for="floorSelect">Floor / Wing</label>
        <select id="floorSelect" name="floor_id">
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
    </div>

    <div class="coord-picker-layout">
        <div class="coord-picker-map-wrap">
            <?php if ($defaultMap !== ''): ?>
                <div class="coord-picker-image-stage">
                    <img id="floorImage" src="<?php echo htmlspecialchars($defaultMap); ?>" alt="Floor plan" class="coord-picker-image">
                    <div id="overlayLayer" class="coord-picker-overlay-layer"></div>
                </div>
            <?php else: ?>
                <div class="coord-picker-empty">No map image found for this floor.</div>
            <?php endif; ?>
        </div>

        <div class="coord-picker-sidebar">
            <div class="coord-picker-panel">
                <div class="coord-picker-panel-head">
                    <h2>Captured Points</h2>
                    <button type="button" id="clearPointsBtn" class="coord-picker-link-btn">Clear</button>
                </div>
                <ul id="pointList" class="point-list">
                    <li class="empty-state">No points captured yet.</li>
                </ul>
            </div>

            <div class="coord-picker-panel">
                <button type="button" id="copySqlBtn" class="coord-picker-btn">Copy All as SQL</button>
                <textarea id="sqlOutput" rows="10" placeholder="SQL will appear here..."></textarea>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const floorSelect = document.getElementById('floorSelect');
    const floorImage = document.getElementById('floorImage');
    const overlayLayer = document.getElementById('overlayLayer');
    const pointList = document.getElementById('pointList');
    const sqlOutput = document.getElementById('sqlOutput');
    const copySqlBtn = document.getElementById('copySqlBtn');
    const clearPointsBtn = document.getElementById('clearPointsBtn');

    let points = [];
    let existingNodes = [];

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;');
    }

    function escapeSql(value) {
        return String(value).replace(/'/g, "\\'");
    }

    function renderPoints() {
        if (!pointList) {
            return;
        }

        pointList.innerHTML = '';

        if (points.length === 0) {
            pointList.innerHTML = '<li class="empty-state">No points captured yet.</li>';
            return;
        }

        points.forEach(function (point, index) {
            const item = document.createElement('li');
            item.className = 'point-item';

            const meta = document.createElement('div');
            meta.className = 'point-meta';
            meta.innerHTML = '<strong>' + escapeHtml(point.label || ('Point ' + (index + 1))) + '</strong><span>' + point.x + ', ' + point.y + '</span>';

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'point-label-input';
            input.value = point.label || ('Point ' + (index + 1));
            input.setAttribute('data-index', index);

            input.addEventListener('input', function () {
                const idx = parseInt(this.getAttribute('data-index'), 10);
                if (!Number.isNaN(idx)) {
                    points[idx].label = this.value;
                    buildSql();
                    const currentLabel = this.value || ('Point ' + (idx + 1));
                    const title = meta.querySelector('strong');
                    if (title) {
                        title.textContent = currentLabel;
                    }
                }
            });

            item.appendChild(meta);
            item.appendChild(input);
            pointList.appendChild(item);
        });
    }

    function buildSql() {
        const currentFloorId = floorSelect.value;
        const rows = points.filter(function (point) {
            return String(point.floorId) === String(currentFloorId);
        }).map(function (point) {
            const label = escapeSql(point.label || 'Untitled');
            return '(' + currentFloorId + ", '" + label + "', 'node_type_placeholder', " + point.x + ", " + point.y + ", '')";
        });

        if (rows.length === 0) {
            sqlOutput.value = '';
            return;
        }

        sqlOutput.value = 'INSERT INTO nodes (floor_id, node_name, node_type, x_coord, y_coord, description)\nVALUES\n' + rows.join(',\n') + ';';
    }

    function addPoint(event) {
        if (!floorImage) {
            return;
        }

        const rect = floorImage.getBoundingClientRect();
        const scaleX = floorImage.naturalWidth ? (floorImage.naturalWidth / rect.width) : 1;
        const scaleY = floorImage.naturalHeight ? (floorImage.naturalHeight / rect.height) : 1;
        const x = Math.round((event.clientX - rect.left) * scaleX);
        const y = Math.round((event.clientY - rect.top) * scaleY);
        const floorId = floorSelect.value;
        const floorLabel = floorSelect.options[floorSelect.selectedIndex].text;

        points.push({
            floorId: floorId,
            floorLabel: floorLabel,
            x: x,
            y: y,
            label: 'Point ' + (points.length + 1)
        });

        renderPoints();
        buildSql();
        renderOverlay();
    }

    if (floorImage) {
        floorImage.addEventListener('click', addPoint);
    }

    function renderOverlay() {
        if (!overlayLayer || !floorImage) {
            return;
        }

        overlayLayer.innerHTML = '';

        const rect = floorImage.getBoundingClientRect();
        const scaleX = floorImage.naturalWidth ? (floorImage.naturalWidth / rect.width) : 1;
        const scaleY = floorImage.naturalHeight ? (floorImage.naturalHeight / rect.height) : 1;

        existingNodes.forEach(function (node) {
            if (String(node.floor_id) !== String(floorSelect.value)) {
                return;
            }

            const left = Math.round(node.x_coord / scaleX);
            const top = Math.round(node.y_coord / scaleY);
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'coord-picker-dot coord-picker-dot-existing';
            dot.style.left = left + 'px';
            dot.style.top = top + 'px';
            var displayNodeName = (node.room_code ? '[' + node.room_code + '] ' : '') + (node.node_name || 'Unnamed');
            const tooltipText = displayNodeName + ' — ' + (node.node_type || 'node') + ' (' + node.x_coord + ', ' + node.y_coord + ')';
            dot.setAttribute('title', tooltipText);
            dot.setAttribute('aria-label', tooltipText);
            overlayLayer.appendChild(dot);
        });

        points.filter(function (point) {
            return String(point.floorId) === String(floorSelect.value);
        }).forEach(function (point) {
            const left = Math.round(point.x / scaleX);
            const top = Math.round(point.y / scaleY);
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'coord-picker-dot coord-picker-dot-new';
            dot.style.left = left + 'px';
            dot.style.top = top + 'px';
            dot.setAttribute('title', point.label || 'New point');
            dot.setAttribute('aria-label', point.label || 'New point');
            overlayLayer.appendChild(dot);
        });
    }

    function loadExistingNodes() {
        const floorId = floorSelect.value;
        fetch('coordinate_picker.php?floor_id=' + encodeURIComponent(floorId) + '&ajax=1')
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                existingNodes = data.nodes || [];
                renderOverlay();
            })
            .catch(function () {
                existingNodes = [];
                renderOverlay();
            });
    }

    floorSelect.addEventListener('change', function () {
        const selectedOption = floorSelect.options[floorSelect.selectedIndex];
        const mapPath = selectedOption.getAttribute('data-map');
        if (floorImage && mapPath) {
            floorImage.src = mapPath;
            floorImage.onload = function () {
                renderOverlay();
                loadExistingNodes();
            };
        }
        buildSql();
    });

    clearPointsBtn.addEventListener('click', function () {
        points = [];
        renderPoints();
        buildSql();
        renderOverlay();
    });

    copySqlBtn.addEventListener('click', function () {
        buildSql();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(sqlOutput.value).then(function () {
                copySqlBtn.textContent = 'Copied!';
                setTimeout(function () { copySqlBtn.textContent = 'Copy All as SQL'; }, 1200);
            });
        }
    });

    renderPoints();
    buildSql();
    loadExistingNodes();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
