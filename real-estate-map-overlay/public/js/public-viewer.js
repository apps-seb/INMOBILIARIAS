(function() {
    'use strict';

    // --- Math Helpers (Reused from Admin) ---

    class Point {
        constructor(x, y) {
            this.x = x;
            this.y = y;
        }
    }

    class MatrixUtils {
        static solve(A, b) {
            const n = A.length;
            for (let i = 0; i < n; i++) { A[i].push(b[i]); }
            for (let i = 0; i < n; i++) {
                let maxRow = i;
                for (let k = i + 1; k < n; k++) { if (Math.abs(A[k][i]) > Math.abs(A[maxRow][i])) maxRow = k; }
                [A[i], A[maxRow]] = [A[maxRow], A[i]];
                const pivot = A[i][i];
                if (Math.abs(pivot) < 1e-10) continue;
                for (let j = i; j <= n; j++) { A[i][j] /= pivot; }
                for (let k = 0; k < n; k++) {
                    if (k !== i) {
                        const factor = A[k][i];
                        for (let j = i; j <= n; j++) { A[k][j] -= factor * A[i][j]; }
                    }
                }
            }
            const x = [];
            for (let i = 0; i < n; i++) { x.push(A[i][n]); }
            return x;
        }
    }

    class Homography {
        static compute(src, dst) {
            const A = [], b = [];
            for (let i = 0; i < 4; i++) {
                const s = src[i], d = dst[i];
                A.push([s.x, s.y, 1, 0, 0, 0, -s.x * d.x, -s.y * d.x]);
                A.push([0, 0, 0, s.x, s.y, 1, -s.x * d.y, -s.y * d.y]);
                b.push(d.x);
                b.push(d.y);
            }
            const h = MatrixUtils.solve(A, b);
            return [[h[0], h[1], h[2]], [h[3], h[4], h[5]], [h[6], h[7], 1]];
        }
        static transform(H, x, y) {
            const numX = H[0][0] * x + H[0][1] * y + H[0][2];
            const numY = H[1][0] * x + H[1][1] * y + H[1][2];
            const w = H[2][0] * x + H[2][1] * y + H[2][2];
            return new Point(numX / w, numY / w);
        }
    }

    // --- Viewer Logic ---

    class MapViewer {
        constructor(canvas, data) {
            this.canvas = canvas;
            this.ctx = canvas.getContext('2d');
            this.rawData = data;
            this.layers = [];
            this.hoveredLayerIndex = -1;

            // Find tooltip
            this.tooltip = canvas.parentElement.querySelector('.remo-tooltip');

            this.init();
        }

        init() {
            this.loadImages();
            this.bindEvents();
        }

        loadImages() {
            let loadedCount = 0;
            // First pass: create layer objects
            if (Array.isArray(this.rawData)) {
                this.rawData.forEach(layerData => {
                    const img = new Image();
                    img.crossOrigin = "anonymous";
                    img.src = layerData.url;

                    const reconstructPoints = pts => pts.map(p => new Point(p.x, p.y));

                    const layer = {
                        type: layerData.type,
                        image: img,
                        srcPts: layerData.srcPts ? reconstructPoints(layerData.srcPts) : null,
                        dstPts: reconstructPoints(layerData.dstPts),
                        media_id: layerData.media_id
                    };

                    this.layers.push(layer);

                    img.onload = () => {
                        loadedCount++;
                        if (loadedCount === this.layers.length) {
                            this.onAllImagesLoaded();
                        } else {
                             // Progressive draw
                             this.draw();
                        }
                    };
                });
            }
        }

        onAllImagesLoaded() {
            // Set canvas size based on master layer
            const master = this.layers.find(l => l.type === 'master');
            if (master) {
                this.canvas.width = master.image.width;
                this.canvas.height = master.image.height;
                // Force wrapper aspect ratio if needed, but canvas is block so it should be fine.
            }
            this.draw();
        }

        bindEvents() {
            this.canvas.addEventListener('mousemove', this.onMouseMove.bind(this));
            this.canvas.addEventListener('mouseleave', () => {
                this.hoveredLayerIndex = -1;
                this.tooltip.style.display = 'none';
                this.draw();
            });
            this.canvas.addEventListener('click', this.onClick.bind(this));
        }

        getMousePos(e) {
            const rect = this.canvas.getBoundingClientRect();
            // Scale if canvas is displayed at different size than intrinsic
            return new Point(
                (e.clientX - rect.left) * (this.canvas.width / rect.width),
                (e.clientY - rect.top) * (this.canvas.height / rect.height)
            );
        }

        isPointInPoly(pt, poly) {
            // Ray casting algorithm
            let inside = false;
            for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
                const xi = poly[i].x, yi = poly[i].y;
                const xj = poly[j].x, yj = poly[j].y;
                const intersect = ((yi > pt.y) !== (yj > pt.y)) &&
                    (pt.x < (xj - xi) * (pt.y - yi) / (yj - yi) + xi);
                if (intersect) inside = !inside;
            }
            return inside;
        }

        onMouseMove(e) {
            const m = this.getMousePos(e);
            let found = -1;

            // Check lots (reverse order to find top-most)
            for (let i = this.layers.length - 1; i >= 0; i--) {
                const layer = this.layers[i];
                if (layer.type === 'lot') {
                    if (this.isPointInPoly(m, layer.dstPts)) {
                        found = i;
                        break;
                    }
                }
            }

            if (found !== this.hoveredLayerIndex) {
                this.hoveredLayerIndex = found;
                this.draw(); // Redraw to show highlight
            }

            // Update Tooltip
            if (found !== -1) {
                this.tooltip.style.display = 'block';
                this.tooltip.style.left = (e.pageX + 10) + 'px';
                this.tooltip.style.top = (e.pageY + 10) + 'px';
                this.tooltip.textContent = "Lot ID: " + this.layers[found].media_id; // Just sample info
            } else {
                this.tooltip.style.display = 'none';
            }
        }

        onClick(e) {
            if (this.hoveredLayerIndex !== -1) {
                console.log("Clicked Lot:", this.layers[this.hoveredLayerIndex]);
                // Trigger custom event or open link
            }
        }

        draw() {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            this.layers.forEach((layer, index) => {
                if (layer.type === 'master') {
                    if (layer.image.complete) {
                        this.ctx.drawImage(layer.image, 0, 0);
                    }
                } else {
                    this.drawWarpedLayer(layer, index === this.hoveredLayerIndex);
                }
            });
        }

        drawWarpedLayer(layer, isHovered) {
            if (!layer.image.complete) return;

            const H = Homography.compute(layer.srcPts, layer.dstPts);
            const STEPS = 10;
            const imgW = layer.image.width;
            const imgH = layer.image.height;
            const stepW = imgW / STEPS;
            const stepH = imgH / STEPS;

            for (let y = 0; y < STEPS; y++) {
                for (let x = 0; x < STEPS; x++) {
                    const u0 = x * stepW;
                    const v0 = y * stepH;
                    const u1 = (x + 1) * stepW;
                    const v1 = (y + 1) * stepH;

                    const p00 = Homography.transform(H, u0, v0);
                    const p10 = Homography.transform(H, u1, v0);
                    const p11 = Homography.transform(H, u1, v1);
                    const p01 = Homography.transform(H, u0, v1);

                    this.drawTriangle(layer.image, u0, v0, u1, v0, u0, v1, p00, p10, p01, isHovered);
                    this.drawTriangle(layer.image, u1, v0, u1, v1, u0, v1, p10, p11, p01, isHovered);
                }
            }

            // Draw highlight border if hovered
            if (isHovered) {
                this.ctx.save();
                this.ctx.strokeStyle = 'rgba(255, 255, 0, 0.8)';
                this.ctx.lineWidth = 3;
                this.ctx.beginPath();
                this.ctx.moveTo(layer.dstPts[0].x, layer.dstPts[0].y);
                for(let i=1; i<4; i++) this.ctx.lineTo(layer.dstPts[i].x, layer.dstPts[i].y);
                this.ctx.closePath();
                this.ctx.stroke();

                // Add overlay tint?
                this.ctx.fillStyle = 'rgba(255, 255, 0, 0.2)';
                this.ctx.fill();
                this.ctx.restore();
            }
        }

        drawTriangle(img, sx0, sy0, sx1, sy1, sx2, sy2, dp0, dp1, dp2, isHovered) {
            this.ctx.save();
            this.ctx.beginPath();
            this.ctx.moveTo(dp0.x, dp0.y);
            this.ctx.lineTo(dp1.x, dp1.y);
            this.ctx.lineTo(dp2.x, dp2.y);
            this.ctx.closePath();
            this.ctx.clip();

            const den = sx0 * (sy2 - sy1) - sx1 * sy2 + sx2 * sy1 + (sx1 - sx2) * sy0;
            if (Math.abs(den) < 1e-6) { this.ctx.restore(); return; }

            const a = (dp0.x * (sy2 - sy1) - dp1.x * sy2 + dp2.x * sy1 + (dp1.x - dp2.x) * sy0) / den;
            const b = (dp0.y * (sy2 - sy1) - dp1.y * sy2 + dp2.y * sy1 + (dp1.y - dp2.y) * sy0) / den;
            const c = (dp1.x * sx2 - dp0.x * sx2 - dp1.x * sx0 + dp2.x * sx0 + (dp0.x - dp2.x) * sx1) / den;
            const d = (dp1.y * sx2 - dp0.y * sx2 - dp1.y * sx0 + dp2.y * sx0 + (dp0.y - dp2.y) * sx1) / den;
            const e = (dp0.x * (sx1 * sy2 - sx2 * sy1) + dp1.x * (sx2 * sy0 - sx0 * sy2) + dp2.x * (sx0 * sy1 - sx1 * sy0)) / den;
            const f = (dp0.y * (sx1 * sy2 - sx2 * sy1) + dp1.y * (sx2 * sy0 - sx0 * sy2) + dp2.y * (sx0 * sy1 - sx1 * sy0)) / den;

            this.ctx.setTransform(a, b, c, d, e, f);
            this.ctx.drawImage(img, 0, 0);

            this.ctx.restore();
        }
    }

    // Initialize Viewers
    document.addEventListener('DOMContentLoaded', function() {
        const canvases = document.querySelectorAll('.remo-viewer-canvas');
        canvases.forEach(canvas => {
            const id = canvas.getAttribute('data-map-id');
            if (window.remoMaps && window.remoMaps[id]) {
                new MapViewer(canvas, window.remoMaps[id]);
            }
        });
    });

})();
