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
    let isPlacingPOI = false;
    let currentPoints = [];
    let selectedLotId = masterplanEditorData.selectedLotId;
    let lotsData = masterplanEditorData.lots;
    let poisData = masterplanEditorData.pois || [];
    let activeTab = 'lots'; // 'lots' or 'pois'

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
        bindPOIEvents();
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

            // Renderizar POIs
            renderMapPOIs();
        });

        // Click en mapa
        map.on('click', function (e) {
            if (isDrawing) {
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
            } else if (isPlacingPOI) {
                // Lógica de ubicación de POI
                updatePOILocation(e.lngLat.lat, e.lngLat.lng);
                addTempPOIMarker(e.lngLat);
                isPlacingPOI = false;
                $('#poi-mode-status').text('Ubicación establecida. Puedes guardar el POI.');
                // Reabrir modal si estaba cerrado (opcional)
                $('#poi-modal').show();
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

    function renderMapPOIs() {
        if (!map) return;

        poisData.forEach(function(poi) {
            if (!poi.lat || !poi.lng) return;

            const el = document.createElement('div');
            el.className = 'poi-marker-admin';

            if (poi.logo_url) {
                el.style.backgroundImage = `url(${poi.logo_url})`;
                el.style.backgroundColor = 'transparent';
            } else {
                el.style.backgroundColor = poi.color || '#3b82f6';
            }

            el.style.width = '32px';
            el.style.height = '32px';
            el.style.backgroundSize = 'cover';
            el.style.backgroundPosition = 'center';
            el.style.borderRadius = '50%';
            el.style.border = `2px solid white`;
            el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';
            el.style.cursor = 'move'; // Indicador de arrastre

            const marker = new maplibregl.Marker({ element: el, draggable: true })
                .setLngLat([poi.lng, poi.lat])
                .addTo(map);

            // Evento al arrastrar marker
            marker.on('dragend', function() {
                const lngLat = marker.getLngLat();
                // Actualizar en el form si está abierto el mismo POI
                if ($('#poi_id').val() == poi.id) {
                    updatePOILocation(lngLat.lat, lngLat.lng);
                } else {
                    // Si no, preguntar si quiere editar este POI
                    if(confirm('¿Quieres actualizar la ubicación de este POI?')) {
                        editPOI(poi.id);
                        updatePOILocation(lngLat.lat, lngLat.lng);
                    } else {
                        // Revertir (complicado sin recargar, dejamos así por ahora)
                    }
                }
            });

            // Click para editar
            el.addEventListener('click', function(e) {
                // e.stopPropagation(); // MapLibre maneja esto
                editPOI(poi.id);
            });
        });
    }

    let tempPOIMarker = null;
    function addTempPOIMarker(lngLat) {
        if (tempPOIMarker) tempPOIMarker.remove();

        const el = document.createElement('div');
        el.style.width = '32px';
        el.style.height = '32px';
        el.style.backgroundColor = '#f59e0b';
        el.style.borderRadius = '50%';
        el.style.border = '2px solid white';

        tempPOIMarker = new maplibregl.Marker({ element: el, draggable: true })
            .setLngLat(lngLat)
            .addTo(map);

        tempPOIMarker.on('dragend', function() {
            const pos = tempPOIMarker.getLngLat();
            updatePOILocation(pos.lat, pos.lng);
        });
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

        // Dibujar polígono temporal
        if (currentPoints.length > 0) {
            drawPolygonOnCanvas(currentPoints, '#667eea', true);
        }

        // Dibujar POIs en modo imagen
        renderImagePOIs();

        // Dibujar marcador temporal de POI
        if (isPlacingPOI) {
            // Si hay un marcador temporal, dibujarlo (se implementará si es necesario visualmente)
        }
    }

    function renderImagePOIs() {
        if (!poisData || poisData.length === 0) return;

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        poisData.forEach(function(poi) {
            if (!poi.lat || !poi.lng) return;

            // En modo imagen, lat=Y, lng=X (0-1)
            const x = offsetX + (poi.lng * drawWidth);
            const y = offsetY + (poi.lat * drawHeight);

            // Dibujar POI
            const size = 24;

            ctx.beginPath();
            ctx.arc(x, y, size/2, 0, Math.PI * 2);
            ctx.fillStyle = poi.color || '#3b82f6';
            ctx.fill();
            ctx.strokeStyle = 'white';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Icono simple (letra P o icono)
            ctx.fillStyle = 'white';
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('P', x, y);

            // Si es el seleccionado en el formulario, resaltar
            if ($('#poi_id').val() == poi.id) {
                ctx.beginPath();
                ctx.arc(x, y, size/2 + 4, 0, Math.PI * 2);
                ctx.strokeStyle = '#fbbf24'; // Amarillo
                ctx.lineWidth = 3;
                ctx.stroke();
            }
        });
    }

    function renderImagePolygons() {
        lotsData.forEach(function (lot) {
            if (!lot.coordinates || lot.coordinates.length < 3) return;
            if (lot.id === selectedLotId && isDrawing) return; // No dibujar el actual si estamos editando

            const color = getStatusColor(lot.status);
            drawPolygonOnCanvas(lot.coordinates, color, false, lot.lot_number);
        });
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

        // Verificar si se clickeó un POI (prioridad si no se está dibujando/colocando)
        if (!isDrawing && !isPlacingPOI && poisData && poisData.length > 0) {
            const clickedPOI = poisData.find(poi => {
                if (!poi.lat || !poi.lng) return false;

                // Coordenadas en pantalla
                const px = offsetX + (poi.lng * drawWidth);
                const py = offsetY + (poi.lat * drawHeight);

                // Radio de click (15px)
                const dist = Math.sqrt(Math.pow(clickX - px, 2) + Math.pow(clickY - py, 2));
                return dist <= 15;
            });

            if (clickedPOI) {
                editPOI(clickedPOI.id);
                // Resaltar selección
                redrawCanvas();
                return;
            }
        }

        if (!isDrawing && !isPlacingPOI) return;

        // Convertir a coordenadas relativas (0-1)
        const relX = (clickX - offsetX) / drawWidth;
        const relY = (clickY - offsetY) / drawHeight;

        if (isDrawing) {
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
        } else if (isPlacingPOI) {
            // En modo imagen, lat/lng son relativos 0-1
            updatePOILocation(relY, relX);
            // TODO: Dibujar marcador temporal en canvas
            isPlacingPOI = false;
            $('#poi-mode-status').text('Ubicación establecida. Puedes guardar el POI.');
            $('#poi-modal').show();
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
    // MANEJO DE PESTAÑAS (Lots vs POIs)
    // ========================================

    function bindEvents() {
        // Tabs
        $('.tab-item').on('click', function() {
            $('.tab-item').removeClass('active');
            $(this).addClass('active');

            const tab = $(this).data('tab');
            activeTab = tab;
            $('.tab-content').hide();
            $('#tab-content-' + tab).show();

            // Switch controls
            if (tab === 'lots') {
                $('#controls-lots').show();
                $('#controls-pois').hide();
            } else {
                $('#controls-lots').hide();
                $('#controls-pois').show();
            }
        });

        // ================= Lots Logic =================

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

        // Cerrar modal
        $(document).on('click', '.modal-close, .modal-overlay', function (e) {
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                $('#new-lot-modal').hide();
                $('#poi-modal').hide();
                stopDrawing();
                isPlacingPOI = false;
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
    }

    // ========================================
    // MANEJO DE POIs
    // ========================================

    function bindPOIEvents() {
        // Nuevo POI
        $('#btn-new-poi').on('click', function() {
            openPOIModal();
        });

        // Editar POI
        $(document).on('click', '.btn-edit-poi, .poi-item', function(e) {
            e.stopPropagation();
            const id = $(this).closest('.poi-item').data('poi-id');
            editPOI(id);
        });

        // Eliminar POI
        $(document).on('click', '.btn-delete-poi', function(e) {
            e.stopPropagation();
            const id = $(this).closest('.poi-item').data('poi-id');
            if(confirm('¿Seguro que deseas eliminar este POI?')) {
                deletePOI(id);
            }
        });

        // Ubicar POI
        $(document).on('click', '.btn-locate-poi', function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.poi-item');
            const id = $item.data('poi-id');
            const lat = $item.data('poi-lat');
            const lng = $item.data('poi-lng');

            if (lat && lng && map) {
                map.flyTo({ center: [lng, lat], zoom: 18 });
            } else {
                alert('POI sin ubicación. Edítalo para ubicarlo.');
            }
        });

        // Botón Ubicar en Mapa (dentro del modal)
        $(document).on('click', '.btn-locate-on-map', function() {
            // No implementado botón específico, se hace cerrando modal y clickeando mapa
            // Pero podemos agregar lógica para ocultar modal y activar modo
        });

        // Input Click en mapa para ubicar
        $('#poi_lat, #poi_lng').on('click', function() {
            $('#poi-modal').hide();
            startPlacingPOI();
        });

        // Formulario Guardar POI
        $('#poi-form').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);

            $.ajax({
                url: masterplanEditorData.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=masterplan_save_poi&nonce=' + masterplanEditorData.nonce,
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        });

        // Subir Logo
        $('#btn-upload-logo').on('click', function(e) {
            e.preventDefault();
            var image = wp.media({
                title: 'Seleccionar Logo',
                multiple: false
            }).open()
            .on('select', function(e){
                var uploaded_image = image.state().get('selection').first();
                var image_url = uploaded_image.toJSON().url;
                var image_id = uploaded_image.toJSON().id;

                $('#poi_logo_id').val(image_id);
                $('#poi-logo-preview').css('background-image', 'url(' + image_url + ')').show();
                $('.delete-logo').show();
            });
        });

        $('.delete-logo').on('click', function() {
            $('#poi_logo_id').val('');
            $('#poi-logo-preview').hide();
            $(this).hide();
        });
    }

    function openPOIModal(poi = null) {
        if (poi) {
            $('#poi-modal-title').text('🚩 Editar POI: ' + poi.title);
            $('#poi_id').val(poi.id);
            $('#poi_title').val(poi.title);
            $('#poi_description').val(poi.excerpt); // Usamos excerpt como descripción
            $('#poi_lat').val(poi.lat);
            $('#poi_lng').val(poi.lng);
            $('#poi_viz_type').val(poi.viz_type);
            $('#poi_color').val(poi.color);

            if (poi.logo_url) {
                // Necesitamos el ID, pero en lots_data solo tenemos URL.
                // Idealmente el backend debe mandar el ID. Si no, solo mostramos preview.
                // Para simplificar, asumimos que si editas sin cambiar imagen, se mantiene.
                // (Backend debe manejar eso, o pasamos el ID en poisData)
                $('#poi-logo-preview').css('background-image', 'url(' + poi.logo_url + ')').show();
            } else {
                $('#poi-logo-preview').hide();
            }
        } else {
            $('#poi-modal-title').text('🚩 Nuevo POI');
            $('#poi-form')[0].reset();
            $('#poi_id').val('');
            $('#poi-logo-preview').hide();
        }
        $('#poi-modal').show();
    }

    function editPOI(id) {
        const poi = poisData.find(p => p.id == id);
        if (poi) {
            openPOIModal(poi);
        }
    }

    function deletePOI(id) {
        $.ajax({
            url: masterplanEditorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'masterplan_delete_poi',
                poi_id: id,
                nonce: masterplanEditorData.nonce
            },
            success: function(response) {
                if(response.success) {
                    location.reload();
                }
            }
        });
    }

    function startPlacingPOI() {
        isPlacingPOI = true;
        $('#poi-mode-status').text('Haga clic en el mapa para establecer la ubicación del POI.');
        $('#editor-status').text('Modo ubicación activado');
    }

    function updatePOILocation(lat, lng) {
        $('#poi_lat').val(lat);
        $('#poi_lng').val(lng);
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

    // Inicializar
    init();
});
