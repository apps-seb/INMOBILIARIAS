(function() {
    'use strict';

    // --- Math Helpers ---

    class Point {
        constructor(x, y) {
            this.x = x;
            this.y = y;
        }
    }

    class MatrixUtils {
        // Solves Ax = b using Gaussian elimination
        static solve(A, b) {
            const n = A.length;
            // Augment A with b
            for (let i = 0; i < n; i++) {
                A[i].push(b[i]);
            }

            // Forward elimination
            for (let i = 0; i < n; i++) {
                // Find pivot
                let maxRow = i;
                for (let k = i + 1; k < n; k++) {
                    if (Math.abs(A[k][i]) > Math.abs(A[maxRow][i])) {
                        maxRow = k;
                    }
                }

                // Swap rows
                [A[i], A[maxRow]] = [A[maxRow], A[i]];

                // Make pivot 1
                const pivot = A[i][i];
                if (Math.abs(pivot) < 1e-10) continue; // Singular

                for (let j = i; j <= n; j++) {
                    A[i][j] /= pivot;
                }

                // Eliminate other rows
                for (let k = 0; k < n; k++) {
                    if (k !== i) {
                        const factor = A[k][i];
                        for (let j = i; j <= n; j++) {
                            A[k][j] -= factor * A[i][j];
                        }
                    }
                }
            }

            // Extract solution
            const x = [];
            for (let i = 0; i < n; i++) {
                x.push(A[i][n]);
            }
            return x;
        }
    }

    class Homography {
        // Calculates the 3x3 homography matrix H that maps src points to dst points
        // src and dst are arrays of 4 Points
        static compute(src, dst) {
            const A = [];
            const b = [];

            for (let i = 0; i < 4; i++) {
                const s = src[i];
                const d = dst[i];
                // x' = (h00*x + h01*y + h02) / (h20*x + h21*y + 1)
                // y' = (h10*x + h11*y + h12) / (h20*x + h21*y + 1)
                // Linearized:
                // h00*x + h01*y + h02 - h20*x*x' - h21*y*x' = x'
                // h10*x + h11*y + h12 - h20*x*y' - h21*y*y' = y'
                A.push([s.x, s.y, 1, 0, 0, 0, -s.x * d.x, -s.y * d.x]);
                A.push([0, 0, 0, s.x, s.y, 1, -s.x * d.y, -s.y * d.y]);
                b.push(d.x);
                b.push(d.y);
            }

            const h = MatrixUtils.solve(A, b);
            // H is 3x3, last element is 1
            return [
                [h[0], h[1], h[2]],
                [h[3], h[4], h[5]],
                [h[6], h[7], 1]
            ];
        }

        static transform(H, x, y) {
            const numX = H[0][0] * x + H[0][1] * y + H[0][2];
            const numY = H[1][0] * x + H[1][1] * y + H[1][2];
            const w = H[2][0] * x + H[2][1] * y + H[2][2];
            return new Point(numX / w, numY / w);
        }
    }

    // --- Main Editor Logic ---

    const Editor = {
        canvas: null,
        ctx: null,
        layers: [], // { type: 'master'|'lot', image: Image, srcPts: [], dstPts: [], id: unique }
        selectedLayerIndex: -1,
        selectedCornerIndex: -1,
        isDragging: false,
        zoom: 1, // Optional: for future zoom support
        pan: { x: 0, y: 0 },

        init: function() {
            this.canvas = document.getElementById('remo-editor-canvas');
            if (!this.canvas) return;
            this.ctx = this.canvas.getContext('2d');

            // Resize canvas
            this.canvas.width = 800;
            this.canvas.height = 600;

            this.bindEvents();
            this.loadInitialData();
            this.loop();
        },

        bindEvents: function() {
            const self = this;

            // Upload Master
            document.getElementById('remo-upload-master').addEventListener('click', function(e) {
                e.preventDefault();
                self.openMediaUploader('master');
            });

            // Add Lot
            document.getElementById('remo-add-lot').addEventListener('click', function(e) {
                e.preventDefault();
                if (self.layers.length === 0 || self.layers[0].type !== 'master') {
                    alert('Please set a Master Image first.');
                    return;
                }
                self.openMediaUploader('lot');
            });

            // Canvas Interaction
            this.canvas.addEventListener('mousedown', this.onMouseDown.bind(this));
            this.canvas.addEventListener('mousemove', this.onMouseMove.bind(this));
            this.canvas.addEventListener('mouseup', this.onMouseUp.bind(this));

            // Handle window resize if needed
        },

        openMediaUploader: function(type) {
            const self = this;
            const frame = wp.media({
                title: remoData.i18n.select_image,
                button: { text: remoData.i18n.use_image },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                const img = new Image();
                img.crossOrigin = "anonymous";
                img.onload = function() {
                    self.addLayer(type, img, attachment.url, attachment.id);
                };
                img.src = attachment.url;
            });

            frame.open();
        },

        addLayer: function(type, img, url, id) {
            if (type === 'master') {
                // Reset or replace master
                this.canvas.width = img.width; // Or keep fixed? Usually fit to master.
                this.canvas.height = img.height;
                // If replacing master, keep lots?
                // For simplicity, let's just set master as layer 0
                const masterLayer = {
                    type: 'master',
                    image: img,
                    url: url,
                    media_id: id,
                    dstPts: [
                        new Point(0, 0),
                        new Point(img.width, 0),
                        new Point(img.width, img.height),
                        new Point(0, img.height)
                    ]
                };
                if (this.layers.length > 0 && this.layers[0].type === 'master') {
                    this.layers[0] = masterLayer;
                } else {
                    this.layers.unshift(masterLayer);
                }
            } else {
                // Add Lot
                // Initial placement: center 50%
                const w = this.canvas.width;
                const h = this.canvas.height;
                const layer = {
                    type: 'lot',
                    image: img,
                    url: url,
                    media_id: id,
                    srcPts: [
                        new Point(0, 0),
                        new Point(img.width, 0),
                        new Point(img.width, img.height),
                        new Point(0, img.height)
                    ],
                    dstPts: [
                        new Point(w * 0.25, h * 0.25),
                        new Point(w * 0.75, h * 0.25),
                        new Point(w * 0.75, h * 0.75),
                        new Point(w * 0.25, h * 0.75)
                    ]
                };
                this.layers.push(layer);
                this.selectedLayerIndex = this.layers.length - 1;
            }
            this.saveData();
            this.draw();
        },

        loadInitialData: function() {
            const input = document.getElementById('remo_map_data');
            if (!input || !input.value) return;

            try {
                const data = JSON.parse(input.value);
                if (Array.isArray(data)) {
                    // Load images
                    data.forEach(layerData => {
                        const img = new Image();
                        img.crossOrigin = "anonymous";
                        img.onload = () => {
                            this.draw();
                        };
                        img.src = layerData.url;

                        // Reconstruct Points (JSON parse makes them plain objects)
                        const reconstructPoints = pts => pts.map(p => new Point(p.x, p.y));

                        this.layers.push({
                            type: layerData.type,
                            image: img,
                            url: layerData.url,
                            media_id: layerData.media_id,
                            srcPts: layerData.srcPts ? reconstructPoints(layerData.srcPts) : null,
                            dstPts: reconstructPoints(layerData.dstPts)
                        });

                        // Set canvas size to master
                        if (layerData.type === 'master') {
                             // We wait for master image load to set canvas size, but valid for now
                             // We might need to handle this async better, but for now simple
                        }
                    });
                }
            } catch (e) {
                console.error("Invalid map data", e);
            }
        },

        saveData: function() {
            // Serialize layers (exclude Image objects)
            const dataToSave = this.layers.map(l => ({
                type: l.type,
                url: l.url,
                media_id: l.media_id,
                srcPts: l.srcPts,
                dstPts: l.dstPts
            }));
            document.getElementById('remo_map_data').value = JSON.stringify(dataToSave);
        },

        // --- Interaction ---

        getMousePos: function(e) {
            const rect = this.canvas.getBoundingClientRect();
            return new Point(
                (e.clientX - rect.left) * (this.canvas.width / rect.width),
                (e.clientY - rect.top) * (this.canvas.height / rect.height)
            );
        },

        onMouseDown: function(e) {
            const m = this.getMousePos(e);

            // Check corners of selected layer first
            if (this.selectedLayerIndex !== -1) {
                const l = this.layers[this.selectedLayerIndex];
                if (l.type === 'lot') {
                     for (let i = 0; i < 4; i++) {
                        const p = l.dstPts[i];
                        if (Math.hypot(p.x - m.x, p.y - m.y) < 10) {
                            this.selectedCornerIndex = i;
                            this.isDragging = true;
                            return;
                        }
                    }
                }
            }

            // Check if clicking inside another layer to select it
            // Simple bounding box check for now, or just iterate layers
            for (let i = this.layers.length - 1; i >= 0; i--) {
                const l = this.layers[i];
                if (l.type === 'lot') {
                    // Check corners
                    for (let j = 0; j < 4; j++) {
                        const p = l.dstPts[j];
                        if (Math.hypot(p.x - m.x, p.y - m.y) < 10) {
                            this.selectedLayerIndex = i;
                            this.selectedCornerIndex = j;
                            this.isDragging = true;
                            this.draw();
                            return;
                        }
                    }
                    // Check inside (Poly check) - simplified
                    // ...
                }
            }

            // Deselect
            // this.selectedLayerIndex = -1;
            this.draw();
        },

        onMouseMove: function(e) {
            if (!this.isDragging) return;
            const m = this.getMousePos(e);

            if (this.selectedLayerIndex !== -1 && this.selectedCornerIndex !== -1) {
                this.layers[this.selectedLayerIndex].dstPts[this.selectedCornerIndex] = m;
                this.draw();
            }
        },

        onMouseUp: function(e) {
            if (this.isDragging) {
                this.isDragging = false;
                this.selectedCornerIndex = -1;
                this.saveData();
            }
        },

        // --- Rendering ---

        loop: function() {
            // this.draw();
            // We use event-based draw for efficiency, but requestAnimationFrame is smoother for drag
            requestAnimationFrame(this.loop.bind(this));
        },

        draw: function() {
            // Clear
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            // Draw Layers
            this.layers.forEach((layer, index) => {
                if (layer.type === 'master') {
                    if (layer.image.complete) {
                         // Update canvas size if it's master
                         if (this.canvas.width !== layer.image.width) {
                             this.canvas.width = layer.image.width;
                             this.canvas.height = layer.image.height;
                         }
                         this.ctx.drawImage(layer.image, 0, 0);
                    }
                } else {
                    // Draw Warped Lot
                    this.drawWarpedLayer(layer);
                }

                // Draw Controls if selected
                if (index === this.selectedLayerIndex && layer.type === 'lot') {
                    this.drawControls(layer);
                }
            });
        },

        drawWarpedLayer: function(layer) {
            if (!layer.image.complete) return;

            // Calculate Homography
            const H = Homography.compute(layer.srcPts, layer.dstPts);

            // Subdivide and draw
            const STEPS = 10;
            const imgW = layer.image.width;
            const imgH = layer.image.height;
            const stepW = imgW / STEPS;
            const stepH = imgH / STEPS;

            for (let y = 0; y < STEPS; y++) {
                for (let x = 0; x < STEPS; x++) {
                    // Source Grid Points
                    const u0 = x * stepW;
                    const v0 = y * stepH;
                    const u1 = (x + 1) * stepW;
                    const v1 = (y + 1) * stepH;

                    // Project to Dest Grid Points
                    // We need 4 points for the quad patch
                    const p00 = Homography.transform(H, u0, v0);
                    const p10 = Homography.transform(H, u1, v0);
                    const p11 = Homography.transform(H, u1, v1);
                    const p01 = Homography.transform(H, u0, v1);

                    // Draw the two triangles for this patch
                    this.drawTriangle(layer.image, u0, v0, u1, v0, u0, v1, p00, p10, p01);
                    this.drawTriangle(layer.image, u1, v0, u1, v1, u0, v1, p10, p11, p01);
                }
            }
        },

        drawTriangle: function(img, sx0, sy0, sx1, sy1, sx2, sy2, dp0, dp1, dp2) {
            // Affine Texture Mapping approximation for a triangle
            // Using Canvas transform:
            // We want to map (sx, sy) -> (dx, dy)
            // It's simpler to use clip() and drawImage with transform.
            // Solve Affine Transform T: dp = T * sp
            // [dx]   [m11 m12 dx] [sx]
            // [dy] = [m21 m22 dy] [sy]
            // [ 1]   [ 0   0   1] [ 1]

            // This is computationally heavy to solve for every triangle in JS.
            // Optimized approach:
            // 1. Save context
            // 2. Clip to the destination triangle path
            // 3. Compute transform that maps the source triangle (from image space) to dest triangle (screen space)
            // 4. Draw image
            // 5. Restore

            this.ctx.save();
            this.ctx.beginPath();
            this.ctx.moveTo(dp0.x, dp0.y);
            this.ctx.lineTo(dp1.x, dp1.y);
            this.ctx.lineTo(dp2.x, dp2.y);
            this.ctx.closePath();
            this.ctx.clip(); // Creates seams sometimes, but okay for now

            // Compute Affine Transform
            // We need to map (sx0,sy0) -> (dp0x, dp0y), etc.
            // X_dest = a*X_src + c*Y_src + e
            // Y_dest = b*X_src + d*Y_src + f
            // Solving this system of linear equations for a,b,c,d,e,f

            // Denominator
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
        },

        drawControls: function(layer) {
            this.ctx.strokeStyle = '#0073aa';
            this.ctx.lineWidth = 2;
            this.ctx.beginPath();
            this.ctx.moveTo(layer.dstPts[0].x, layer.dstPts[0].y);
            this.ctx.lineTo(layer.dstPts[1].x, layer.dstPts[1].y);
            this.ctx.lineTo(layer.dstPts[2].x, layer.dstPts[2].y);
            this.ctx.lineTo(layer.dstPts[3].x, layer.dstPts[3].y);
            this.ctx.closePath();
            this.ctx.stroke();

            // Corners
            this.ctx.fillStyle = '#0073aa';
            layer.dstPts.forEach(p => {
                this.ctx.beginPath();
                this.ctx.arc(p.x, p.y, 5, 0, Math.PI * 2);
                this.ctx.fill();
            });
        }
    };

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('remo-editor-canvas')) {
            Editor.init();
        }
    });

    // Expose for debugging if needed
    window.RemoEditor = Editor;

})();
