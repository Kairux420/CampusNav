/**
 * map-viewer.js
 *
 * Renders a route (possibly spanning multiple floors) on top of the real
 * floor map images, using Leaflet with CRS.Simple (plain pixel coordinates,
 * not lat/lng — matches the x_coord/y_coord values stored in the `nodes` table).
 *
 * Expects a global `routeData` object (set inline by navigate.php) shaped like:
 * {
 *   segments: [
 *     {
 *       floorLabel: "Ground Floor (Left Wing)",
 *       imageUrl: "/campusnav/assets/maps/main_ground_left.png",
 *       imageWidth: 1790,
 *       imageHeight: 1039,
 *       points: [ { nodeId, name, type, x, y }, ... ]  // in path order
 *     },
 *     ...
 *   ]
 * }
 */

(function () {
    if (typeof routeData === 'undefined' || !routeData.segments || routeData.segments.length === 0) {
        return; // nothing to render — navigate.php only includes this script when there's a route
    }

    const segments = routeData.segments;
    let currentIndex = 0;
    let map = null;
    let imageLayer = null;
    let pathLayer = null;
    let markersLayer = null;

    const mapEl = document.getElementById('route-map');
    const labelEl = document.getElementById('route-floor-label');
    const prevBtn = document.getElementById('route-prev-floor');
    const nextBtn = document.getElementById('route-next-floor');

    function pixelToLatLng(x, y, imageHeight) {
        // Image top-left is (0,0) in pixel space; Leaflet's CRS.Simple treats
        // higher lat as "up", so we flip the y-axis to match the image.
        return [imageHeight - y, x];
    }

    function renderSegment(index) {
        const seg = segments[index];
        const bounds = [[0, 0], [seg.imageHeight, seg.imageWidth]];

        if (!map) {
            map = L.map(mapEl, {
                crs: L.CRS.Simple,
                minZoom: -4,
                maxZoom: 2,
                zoomControl: false,
                attributionControl: false
            });

            // Add Custom Zoom Slider
            var ZoomSlider = L.Control.extend({
                options: { position: 'topleft' },
                onAdd: function(map) {
                    var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                    container.style.background = 'var(--bg-card, #fff)';
                    container.style.padding = '15px 5px';
                    container.style.display = 'flex';
                    container.style.flexDirection = 'column';
                    container.style.alignItems = 'center';
                    container.style.gap = '10px';
                    container.style.border = '1px solid var(--border-color, #ddd)';
                    container.style.boxShadow = 'var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.1))';
                    container.style.borderRadius = '8px';

                    var plus = L.DomUtil.create('div', '', container);
                    plus.innerHTML = '+';
                    plus.style.fontWeight = 'bold';
                    plus.style.color = 'var(--text-main, #333)';

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
                    minus.style.color = 'var(--text-main, #333)';

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
        }

        if (imageLayer) map.removeLayer(imageLayer);
        if (pathLayer) map.removeLayer(pathLayer);
        if (markersLayer) map.removeLayer(markersLayer);

        imageLayer = L.imageOverlay(seg.imageUrl, bounds).addTo(map);
        map.fitBounds(bounds);

        const latLngs = seg.points.map(p => pixelToLatLng(p.x, p.y, seg.imageHeight));
        pathLayer = L.polyline(latLngs, { color: '#0D7377', weight: 4, opacity: 0.85 }).addTo(map);

        markersLayer = L.layerGroup().addTo(map);
        seg.points.forEach((p, i) => {
            const isStart = index === 0 && i === 0;
            const isEnd = index === segments.length - 1 && i === seg.points.length - 1;
            let fillColor = '#2563eb';
            if (isStart) fillColor = '#16a34a';
            if (isEnd) fillColor = '#dc2626';

            const marker = L.circleMarker(pixelToLatLng(p.x, p.y, seg.imageHeight), {
                radius: isStart || isEnd ? 9 : 6,
                color: '#ffffff',
                weight: 2,
                fillColor: fillColor,
                fillOpacity: 1,
            });
            marker.bindPopup(
                (isStart ? 'Start: ' : isEnd ? 'Destination: ' : '') + p.name +
                ' (' + p.type + ')'
            );
            marker.addTo(markersLayer);
        });

        if (labelEl) {
            labelEl.textContent = 'Floor ' + (index + 1) + ' of ' + segments.length +
                ': ' + seg.floorLabel;
        }
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) nextBtn.disabled = index === segments.length - 1;
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (currentIndex > 0) {
                currentIndex--;
                renderSegment(currentIndex);
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (currentIndex < segments.length - 1) {
                currentIndex++;
                renderSegment(currentIndex);
            }
        });
    }

    renderSegment(currentIndex);
})();