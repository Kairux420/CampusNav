<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_node') {
        $node_id = (int)$_POST['node_id'];
        $room_code = !empty($_POST['room_code']) ? $_POST['room_code'] : null;
        $node_name = !empty($_POST['node_name']) ? $_POST['node_name'] : null;
        $node_type = $_POST['node_type'];
        $category = !empty($_POST['category']) ? $_POST['category'] : null;
        
        $stmt = $pdo->prepare("UPDATE nodes SET room_code = ?, node_name = ?, node_type = ?, category = ? WHERE node_id = ?");
        $stmt->execute([$room_code, $node_name, $node_type, $category, $node_id]);
        
        $qs = [];
        if (!empty($_POST['wing'])) $qs[] = 'wing=' . urlencode($_POST['wing']);
        if (!empty($_POST['floor_name'])) $qs[] = 'floor_name=' . urlencode($_POST['floor_name']);
        
        header("Location: map_editor.php" . (!empty($qs) ? '?' . implode('&', $qs) : ''));
        exit;
    }

    if ($_POST['action'] === 'update_pos') {
        $node_id = (int)$_POST['node_id'];
        $x = (int)$_POST['x'];
        $y = (int)$_POST['y'];
        $pdo->prepare("UPDATE nodes SET x_coord = ?, y_coord = ? WHERE node_id = ?")->execute([$x, $y, $node_id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM floors ORDER BY building, floor_order");
$allFloors = $stmt->fetchAll();

$wings = array_unique(array_column($allFloors, 'wing'));
$floorNames = array_unique(array_column($allFloors, 'floor_name'));

$selectedWing = $_GET['wing'] ?? null;
$selectedFloorName = $_GET['floor_name'] ?? null;
$defaultFloor = null;

if ($selectedWing && $selectedFloorName) {
    foreach ($allFloors as $f) {
        if ($f['wing'] === $selectedWing && $f['floor_name'] === $selectedFloorName) {
            $defaultFloor = $f;
            break;
        }
    }
}
if (!$defaultFloor && $selectedWing) {
    foreach ($allFloors as $f) {
        if ($f['wing'] === $selectedWing) {
            $defaultFloor = $f;
            $selectedFloorName = $f['floor_name'];
            break;
        }
    }
}
if (!$defaultFloor && $selectedFloorName) {
    foreach ($allFloors as $f) {
        if ($f['floor_name'] === $selectedFloorName) {
            $defaultFloor = $f;
            $selectedWing = $f['wing'];
            break;
        }
    }
}
if (!$defaultFloor && count($allFloors) > 0) {
    foreach ($allFloors as $f) {
        if (stripos($f['floor_name'], 'Ground') !== false || $f['floor_order'] == 1) {
            $defaultFloor = $f;
            break;
        }
    }
    if (!$defaultFloor) {
        $defaultFloor = $allFloors[0];
    }
    $selectedWing = $defaultFloor['wing'];
    $selectedFloorName = $defaultFloor['floor_name'];
}

$loadLeafletCss = true;
require_once '../includes/header.php';
?>

<section class="admin-dashboard">
    <div class="dashboard-header" style="margin-bottom: 20px;">
        <h1 style="display:flex; align-items:center; gap:10px;">
            <a href="index.php" style="color:var(--text-muted); text-decoration:none;">&larr; Admin Hub</a> / Map Editor
        </h1>
        <p>Drag and drop nodes to adjust their precise locations, or click a node to edit its details.</p>
    </div>

    <?php if ($defaultFloor): ?>
        <?php
        $stmt = $pdo->prepare("SELECT * FROM nodes WHERE floor_id = ?");
        $stmt->execute([$defaultFloor['floor_id']]);
        $floorNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapImagePath = __DIR__ . '/../assets/maps/' . $defaultFloor['map_image'];
        $mapWidth = 1790;
        $mapHeight = 1039;
        if (file_exists($mapImagePath)) {
            $size = @getimagesize($mapImagePath);
            if ($size) {
                $mapWidth = $size[0];
                $mapHeight = $size[1];
            }
        }
        ?>
        <div class="home-map-container" style="background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: var(--shadow-sm);">
            
            <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <h3 style="font-size: 1.1rem; color: var(--text-main); margin: 0;">Interactive Map Editor</h3>
                <form method="GET" action="map_editor.php" style="margin: 0; display: flex; gap: 10px;">
                    <select name="wing" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-family: inherit; font-size: 0.9rem; cursor: pointer;">
                        <?php foreach ($wings as $w): ?>
                            <option value="<?php echo htmlspecialchars($w); ?>" <?php echo $w === $selectedWing ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($w); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="floor_name" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-family: inherit; font-size: 0.9rem; cursor: pointer;">
                        <?php foreach ($floorNames as $fn): ?>
                            <option value="<?php echo htmlspecialchars($fn); ?>" <?php echo $fn === $selectedFloorName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fn); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div id="home-map" style="width: 100%; height: 60vh; min-height: 400px; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); z-index: 1;"></div>
        </div>

        <script src="/assets/vendor/leaflet/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var map = L.map('home-map', {
                    crs: L.CRS.Simple,
                    minZoom: -4,
                    maxZoom: 2,
                    zoomControl: false,
                    attributionControl: false
                });

                var imageUrl = '/assets/maps/<?php echo htmlspecialchars($defaultFloor['map_image']); ?>';
                var imgW = <?php echo $mapWidth; ?>;
                var imgH = <?php echo $mapHeight; ?>;
                var imageBounds = [[0, 0], [imgH, imgW]]; 

                L.imageOverlay(imageUrl, imageBounds).addTo(map);
                
                var nodes = <?php echo json_encode($floorNodes); ?>;
                nodes.forEach(function(node) {
                    var x = parseInt(node.x_coord);
                    var y = parseInt(node.y_coord);
                    var latLng = [imgH - y, x];

                    var markerColor = '#2563eb'; 
                    if (node.node_type === 'stairs' || node.node_type === 'lift') {
                        markerColor = '#f59e0b';
                    } else if (node.node_type === 'room') {
                        var cat = (node.category || '').toLowerCase();
                        if (cat === 'lecturer office' || cat === 'office / admin') markerColor = '#3b82f6';
                        else if (cat === 'computer lab') markerColor = '#06b6d4';
                        else if (cat === 'classroom / tutorial') markerColor = '#10b981';
                        else if (cat === 'meeting room') markerColor = '#84cc16';
                        else if (cat === 'restroom' || cat === 'surau') markerColor = '#8b5cf6';
                        else if (cat === 'cafeteria') markerColor = '#ef4444';
                        else if (cat === 'storage') markerColor = '#64748b';
                        else markerColor = '#10b981';
                    }
                    
                    var myIcon = L.divIcon({
                        html: `<div style="width: 14px; height: 14px; background: ${markerColor}; border: 2px solid #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>`,
                        className: '',
                        iconSize: [14, 14],
                        iconAnchor: [7, 7]
                    });

                    var marker = L.marker(latLng, {
                        icon: myIcon,
                        draggable: true
                    }).addTo(map);

                    marker.on('dragend', function(e) {
                        var newPos = e.target.getLatLng();
                        var newX = Math.round(newPos.lng);
                        var newY = Math.round(imgH - newPos.lat);
                        
                        var formData = new URLSearchParams();
                        formData.append('action', 'update_pos');
                        formData.append('node_id', node.node_id);
                        formData.append('x', newX);
                        formData.append('y', newY);

                        fetch('map_editor.php', {
                            method: 'POST',
                            body: formData
                        });
                    });

                    var labelParts = [];
                    if (node.room_code) labelParts.push('[' + node.room_code + ']');
                    if (node.node_name) labelParts.push(node.node_name);
                    var displayTitle = labelParts.length > 0 ? labelParts.join(' ') : 'Unnamed';
                    
                    marker.bindTooltip('<strong>' + displayTitle + '</strong><br>' + (node.node_type || 'Unknown'), {
                        direction: 'top',
                        offset: [0, -6],
                        opacity: 0.9
                    });

                    marker.on('click', function() {
                        document.getElementById('editNodeId').value = node.node_id;
                        document.getElementById('editRoomCode').value = node.room_code || '';
                        document.getElementById('editNodeName').value = node.node_name || '';
                        document.getElementById('editNodeType').value = node.node_type || 'room';
                        document.getElementById('editNodeCategory').value = node.category || '';
                        
                        document.getElementById('editNodeModal').style.display = 'flex';
                    });
                });
                
                // Custom Zoom Slider
                var ZoomSlider = L.Control.extend({
                    options: { position: 'topleft' },
                    onAdd: function(map) {
                        var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                        container.style.background = 'var(--bg-card)';
                        container.style.padding = '15px 5px';
                        container.style.display = 'flex';
                        container.style.flexDirection = 'column';
                        container.style.alignItems = 'center';
                        container.style.gap = '10px';
                        container.style.border = '1px solid var(--border-color)';
                        container.style.boxShadow = 'var(--shadow-sm)';
                        container.style.borderRadius = '8px';

                        var plus = L.DomUtil.create('div', '', container);
                        plus.innerHTML = '+';
                        plus.style.fontWeight = 'bold';
                        plus.style.color = 'var(--text-main)';

                        var wrapper = L.DomUtil.create('div', '', container);
                        wrapper.style.height = '120px';
                        wrapper.style.display = 'flex';
                        wrapper.style.alignItems = 'center';

                        var slider = L.DomUtil.create('input', '', wrapper);
                        slider.type = 'range';
                        slider.min = map.getMinZoom();
                        slider.max = map.getMaxZoom();
                        slider.step = 0.1;
                        slider.value = map.getZoom();
                        slider.style.appearance = 'slider-vertical';
                        slider.style.width = '8px';
                        slider.style.height = '100%';
                        slider.style.writingMode = 'bt-lr';
                        slider.orient = 'vertical';
                        
                        var minus = L.DomUtil.create('div', '', container);
                        minus.innerHTML = '−';
                        minus.style.fontWeight = 'bold';
                        minus.style.color = 'var(--text-main)';

                        L.DomEvent.disableClickPropagation(container);
                        L.DomEvent.disableScrollPropagation(container);

                        slider.addEventListener('input', function(e) {
                            map.setZoom(e.target.value, {animate: false});
                        });
                        
                        map.on('zoom', function() {
                            slider.value = map.getZoom();
                        });

                        return container;
                    }
                });
                map.addControl(new ZoomSlider());

                map.setView([imgH / 2, imgW / 2], 0);

                const resizeObserver = new ResizeObserver(function() {
                    map.invalidateSize();
                });
                resizeObserver.observe(document.getElementById('home-map'));
            });
        </script>
    <?php endif; ?>
</section>

<!-- Edit Node Modal -->
<div id="editNodeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); padding: 30px; border-radius: 16px; width: 420px; max-width: 90%; border: 1px solid var(--border-color); box-shadow: 0 15px 35px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; margin-bottom: 20px; color: var(--text-main); font-size: 1.5rem;">Edit Node</h2>
        <form method="POST" action="map_editor.php">
            <input type="hidden" name="action" value="edit_node">
            <input type="hidden" name="node_id" id="editNodeId">
            <input type="hidden" name="wing" value="<?php echo htmlspecialchars($selectedWing ?? ''); ?>">
            <input type="hidden" name="floor_name" value="<?php echo htmlspecialchars($selectedFloorName ?? ''); ?>">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted); font-size: 0.9rem;">Room Code (Optional)</label>
                <input type="text" name="room_code" id="editRoomCode" placeholder="e.g. MK-204" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 1rem; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted); font-size: 0.9rem;">Node/Room Name</label>
                <input type="text" name="node_name" id="editNodeName" placeholder="e.g. Chemistry Lab" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 1rem; box-sizing: border-box;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted); font-size: 0.9rem;">Type</label>
                <select name="node_type" id="editNodeType" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 1rem; box-sizing: border-box; cursor: pointer;">
                    <option value="room">Room / Lab</option>
                    <option value="stairs">Stairs</option>
                    <option value="lift">Lift</option>
                    <option value="junction">Junction</option>
                    <option value="entrance">Entrance</option>
                </select>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted); font-size: 0.9rem;">Category (Optional)</label>
                <input type="text" name="category" id="editNodeCategory" placeholder="e.g. restroom, surau" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 1rem; box-sizing: border-box;">
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('editNodeModal').style.display = 'none'" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-main); padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.95rem; transition: background 0.2s;">Cancel</button>
                <button type="submit" class="btn-primary" style="padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 0.95rem;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
