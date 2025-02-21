class DrawingCanvas {
    constructor(container) {
        this.container = container;
        this.layers = [];
        this.currentLayer = null;
        this.tool = 'pencil';
        this.color = '#000000';
        this.lineWidth = 2;
        this.opacity = 1;
        this.isDrawing = false;
        this.history = [];
        this.historyIndex = -1;

        this.initializeCanvas();
        this.setupEventListeners();
    }

    initializeCanvas() {
        // Create main canvas container
        this.canvasContainer = document.createElement('div');
        this.canvasContainer.className = 'canvas-container';
        this.canvasContainer.style.position = 'relative';
        
        // Create first layer
        this.addLayer();
        
        this.container.appendChild(this.canvasContainer);
    }

    addLayer() {
        const canvas = document.createElement('canvas');
        canvas.width = this.container.clientWidth;
        canvas.height = this.container.clientHeight;
        canvas.style.position = 'absolute';
        canvas.style.left = '0';
        canvas.style.top = '0';
        
        const layer = {
            canvas: canvas,
            ctx: canvas.getContext('2d'),
            visible: true,
            locked: false,
            opacity: 1
        };

        this.layers.push(layer);
        this.canvasContainer.appendChild(canvas);
        this.setCurrentLayer(this.layers.length - 1);
        
        // Save initial state
        this.saveToHistory();
    }

    setCurrentLayer(index) {
        if (index >= 0 && index < this.layers.length) {
            this.currentLayer = this.layers[index];
        }
    }

    setupEventListeners() {
        const canvas = this.currentLayer.canvas;

        canvas.addEventListener('mousedown', (e) => this.startDrawing(e));
        canvas.addEventListener('mousemove', (e) => this.draw(e));
        canvas.addEventListener('mouseup', () => this.stopDrawing());
        canvas.addEventListener('mouseout', () => this.stopDrawing());

        // Touch support
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            this.startDrawing(mouseEvent);
        });

        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            this.draw(mouseEvent);
        });

        canvas.addEventListener('touchend', () => this.stopDrawing());
    }

    startDrawing(e) {
        if (this.currentLayer.locked) return;

        this.isDrawing = true;
        const ctx = this.currentLayer.ctx;
        const rect = this.currentLayer.canvas.getBoundingClientRect();
        this.lastX = e.clientX - rect.left;
        this.lastY = e.clientY - rect.top;

        ctx.beginPath();
        ctx.strokeStyle = this.color;
        ctx.lineWidth = this.lineWidth;
        ctx.globalAlpha = this.opacity;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        switch (this.tool) {
            case 'marker':
                ctx.globalCompositeOperation = 'multiply';
                break;
            case 'highlighter':
                ctx.globalCompositeOperation = 'overlay';
                ctx.globalAlpha = 0.4;
                break;
            default:
                ctx.globalCompositeOperation = 'source-over';
                break;
        }
    }

    draw(e) {
        if (!this.isDrawing || this.currentLayer.locked) return;

        const ctx = this.currentLayer.ctx;
        const rect = this.currentLayer.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        switch (this.tool) {
            case 'pencil':
                this.drawFreehand(ctx, x, y);
                break;
            case 'marker':
                this.drawMarker(ctx, x, y);
                break;
            case 'highlighter':
                this.drawHighlighter(ctx, x, y);
                break;
            case 'eraser':
                this.erase(ctx, x, y);
                break;
            case 'line':
                this.drawLine(ctx, x, y);
                break;
            case 'rectangle':
                this.drawRectangle(ctx, x, y);
                break;
            case 'circle':
                this.drawCircle(ctx, x, y);
                break;
        }

        this.lastX = x;
        this.lastY = y;
    }

    drawFreehand(ctx, x, y) {
        ctx.beginPath();
        ctx.moveTo(this.lastX, this.lastY);
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    drawMarker(ctx, x, y) {
        ctx.lineWidth = this.lineWidth * 2;
        this.drawFreehand(ctx, x, y);
    }

    drawHighlighter(ctx, x, y) {
        ctx.lineWidth = this.lineWidth * 4;
        this.drawFreehand(ctx, x, y);
    }

    erase(ctx, x, y) {
        ctx.save();
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(x, y, this.lineWidth * 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    drawLine(ctx, x, y) {
        this.clearPreview();
        ctx.beginPath();
        ctx.moveTo(this.startX, this.startY);
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    drawRectangle(ctx, x, y) {
        this.clearPreview();
        const width = x - this.startX;
        const height = y - this.startY;
        ctx.strokeRect(this.startX, this.startY, width, height);
    }

    drawCircle(ctx, x, y) {
        this.clearPreview();
        const radius = Math.sqrt(
            Math.pow(x - this.startX, 2) + Math.pow(y - this.startY, 2)
        );
        ctx.beginPath();
        ctx.arc(this.startX, this.startY, radius, 0, Math.PI * 2);
        ctx.stroke();
    }

    stopDrawing() {
        if (this.isDrawing) {
            this.isDrawing = false;
            this.saveToHistory();
        }
    }

    clearPreview() {
        if (this.previewCtx) {
            this.previewCtx.clearRect(0, 0, this.previewCanvas.width, this.previewCanvas.height);
        }
    }

    saveToHistory() {
        // Trim any redo history
        this.history = this.history.slice(0, this.historyIndex + 1);
        
        // Save current state
        const state = this.layers.map(layer => {
            return {
                imageData: layer.ctx.getImageData(0, 0, layer.canvas.width, layer.canvas.height),
                visible: layer.visible,
                locked: layer.locked,
                opacity: layer.opacity
            };
        });
        
        this.history.push(state);
        this.historyIndex++;
        
        // Limit history size
        if (this.history.length > 50) {
            this.history.shift();
            this.historyIndex--;
        }
    }

    undo() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.restoreState(this.history[this.historyIndex]);
        }
    }

    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.restoreState(this.history[this.historyIndex]);
        }
    }

    restoreState(state) {
        state.forEach((layerState, index) => {
            if (index >= this.layers.length) {
                this.addLayer();
            }
            const layer = this.layers[index];
            layer.ctx.putImageData(layerState.imageData, 0, 0);
            layer.visible = layerState.visible;
            layer.locked = layerState.locked;
            layer.opacity = layerState.opacity;
            layer.canvas.style.opacity = layerState.opacity;
            layer.canvas.style.display = layerState.visible ? 'block' : 'none';
        });
    }

    setTool(toolName) {
        this.tool = toolName;
    }

    setColor(color) {
        this.color = color;
    }

    setLineWidth(width) {
        this.lineWidth = width;
    }

    setOpacity(opacity) {
        this.opacity = opacity;
    }

    clearLayer() {
        if (!this.currentLayer.locked) {
            this.currentLayer.ctx.clearRect(
                0, 0,
                this.currentLayer.canvas.width,
                this.currentLayer.canvas.height
            );
            this.saveToHistory();
        }
    }

    clearAll() {
        this.layers.forEach(layer => {
            if (!layer.locked) {
                layer.ctx.clearRect(0, 0, layer.canvas.width, layer.canvas.height);
            }
        });
        this.saveToHistory();
    }

    toggleLayerVisibility(index) {
        if (index >= 0 && index < this.layers.length) {
            const layer = this.layers[index];
            layer.visible = !layer.visible;
            layer.canvas.style.display = layer.visible ? 'block' : 'none';
        }
    }

    toggleLayerLock(index) {
        if (index >= 0 && index < this.layers.length) {
            const layer = this.layers[index];
            layer.locked = !layer.locked;
        }
    }

    setLayerOpacity(index, opacity) {
        if (index >= 0 && index < this.layers.length) {
            const layer = this.layers[index];
            layer.opacity = opacity;
            layer.canvas.style.opacity = opacity;
        }
    }

    deleteLayer(index) {
        if (index >= 0 && index < this.layers.length && this.layers.length > 1) {
            this.layers[index].canvas.remove();
            this.layers.splice(index, 1);
            if (this.currentLayer === this.layers[index]) {
                this.setCurrentLayer(Math.max(0, index - 1));
            }
            this.saveToHistory();
        }
    }

    moveLayer(fromIndex, toIndex) {
        if (
            fromIndex >= 0 && fromIndex < this.layers.length &&
            toIndex >= 0 && toIndex < this.layers.length
        ) {
            const layer = this.layers.splice(fromIndex, 1)[0];
            this.layers.splice(toIndex, 0, layer);
            
            // Update z-index of canvases
            this.layers.forEach((layer, index) => {
                layer.canvas.style.zIndex = index;
            });
            
            this.saveToHistory();
        }
    }

    exportImage(format = 'png') {
        // Create a temporary canvas to merge all visible layers
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = this.currentLayer.canvas.width;
        tempCanvas.height = this.currentLayer.canvas.height;
        const tempCtx = tempCanvas.getContext('2d');

        // Merge visible layers
        this.layers.forEach(layer => {
            if (layer.visible) {
                tempCtx.globalAlpha = layer.opacity;
                tempCtx.drawImage(layer.canvas, 0, 0);
            }
        });

        // Convert to data URL
        return tempCanvas.toDataURL(`image/${format}`);
    }

    importImage(imageUrl, newLayer = true) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                if (newLayer) {
                    this.addLayer();
                }
                this.currentLayer.ctx.drawImage(img, 0, 0);
                this.saveToHistory();
                resolve();
            };
            img.onerror = reject;
            img.src = imageUrl;
        });
    }

    // Load a saved drawing state
    loadState(state) {
        // Clear existing layers
        this.layers.forEach(layer => layer.canvas.remove());
        this.layers = [];

        // Create new layers from state
        state.layers.forEach(layerState => {
            this.addLayer();
            const layer = this.layers[this.layers.length - 1];
            layer.visible = layerState.visible;
            layer.locked = layerState.locked;
            layer.opacity = layerState.opacity;
            
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    layer.ctx.drawImage(img, 0, 0);
                    resolve();
                };
                img.onerror = reject;
                img.src = layerState.imageData;
            });
        });

        // Restore current layer
        this.setCurrentLayer(state.currentLayerIndex);
    }

    // Save current drawing state
    saveState() {
        return {
            layers: this.layers.map(layer => ({
                imageData: layer.canvas.toDataURL(),
                visible: layer.visible,
                locked: layer.locked,
                opacity: layer.opacity
            })),
            currentLayerIndex: this.layers.indexOf(this.currentLayer)
        };
    }
}
