/**
 * MasterPlan 3D Pro - Frontend Viewer
 * Visualización 3D de lotes en el frontend
 * Soporta modo mapa y modo imagen
 */

jQuery(document).ready(function ($) {
    'use strict';

    let map = null;
    let canvas = null;
    let ctx = null;
    let backgroundImage = null;
    let lotsData = [];
    let poisData = [];
    let routesData = [];

    // Configuración del proyecto (inyectada por PHP)
    const projectConfig = window.masterplanProjectConfig || {};
    const projectId = projectConfig.projectId || null;
    const useCustomImage = projectConfig.useCustomImage === true;
    const logoUrl = projectConfig.logoUrl || '';

    /**
     * Inicializar viewer según modo
     */
    function init() {
        // Inyectar HTML del Bottom Sheet
        if ($('#poi-bottom-sheet').length === 0) {
            $('body').append(`
                <div id="poi-bottom-sheet" class="poi-bottom-sheet">
                    <div class="poi-sheet-header">
                        <div class="poi-sheet-handle"></div>
                        <button class="poi-sheet-close">&times;</button>
                    </div>
                    <div class="poi-sheet-content" id="poi-sheet-content"></div>
                </div>
            `);

            $(document).on('click', '.poi-sheet-close', closePoiBottomSheet);
            // El overlay también cierra el bottom sheet si se clickea fuera
             $(document).on('click', '.masterplan-overlay', function() {
                 if ($('#poi-bottom-sheet').hasClass('active')) closePoiBottomSheet();
             });
        }

        // Render UI Controls
        renderUiControls();

        if (useCustomImage && projectConfig.customImageUrl) {
            initImageViewer();
        } else if ($('#masterplan-public-map').length) {
            initMap();
        }
    }

    function renderUiControls() {
        // Render Logo
        if (logoUrl) {
            $('#masterplan-logo-container').html(`<img src="${logoUrl}" class="masterplan-project-logo" alt="Project Logo">`);
        }

        // Bind Control Buttons
        $('#btn-toggle-project').on('click', function() {
            // Reset to initial view (Center of project)
            if (map) {
                map.flyTo({
                    center: [parseFloat(projectConfig.centerLng), parseFloat(projectConfig.centerLat)],
                    zoom: parseFloat(projectConfig.zoom),
                    pitch: 60,
                    bearing: 0,
                    essential: true
                });
            } else if (canvas) {
                // Reset canvas pan/zoom if implemented (currently static)
            }
        });

        $('#btn-toggle-routes').on('click', function() {
            $(this).toggleClass('active');
            const visible = $(this).hasClass('active');
            toggleRoutesVisibility(visible);
        });

        $('#btn-toggle-pois').on('click', function() {
            $(this).toggleClass('active');
            const visible = $(this).hasClass('active');
            togglePoisVisibility(visible);
        });
    }

    function toggleRoutesVisibility(visible) {
        if (map) {
            routesData.forEach(route => {
                const layerId = 'route-' + route.id;
                if (map.getLayer(layerId)) {
                    if (visible) {
                         // Reset and animate "drawing"
                         animateRouteDrawing(layerId, route.coordinates);
                    } else {
                         // Hide immediately or fade out
                         map.setPaintProperty(layerId, 'line-opacity', 0);
                    }
                }
            });
        } else if (canvas) {
            // For canvas, we just toggle redraw. Advanced animation on canvas is complex here.
            redrawCanvas();
        }
    }

    // Animation loop for MapLibre routes (simulating drawing)
    function animateRouteDrawing(layerId, coordinates) {
        // First make it visible but transparent or dashed
        map.setPaintProperty(layerId, 'line-opacity', 1);

        // We use a trick: line-dasharray to simulate drawing.
        // But MapLibre line-dasharray is static in some versions or hard to animate smoothly without a custom source update loop.
        // A simpler "Luxury" effect is a slow fade in + subtle dash movement or just a progress line.
        // Given constraints, we will use a requestAnimationFrame loop to update the 'data' of the source
        // to progressively add points (LineString growth).

        // Fix: Ensure sourceId matches the source ID used in displayRoutesOnMap (which is 'route-' + route.id)
        // layerId is also 'route-' + route.id
        const sourceId = layerId;
        const totalPoints = coordinates.length;
        if (totalPoints < 2) return;

        let currentIdx = 1;
        // Adjust speed: skip points if too many
        const step = Math.ceil(totalPoints / 100) || 1;

        function frame() {
            if (!map.getSource(sourceId)) return;

            currentIdx += step;
            if (currentIdx > totalPoints) currentIdx = totalPoints;

            const subset = coordinates.slice(0, currentIdx);

            map.getSource(sourceId).setData({
                type: 'Feature',
                geometry: {
                    type: 'LineString',
                    coordinates: subset
                }
            });

            if (currentIdx < totalPoints) {
                requestAnimationFrame(frame);
            }
        }

        frame();
    }

    function togglePoisVisibility(visible) {
        if (visible) {
            $('.poi-marker-wrapper, .poi-overlay-container').show();
        } else {
            $('.poi-marker-wrapper, .poi-overlay-container').hide();
        }
    }

    // ========================================
    // MODO MAPA 3D
    // ========================================

    function initMap() {
        if (!masterplanPublic.apiKey) {
            console.error('API key no configurada');
            return;
        }

        const centerLat = projectConfig.centerLat || parseFloat(masterplanPublic.centerLat);
        const centerLng = projectConfig.centerLng || parseFloat(masterplanPublic.centerLng);
        const zoom = projectConfig.zoom || parseInt(masterplanPublic.zoom);

        map = new maplibregl.Map({
            container: 'masterplan-public-map',
            style: `https://api.maptiler.com/maps/hybrid/style.json?key=${masterplanPublic.apiKey}`,
            center: [centerLng, centerLat],
            zoom: zoom,
            pitch: 60,
            bearing: 0,
            antialias: true
        });

        map.addControl(new maplibregl.NavigationControl({
            visualizePitch: true
        }), 'top-right');

        map.addControl(new maplibregl.ScaleControl({
            maxWidth: 200,
            unit: 'metric'
        }), 'bottom-left');

        map.on('load', function () {
            map.addSource('terrain', {
                type: 'raster-dem',
                url: `https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=${masterplanPublic.apiKey}`,
                tileSize: 256
            });

            map.setTerrain({
                source: 'terrain',
                exaggeration: 1.5
            });

            loadLots();
            loadPOIs();
            loadRoutes();

            // Zoom Scaling for POIs
            map.on('zoom', updatePoiScale);
            // Click on map to close POI sheet (since overlay is not active)
            map.on('click', function() {
                if ($('#poi-bottom-sheet').hasClass('active')) {
                    closePoiBottomSheet();
                }
            });
        });
    }


    // ========================================
    // MODO IMAGEN
    // ========================================

    function initImageViewer() {
        canvas = document.getElementById('masterplan-canvas');
        if (!canvas) return;

        // Crear contenedor de overlay para POIs si no existe
        const container = canvas.parentElement;
        if (!container.querySelector('.poi-overlay-container')) {
             const overlay = document.createElement('div');
             overlay.className = 'poi-overlay-container';
             overlay.id = 'poi-overlay-container';
             container.appendChild(overlay);
        }

        ctx = canvas.getContext('2d');
        backgroundImage = document.getElementById('masterplan-background');

        backgroundImage.onload = function () {
            resizeCanvas();
            loadLots();
            loadPOIs();
        };

        if (backgroundImage.complete) {
            resizeCanvas();
            loadLots();
            loadPOIs();
        }

        window.addEventListener('resize', resizeCanvas);
        canvas.addEventListener('click', onCanvasClick);
    }

    function resizeCanvas() {
        const container = canvas.parentElement;
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

        canvas.dataset.offsetX = (containerWidth - drawWidth) / 2;
        canvas.dataset.offsetY = (containerHeight - drawHeight) / 2;
        canvas.dataset.drawWidth = drawWidth;
        canvas.dataset.drawHeight = drawHeight;

        redrawCanvas();
    }

    function redrawCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        ctx.drawImage(backgroundImage, offsetX, offsetY, drawWidth, drawHeight);

        // Dibujar lotes
        lotsData.forEach(lot => {
            if (!lot.coordinates || lot.coordinates.length < 3) return;
            drawLotOnCanvas(lot);
        });

        // Dibujar Rutas (si están activas)
        if ($('#btn-toggle-routes').hasClass('active')) {
            drawRoutesOnCanvas();
        }

        // Actualizar posiciones de POIs en Overlay
        updatePoiOverlayPositions();
    }

    function drawRoutesOnCanvas() {
        if (!routesData || routesData.length === 0) return;
        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        routesData.forEach(route => {
            if (!route.coordinates || route.coordinates.length < 2) return;

            ctx.beginPath();
            route.coordinates.forEach((point, index) => {
                const x = offsetX + (point[0] * drawWidth);
                const y = offsetY + (point[1] * drawHeight);
                if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.strokeStyle = route.color;
            ctx.lineWidth = route.width;
            ctx.stroke();
        });
    }

    // Ya no dibujamos POIs en el canvas, usamos DOM Overlay
    function updatePoiOverlayPositions() {
        const overlay = document.getElementById('poi-overlay-container');
        if (!overlay) return;

        if (overlay.children.length !== poisData.length) {
            overlay.innerHTML = '';
            poisData.forEach(poi => {
                if (!poi.lat || !poi.lng) return;
                const el = createPoiMarkerElement(poi);
                el.dataset.poiId = poi.id;

                // Evento Click
                el.addEventListener('click', function(e) {
                    e.stopPropagation(); // Evitar click en canvas
                    openPoiBottomSheet(poi);
                });

                overlay.appendChild(el);
            });
        }

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        // Actualizar posiciones
        Array.from(overlay.children).forEach(el => {
            const poiId = el.dataset.poiId;
            const poi = poisData.find(p => p.id == poiId);
            if (poi) {
                const x = offsetX + (parseFloat(poi.lng) * drawWidth);
                const y = offsetY + (parseFloat(poi.lat) * drawHeight);
                el.style.left = x + 'px';
                el.style.top = y + 'px';
                // Centrar horizontalmente es handled por CSS transform/flex, pero bottom anchor es default
                // CSS .poi-marker { transform: translate(-50%, -100%); } si anchor es bottom center
                // Nuestro CSS usa flex column justify-end, pero necesitamos posicionar el contenedor.
                // Ajustaremos con transform.
                el.style.transform = 'translate(-50%, -100%)';
            }
        });
    }

    /**
     * Cargar Rutas desde la API
     */
    function loadRoutes() {
        let url = masterplanPublic.apiUrl + 'routes';
        if (projectId) {
            url += '?project_id=' + projectId;
        }

        $.ajax({
            url: url,
            type: 'GET',
            success: function (routes) {
                routesData = routes;
                if (useCustomImage && canvas) {
                    redrawCanvas();
                } else if (map) {
                    displayRoutesOnMap(routes);
                }
            },
            error: function () {
                console.error('Error al cargar Rutas');
            }
        });
    }

    function displayRoutesOnMap(routes) {
        routes.forEach(route => {
             if(!route.coordinates || route.coordinates.length < 2) return;
             const sourceId = 'route-' + route.id;

             // Check initial state
             const isVisible = $('#btn-toggle-routes').hasClass('active');

             map.addSource(sourceId, {
                 type: 'geojson',
                 // If visible initially, show full. If not, we might animate later.
                 data: { type: 'Feature', geometry: { type: 'LineString', coordinates: isVisible ? route.coordinates : [] } }
             });

             map.addLayer({
                 id: sourceId, type: 'line', source: sourceId,
                 paint: {
                     'line-color': route.color,
                     'line-width': parseInt(route.width),
                     'line-opacity': isVisible ? 1 : 0,
                     'line-blur': 1 // Soft edge for "glow" feel
                 },
                 layout: { 'line-join': 'round', 'line-cap': 'round' }
             });
        });
    }

    function drawLotOnCanvas(lot) {
        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        const color = getStatusColor(lot.status);
        const points = lot.coordinates;

        ctx.beginPath();
        points.forEach((point, index) => {
            const x = offsetX + (point[0] * drawWidth);
            const y = offsetY + (point[1] * drawHeight);
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.closePath();

        ctx.fillStyle = color + 'aa';
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Etiqueta con número
        if (lot.lot_number) {
            const centroid = calculateCentroid(points);
            const cx = offsetX + (centroid[0] * drawWidth);
            const cy = offsetY + (centroid[1] * drawHeight);

            // Efecto pulse
            const pulseSize = 30 + Math.sin(Date.now() / 500) * 5;
            ctx.beginPath();
            ctx.arc(cx, cy, pulseSize / 2, 0, Math.PI * 2);
            ctx.fillStyle = color + '66';
            ctx.fill();

            // Badge
            ctx.font = 'bold 12px Arial';
            const textWidth = ctx.measureText(lot.lot_number).width;
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.roundRect(cx - textWidth / 2 - 10, cy - 12, textWidth + 20, 24, 12);
            ctx.fill();

            ctx.fillStyle = 'white';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(lot.lot_number, cx, cy);
        }
    }

    function onCanvasClick(e) {
        // En Modo Imagen, los POIs ahora son DOM elements y capturan su propio click.
        // Solo necesitamos detectar clicks en lotes aquí.

        const rect = canvas.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        // Convertir a coordenadas relativas para lotes
        const relX = (clickX - offsetX) / drawWidth;
        const relY = (clickY - offsetY) / drawHeight;

        // Buscar lote clickeado
        const clickedLot = lotsData.find(lot => {
            if (!lot.coordinates || lot.coordinates.length < 3) return false;
            return isPointInPolygon([relX, relY], lot.coordinates);
        });

        if (clickedLot) {
            openSidebar(clickedLot.id);
        }
    }

    function isPointInPolygon(point, polygon) {
        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i][0], yi = polygon[i][1];
            const xj = polygon[j][0], yj = polygon[j][1];

            if (((yi > point[1]) !== (yj > point[1])) &&
                (point[0] < (xj - xi) * (point[1] - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }
        return inside;
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
        return colors[status] || '#64748b';
    }

    function calculateCentroid(coordinates) {
        let xSum = 0, ySum = 0;
        const numPoints = coordinates.length;

        for (let i = 0; i < numPoints; i++) {
            xSum += coordinates[i][0];
            ySum += coordinates[i][1];
        }

        return [xSum / numPoints, ySum / numPoints];
    }

    /**
     * Cargar lotes desde la API
     */
    function loadLots() {
        let url = masterplanPublic.apiUrl + 'lots';
        if (projectId) {
            url += '?project_id=' + projectId;
        }

        $.ajax({
            url: url,
            type: 'GET',
            success: function (lots) {
                lotsData = lots;

                if (useCustomImage && canvas) {
                    redrawCanvas();
                    // Animar pulse
                    setInterval(redrawCanvas, 100);
                } else if (map) {
                    displayLotsOnMap(lots);
                }
            },
            error: function () {
                console.error('Error al cargar lotes');
            }
        });
    }

    /**
     * Cargar POIs desde la API
     */
    function loadPOIs() {
        let url = masterplanPublic.apiUrl + 'pois';
        if (projectId) {
            url += '?project_id=' + projectId;
        }

        $.ajax({
            url: url,
            type: 'GET',
            success: function (pois) {
                poisData = pois;

                if (useCustomImage && canvas) {
                    redrawCanvas();
                } else if (map) {
                    displayPOIsOnMap(pois);
                }
            },
            error: function () {
                console.error('Error al cargar POIs');
            }
        });
    }

    /**
     * Mostrar lotes en el mapa
     */
    function displayLotsOnMap(lots) {
        if (!lots || lots.length === 0) {
            console.warn('No hay lotes para mostrar');
            return;
        }

        const features = lots.map(lot => ({
            type: 'Feature',
            id: lot.id,
            properties: {
                id: lot.id,
                title: lot.title,
                lot_number: lot.lot_number,
                status: lot.status,
                price: lot.price,
                area: lot.area
            },
            geometry: {
                type: 'Polygon',
                coordinates: [lot.coordinates]
            }
        }));

        map.addSource('lots', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: features
            }
        });

        map.addLayer({
            id: 'lots-fill',
            type: 'fill',
            source: 'lots',
            paint: {
                'fill-color': [
                    'match',
                    ['get', 'status'],
                    'disponible', '#10b981',
                    'reservado', '#f59e0b',
                    'vendido', '#ef4444',
                    '#64748b'
                ],
                'fill-opacity': 0.6
            }
        });

        map.addLayer({
            id: 'lots-outline',
            type: 'line',
            source: 'lots',
            paint: {
                'line-color': '#ffffff',
                'line-width': 2
            }
        });

        map.addLayer({
            id: 'lots-outline-hover',
            type: 'line',
            source: 'lots',
            paint: {
                'line-color': '#fbbf24',
                'line-width': 4,
                'line-opacity': [
                    'case',
                    ['boolean', ['feature-state', 'hover'], false],
                    1,
                    0
                ]
            }
        });

        addLotMarkers(lots);

        map.on('click', 'lots-fill', function (e) {
            const lotId = e.features[0].properties.id;
            openSidebar(lotId);
        });

        let hoveredLotId = null;
        map.on('mousemove', 'lots-fill', function (e) {
            map.getCanvas().style.cursor = 'pointer';
            if (e.features.length > 0) {
                if (hoveredLotId !== null) {
                    map.setFeatureState({ source: 'lots', id: hoveredLotId }, { hover: false });
                }
                hoveredLotId = e.features[0].id;
                map.setFeatureState({ source: 'lots', id: hoveredLotId }, { hover: true });
            }
        });

        map.on('mouseleave', 'lots-fill', function () {
            map.getCanvas().style.cursor = '';
            if (hoveredLotId !== null) {
                map.setFeatureState({ source: 'lots', id: hoveredLotId }, { hover: false });
            }
            hoveredLotId = null;
        });
    }

    function addLotMarkers(lots) {
        lots.forEach(lot => {
            const centroid = calculateCentroid(lot.coordinates);

            const el = document.createElement('div');
            el.className = 'lot-marker';
            el.innerHTML = `
                <div class="marker-pulse"></div>
                <div class="marker-number">${lot.lot_number}</div>
            `;

            new maplibregl.Marker({ element: el, anchor: 'center' })
                .setLngLat(centroid)
                .addTo(map);
        });
    }

    function displayPOIsOnMap(pois) {
        pois.forEach(poi => {
            if (!poi.lat || !poi.lng) return;

            const el = createPoiMarkerElement(poi);

            // En MapLibre, usamos el marcador DOM directamente
            const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                .setLngLat([parseFloat(poi.lng), parseFloat(poi.lat)])
                .addTo(map);

            // Click Handler para Bottom Sheet & Auto-Center
            el.addEventListener('click', (e) => {
                e.stopPropagation();

                // Fly to functionality
                map.flyTo({
                    center: [parseFloat(poi.lng), parseFloat(poi.lat)],
                    zoom: 17, // Closer zoom for better view
                    essential: true,
                    speed: 1.5,
                    curve: 1
                });

                openPoiBottomSheet(poi);
            });
        });
    }

    /**
     * Crea el elemento DOM para el marcador basado en el estilo
     */
    function createPoiMarkerElement(poi) {
        const wrapper = document.createElement('div');
        wrapper.className = `poi-marker-wrapper`; // Wrapper for MapLibre positioning
        // MapLibre will move this wrapper. We scale the INNER child.

        const el = document.createElement('div');
        el.className = `poi-marker style-${poi.style || 'default'} poi-scalable-content`;
        el.title = poi.title;

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

        wrapper.appendChild(el);
        return wrapper;
    }

    // Helper to update scale
    function updatePoiScale() {
        if (!map) return;
        const zoom = map.getZoom();
        // Scale logic: Reduced slightly as requested (smaller start)
        // 0.3 at zoom 12, 0.8 at zoom 17
        let scale = 0.3 + 0.5 * ((zoom - 12) / (17 - 12));
        scale = Math.max(0.3, Math.min(0.9, scale));

        // Apply scale ONLY, no translation. MapLibre handles the position of the wrapper.
        // The wrapper is anchored at 'bottom', so the bottom-center of the wrapper is at the lat/lng.
        // We scale the content relative to that bottom-center anchor.
        $('.poi-scalable-content').css({
            'transform': `scale(${scale})`,
            'transform-origin': 'bottom center',
            'transition': 'transform 0.1s cubic-bezier(0.4, 0, 0.2, 1)'
        });
    }

    /**
     * Abrir Bottom Sheet con información del POI
     */
    function openPoiBottomSheet(poi) {
        const typeLabels = {
            'info': 'ℹ️ Información',
            'park': '🌳 Parque / Zona Verde',
            'facility': '🏢 Instalación / Amenidad',
            'entrance': '🚪 Acceso / Portería'
        };

        // Calcular Distancia (Solo Map Mode)
        let distanceHtml = '';
        if (map && projectConfig.centerLat && projectConfig.centerLng) {
            const dist = getDistanceFromLatLonInKm(
                parseFloat(projectConfig.centerLat),
                parseFloat(projectConfig.centerLng),
                parseFloat(poi.lat),
                parseFloat(poi.lng)
            );

            const distText = dist < 1
                ? `${Math.round(dist * 1000)} metros`
                : `${dist.toFixed(2)} km`;

            distanceHtml = `
                <div class="poi-sheet-distance">
                    <span>📏</span> A ${distText} del proyecto
                </div>
            `;
        }

        const hasImage = !!poi.thumbnail;

        const sheetHTML = `
            <div class="poi-content-grid ${hasImage ? 'has-image' : ''}">
                ${poi.thumbnail ? `
                    <div class="poi-sheet-image-wrapper">
                        <img src="${poi.thumbnail}" class="poi-sheet-image" alt="${poi.title}">
                    </div>
                ` : ''}

                <div class="poi-details-wrapper">
                    <div class="poi-sheet-category">${typeLabels[poi.type] || poi.type}</div>
                    <h2 class="poi-sheet-title">${poi.title}</h2>

                    <div class="poi-sheet-meta">
                        ${distanceHtml}
                    </div>

                    ${poi.description ? `<p style="color:#444; line-height:1.5; font-size: 14px; margin-top:10px;">${poi.description}</p>` : ''}
                </div>
            </div>
        `;

        $('#poi-sheet-content').html(sheetHTML);
        $('#poi-bottom-sheet').addClass('active');
        // REMOVED: $('#masterplan-overlay').addClass('active'); // Don't darken map
    }

    function closePoiBottomSheet() {
        $('#poi-bottom-sheet').removeClass('active');
        $('#masterplan-overlay').removeClass('active');
    }

    // Haversine Formula
    function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
        var R = 6371; // Radius of the earth in km
        var dLat = deg2rad(lat2 - lat1);
        var dLon = deg2rad(lon2 - lon1);
        var a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        var d = R * c; // Distance in km
        return d;
    }

    function deg2rad(deg) {
        return deg * (Math.PI / 180);
    }

    function openSidebar(lotId) {
        const lot = lotsData.find(l => l.id === lotId);
        if (!lot) return;

        // Formatear precio COP
        const formattedPrice = `$ ${new Intl.NumberFormat('es-CO').format(lot.price)} COP`;

        const statusLabels = {
            'disponible': '🟢 Disponible',
            'reservado': '🟡 Reservado',
            'vendido': '🔴 Vendido'
        };

        const statusColors = {
            'disponible': '#10b981',
            'reservado': '#f59e0b',
            'vendido': '#ef4444'
        };

        const projectInfo = lot.project ? `<p class="lot-project">📍 ${lot.project.title}</p>` : '';

        const sidebarHTML = `
            <div class="lot-detail">
                ${lot.thumbnail ? `<img src="${lot.thumbnail}" alt="${lot.title}" class="lot-image">` : ''}

                <div class="lot-header">
                    <span class="lot-number-badge">${lot.lot_number}</span>
                    <h2 class="lot-title">${lot.title}</h2>
                    ${projectInfo}
                </div>

                <div class="lot-status" style="background-color: ${statusColors[lot.status]};">
                    ${statusLabels[lot.status]}
                </div>

                <div class="lot-info">
                    <div class="info-row">
                        <span class="info-label">💰 Precio:</span>
                        <span class="info-value price">${formattedPrice}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📐 Área:</span>
                        <span class="info-value">${lot.area} m²</span>
                    </div>
                </div>

                ${lot.excerpt ? `<div class="lot-description">${lot.excerpt}</div>` : ''}

                <div class="lot-actions">
                    ${masterplanPublic.whatsappNumber ? `
                        <a href="${generateWhatsAppLink(lot)}" target="_blank" class="btn btn-whatsapp">
                            💬 Consultar por WhatsApp
                        </a>
                    ` : ''}

                    <button class="btn btn-contact" onclick="showContactForm(${lot.id})">
                        📧 Enviar Consulta por Email
                    </button>
                </div>

                <div id="contact-form-container" class="contact-form-container" style="display:none;">
                    <h3>✉️ Enviar Consulta</h3>
                    <form id="contact-form" data-lot-id="${lot.id}">
                        <input type="text" name="name" placeholder="Tu Nombre *" required>
                        <input type="email" name="email" placeholder="Tu Email *" required>
                        <input type="tel" name="phone" placeholder="Tu Teléfono (+57...) *" required>
                        <textarea name="message" placeholder="Mensaje (opcional)" rows="4"></textarea>
                        <button type="submit" class="btn btn-primary btn-submit">Enviar Consulta</button>
                    </form>
                </div>
            </div>
        `;

        $('#sidebar-content').html(sidebarHTML);
        $('#masterplan-sidebar').addClass('active');
        $('#masterplan-overlay').addClass('active');
    }

    function generateWhatsAppLink(lot) {
        const phone = masterplanPublic.whatsappNumber.replace(/[^0-9]/g, '');
        const projectName = lot.project ? ` en ${lot.project.title}` : '';
        const message = `Hola! Estoy interesado en el Lote ${lot.lot_number}${projectName}. ¿Podrían darme más información?`;
        return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    }

    function closeSidebar() {
        $('#masterplan-sidebar').removeClass('active');
        $('#masterplan-overlay').removeClass('active');
    }

    $(document).on('click', '#sidebar-close-btn', closeSidebar);

    // Remover click en overlay para sidebar si es necesario,
    // pero como ahora usamos el overlay para cerrar también el bottom sheet,
    // y la función closeSidebar también remueve .active del overlay,
    // podemos dejar que closeSidebar maneje su parte y closePoiBottomSheet la suya.
    // Solo hay que asegurar que no se solapen conflictos.
    // El click en overlay en init() llama a closePoiBottomSheet.
    // Añadiremos handler de overlay para sidebar aquí para seguridad si no estaba.
    $(document).on('click', '#masterplan-overlay', function() {
        if ($('#masterplan-sidebar').hasClass('active')) closeSidebar();
    });


    window.showContactForm = function (lotId) {
        $('#contact-form-container').slideDown();
    };

    $(document).on('submit', '#contact-form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const lotId = $form.data('lot-id');
        const $submitBtn = $form.find('.btn-submit');

        $submitBtn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: masterplanPublic.apiUrl + 'contact',
            type: 'POST',
            data: {
                lot_id: lotId,
                name: $form.find('[name="name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val(),
                message: $form.find('[name="message"]').val(),
                nonce: masterplanPublic.nonce
            },
            success: function (response) {
                // Mensaje de éxito estilizado
                $('#contact-form-container').html(`
                    <div style="text-align: center; padding: 30px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
                        <h3 style="color: #10b981; margin: 0 0 10px;">¡Consulta Enviada!</h3>
                        <p style="color: #64748b; margin: 0;">Revisa tu email. Un asesor te contactará pronto.</p>
                    </div>
                `);
            },
            error: function (xhr) {
                const response = xhr.responseJSON;
                alert(response.message || 'Error al enviar la consulta');
                $submitBtn.prop('disabled', false).text('Enviar Consulta');
            }
        });
    });

    // Inicializar
    init();
});
