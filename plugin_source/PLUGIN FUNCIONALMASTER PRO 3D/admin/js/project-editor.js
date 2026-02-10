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

    // Nuevas variables para POIs
    let currentMode = 'lot'; // 'lot' or 'poi'
    let isPlacingPoi = false;
    let selectedPoiId = masterplanEditorData.selectedPoiId;
    let poisData = masterplanEditorData.pois;
    let currentPoiLocation = null;
    let poiMarkerTemp = null;

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

        // Seleccionar POI si viene en URL
        if (selectedPoiId) {
            // Cambiar a tab de POIs
            $('.tab-item[data-tab="pois"]').click();
            selectPoi(selectedPoiId);
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
            renderMapPois();

        });

        // Click en mapa
        map.on('click', function (e) {
            if (currentMode === 'lot' && isDrawing) {
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
            } else if (currentMode === 'poi' && isPlacingPoi) {
                // Lógica de ubicación de POI
                placePoiMarkerMap(e.lngLat);
            }
        });
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
            if (!lot.coordinates || lot.coordinates.length < 3) return;

            const color = getStatusColor(lot.status);
            const sourceId = 'lot-' + lot.id;

            // Cerrar el polígono
            const coords = [...lot.coordinates, lot.coordinates[0]];

            map.addSource(sourceId, {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    properties: { id: lot.id, lot_number: lot.lot_number },
                    geometry: {
                        type: 'Polygon',
                        coordinates: [coords]
                    }
                }
            });

            map.addLayer({
                id: sourceId + '-fill',
                type: 'fill',
                source: sourceId,
                paint: {
                    'fill-color': color,
                    'fill-opacity': 0.4
                }
            });

            map.addLayer({
                id: sourceId + '-line',
                type: 'line',
                source: sourceId,
                paint: {
                    'line-color': color,
                    'line-width': 2
                }
            });

            // Marcador con número
            const centroid = calculateCentroid(lot.coordinates);
            const markerEl = document.createElement('div');
            markerEl.className = 'lot-marker-admin';
            markerEl.innerHTML = `<span class="marker-label">${lot.lot_number}</span>`;
            markerEl.style.cssText = `
                background: ${color};
                color: white;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                cursor: pointer;
                box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            `;

            new maplibregl.Marker(markerEl)
                .setLngLat(centroid)
                .addTo(map);
        });
    }

    function renderMapPois() {
        poisData.forEach(function (poi) {
            if (!poi.coordinates) return;

            const el = document.createElement('div');
            el.className = 'poi-marker-admin';
            el.innerHTML = '📍';
            el.style.cssText = 'font-size: 24px; cursor: pointer;';
            el.title = poi.title;

            // Al hacer click, seleccionar el POI
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                if(currentMode === 'poi') {
                    selectPoi(poi.id);
                }
            });

            new maplibregl.Marker({ element: el, anchor: 'bottom' })
                .setLngLat(poi.coordinates) // [lng, lat]
                .addTo(map);
        });
    }

    function placePoiMarkerMap(lngLat) {
        if (poiMarkerTemp) {
            poiMarkerTemp.remove();
        }

        const el = document.createElement('div');
        el.innerHTML = '🎯';
        el.style.cssText = 'font-size: 30px; cursor: pointer;';

        poiMarkerTemp = new maplibregl.Marker({ element: el, anchor: 'bottom', draggable: true })
            .setLngLat(lngLat)
            .addTo(map);

        // Guardar coordenadas
        currentPoiLocation = [lngLat.lat, lngLat.lng]; // [lat, lng]

        poiMarkerTemp.on('dragend', function() {
            const lngLat = poiMarkerTemp.getLngLat();
            currentPoiLocation = [lngLat.lat, lngLat.lng];
        });

        $('#btn-save-poi').prop('disabled', false);
        $('#editor-status').text('Ubicación establecida. Haz clic en "Guardar Ubicación"');
    }


    // ========================================
    // EDITOR DE IMAGEN
    // ========================================

    function initImageEditor() {
        canvas = document.getElementById('image-canvas');
        if (!canvas) return;

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
        renderImagePois();

        // Dibujar polígono temporal
        if (currentPoints.length > 0) {
            drawPolygonOnCanvas(currentPoints, '#667eea', true);
        }

        // Dibujar POI temporal
        if (poiMarkerTemp && currentMode === 'poi') {
            drawPoiOnCanvas(poiMarkerTemp, '#ef4444', true);
        }

    }

    function renderImagePolygons() {
        lotsData.forEach(function (lot) {
            if (!lot.coordinates || lot.coordinates.length < 3) return;
            if (lot.id === selectedLotId && isDrawing) return; // No dibujar el actual si estamos editando

            const color = getStatusColor(lot.status);
            drawPolygonOnCanvas(lot.coordinates, color, false, lot.lot_number);
        });
    }

    function renderImagePois() {
        poisData.forEach(function (poi) {
            if (!poi.coordinates) return;
            // Coords en imagen son [y, x] (lat, lng) -> pero guardamos como [lat, lng].
            // En map mode: lng(x), lat(y).
            // En image mode: guardamos lat=Y, lng=X (relativos 0-1).
            // Entonces poi.coordinates es [y, x].

            // Wait, in PHP:
            // $lat = get_post_meta($poi->ID, '_poi_lat', true); // Y
            // $lng = get_post_meta($poi->ID, '_poi_lng', true); // X
            // coordinates => [floatval($lat), floatval($lng)] => [Y, X]

            // Para dibujar necesitamos [X, Y].
            // X = poi.coordinates[1]
            // Y = poi.coordinates[0]

            const point = [poi.coordinates[1], poi.coordinates[0]];

            drawPoiOnCanvas(point, '#8b5cf6', false, poi.title);
        });
    }

    function drawPoiOnCanvas(point, color, isTemp, label) {
        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        const x = offsetX + (point[0] * drawWidth);
        const y = offsetY + (point[1] * drawHeight);

        ctx.beginPath();
        // Dibujar pin
        ctx.moveTo(x, y);
        ctx.lineTo(x - 10, y - 25);
        ctx.bezierCurveTo(x - 10, y - 40, x + 10, y - 40, x + 10, y - 25);
        ctx.lineTo(x, y);

        ctx.fillStyle = color;
        ctx.fill();
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(x, y - 25, 4, 0, Math.PI * 2);
        ctx.fillStyle = 'white';
        ctx.fill();

        // Etiqueta
        if (label) {
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            const textWidth = ctx.measureText(label).width;
            ctx.fillStyle = 'rgba(0,0,0,0.7)';
            ctx.beginPath();
            ctx.roundRect(x - textWidth / 2 - 5, y - 55, textWidth + 10, 20, 5);
            ctx.fill();

            ctx.fillStyle = 'white';
            ctx.fillText(label, x, y - 45);
        }
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
        if (!isDrawing && !isPlacingPoi) return;

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

        if (currentMode === 'lot' && isDrawing) {
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
        } else if (currentMode === 'poi' && isPlacingPoi) {
             // Guardamos [X, Y] para dibujar, pero guardamos [Y, X] en DB (lat, lng)
            poiMarkerTemp = [relX, relY];
            currentPoiLocation = [relY, relX]; // [Y=lat, X=lng]
            redrawCanvas();

            $('#btn-save-poi').prop('disabled', false);
            $('#editor-status').text('Ubicación establecida. Haz clic en "Guardar Ubicación"');
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
    // MANEJO DE PESTAÑAS
    // ========================================

    function bindEvents() {
        // Tabs Switching
        $('.tab-item').on('click', function() {
            $('.tab-item').removeClass('active');
            $(this).addClass('active');
            const tabId = $(this).data('tab');
            $('.tab-content').hide();
            $('#tab-content-' + tabId).show();

            currentMode = tabId === 'pois' ? 'poi' : 'lot';

            // Toggle controls
            if (currentMode === 'poi') {
                $('#controls-lots').hide();
                $('#controls-pois').show();
            } else {
                $('#controls-lots').show();
                $('#controls-pois').hide();
            }

            // Clean up states
            stopDrawing();
            stopPlacingPoi();

            // Refresh visuals (especially for canvas clicks)
            if (canvas) redrawCanvas();
        });

        // -------------------------
        // LOT EVENTS
        // -------------------------
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
        $('#btn-save-polygon').on('click', savePolygon);

        // Nuevo lote
        $('#btn-new-lot').on('click', function () {
            $('#new-lot-modal').show();
        });

        // -------------------------
        // POI EVENTS
        // -------------------------
        // Seleccionar POI
        $(document).on('click', '.poi-item', function (e) {
            if ($(e.target).closest('.poi-actions').length) return;
            selectPoi($(this).data('poi-id'));
        });

        // Ubicar desde lista
        $(document).on('click', '.poi-item .btn-draw-poi', function (e) {
            e.stopPropagation();
            const poiId = $(this).closest('.poi-item').data('poi-id');
            selectPoi(poiId);
            startPlacingPoi();
        });

        // Botón ubicar
        $('#btn-place-poi').on('click', function () {
            if (isPlacingPoi) {
                stopPlacingPoi();
            } else {
                startPlacingPoi();
            }
        });

        // Botón borrar POI
        $('#btn-clear-poi').on('click', clearPoiLocation);

        // Botón guardar POI
        $('#btn-save-poi').on('click', savePoiLocation);

        // Nuevo POI
        $('#btn-new-poi').on('click', function () {
            $('#new-poi-modal').show();
        });

        // -------------------------
        // MODAL & FORMS
        // -------------------------
        // Cerrar modal
        $(document).on('click', '.modal-close, .modal-overlay', function (e) {
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                $('#new-lot-modal').hide();
                $('#new-poi-modal').hide();
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
                        // Recargar página con POI seleccionado
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('poi_id', response.data.poi_id);
                        // Asegurar que lot_id se quita si existía
                        currentUrl.searchParams.delete('lot_id');
                        window.location.href = currentUrl.toString();
                    } else {
                        alert('Error: ' + (response.data.message || 'Error desconocido'));
                    }
                },
                complete: function () { $submit.prop('disabled', false).text('Crear Punto'); }
            });
        });
    }


    // ========================================
    // MANEJO DE LOTES (FUNCIONES AUXILIARES)
    // ========================================

    function selectLot(lotId) {
        selectedLotId = lotId;

        // UI: resaltar lote seleccionado
        $('.lot-item').removeClass('active');
        $(`.lot-item[data-lot-id="${lotId}"]`).addClass('active');

        // Habilitar controles
        $('#btn-draw-polygon').prop('disabled', false);
        $('#btn-clear-polygon').prop('disabled', false);
        $('#editor-status').text('Lote seleccionado: Haz clic en "Dibujar Polígono"');

        // Cargar puntos existentes si los hay
        const lot = lotsData.find(l => l.id == lotId);
        if (lot && lot.coordinates) {
            currentPoints = [...lot.coordinates];
        } else {
            currentPoints = [];
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
                    // Actualizar datos locales
                    const lotIndex = lotsData.findIndex(l => l.id == selectedLotId);
                    if (lotIndex !== -1) {
                        lotsData[lotIndex].coordinates = currentPoints;
                    }

                    alert('✅ Polígono guardado exitosamente');
                    $('#editor-status').text('Polígono guardado');

                    // Recargar página para refrescar visualización
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Error desconocido'));
                }
            },
            error: function () {
                alert('Error de conexión');
            },
            complete: function () {
                $('#btn-save-polygon').prop('disabled', false).text('💾 Guardar');
            }
        });
    }

    // ========================================
    // MANEJO DE POIs (FUNCIONES AUXILIARES)
    // ========================================

    function selectPoi(poiId) {
        selectedPoiId = poiId;

        // UI: resaltar POI seleccionado
        $('.poi-item').removeClass('active');
        $(`.poi-item[data-poi-id="${poiId}"]`).addClass('active');

        // Habilitar controles
        $('#btn-place-poi').prop('disabled', false);
        $('#btn-clear-poi').prop('disabled', false);
        $('#editor-status').text('POI seleccionado: Haz clic en "Ubicar Punto"');

        // Cargar ubicación existente si la hay
        const poi = poisData.find(p => p.id == poiId);
        if (poi && poi.coordinates) {
            // [lat, lng]
            currentPoiLocation = poi.coordinates;

            // Si estamos en modo Mapa
            if (map) {
                // Centrar mapa
                map.flyTo({ center: [poi.coordinates[1], poi.coordinates[0]] }); // [lng, lat]
                placePoiMarkerMap({ lat: poi.coordinates[0], lng: poi.coordinates[1] });
            }

            // Si estamos en modo Imagen
            if (canvas) {
                // Coordenadas en imagen son [y, x] -> guardamos como [y, x]
                // Dibujamos con drawPoiOnCanvas que espera [x, y]
                // poi.coordinates = [lat, lng]
                // En modo imagen, lat=Y, lng=X.

                // Setear marcador temporal para visualizar
                poiMarkerTemp = [poi.coordinates[1], poi.coordinates[0]]; // [X, Y]
                redrawCanvas();
            }
        } else {
            currentPoiLocation = null;
            if(map && poiMarkerTemp) poiMarkerTemp.remove();
            poiMarkerTemp = null;
            if(canvas) redrawCanvas();
        }
    }

    function startPlacingPoi() {
        if (!selectedPoiId) {
            alert('Selecciona un punto primero');
            return;
        }

        isPlacingPoi = true;

        $('#btn-place-poi').text('🛑 Detener');
        $('#btn-save-poi').prop('disabled', false);
        $('#editor-status').text('Ubicando... Haz clic en el mapa/imagen para poner el punto');
    }

    function stopPlacingPoi() {
        isPlacingPoi = false;
        $('#btn-place-poi').text('🎯 Ubicar Punto');
        $('#editor-status').text('Ubicación pausada');
    }

    function clearPoiLocation() {
        currentPoiLocation = null;
        if(map && poiMarkerTemp) poiMarkerTemp.remove();
        poiMarkerTemp = null;
        if(canvas) redrawCanvas();

        isPlacingPoi = false;
        $('#btn-place-poi').text('🎯 Ubicar Punto');
        $('#editor-status').text('Ubicación eliminada');
    }

    function savePoiLocation() {
        if (!selectedPoiId) {
            alert('Selecciona un punto primero');
            return;
        }

        if (!currentPoiLocation) {
            alert('Debes ubicar el punto en el mapa primero');
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
                lat: currentPoiLocation[0],
                lng: currentPoiLocation[1]
            },
            success: function (response) {
                if (response.success) {
                    // Actualizar datos locales
                    const poiIndex = poisData.findIndex(p => p.id == selectedPoiId);
                    if (poiIndex !== -1) {
                        poisData[poiIndex].coordinates = currentPoiLocation;
                    }

                    alert('✅ Ubicación guardada exitosamente');
                    $('#editor-status').text('Ubicación guardada');

                    // Recargar página para refrescar visualización
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Error desconocido'));
                }
            },
            error: function () {
                alert('Error de conexión');
            },
            complete: function () {
                $('#btn-save-poi').prop('disabled', false).text('💾 Guardar Ubicación');
            }
        });
    }

    // Inicializar
    init();
});
