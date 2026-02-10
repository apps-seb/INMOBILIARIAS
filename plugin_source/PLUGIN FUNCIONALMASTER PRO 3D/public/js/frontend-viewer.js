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
    let poisVisible = true;

    // Configuración del proyecto (inyectada por PHP)
    const projectConfig = window.masterplanProjectConfig || {};
    const projectId = projectConfig.projectId || null;
    const useCustomImage = projectConfig.useCustomImage === true;

    /**
     * Inicializar viewer según modo
     */
    function init() {
        // Inicializar control de visibilidad
        initVisibilityControl();

        if (useCustomImage && projectConfig.customImageUrl) {
            initImageViewer();
        } else if ($('#masterplan-public-map').length) {
            initMap();
        }
    }

    function initVisibilityControl() {
        const $control = $('#poi-visibility-control');
        const $checkbox = $('#toggle-pois');

        // Mostrar control
        $control.show();

        // Evento cambio
        $checkbox.on('change', function() {
            poisVisible = $(this).is(':checked');
            if (useCustomImage) {
                redrawCanvas();
            } else if (map) {
                toggleMapPOIs(poisVisible);
            }
        });
    }

    function toggleMapPOIs(visible) {
        const display = visible ? 'block' : 'none';
        $('.poi-marker-container').css('display', display);
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
        });
    }

    // ========================================
    // MODO IMAGEN
    // ========================================

    function initImageViewer() {
        canvas = document.getElementById('masterplan-canvas');
        if (!canvas) return;

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

        // Dibujar POIs (si están visibles)
        if (poisVisible && typeof poisData !== 'undefined' && poisData.length > 0) {
            drawPOIsOnCanvas();
        }
    }

    function drawPOIsOnCanvas() {
        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        poisData.forEach(poi => {
            if (poi.lat === null || poi.lng === null) return;

            // En modo imagen, lat es Y (0-1), lng es X (0-1)
            const x = offsetX + (poi.lng * drawWidth);
            const y = offsetY + (poi.lat * drawHeight);

            // Dibujar marcador
            ctx.beginPath();

            if (poi.imgElement && poi.imgElement.complete && poi.imgElement.naturalWidth !== 0) {
                // Dibujar logo
                const size = 32;
                ctx.save();
                ctx.beginPath();
                ctx.arc(x, y, size/2, 0, Math.PI * 2);
                ctx.clip();
                ctx.drawImage(poi.imgElement, x - size/2, y - size/2, size, size);
                ctx.restore();

                // Borde
                ctx.beginPath();
                ctx.arc(x, y, size/2, 0, Math.PI * 2);
                ctx.lineWidth = 2;
                ctx.strokeStyle = 'white';
                ctx.stroke();
            } else {
                // Dibujar círculo de color
                ctx.arc(x, y, 10, 0, Math.PI * 2);
                ctx.fillStyle = poi.color || '#3b82f6';
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = 'white';
                ctx.stroke();
            }
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
        const rect = canvas.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        const offsetX = parseFloat(canvas.dataset.offsetX);
        const offsetY = parseFloat(canvas.dataset.offsetY);
        const drawWidth = parseFloat(canvas.dataset.drawWidth);
        const drawHeight = parseFloat(canvas.dataset.drawHeight);

        // Buscar POI clickeado (prioridad sobre lotes, si están visibles)
        if (poisVisible && typeof poisData !== 'undefined') {
            const clickedPOI = poisData.find(poi => {
                if (poi.lat === null || poi.lng === null) return false;

                // Coordenadas en pantalla
                const px = offsetX + (poi.lng * drawWidth);
                const py = offsetY + (poi.lat * drawHeight);

                // Radio de click (20px)
                const dist = Math.sqrt(Math.pow(clickX - px, 2) + Math.pow(clickY - py, 2));
                return dist <= 20;
            });

            if (clickedPOI) {
                openPOISidebar(clickedPOI);
                return;
            }
        }

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
     * Cargar POIs desde la API
     */
    function loadPOIs() {
        if (!projectId) return;

        $.ajax({
            url: masterplanPublic.apiUrl + 'projects/' + projectId + '/pois',
            type: 'GET',
            success: function (pois) {
                poisData = pois;
                console.log('MasterPlan: POIs loaded', poisData); // Debug

                if (useCustomImage && canvas) {
                    // Precargar logos si existen
                    let toLoad = 0;
                    let loaded = 0;

                    if (poisData.length === 0) {
                        redrawCanvas();
                        return;
                    }

                    poisData.forEach(poi => {
                        if (poi.logo) {
                            toLoad++;
                            const img = new Image();
                            img.src = poi.logo;
                            img.onload = function() {
                                loaded++;
                                if (loaded === toLoad) redrawCanvas();
                            };
                            img.onerror = function() {
                                console.warn('MasterPlan: Error loading POI logo', poi.logo);
                                loaded++; // Continuar aunque falle
                                if (loaded === toLoad) redrawCanvas();
                            };
                            poi.imgElement = img;
                        }
                    });

                    if (toLoad === 0) {
                        redrawCanvas();
                    }
                } else if (map) {
                    displayPOIsOnMap(pois);
                }
            },
            error: function (xhr, status, error) {
                console.error('MasterPlan: Error loading POIs', error);
            }
        });
    }

    /**
     * Mostrar POIs en el mapa
     */
    function displayPOIsOnMap(pois) {
        pois.forEach(poi => {
            if (!poi.lat || !poi.lng) return;

            const el = document.createElement('div');
            el.className = 'poi-marker-container';

            // Contenido interior
            let iconStyle = '';
            if (poi.logo) {
                iconStyle = `background-image: url(${poi.logo});`;
            } else {
                iconStyle = `background-color: ${poi.color || '#3b82f6'};`;
            }

            el.innerHTML = `
                <div class="poi-content">
                    <div class="poi-label">${poi.title}</div>
                    <div class="poi-icon" style="${iconStyle}"></div>
                </div>
                <div class="poi-stalk"></div>
                <div class="poi-anchor"></div>
            `;

            // Usar anchor 'bottom' para que la parte inferior toque el suelo
            const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                .setLngLat([poi.lng, poi.lat])
                .addTo(map);

            // Click event en el contenedor para abrir sidebar
            el.addEventListener('click', function(e) {
                e.stopPropagation(); // Evitar click en mapa
                openPOISidebar(poi);
            });
        });
    }

    function openPOISidebar(poi) {
        let distanceHTML = '';
        let buttonHTML = '';

        if (map) {
            // Calcular distancia desde el centro del mapa (usuario)
            const center = map.getCenter();
            const dist = calculateDistance(center.lat, center.lng, poi.lat, poi.lng);
            const distFormatted = dist > 1000 ? (dist / 1000).toFixed(2) + ' km' : Math.round(dist) + ' m';

            distanceHTML = `
                <div class="info-row">
                    <span class="info-label">📍 Distancia:</span>
                    <span class="info-value" style="color: ${poi.color || '#3b82f6'}; font-weight:bold;">${distFormatted}</span>
                </div>
                <p style="font-size: 11px; color: #666; margin-top: 5px; font-style: italic;">(Distancia aproximada desde tu punto de vista)</p>
            `;

            buttonHTML = `<button id="update-dist-btn" class="btn btn-secondary" style="margin-top: 15px; width: 100%;">📏 Recalcular Distancia</button>`;
        }

        const sidebarHTML = `
            <div class="lot-detail">
                ${poi.logo ? `<div style="text-align:center; margin-bottom:20px;"><img src="${poi.logo}" alt="${poi.title}" style="max-width:100%; height:auto; border-radius:8px;"></div>` : ''}

                <h2 class="lot-title" style="border-left: 4px solid ${poi.color || '#3b82f6'}; padding-left: 10px;">${poi.title}</h2>

                <div class="lot-info" style="margin-top: 15px;">
                    ${distanceHTML}
                </div>

                <div class="lot-description" style="margin-top: 20px;">
                    ${poi.description || ''}
                </div>

                ${buttonHTML}
            </div>
        `;

        $('#sidebar-content').html(sidebarHTML);
        $('#masterplan-sidebar').addClass('active');
        $('#masterplan-overlay').addClass('active');

        // Botón para recalcular distancia si el usuario se mueve
        if (map) {
            $('#update-dist-btn').on('click', function() {
                 const newCenter = map.getCenter();
                 const newDist = calculateDistance(newCenter.lat, newCenter.lng, poi.lat, poi.lng);
                 const newDistFormatted = newDist > 1000 ? (newDist / 1000).toFixed(2) + ' km' : Math.round(newDist) + ' m';
                 $(this).parent().find('.info-value').text(newDistFormatted);
            });
        }
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // metres
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;

        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return R * c;
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

    /**
     * Abrir sidebar con información del lote
     */
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

    $(document).on('click', '#sidebar-close-btn, #masterplan-overlay', closeSidebar);

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
