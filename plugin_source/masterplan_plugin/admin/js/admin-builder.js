/**
 * MasterPlan 3D Pro - Admin Map Builder
 * Script para dibujar polígonos en mapa 3D con MapLibre GL JS
 */

jQuery(document).ready(function ($) {
    'use strict';

    let map = null;
    let drawingMode = false;
    let currentPolygonCoords = [];
    let currentLotId = null;
    let polygonLayer = null;
    let markersLayer = null;

    /**
     * Inicializar mapa 3D
     */
    function initMap() {
        // Verificar que tenemos API key
        if (!masterplanAdmin.apiKey) {
            alert('Error: No se ha configurado la API key de Maptiler. Ve a Configuración.');
            return;
        }

        // Crear instancia del mapa
        map = new maplibregl.Map({
            container: 'masterplan-map-container',
            style: `https://api.maptiler.com/maps/satellite/style.json?key=${masterplanAdmin.apiKey}`,
            center: [parseFloat(masterplanAdmin.centerLng), parseFloat(masterplanAdmin.centerLat)],
            zoom: parseInt(masterplanAdmin.zoom),
            pitch: 60, // Inclinación para vista 3D
            bearing: 0,
            antialias: true
        });

        // Agregar controles de navegación
        map.addControl(new maplibregl.NavigationControl({
            visualizePitch: true
        }), 'top-right');

        // Agregar control de escala
        map.addControl(new maplibregl.ScaleControl({
            maxWidth: 200,
            unit: 'metric'
        }), 'bottom-left');

        // Cuando el mapa se carga
        map.on('load', function () {
            // Agregar terreno 3D usando Maptiler Terrain
            map.addSource('terrain', {
                type: 'raster-dem',
                url: `https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=${masterplanAdmin.apiKey}`,
                tileSize: 256
            });

            map.setTerrain({
                source: 'terrain',
                exaggeration: 1.5 // Exagerar el relieve para mejor visualización
            });

            // Preparar layers para polígonos y marcadores
            setupLayers();

            // Cargar polígono existente si estamos editando un lote
            if (masterplanAdmin.lotId) {
                loadExistingPolygon(masterplanAdmin.lotId);
            }

            console.log('Mapa 3D inicializado correctamente');
        });

        // Click en el mapa para dibujar
        map.on('click', function (e) {
            if (!drawingMode) return;

            addPointToPolygon(e.lngLat);
        });

        // Cambiar cursor cuando está en modo dibujo
        map.on('mouseenter', function () {
            if (drawingMode) {
                map.getCanvas().style.cursor = 'crosshair';
            }
        });
    }

    /**
     * Configurar layers para polígonos y marcadores
     */
    function setupLayers() {
        // Source para el polígono
        map.addSource('current-polygon', {
            type: 'geojson',
            data: {
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [[]]
                }
            }
        });

        // Layer para el relleno del polígono
        map.addLayer({
            id: 'polygon-fill',
            type: 'fill',
            source: 'current-polygon',
            paint: {
                'fill-color': '#3b82f6',
                'fill-opacity': 0.5
            }
        });

        // Layer para el borde del polígono
        map.addLayer({
            id: 'polygon-outline',
            type: 'line',
            source: 'current-polygon',
            paint: {
                'line-color': '#1d4ed8',
                'line-width': 3
            }
        });

        // Source para los marcadores de puntos
        map.addSource('polygon-points', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: []
            }
        });

        // Layer para los puntos
        map.addLayer({
            id: 'points-layer',
            type: 'circle',
            source: 'polygon-points',
            paint: {
                'circle-radius': 6,
                'circle-color': '#ef4444',
                'circle-stroke-width': 2,
                'circle-stroke-color': '#ffffff'
            }
        });
    }

    /**
     * Agregar punto al polígono
     */
    function addPointToPolygon(lngLat) {
        const coords = [lngLat.lng, lngLat.lat];

        // Verificar si el punto cierra el polígono
        if (currentPolygonCoords.length >= 3) {
            const firstPoint = currentPolygonCoords[0];
            const distance = getDistance(coords, firstPoint);

            // Si está cerca del primer punto, cerrar polígono
            if (distance < 0.0001) { // ~10 metros
                closePolygon();
                return;
            }
        }

        // Agregar el punto
        currentPolygonCoords.push(coords);
        updatePolygonDisplay();
    }

    /**
     * Cerrar polígono automáticamente
     */
    function closePolygon() {
        if (currentPolygonCoords.length < 3) {
            alert('Necesitas al menos 3 puntos para crear un polígono');
            return;
        }

        // Agregar el primer punto al final para cerrar
        const closedCoords = [...currentPolygonCoords, currentPolygonCoords[0]];

        // Actualizar el polígono
        map.getSource('current-polygon').setData({
            type: 'Feature',
            geometry: {
                type: 'Polygon',
                coordinates: [closedCoords]
            }
        });

        // Desactivar modo dibujo
        drawingMode = false;
        map.getCanvas().style.cursor = '';

        // Habilitar botón de guardar
        $('#save-polygon-btn').prop('disabled', false);
        $('#start-drawing-btn').text('Redibujar Polígono').find('.dashicons').removeClass('dashicons-edit').addClass('dashicons-update');

        console.log('Polígono cerrado con éxito');
    }

    /**
     * Actualizar visualización del polígono
     */
    function updatePolygonDisplay() {
        // Actualizar polígono
        if (currentPolygonCoords.length >= 2) {
            map.getSource('current-polygon').setData({
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [currentPolygonCoords]
                }
            });
        }

        // Actualizar marcadores de puntos
        const features = currentPolygonCoords.map(coord => ({
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: coord
            }
        }));

        map.getSource('polygon-points').setData({
            type: 'FeatureCollection',
            features: features
        });
    }

    /**
     * Calcular distancia entre dos puntos
     */
    function getDistance(coord1, coord2) {
        const dx = coord1[0] - coord2[0];
        const dy = coord1[1] - coord2[1];
        return Math.sqrt(dx * dx + dy * dy);
    }

    /**
     * Cargar polígono existente
     */
    function loadExistingPolygon(lotId) {
        $.ajax({
            url: masterplanAdmin.ajaxUrl,
            type: 'GET',
            data: {
                action: 'masterplan_get_lot_data',
                lot_id: lotId,
                nonce: masterplanAdmin.nonce
            },
            success: function (response) {
                if (response.success && response.data.coordinates) {
                    const coords = response.data.coordinates;
                    currentPolygonCoords = coords.slice(0, -1); // Remover el último punto (cierre)

                    // Mostrar en el mapa
                    map.getSource('current-polygon').setData({
                        type: 'Feature',
                        geometry: {
                            type: 'Polygon',
                            coordinates: [coords]
                        }
                    });

                    // Centrar mapa en el polígono
                    const bounds = coords.reduce((bounds, coord) => {
                        return bounds.extend(coord);
                    }, new maplibregl.LngLatBounds(coords[0], coords[0]));

                    map.fitBounds(bounds, { padding: 50 });

                    $('#save-polygon-btn').prop('disabled', false);
                    $('#clear-polygon-btn').prop('disabled', false);
                }
            }
        });
    }

    /**
     * Event: Cambiar selector de lote
     */
    $('#lot-selector').on('change', function () {
        const lotId = $(this).val();

        if (!lotId) {
            currentLotId = null;
            $('#start-drawing-btn').prop('disabled', true);
            $('#clear-polygon-btn').prop('disabled', true);
            $('#save-polygon-btn').prop('disabled', true);
            return;
        }

        currentLotId = parseInt(lotId);

        // Limpiar polígono actual
        clearPolygon();

        // Cargar polígono si existe
        loadExistingPolygon(currentLotId);

        // Habilitar botones
        $('#start-drawing-btn').prop('disabled', false);

        // Actualizar info del lote
        const lotName = $(this).find('option:selected').text();
        $('#current-lot-info').html('<strong>' + lotName + '</strong>');
    });

    /**
     * Event: Iniciar dibujo
     */
    $('#start-drawing-btn').on('click', function () {
        if (!currentLotId) {
            alert('Selecciona un lote primero');
            return;
        }

        // Limpiar polígono anterior
        clearPolygon();

        // Activar modo dibujo
        drawingMode = true;
        $(this).text('Dibujando...').prop('disabled', true);
        $('#clear-polygon-btn').prop('disabled', false);

        console.log('Modo dibujo activado');
    });

    /**
     * Event: Limpiar polígono
     */
    $('#clear-polygon-btn').on('click', function () {
        if (confirm('¿Estás seguro de que quieres borrar el polígono?')) {
            clearPolygon();
            $('#start-drawing-btn').prop('disabled', false).text('Dibujar Polígono').find('.dashicons').removeClass('dashicons-update').addClass('dashicons-edit');
            $('#save-polygon-btn').prop('disabled', true);
        }
    });

    /**
     * Event: Guardar polígono
     */
    $('#save-polygon-btn').on('click', function () {
        if (!currentLotId) {
            alert('Selecciona un lote primero');
            return;
        }

        if (currentPolygonCoords.length < 3) {
            alert('El polígono debe tener al menos 3 puntos');
            return;
        }

        // Crear coordenadas cerradas
        const closedCoords = [...currentPolygonCoords, currentPolygonCoords[0]];

        // Guardar vía AJAX
        const $btn = $(this);
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: masterplanAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'masterplan_save_polygon',
                lot_id: currentLotId,
                coordinates: JSON.stringify(closedCoords),
                nonce: masterplanAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert('Polígono guardado exitosamente');
                    $btn.html('<span class="dashicons dashicons-saved"></span> Guardado');
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar');
                }
            },
            error: function () {
                alert('Error al guardar el polígono');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar');
            }
        });
    });

    /**
     * Limpiar polígono actual
     */
    function clearPolygon() {
        currentPolygonCoords = [];
        drawingMode = false;

        // Limpiar mapa
        if (map && map.getSource('current-polygon')) {
            map.getSource('current-polygon').setData({
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [[]]
                }
            });

            map.getSource('polygon-points').setData({
                type: 'FeatureCollection',
                features: []
            });
        }

        map.getCanvas().style.cursor = '';
    }

    // Inicializar cuando el DOM esté listo
    if ($('#masterplan-map-container').length) {
        initMap();
    }
});
