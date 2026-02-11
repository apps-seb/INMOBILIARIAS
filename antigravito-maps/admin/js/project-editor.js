/**
 * MasterPlan 3D Pro - Project Editor JavaScript
 * Editor de lotes dentro de un proyecto
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Verificar que estamos en el editor
    if (typeof masterplanEditorData === 'undefined') {
        return;
    }

    let map = null;
    let canvas = null;
    let ctx = null;
    let backgroundImage = null;
    let isDrawing = false;
    let currentPoints = [];
    let selectedLotId = masterplanEditorData.selectedLotId;
    let lotsData = masterplanEditorData.lots;
    let poisData = masterplanEditorData.pois || [];
    let selectedPoiId = null;
    let isPlacingPoi = false;
    let activeTab = 'lots';
    let currentPoiMarker = null; // Para modo mapa

    // Rutas
    let routesData = masterplanEditorData.routes || [];
    let selectedRouteId = null;
    let isDrawingRoute = false;
    let currentRoutePoints = [];

    // Project Overlays
    let overlaysData = masterplanEditorData.overlays || [];
    let selectedOverlayId = null;

    // Overlay (Shared Editing State)
    let isEditingOverlay = false;
    let overlayCorners = []; // [TL, TR, BR, BL] (lngLat)
    let overlayImage = null; // Image object or URL
    let dragCornerIndex = -1;

    // ========================================
    // INICIALIZACIÓN
    // ========================================

    function init() {
        if (masterplanEditorData.useCustomImage === true || masterplanEditorData.useCustomImage === 'true') {
            initImageEditor();
        } else {
            initMapEditor();
        }

        bindEvents();
        renderExistingPolygons();

        // Seleccionar lote si viene en URL
        if (selectedLotId) {
            selectLot(selectedLotId);
        }
    }

    // ========================================
    // EDITOR DE MAPA 3D
    // ========================================

    function initMapEditor() {
        if (!document.getElementById('map-editor-container')) return;

        map = new maplibregl.Map({
            container: 'map-editor-container',
            style: `https://api.maptiler.com/maps/hybrid/style.json?key=${masterplanEditorData.apiKey}`,
            center: [masterplanEditorData.centerLng, masterplanEditorData.centerLat],
            zoom: masterplanEditorData.zoom,
            pitch: 45,
            bearing: 0
        });

        // Agregar terreno 3D
        map.on('load', function () {
            map.addSource('terrain', {
                type: 'raster-dem',
                url: `https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=${masterplanEditorData.apiKey}`
            });

            map.setTerrain({
                source: 'terrain',
                exaggeration: 1.5
            });

            // Agregar controles
            map.addControl(new maplibregl.NavigationControl());

            // Renderizar polígonos existentes
            renderMapPolygons();
            renderMapPOIs();
            renderMapRoutes();
            renderMapOverlays();
        });

        // Click en mapa
        map.on('click', function (e) {
            if (activeTab === 'lots' && isDrawing) {
                // Lógica de dibujo de lotes
                const point = [e.lngLat.lng, e.lngLat.lat];
                currentPoints.push(point);
                addPointMarker(e.lngLat);
                updateTempPolygon();

                if (currentPoints.length > 2) {
                    const first = currentPoints[0];
                    const dist = Math.sqrt(
                        Math.pow(point[0] - first[0], 2) +
                        Math.pow(point[1] - first[1], 2)
                    );
                    if (dist < 0.0001) finishDrawing();
                }
            } else if (activeTab === 'pois' && isPlacingPoi) {
                // Lógica de ubicación de POI
                updatePoiLocation(e.lngLat.lat, e.lngLat.lng);
            } else if (activeTab === 'routes' && isDrawingRoute) {
                // Lógica de trazado de ruta
                const point = [e.lngLat.lng, e.lngLat.lat];
                currentRoutePoints.push(point);
                updateTempRoute();
            }
        });

        // Eventos Drag Overlay
        map.on('mousedown', onMapMouseDown);
        map.on('mousemove', onMapMouseMove);
        map.on('mouseup', onMapMouseUp);
    }

    // --- Overlay Drag Logic (MapLibre) ---
    function onMapMouseDown(e) {
        if (!isEditingOverlay || !overlayCorners.length) return;

        // Check if near a corner
        const point = e.point;
        const dists = overlayCorners.map((c, i) => {
            const p = map.project(c);
            const dx = p.x - point.x;
            const dy = p.y - point.y;
            return { idx: i, dist: Math.sqrt(dx*dx + dy*dy) };
        });

        const closest = dists.sort((a,b) => a.dist - b.dist)[0];
        if (closest.dist < 20) { // 20px hit area
            dragCornerIndex = closest.idx;
            map.getCanvas().style.cursor = 'move';
            e.preventDefault(); // Prevent map drag
            map.dragPan.disable();
        }
    }

    function onMapMouseMove(e) {
        if (!isEditingOverlay) return;

        if (dragCornerIndex !== -1) {
            // Dragging
            overlayCorners[dragCornerIndex] = [e.lngLat.lng, e.lngLat.lat];
            if (activeTab === 'overlays') {
                updateProjectOverlayMap();
            } else {
                updateMapOverlay();
            }
        } else {
            // Hover cursor check
            // (Optional optimization: only check if close)
        }
    }

    function onMapMouseUp(e) {
        if (dragCornerIndex !== -1) {
            dragCornerIndex = -1;
            map.getCanvas().style.cursor = '';
            map.dragPan.enable();
            saveOverlayState(); // Save locally/temp
        }
    }

    function addPointMarker(lngLat) {
        const el = document.createElement('div');
        el.className = 'draw-point-marker';
        el.style.cssText = 'width: 12px; height: 12px; background: #667eea; border: 2px solid white; border-radius: 50%; cursor: pointer;';

        new maplibregl.Marker(el)
            .setLngLat(lngLat)
            .addTo(map);
    }

    function updateTempPolygon() {
        if (currentPoints.length < 2) return;

        const sourceId = 'temp-polygon';

        // Remover si existe
        if (map.getSource(sourceId)) {
            map.removeLayer(sourceId + '-line');
            map.removeLayer(sourceId + '-fill');
            map.removeSource(sourceId);
        }

        // Cerrar el polígono temporalmente para visualización
        const closedPoints = [...currentPoints, currentPoints[0]];

        map.addSource(sourceId, {
            type: 'geojson',
            data: {
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [closedPoints]
                }
            }
        });

        map.addLayer({
            id: sourceId + '-fill',
            type: 'fill',
            source: sourceId,
            paint: {
                'fill-color': '#667eea',
                'fill-opacity': 0.3
            }
        });

        map.addLayer({
            id: sourceId + '-line',
            type: 'line',
            source: sourceId,
            paint: {
                'line-color': '#667eea',
                'line-width': 2,
                'line-dasharray': [2, 2]
            }
        });
    }

    function renderMapPolygons() {
        lotsData.forEach(function (lot) {
            // Render Polygon
            if (lot.coordinates && lot.coordinates.length >= 3) {
                const color = getStatusColor(lot.status);
                const sourceId = 'lot-' + lot.id;
                const coords = [...lot.coordinates, lot.coordinates[0]];

                if(!map.getSource(sourceId)) {
                    map.addSource(sourceId, {
                        type: 'geojson',
                        data: {
                            type: 'Feature',
                            properties: { id: lot.id, lot_number: lot.lot_number },
                            geometry: { type: 'Polygon', coordinates: [coords] }
                        }
                    });

                    map.addLayer({
                        id: sourceId + '-fill',
                        type: 'fill',
                        source: sourceId,
                        paint: { 'fill-color': color, 'fill-opacity': 0.4 }
                    });

                    map.addLayer({
                        id: sourceId + '-line',
                        type: 'line',
                        source: sourceId,
                        paint: { 'line-color': color, 'line-width': 2 }
                    });
                }

                // Marker
                const centroid = calculateCentroid(lot.coordinates);
                const markerEl = document.createElement('div');
                markerEl.className = 'lot-marker-admin';
                markerEl.innerHTML = `<span class="marker-label">${lot.lot_number}</span>`;
                markerEl.style.cssText = `
                    background: ${color}; color: white; padding: 4px 10px;
                    border-radius: 12px; font-size: 11px; font-weight: bold;
                    cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                `;
                new maplibregl.Marker(markerEl).setLngLat(centroid).addTo(map);
            }

            // Render Overlay if exists
            if (lot.overlay_url && lot.overlay_corners) {
                renderMapOverlayForLot(lot);
            }
        });
    }

    function renderMapOverlayForLot(lot) {
        const sourceId = 'overlay-' + lot.id;
        if (map.getSource(sourceId)) return;

        map.addSource(sourceId, {
            type: 'image',
            url: lot.overlay_url,
            coordinates: lot.overlay_corners
        });
        map.addLayer({
            id: sourceId,
            type: 'raster',
            source: sourceId,
            paint: { 'raster-opacity': 0.9 }
        }, 'lots-fill'); // Below lots fill if possible, or just add
    }

    function renderMapPOIs() {
        document.querySelectorAll('.poi-marker-admin').forEach(el => el.remove());
        document.querySelectorAll('.poi-marker').forEach(el => el.remove()); // Remove custom styled ones too

        poisData.forEach(poi => {
            renderSingleMapMarker(poi);
        });
    }

    function renderSingleMapMarker(poi) {
        if (!poi.lat || !poi.lng) return;

        const el = createPoiMarkerElement(poi);
        el.className += ' poi-marker-admin'; // Add admin class for easy selection

        // Ensure style for admin view visibility if css is missing
        if (!poi.style || poi.style === 'default') {
             el.style.fontSize = '30px';
             el.style.cursor = 'pointer';
        }

        const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
            .setLngLat([parseFloat(poi.lng), parseFloat(poi.lat)])
            .addTo(map);

        // Evento click para seleccionar
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            selectPoi(poi.id);
        });
    }

    // ========================================
    // EDITOR DE IMAGEN
    // ========================================

    function initImageEditor() {
        canvas = document.getElementById('image-canvas');
        if (!canvas) return;

        // Create Overlay Container for POIs in Admin Mode too
        const container = document.getElementById('image-editor-container');
        if (!container.querySelector('.poi-overlay-container')) {
             const overlay = document.createElement('div');
             overlay.className = 'poi-overlay-container';
             overlay.id = 'poi-overlay-container-admin';
             // Need basic styles for overlay in admin
             overlay.style.position = 'absolute';
             overlay.style.top = '0';
             overlay.style.left = '0';
             overlay.style.width = '100%';
             overlay.style.height = '100%';
             overlay.style.pointerEvents = 'none';
             overlay.style.zIndex = '10'; // Above canvas
             container.appendChild(overlay);
        }

        ctx = canvas.getContext('2d');
        backgroundImage = document.getElementById('background-image');

        backgroundImage.onload = function () {
            resizeCanvas();
            renderImagePolygons();
        };

        if (backgroundImage.complete) {
            resizeCanvas();
            renderImagePolygons();
        }

        // Eventos de canvas
        canvas.addEventListener('click', onCanvasClick);
        window.addEventListener('resize', resizeCanvas);
    }

    function resizeCanvas() {
        const container = document.getElementById('image-editor-container');
        const containerWidth = container.clientWidth;
        const containerHeight = container.clientHeight;

        const imgAspect = backgroundImage.naturalWidth / backgroundImage.naturalHeight;
        const containerAspect = containerWidth / containerHeight;

        let drawWidth, drawHeight;
        if (imgAspect > containerAspect) {
            drawWidth = containerWidth;
            drawHeight = containerWidth / imgAspect;
        } else {
            drawHeight = containerHeight;
            drawWidth = containerHeight * imgAspect;
        }

        canvas.width = containerWidth;
        canvas.height = containerHeight;
        canvas.style.width = containerWidth + 'px';
        canvas.style.height = containerHeight + 'px';

        // Guardar offset para coordenadas
        canvas.dataset.offsetX = (containerWidth - drawWidth) / 2;
        canvas.dataset.offsetY = (containerHeight - drawHeight) / 2;
        canvas.dataset.drawWidth = drawWidth;
        canvas.dataset.drawHeight = drawHeight;

        redrawCanvas();
    }

    function redrawCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Dibujar imagen de fondo
        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        ctx.drawImage(backgroundImage, offsetX, offsetY, drawWidth, drawHeight);

        // Dibujar polígonos existentes
        renderImagePolygons();

        // Render POIs using DOM Overlay
        updatePoiOverlayPositions();

        // Renderizar Rutas
        renderImageRoutes();

        // Dibujar polígono temporal
        if (activeTab === 'lots' && currentPoints.length > 0) {
            drawPolygonOnCanvas(currentPoints, '#667eea', true);
        }

        // Dibujar ruta temporal
        if (activeTab === 'routes' && currentRoutePoints.length > 0) {
            drawRouteOnCanvas(currentRoutePoints, '#ffffff', true);
        }

        // Dibujar marcador de POI actual si se está colocando (TEMP)
        if (activeTab === 'pois' && isPlacingPoi && currentPoiMarker) {
             // For temp marker, render it in overlay too
             // We handle this inside updatePoiOverlayPositions by including currentPoiMarker in the loop logic
             // Or explicitly adding it.
             // To keep it simple, we can rely on `poisData` update for saved ones,
             // but `currentPoiMarker` is temp. Let's add a special temporary DOM element.
             renderTempPoiMarker(currentPoiMarker);
        } else {
            const tempEl = document.getElementById('temp-poi-marker');
            if (tempEl) tempEl.remove();
        }
    }

    function renderTempPoiMarker(poi) {
        const overlay = document.getElementById('poi-overlay-container-admin');
        let el = document.getElementById('temp-poi-marker');

        if (!el) {
            el = createPoiMarkerElement(poi);
            el.id = 'temp-poi-marker';
            el.style.opacity = '0.7'; // Indicate temp status
            overlay.appendChild(el);
        } else {
            // Update style if needed (e.g. if we had style editing live, which we don't really here yet)
             // But we do update position
        }

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        const x = offsetX + (parseFloat(poi.lng) * drawWidth);
        const y = offsetY + (parseFloat(poi.lat) * drawHeight);

        el.style.position = 'absolute';
        el.style.left = x + 'px';
        el.style.top = y + 'px';
        el.style.transform = 'translate(-50%, -100%)';
    }

    function renderImagePolygons() {
        lotsData.forEach(function (lot) {
            if (!lot.coordinates || lot.coordinates.length < 3) return;
            if (lot.id === selectedLotId && isDrawing) return; // No dibujar el actual si estamos editando

            const color = getStatusColor(lot.status);
            drawPolygonOnCanvas(lot.coordinates, color, false, lot.lot_number);
        });
    }

    function renderImagePOIs() {
        // Now handled by updatePoiOverlayPositions
    }

    function updatePoiOverlayPositions() {
        const overlay = document.getElementById('poi-overlay-container-admin');
        if (!overlay) return;

        // Clear existing REAL markers (keep temp one if handled separately, but easier to rebuild all)
        // We will remove all except temp one
        Array.from(overlay.children).forEach(child => {
            if (child.id !== 'temp-poi-marker') child.remove();
        });

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        poisData.forEach(poi => {
            if (!poi.lat || !poi.lng) return;
            if (poi.id == selectedPoiId && isPlacingPoi) return; // Don't show original if moving

            const el = createPoiMarkerElement(poi);

            // Highlight selected
            if (poi.id == selectedPoiId) {
                el.style.filter = 'drop-shadow(0 0 5px #667eea)';
                el.style.zIndex = '200';
            }

            const x = offsetX + (parseFloat(poi.lng) * drawWidth);
            const y = offsetY + (parseFloat(poi.lat) * drawHeight);

            el.style.position = 'absolute';
            el.style.left = x + 'px';
            el.style.top = y + 'px';
            el.style.transform = 'translate(-50%, -100%)';

            // Event
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                selectPoi(poi.id);
            });

            overlay.appendChild(el);
        });
    }

    // Helper to create the sophisticated marker DOM structure (Shared with Frontend)
    function createPoiMarkerElement(poi) {
        const el = document.createElement('div');
        el.className = `poi-marker style-${poi.style || 'default'}`;
        el.title = poi.title;
        // Enable pointer events for admin selection
        el.style.pointerEvents = 'auto';

        // Custom Image
        const imgHtml = poi.custom_image_url
            ? `<img src="${poi.custom_image_url}" alt="${poi.title}">`
            : `<span style="font-size:20px">📍</span>`;

        if (poi.style === 'orthogonal-label') {
             el.innerHTML = `
                <div class="poi-label-content">
                    <span class="poi-label-text">${poi.title}</span>
                    <div class="poi-horizontal-line"></div>
                </div>
                <div class="poi-vertical-line"></div>
                <div class="poi-dot"></div>
            `;
        } else if (poi.style === 'orthogonal') {
            el.innerHTML = `
                <div class="poi-icon-box">${imgHtml}</div>
                <div class="poi-line"></div>
                <div class="poi-dot"></div>
            `;
        } else if (poi.style === 'gold') {
            el.innerHTML = `
                <div class="poi-icon-wrapper">
                    <div class="poi-icon-inner">${imgHtml}</div>
                </div>
            `;
        } else if (poi.style === 'flag') {
            el.innerHTML = `
                <div class="poi-flag-content">
                     ${imgHtml} <span>${poi.title}</span>
                </div>
                <div class="poi-pole"></div>
            `;
        } else {
            // Default
            el.innerHTML = `
                <div style="font-size: 30px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">📍</div>
            `;
        }

        return el;
    }

    function drawPolygonOnCanvas(points, color, isTemp, label) {
        if (points.length < 2) return;

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        ctx.beginPath();

        points.forEach(function (point, index) {
            // Convertir coordenadas relativas (0-1) a canvas
            const x = offsetX + (point[0] * drawWidth);
            const y = offsetY + (point[1] * drawHeight);

            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });

        if (!isTemp) {
            ctx.closePath();
        }

        ctx.fillStyle = color + '66'; // Con transparencia
        ctx.fill();

        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        if (isTemp) {
            ctx.setLineDash([5, 5]);
        } else {
            ctx.setLineDash([]);
        }
        ctx.stroke();

        // Puntos
        points.forEach(function (point) {
            const x = offsetX + (point[0] * drawWidth);
            const y = offsetY + (point[1] * drawHeight);

            ctx.beginPath();
            ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fillStyle = isTemp ? '#667eea' : color;
            ctx.fill();
            ctx.strokeStyle = 'white';
            ctx.lineWidth = 2;
            ctx.stroke();
        });

        // Etiqueta
        if (label && points.length >= 3) {
            const centroid = calculateCentroid(points);
            const cx = offsetX + (centroid[0] * drawWidth);
            const cy = offsetY + (centroid[1] * drawHeight);

            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            // Fondo
            const textWidth = ctx.measureText(label).width;
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.roundRect(cx - textWidth / 2 - 8, cy - 10, textWidth + 16, 20, 10);
            ctx.fill();

            // Texto
            ctx.fillStyle = 'white';
            ctx.fillText(label, cx, cy);
        }
    }

    function onCanvasClick(e) {
        if (!isDrawing && !isPlacingPoi && !isDrawingRoute) return;

        const rect = canvas.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        // Verificar que el click está dentro de la imagen
        if (clickX < offsetX || clickX > offsetX + drawWidth ||
            clickY < offsetY || clickY > offsetY + drawHeight) {
            return;
        }

        // Convertir a coordenadas relativas (0-1)
        const relX = (clickX - offsetX) / drawWidth;
        const relY = (clickY - offsetY) / drawHeight;

        if (activeTab === 'lots' && isDrawing) {
            currentPoints.push([relX, relY]);
            redrawCanvas();

            if (currentPoints.length > 2) {
                const first = currentPoints[0];
                const dist = Math.sqrt(
                    Math.pow(relX - first[0], 2) +
                    Math.pow(relY - first[1], 2)
                );
                if (dist < 0.03) finishDrawing();
            }
        } else if (activeTab === 'pois' && isPlacingPoi) {
             updatePoiLocation(relY, relX); // Y=Lat, X=Lng
        } else if (activeTab === 'routes' && isDrawingRoute) {
             currentRoutePoints.push([relX, relY]);
             redrawCanvas();
        }
    }

    // ========================================
    // FUNCIONES COMUNES
    // ========================================

    function getStatusColor(status) {
        const colors = {
            'disponible': '#10b981',
            'reservado': '#f59e0b',
            'vendido': '#ef4444'
        };
        return colors[status] || '#6b7280';
    }

    function calculateCentroid(points) {
        let sumX = 0, sumY = 0;
        points.forEach(function (p) {
            sumX += p[0];
            sumY += p[1];
        });
        return [sumX / points.length, sumY / points.length];
    }

    function renderExistingPolygons() {
        // Ya manejado en initMapEditor o initImageEditor
    }

    // ========================================
    // MANEJO DE PESTAÑAS Y EVENTOS
    // ========================================

    function bindEvents() {
        // Tabs
        $('.tab-item').on('click', function() {
            const tab = $(this).data('tab');
            activeTab = tab;

            // UI Tabs
            $('.tab-item').removeClass('active');
            $(this).addClass('active');

            // UI Content
            $('.tab-content').hide();
            $(`#tab-content-${tab}`).show();

            // UI Controls
            if (tab === 'lots') {
                $('#controls-lots').show();
                $('#controls-pois').hide();
                $('#controls-routes').hide();
                $('#controls-overlays-project').hide();
                $('#editor-status').text('Modo Lotes activo');
                stopDrawing(); stopPlacingPoi(); stopDrawingRoute(); stopEditingProjectOverlay();
            } else if (tab === 'pois') {
                $('#controls-lots').hide();
                $('#controls-pois').show();
                $('#controls-routes').hide();
                $('#controls-overlays-project').hide();
                $('#editor-status').text('Modo Puntos de Interés activo');
                stopDrawing(); stopPlacingPoi(); stopDrawingRoute(); stopEditingProjectOverlay();
            } else if (tab === 'routes') {
                $('#controls-lots').hide();
                $('#controls-pois').hide();
                $('#controls-routes').show();
                $('#controls-overlays-project').hide();
                $('#editor-status').text('Modo Rutas activo');
                stopDrawing(); stopPlacingPoi(); stopDrawingRoute(); stopEditingProjectOverlay();
            } else if (tab === 'overlays') {
                $('#controls-lots').hide();
                $('#controls-pois').hide();
                $('#controls-routes').hide();
                $('#controls-overlays-project').show();
                $('#editor-status').text('Modo Planos activo');
                stopDrawing(); stopPlacingPoi(); stopDrawingRoute();
            }
        });

        // --- LOTES ---

        // Seleccionar lote
        $(document).on('click', '.lot-item', function (e) {
            if ($(e.target).closest('.lot-actions').length) return;
            selectLot($(this).data('lot-id'));
        });

        // Dibujar desde lista
        $(document).on('click', '.lot-item .btn-draw', function (e) {
            e.stopPropagation();
            const lotId = $(this).closest('.lot-item').data('lot-id');
            selectLot(lotId);
            startDrawing();
        });

        // Botón dibujar
        $('#btn-draw-polygon').on('click', function () {
            if (isDrawing) {
                stopDrawing();
            } else {
                startDrawing();
            }
        });

        // Botón borrar
        $('#btn-clear-polygon').on('click', clearPolygon);

        // Botón guardar
        $('#btn-save-polygon').on('click', function() {
            if (isEditingOverlay) {
                saveOverlay();
            } else {
                savePolygon();
            }
        });

        // Overlay Buttons
        $('#btn-edit-overlay').on('click', function() {
            if (isEditingOverlay) {
                stopEditingOverlay();
            } else {
                startEditingOverlay();
            }
        });

        $('#btn-upload-overlay').on('click', function() {
            if (!selectedLotId) return;
            const frame = wp.media({ title: 'Imagen Overlay', button: { text: 'Usar' }, multiple: false });
            frame.on('select', () => {
                const att = frame.state().get('selection').first().toJSON();
                setOverlayImage(att.id, att.url);
            });
            frame.open();
        });

        $('#btn-remove-overlay').on('click', function() {
            if(confirm('¿Eliminar overlay?')) {
                removeOverlay();
            }
        });

        // Nuevo lote
        $('#btn-new-lot').on('click', function () {
            $('#new-lot-modal').show();
        });

        // --- POIS ---

        // Seleccionar POI
        $(document).on('click', '.poi-item', function (e) {
            if ($(e.target).closest('.poi-actions').length) return;
            selectPoi($(this).data('poi-id'));
        });

        // Ubicar POI desde lista
        $(document).on('click', '.btn-locate-poi', function (e) {
             e.stopPropagation();
             const poiId = $(this).closest('.poi-item').data('poi-id');
             selectPoi(poiId);
             startPlacingPoi();
        });

        // Eliminar POI
        $(document).on('click', '.btn-delete-poi', function (e) {
             e.stopPropagation();
             const poiId = $(this).closest('.poi-item').data('poi-id');
             if(confirm('¿Estás seguro de eliminar este punto?')) {
                 deletePoi(poiId);
             }
        });

        // Nuevo POI
        $('#btn-new-poi').on('click', function() {
            $('#new-poi-modal').show();
        });

        // Toggle ubicar
        $('#btn-place-poi').on('click', function() {
             if (isPlacingPoi) {
                 stopPlacingPoi();
             } else {
                 startPlacingPoi();
             }
        });

        // Guardar POI
        $('#btn-save-poi').on('click', savePoiLocation);

        // --- MODALES ---

        // Cerrar modal
        $(document).on('click', '.modal-close, .modal-overlay', function (e) {
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                $('.modal-overlay').hide();
                stopDrawing();
                stopPlacingPoi();
            }
        });

        // Formulario nuevo lote
        $('#new-lot-form').on('submit', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');
            $submit.prop('disabled', true).text('Creando...');

            $.ajax({
                url: masterplanEditorData.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=masterplan_create_lot&nonce=' + masterplanEditorData.nonce,
                success: function (response) {
                    if (response.success) {
                        window.location.href = window.location.href + '&lot_id=' + response.data.lot_id;
                    } else {
                        alert('Error: ' + (response.data.message || 'Error desconocido'));
                    }
                },
                complete: function () { $submit.prop('disabled', false).text('Crear Lote'); }
            });
        });

        // Formulario nuevo POI
        $('#new-poi-form').on('submit', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');
            $submit.prop('disabled', true).text('Creando...');

            $.ajax({
                url: masterplanEditorData.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=masterplan_create_poi&nonce=' + masterplanEditorData.nonce,
                success: function (response) {
                    if (response.success) {
                        alert('POI creado');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.message || 'Error desconocido'));
                    }
                },
                complete: function () { $submit.prop('disabled', false).text('Crear POI'); }
            });
        });

        // --- RUTAS ---
        $(document).on('click', '.route-item', function (e) {
            if ($(e.target).closest('.lot-actions').length) return;
            selectRoute($(this).data('route-id'));
        });

        $(document).on('click', '.btn-draw-route', function (e) {
            e.stopPropagation();
            const rid = $(this).closest('.route-item').data('route-id');
            selectRoute(rid);
            startDrawingRoute();
        });

         $(document).on('click', '.btn-delete-route', function (e) {
             e.stopPropagation();
             const rid = $(this).closest('.route-item').data('route-id');
             if(confirm('¿Eliminar ruta?')) {
                 $.post(masterplanEditorData.ajaxUrl, {
                     action: 'masterplan_delete_route',
                     route_id: rid,
                     nonce: masterplanEditorData.nonce
                 }, function() { location.reload(); });
             }
        });

        $('#btn-new-route').on('click', function() { $('#new-route-modal').show(); });
        $('#new-route-form').on('submit', function(e) {
            e.preventDefault();
            $.post(masterplanEditorData.ajaxUrl, $(this).serialize() + '&action=masterplan_create_route&nonce=' + masterplanEditorData.nonce, function(res) {
                if (res.success) location.reload(); else alert('Error');
            });
        });

        $('#btn-draw-route').on('click', function() {
            if(isDrawingRoute) stopDrawingRoute(); else startDrawingRoute();
        });
        $('#btn-clear-route').on('click', clearRoute);
        $('#btn-save-route').on('click', saveRoute);

        // --- OVERLAYS PROJECT ---
        $('#btn-new-project-overlay').on('click', function() {
             const frame = wp.media({ title: 'Subir Plano', button: { text: 'Usar' }, multiple: false });
             frame.on('select', () => {
                 const att = frame.state().get('selection').first().toJSON();
                 addProjectOverlay(att);
             });
             frame.open();
        });

        $(document).on('click', '.overlay-item', function(e) {
            if ($(e.target).closest('.lot-actions').length) return;
            // Select logic if needed, currently editing is via button
            const oid = $(this).data('overlay-id');
            selectedOverlayId = oid;
            $('.overlay-item').removeClass('active');
            $(this).addClass('active');
        });

        $(document).on('click', '.btn-edit-project-overlay', function(e) {
             e.stopPropagation();
             const oid = $(this).closest('.overlay-item').data('overlay-id');
             selectedOverlayId = oid;
             $('.overlay-item').removeClass('active');
             $(this).closest('.overlay-item').addClass('active');
             startEditingProjectOverlay(oid);
        });

        $(document).on('click', '.btn-delete-project-overlay', function(e) {
             e.stopPropagation();
             const oid = $(this).closest('.overlay-item').data('overlay-id');
             if(confirm('¿Eliminar este plano?')) deleteProjectOverlay(oid);
        });

        $('#btn-edit-overlay-pos').on('click', function() {
             if (isEditingOverlay) stopEditingProjectOverlay();
             else if (selectedOverlayId) startEditingProjectOverlay(selectedOverlayId);
        });

        $('#btn-save-overlay-project').on('click', saveProjectOverlay);

        $('#overlay-opacity-slider').on('input', function() {
             if (selectedOverlayId && map) {
                 const val = parseFloat($(this).val());
                 const layerId = 'project-overlay-' + selectedOverlayId;
                 if (map.getLayer(layerId)) {
                     map.setPaintProperty(layerId, 'raster-opacity', val);
                 }
                 // Update data in memory
                 const ov = overlaysData.find(o => o.id == selectedOverlayId);
                 if(ov) ov.opacity = val;
             }
        });
    }


    // ========================================
    // MANEJO DE LOTES Y POIS (FUNCIONES AUXILIARES)
    // ========================================

    // --- LOTES ---

    function selectLot(lotId) {
        selectedLotId = lotId;
        isEditingOverlay = false;
        $('#controls-overlay').hide();

        // UI: resaltar lote seleccionado
        $('.lot-item').removeClass('active');
        $(`.lot-item[data-lot-id="${lotId}"]`).addClass('active');

        // Habilitar controles
        $('#btn-draw-polygon').prop('disabled', false);
        $('#btn-clear-polygon').prop('disabled', false);
        $('#btn-edit-overlay').prop('disabled', false);

        const lot = lotsData.find(l => l.id == lotId);
        $('#editor-status').text(lot ? 'Lote: ' + lot.title : 'Lote seleccionado');

        if (lot && lot.coordinates) {
            currentPoints = [...lot.coordinates];
        } else {
            currentPoints = [];
        }

        // Reset overlay editing
        if (map) {
            // Remove temp handles
            document.querySelectorAll('.overlay-handle').forEach(e => e.remove());
        }
    }

    function startDrawing() {
        if (!selectedLotId) {
            alert('Selecciona un lote primero');
            return;
        }

        isDrawing = true;
        currentPoints = [];

        $('#btn-draw-polygon').text('🛑 Detener');
        $('#btn-save-polygon').prop('disabled', false);
        $('#editor-status').text('Dibujando... Haz clic para agregar puntos');

        // Limpiar polígono temporal del mapa
        if (map && map.getSource('temp-polygon')) {
            map.removeLayer('temp-polygon-line');
            map.removeLayer('temp-polygon-fill');
            map.removeSource('temp-polygon');
        }

        // Limpiar canvas
        if (canvas) {
            redrawCanvas();
        }
    }

    function stopDrawing() {
        isDrawing = false;
        $('#btn-draw-polygon').text('🎨 Dibujar Polígono');
        $('#editor-status').text('Dibujo pausado');
    }

    function finishDrawing() {
        isDrawing = false;
        $('#btn-draw-polygon').text('🎨 Dibujar Polígono');
        $('#editor-status').text(`Polígono listo con ${currentPoints.length} puntos. Haz clic en "Guardar"`);

        if (canvas) {
            redrawCanvas();
        }
    }

    function clearPolygon() {
        currentPoints = [];
        isDrawing = false;

        $('#btn-draw-polygon').text('🎨 Dibujar Polígono');
        $('#editor-status').text('Polígono eliminado');

        // Limpiar mapa
        if (map) {
            // Remover temp
            if (map.getSource('temp-polygon')) {
                map.removeLayer('temp-polygon-line');
                map.removeLayer('temp-polygon-fill');
                map.removeSource('temp-polygon');
            }
            // Remover marcadores de puntos
            document.querySelectorAll('.draw-point-marker').forEach(el => el.remove());
        }

        // Limpiar canvas
        if (canvas) {
            redrawCanvas();
        }
    }

    function savePolygon() {
        if (!selectedLotId) {
            alert('Selecciona un lote primero');
            return;
        }

        if (currentPoints.length < 3) {
            alert('El polígono debe tener al menos 3 puntos');
            return;
        }

        $('#btn-save-polygon').prop('disabled', true).text('Guardando...');

        $.ajax({
            url: masterplanEditorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'masterplan_save_lot_polygon',
                nonce: masterplanEditorData.nonce,
                lot_id: selectedLotId,
                project_id: masterplanEditorData.projectId,
                coordinates: JSON.stringify(currentPoints)
            },
            success: function (response) {
                if (response.success) {
                    const lotIndex = lotsData.findIndex(l => l.id == selectedLotId);
                    if (lotIndex !== -1) lotsData[lotIndex].coordinates = currentPoints;
                    alert('✅ Polígono guardado');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function () {
                $('#btn-save-polygon').prop('disabled', false).text('💾 Guardar');
            }
        });
    }

    // --- OVERLAY FUNCTIONS ---

    function startEditingOverlay() {
        if (!selectedLotId) return;
        isEditingOverlay = true;
        $('#controls-overlay').show();
        $('#btn-edit-overlay').text('Done');
        $('#btn-save-polygon').prop('disabled', false); // Allow saving

        const lot = lotsData.find(l => l.id == selectedLotId);
        if (lot && lot.overlay_url) {
            overlayImage = { url: lot.overlay_url, id: lot.overlay_id }; // Need id if saved
            if (lot.overlay_corners) {
                overlayCorners = lot.overlay_corners;
            } else {
                // Default corners based on lot bounds or center
                // Simple default: around center
                const center = map.getCenter();
                const d = 0.0005;
                overlayCorners = [
                    [center.lng - d, center.lat + d], // TL
                    [center.lng + d, center.lat + d], // TR
                    [center.lng + d, center.lat - d], // BR
                    [center.lng - d, center.lat - d]  // BL
                ];
            }
            updateMapOverlay();
        } else {
            $('#editor-status').text('Sube una imagen para el overlay');
        }
    }

    function stopEditingOverlay() {
        isEditingOverlay = false;
        $('#controls-overlay').hide();
        $('#btn-edit-overlay').text('🖼️ Overlay');
        // Clean up handles
        document.querySelectorAll('.overlay-handle').forEach(e => e.remove());
    }

    function setOverlayImage(id, url) {
        const lot = lotsData.find(l => l.id == selectedLotId);
        if(!lot) return;

        lot.overlay_url = url;
        lot.overlay_id = id;

        // Init corners if empty
        if (!overlayCorners || !overlayCorners.length) {
             const center = map.getCenter();
             const d = 0.0005;
             overlayCorners = [
                [center.lng - d, center.lat + d],
                [center.lng + d, center.lat + d],
                [center.lng + d, center.lat - d],
                [center.lng - d, center.lat - d]
             ];
        }

        updateMapOverlay();
    }

    function updateMapOverlay() {
        if (!map) return;
        const sourceId = 'overlay-edit';

        // 1. Image Source
        if (map.getSource(sourceId)) {
            map.getSource(sourceId).setCoordinates(overlayCorners);
        } else {
            // Only add if URL exists
            const lot = lotsData.find(l => l.id == selectedLotId);
            const url = (lot && lot.overlay_url) ? lot.overlay_url : '';
            if (url) {
                map.addSource(sourceId, {
                    type: 'image',
                    url: url,
                    coordinates: overlayCorners
                });
                map.addLayer({
                    id: sourceId,
                    type: 'raster',
                    source: sourceId,
                    paint: { 'raster-opacity': 0.8 }
                });
            }
        }

        // 2. Corner Handles
        document.querySelectorAll('.overlay-handle').forEach(e => e.remove());
        overlayCorners.forEach((c, i) => {
            const el = document.createElement('div');
            el.className = 'overlay-handle';
            el.style.cssText = 'width: 15px; height: 15px; background: white; border: 2px solid red; border-radius: 50%; cursor: move;';
            new maplibregl.Marker(el, { draggable: false }) // We handle drag manually
                .setLngLat(c)
                .addTo(map);
        });
    }

    function saveOverlay() {
        if (!selectedLotId) return;
        const lot = lotsData.find(l => l.id == selectedLotId);

        $('#btn-save-polygon').prop('disabled', true).text('Guardando Overlay...');

        $.post(masterplanEditorData.ajaxUrl, {
            action: 'masterplan_save_lot_overlay',
            nonce: masterplanEditorData.nonce,
            lot_id: selectedLotId,
            overlay_id: lot.overlay_id || '',
            corners: JSON.stringify(overlayCorners)
        }, function(res) {
            $('#btn-save-polygon').prop('disabled', false).text('💾 Guardar');
            if (res.success) {
                // Update local
                lot.overlay_corners = overlayCorners;
                alert('Overlay guardado');
                stopEditingOverlay();
                location.reload();
            } else {
                alert('Error');
            }
        });
    }

    function removeOverlay() {
        if (!selectedLotId) return;
        overlayCorners = [];
        overlayImage = null;
        const lot = lotsData.find(l => l.id == selectedLotId);
        lot.overlay_url = '';
        lot.overlay_id = '';
        lot.overlay_corners = null;

        if (map.getLayer('overlay-edit')) map.removeLayer('overlay-edit');
        if (map.getSource('overlay-edit')) map.removeSource('overlay-edit');
        document.querySelectorAll('.overlay-handle').forEach(e => e.remove());

        saveOverlay(); // Save emptiness
    }

    // --- POIS ---

    function selectPoi(poiId) {
        selectedPoiId = poiId;

        $('.poi-item').removeClass('active');
        $(`.poi-item[data-poi-id="${poiId}"]`).addClass('active');

        $('#btn-place-poi').prop('disabled', false);
        $('#btn-save-poi').prop('disabled', true);

        $('#editor-status').text('POI seleccionado. Haz clic en "Ubicar Punto" para moverlo.');

        const poi = poisData.find(p => p.id == poiId);
        if (poi) {
            currentPoiMarker = { ...poi }; // Clone
        }
    }

    function startPlacingPoi() {
        if (!selectedPoiId) {
            alert('Selecciona un POI primero');
            return;
        }

        isPlacingPoi = true;
        $('#btn-place-poi').text('🛑 Detener');
        $('#btn-save-poi').prop('disabled', false);
        $('#editor-status').text('Haz clic en el mapa/imagen para ubicar el punto');

        // Cargar ubicación actual si existe
        const poi = poisData.find(p => p.id == selectedPoiId);
        if (poi && poi.lat && poi.lng) {
             currentPoiMarker = { ...poi };
        } else {
             currentPoiMarker = null;
        }
    }

    function stopPlacingPoi() {
        isPlacingPoi = false;
        $('#btn-place-poi').text('📍 Ubicar Punto');
        $('#editor-status').text('Modo edición POI pausado');

        if (canvas) redrawCanvas();
    }

    function updatePoiLocation(lat, lng) {
        if (!isPlacingPoi) return;

        // Actualizar marcador temporal
        const poi = poisData.find(p => p.id == selectedPoiId);
        currentPoiMarker = {
            id: selectedPoiId,
            title: poi ? poi.title : 'POI',
            lat: lat,
            lng: lng,
            style: poi ? poi.style : 'default',
            custom_image_url: poi ? poi.custom_image_url : ''
        };

        if (map) {
             // Limpiar y redibujar
             document.querySelectorAll('.poi-marker-admin').forEach(el => el.remove());
             document.querySelectorAll('.poi-marker').forEach(el => el.remove());
             poisData.forEach(p => {
                 if(p.id == selectedPoiId) return; // No dibujar el original
                 renderSingleMapMarker(p);
             });
             renderSingleMapMarker(currentPoiMarker);
        }

        if (canvas) {
            redrawCanvas();
        }
    }

    function savePoiLocation() {
        if (!selectedPoiId || !currentPoiMarker) {
            return;
        }

        $('#btn-save-poi').prop('disabled', true).text('Guardando...');

        $.ajax({
            url: masterplanEditorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'masterplan_save_poi_location',
                nonce: masterplanEditorData.nonce,
                poi_id: selectedPoiId,
                lat: currentPoiMarker.lat,
                lng: currentPoiMarker.lng
            },
            success: function(response) {
                if (response.success) {
                    alert('Ubicación guardada');
                    // Actualizar datos locales
                    const idx = poisData.findIndex(p => p.id == selectedPoiId);
                    if (idx !== -1) {
                        poisData[idx].lat = currentPoiMarker.lat;
                        poisData[idx].lng = currentPoiMarker.lng;
                    }
                    stopPlacingPoi();
                    location.reload();
                } else {
                    alert('Error');
                }
            },
            complete: function() {
                $('#btn-save-poi').prop('disabled', false).text('💾 Guardar Ubicación');
            }
        });
    }

    function deletePoi(poiId) {
        $.ajax({
            url: masterplanEditorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'masterplan_delete_poi',
                nonce: masterplanEditorData.nonce,
                poi_id: poiId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar');
                }
            }
        });
    }

    // ========================================
    // MANEJO DE RUTAS
    // ========================================

    function selectRoute(rid) {
        selectedRouteId = rid;
        $('.route-item').removeClass('active');
        $(`.route-item[data-route-id="${rid}"]`).addClass('active');

        $('#btn-draw-route').prop('disabled', false);
        $('#btn-clear-route').prop('disabled', false);
        $('#editor-status').text('Ruta seleccionada');

        const route = routesData.find(r => r.id == rid);
        if (route && route.coordinates) {
            currentRoutePoints = [...route.coordinates];
        } else {
            currentRoutePoints = [];
        }
    }

    function startDrawingRoute() {
        if (!selectedRouteId) return;
        isDrawingRoute = true;
        currentRoutePoints = []; // Reset for new draw or continue? Usually reset or edit. Simple: reset.

        $('#btn-draw-route').text('🛑 Detener');
        $('#btn-save-route').prop('disabled', false);
        $('#editor-status').text('Trazando ruta... Click para añadir puntos');

        if (map) {
             if (map.getSource('temp-route')) {
                 map.removeLayer('temp-route-line');
                 map.removeSource('temp-route');
             }
        }
        if (canvas) redrawCanvas();
    }

    function stopDrawingRoute() {
        isDrawingRoute = false;
        $('#btn-draw-route').text('✏️ Trazar Ruta');
    }

    function updateTempRoute() {
        if (map) {
            const sourceId = 'temp-route';
            if (map.getSource(sourceId)) {
                map.getSource(sourceId).setData({
                    type: 'Feature', geometry: { type: 'LineString', coordinates: currentRoutePoints }
                });
            } else {
                map.addSource(sourceId, {
                    type: 'geojson',
                    data: { type: 'Feature', geometry: { type: 'LineString', coordinates: currentRoutePoints } }
                });
                map.addLayer({
                    id: sourceId + '-line', type: 'line', source: sourceId,
                    paint: { 'line-color': '#ffffff', 'line-width': 4, 'line-dasharray': [2, 1] }
                });
            }
        }
        if (canvas) redrawCanvas();
    }

    function clearRoute() {
        currentRoutePoints = [];
        if(map && map.getSource('temp-route')) {
            map.removeLayer('temp-route-line'); map.removeSource('temp-route');
        }
        if(canvas) redrawCanvas();
    }

    function saveRoute() {
        if (!selectedRouteId || currentRoutePoints.length < 2) return;
        $('#btn-save-route').prop('disabled', true).text('Guardando...');

        $.post(masterplanEditorData.ajaxUrl, {
            action: 'masterplan_save_route_path',
            nonce: masterplanEditorData.nonce,
            route_id: selectedRouteId,
            coordinates: JSON.stringify(currentRoutePoints)
        }, function(res) {
            if(res.success) location.reload(); else alert('Error');
        });
    }

    function renderMapRoutes() {
        routesData.forEach(route => {
             if(!route.coordinates || route.coordinates.length < 2) return;
             const sourceId = 'route-' + route.id;
             map.addSource(sourceId, {
                 type: 'geojson',
                 data: { type: 'Feature', geometry: { type: 'LineString', coordinates: route.coordinates } }
             });
             map.addLayer({
                 id: sourceId, type: 'line', source: sourceId,
                 paint: { 'line-color': route.color, 'line-width': parseInt(route.width) }
             });
        });
    }

    function renderImageRoutes() {
        if (!canvas) return;
        routesData.forEach(route => {
             if(!route.coordinates || route.coordinates.length < 2) return;
             // Don't draw current if drawing
             if (route.id == selectedRouteId && isDrawingRoute) return;
             drawRouteOnCanvas(route.coordinates, route.color, false, route.width);
        });
    }

    function drawRouteOnCanvas(points, color, isTemp, width) {
        if (points.length < 2) return;
        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        ctx.beginPath();
        points.forEach((point, index) => {
            const x = offsetX + (point[0] * drawWidth);
            const y = offsetY + (point[1] * drawHeight);
            if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        });

        ctx.strokeStyle = color;
        ctx.lineWidth = isTemp ? 2 : (width || 4);
        if (isTemp) ctx.setLineDash([5, 5]); else ctx.setLineDash([]);
        ctx.stroke();
    }

    // ========================================
    // PROJECT OVERLAYS LOGIC
    // ========================================

    function renderMapOverlays() {
        if (!map) return;
        overlaysData.forEach(overlay => {
             const sourceId = 'project-overlay-' + overlay.id;
             if (map.getSource(sourceId)) return;

             map.addSource(sourceId, {
                 type: 'image',
                 url: overlay.url,
                 coordinates: overlay.corners
             });
             // Add before lots to be background
             const beforeLayer = map.getLayer('lots-fill') ? 'lots-fill' : undefined;
             map.addLayer({
                 id: sourceId,
                 type: 'raster',
                 source: sourceId,
                 paint: { 'raster-opacity': parseFloat(overlay.opacity || 0.7) }
             }, beforeLayer);
        });
    }

    function addProjectOverlay(att) {
        if (!map) {
            alert('La función de Planos/Mapas solo está disponible en el Modo Mapa 3D actualmente.');
            return;
        }

        // Default corners
        const center = map.getCenter();
        const d = 0.002; // Bigger default for project map
        const corners = [
            [center.lng - d, center.lat + d],
            [center.lng + d, center.lat + d],
            [center.lng + d, center.lat - d],
            [center.lng - d, center.lat - d]
        ];

        const newOverlay = {
            id: 'temp_' + Date.now(), // Temp ID until saved? Or just use rand
            wp_id: att.id,
            url: att.url,
            title: att.title || 'Nuevo Plano',
            corners: corners,
            opacity: 0.7
        };

        // We save immediately to persist the structure
        saveProjectOverlayData(newOverlay, true);
    }

    function startEditingProjectOverlay(oid) {
        if (!map) {
            alert('La edición de Planos solo está disponible en el Modo Mapa 3D.');
            return;
        }

        const overlay = overlaysData.find(o => o.id == oid);
        if (!overlay) return;

        isEditingOverlay = true;
        selectedOverlayId = oid;
        overlayCorners = [...overlay.corners]; // Clone

        $('#btn-edit-overlay-pos').text('Done');
        $('#btn-save-overlay-project').prop('disabled', false);
        $('#editor-status').text('Arrastra las esquinas para ajustar el plano');
        $('#overlay-opacity-slider').val(overlay.opacity || 0.7);

        // Add handles
        updateProjectOverlayMap();
    }

    function stopEditingProjectOverlay() {
        isEditingOverlay = false;
        $('#btn-edit-overlay-pos').text('📐 Ajustar Esquinas');
        // Clean handles
        document.querySelectorAll('.overlay-handle').forEach(e => e.remove());
    }

    function updateProjectOverlayMap() {
        if (!map || !selectedOverlayId) return;
        const sourceId = 'project-overlay-' + selectedOverlayId;

        // 1. Update Image
        if (map.getSource(sourceId)) {
            map.getSource(sourceId).setCoordinates(overlayCorners);
        }

        // 2. Update Handles
        document.querySelectorAll('.overlay-handle').forEach(e => e.remove());
        overlayCorners.forEach((c, i) => {
            const el = document.createElement('div');
            el.className = 'overlay-handle';
            el.style.cssText = 'width: 20px; height: 20px; background: white; border: 3px solid #667eea; border-radius: 50%; cursor: move; box-shadow: 0 0 5px rgba(0,0,0,0.5);';
            new maplibregl.Marker(el, { draggable: false })
                .setLngLat(c)
                .addTo(map);
        });
    }

    function saveProjectOverlay() {
        if (!selectedOverlayId) return;
        const overlay = overlaysData.find(o => o.id == selectedOverlayId);
        if (!overlay) return;

        // Update local object
        overlay.corners = overlayCorners;
        overlay.opacity = $('#overlay-opacity-slider').val();

        saveProjectOverlayData(overlay, false);
    }

    function saveProjectOverlayData(overlay, isNew) {
        $('#btn-save-overlay-project').prop('disabled', true).text('Guardando...');

        $.post(masterplanEditorData.ajaxUrl, {
            action: 'masterplan_save_project_overlay',
            nonce: masterplanEditorData.nonce,
            project_id: masterplanEditorData.projectId,
            overlay_data: JSON.stringify(overlay)
        }, function(res) {
             $('#btn-save-overlay-project').prop('disabled', false).text('💾 Guardar Posición');
             if (res.success) {
                 if(isNew) location.reload();
                 else {
                     alert('Plano guardado');
                     stopEditingProjectOverlay();
                 }
             } else {
                 alert('Error');
             }
        });
    }

    function deleteProjectOverlay(oid) {
        $.post(masterplanEditorData.ajaxUrl, {
            action: 'masterplan_delete_project_overlay',
            nonce: masterplanEditorData.nonce,
            project_id: masterplanEditorData.projectId,
            overlay_id: oid
        }, function(res) {
             if(res.success) location.reload();
        });
    }


    // Inicializar
    init();
});
