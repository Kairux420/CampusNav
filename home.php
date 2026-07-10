<?php
$loadLeafletCss = true; // Tell header to load leaflet.css
require_once 'includes/auth_check.php';
require_once 'config/db.php';



$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Fetch all floors
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
    // Fallback to Ground Floor or first floor
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

require_once 'includes/header.php';
?>

<section class="home-dashboard">
    <div class="dashboard-header">
        <h1><em>CampusNav</em></h1>
        <p>Navigate your campus with ease</p>
    </div>

    <div class="search-card">
        <form action="search.php" method="GET" class="search-form-main">
            <div class="search-input-group">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" placeholder="Search for a room, lab, or lecturer (e.g., MK-204)" required>
            </div>
            <button type="submit" class="btn-primary">Plan Your Route</button>
        </form>
    </div>

<?php
    $mapImagePath = __DIR__ . '/assets/maps/' . $defaultFloor['map_image'];
    $mapWidth = 1790;
    $mapHeight = 1039;
    if (file_exists($mapImagePath)) {
        $size = @getimagesize($mapImagePath);
        if ($size) {
            $mapWidth = $size[0];
            $mapHeight = $size[1];
        }
    }
    
    // Fetch nodes for this floor
    $stmt = $pdo->prepare("SELECT * FROM nodes WHERE floor_id = ?");
    $stmt->execute([$defaultFloor['floor_id']]);
    $floorNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <?php if ($defaultFloor): ?>
        <div class="home-map-container" style="background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: var(--shadow-sm);">
            
            <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <h3 style="font-size: 1.1rem; color: var(--text-main); margin: 0;">Campus Map Preview</h3>
                <form method="GET" action="home.php" style="margin: 0; display: flex; gap: 10px;">
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

        <script src="/campusnav/assets/vendor/leaflet/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var map = L.map('home-map', {
                    crs: L.CRS.Simple,
                    minZoom: -4,
                    maxZoom: 2,
                    maxZoom: 2,
                    zoomControl: false, // We will use our custom slider instead
                    attributionControl: false
                });

                var imageUrl = '/campusnav/assets/maps/<?php echo htmlspecialchars($defaultFloor['map_image']); ?>';
                var imgW = <?php echo $mapWidth; ?>;
                var imgH = <?php echo $mapHeight; ?>;
                var imageBounds = [[0, 0], [imgH, imgW]]; 

                L.imageOverlay(imageUrl, imageBounds).addTo(map);
                
                var nodes = <?php echo json_encode($floorNodes); ?>;
                nodes.forEach(function(node) {
                    var x = parseInt(node.x_coord);
                    var y = parseInt(node.y_coord);
                    var latLng = [imgH - y, x];

                    var markerColor = '#2563eb'; // Default blue (Junctions, Entrances)
                    
                    if (node.node_type === 'stairs' || node.node_type === 'lift') {
                        markerColor = '#f59e0b'; // Orange
                    } else if (node.node_type === 'room') {
                        var cat = (node.category || '').toLowerCase();
                        if (cat === 'lecturer office' || cat === 'office / admin') markerColor = '#3b82f6'; // Light Blue
                        else if (cat === 'computer lab') markerColor = '#06b6d4'; // Cyan
                        else if (cat === 'classroom / tutorial') markerColor = '#10b981'; // Emerald Green
                        else if (cat === 'meeting room') markerColor = '#84cc16'; // Lime Green
                        else if (cat === 'restroom' || cat === 'surau') markerColor = '#8b5cf6'; // Purple
                        else if (cat === 'cafeteria') markerColor = '#ef4444'; // Red
                        else if (cat === 'storage') markerColor = '#64748b'; // Slate Gray
                        else markerColor = '#10b981'; // Default Green for generic rooms
                    }
                    
                    var marker = L.circleMarker(latLng, {
                        radius: 6,
                        color: '#ffffff',
                        weight: 2,
                        fillColor: markerColor,
                        fillOpacity: 1
                    }).addTo(map);

                    var labelParts = [];
                    if (node.room_code) labelParts.push('[' + node.room_code + ']');
                    if (node.node_name) labelParts.push(node.node_name);
                    var displayTitle = labelParts.length > 0 ? labelParts.join(' ') : 'Unnamed';
                    
                    marker.bindTooltip('<strong>' + displayTitle + '</strong><br>' + (node.node_type || 'Unknown'), {
                        direction: 'top',
                        offset: [0, -6],
                        opacity: 0.9
                    });
                });
                
                // Add a custom Zoom Slider
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
                        slider.style.appearance = 'slider-vertical'; // Webkit/Blink vertical slider
                        slider.style.width = '8px';
                        slider.style.height = '100%';
                        slider.style.writingMode = 'bt-lr'; // IE vertical slider
                        slider.orient = 'vertical'; // Firefox vertical slider
                        
                        var minus = L.DomUtil.create('div', '', container);
                        minus.innerHTML = '−';
                        minus.style.fontWeight = 'bold';
                        minus.style.color = 'var(--text-main)';

                        L.DomEvent.disableClickPropagation(container);
                        L.DomEvent.disableScrollPropagation(container);

                        // Sync slider -> map
                        slider.addEventListener('input', function(e) {
                            map.setZoom(e.target.value, {animate: false});
                        });
                        
                        // Sync map -> slider
                        map.on('zoom', function() {
                            slider.value = map.getZoom();
                        });

                        return container;
                    }
                });
                map.addControl(new ZoomSlider());

                // Center map with a 1:1 default zoom so it doesn't look tiny
                map.setView([imgH / 2, imgW / 2], 0);

                // Optional: keep map properly sized if window resizes without forcing full zoom out
                const resizeObserver = new ResizeObserver(function() {
                    map.invalidateSize();
                });
                resizeObserver.observe(document.getElementById('home-map'));
            });
        </script>
    <?php endif; ?>

    <div class="quick-links-section">
        <h2>Quick Categories</h2>
        <div class="quick-links-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px;">
            <a href="search.php?category=Office+%2F+Admin" class="quick-link-card">
                <div class="ql-icon">🏢</div>
                <span>Admin Offices</span>
            </a>
            <a href="search.php?category=Classroom+%2F+Tutorial" class="quick-link-card">
                <div class="ql-icon">📖</div>
                <span>Classrooms</span>
            </a>
            <a href="search.php?category=Lecturer+Office" class="quick-link-card">
                <div class="ql-icon">👥</div>
                <span>Offices</span>
            </a>
            <a href="search.php?category=Meeting+Room" class="quick-link-card">
                <div class="ql-icon">🤝</div>
                <span>Meeting Rooms</span>
            </a>
            <a href="search.php?category=Surau" class="quick-link-card">
                <div class="ql-icon">🕌</div>
                <span>Surau</span>
            </a>
            <a href="search.php?category=Restroom" class="quick-link-card">
                <div class="ql-icon">🚻</div>
                <span>Restrooms</span>
            </a>
        </div>
    </div>

    <?php
    // Fetch top 3 popular searches dynamically
    $popularStmt = $pdo->query("SELECT query FROM search_logs GROUP BY query ORDER BY COUNT(*) DESC LIMIT 3");
    $popularSearches = $popularStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($popularSearches)) {
        // Fallbacks if no data yet
        $popularSearches = ['F1-118', 'Surau', 'Computer Lab'];
    }
    ?>
    <div class="recent-searches-section">
        <h2>Popular Searches</h2>
        <ul class="recent-list">
            <?php foreach ($popularSearches as $popSearch): ?>
                <li><a href="search.php?q=<?php echo urlencode($popSearch); ?>"><span class="clock-icon">🔥</span> <?php echo htmlspecialchars($popSearch); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
