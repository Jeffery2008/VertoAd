/**
 * Enhanced Drawing Tools Module for Ad Canvas
 * Provides advanced drawing functionality with layers, multiple tools, and text editing
 */
class DrawingTools {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        
        // Create offscreen canvas for each layer
        this.layers = [];
        this.activeLayer = 0;
        this.initializeLayers();
        
        // Set default options
        this.options = {
            strokeStyle: '#000000',
            lineWidth: 2,
            lineCap: 'round',
            lineJoin: 'round',
            opacity: 1,
            tool: 'pencil',
            fontSize: '16px',
            fontFamily: 'Arial',
            textAlign: 'left',
            ...options
        };

        // Drawing state
        this.isDrawing = false;
        this.lastX = 0;
        this.lastY = 0;
        this.currentShape = null;
        this.shapeStartX = 0;
        this.shapeStartY = 0;

        // Text editing state
        this.isEditingText = false;
        this.textElement = null;
        this.textLayer = null;

        // Layer management
        this.selectedObjects = [];
        this.clipboard = null;

        // History for undo/redo
        this.history = [];
        this.historyIndex = -1;
        this.maxHistorySteps = 50;
        
        // Templates
        this.templates = {};
        this.loadTemplatesFromStorage();

        // Filters
        this.filters = {
            brightness: 100,
            contrast: 100,
            saturation: 100,
            blur: 0,
            grayscale: 0,
            sepia: 0,
            invert: 0
        };

        // Auto-save interval (every 30 seconds)
        this.autoSaveInterval = setInterval(() => this.autoSave(), 30000);

        // Bind keyboard shortcuts
        this.bindKeyboardShortcuts();
        
        // Bind events
        this.bindEvents();
    }

    initializeLayers() {
        // Create background layer
        this.addLayer('Background');
        
        // Create default drawing layer
        this.addLayer('Layer 1');
    }

    addLayer(name) {
        const layer = {
            name,
            canvas: document.createElement('canvas'),
            visible: true,
            opacity: 1,
            objects: [],  // Store vector objects for this layer
            filters: { ...this.filters } // Layer-specific filters
        };
        
        layer.canvas.width = this.canvas.width;
        layer.canvas.height = this.canvas.height;
        
        this.layers.push(layer);
        this.activeLayer = this.layers.length - 1;
        this.renderLayers();
        
        return this.layers.length - 1;  // Return layer index
    }

    removeLayer(index) {
        if (index === 0) return; // Can't remove background layer
        if (index >= this.layers.length) return;
        
        this.layers.splice(index, 1);
        if (this.activeLayer >= this.layers.length) {
            this.activeLayer = this.layers.length - 1;
        }
        this.renderLayers();
    }

    setLayerVisibility(index, visible) {
        if (index >= this.layers.length) return;
        this.layers[index].visible = visible;
        this.renderLayers();
    }

    setLayerOpacity(index, opacity) {
        if (index >= this.layers.length) return;
        this.layers[index].opacity = opacity;
        this.renderLayers();
    }

    moveLayer(fromIndex, toIndex) {
        if (fromIndex === 0 || toIndex === 0) return; // Can't move background layer
        if (fromIndex >= this.layers.length || toIndex >= this.layers.length) return;
        
        const layer = this.layers.splice(fromIndex, 1)[0];
        this.layers.splice(toIndex, 0, layer);
        this.renderLayers();
    }

    renderLayers() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        this.layers.forEach(layer => {
            if (layer.visible) {
                // Create a temporary canvas to apply filters
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = this.canvas.width;
                tempCanvas.height = this.canvas.height;
                const tempCtx = tempCanvas.getContext('2d');
                
                // Draw layer content to temporary canvas
                tempCtx.drawImage(layer.canvas, 0, 0);
                
                // Apply filters
                if (this.hasActiveFilters(layer.filters)) {
                    this.applyFilters(tempCtx, layer.filters);
                }
                
                // Draw filtered result to main canvas
                this.ctx.globalAlpha = layer.opacity;
                this.ctx.drawImage(tempCanvas, 0, 0);
            }
        });
        
        this.ctx.globalAlpha = 1;
    }

    hasActiveFilters(filters) {
        return filters.brightness !== 100 || 
               filters.contrast !== 100 || 
               filters.saturation !== 100 || 
               filters.blur > 0 || 
               filters.grayscale > 0 || 
               filters.sepia > 0 || 
               filters.invert > 0;
    }

    applyFilters(ctx, filters) {
        // Get image data
        const imageData = ctx.getImageData(0, 0, ctx.canvas.width, ctx.canvas.height);
        const data = imageData.data;
        
        // Apply pixel manipulation filters
        this.applyPixelFilters(data, filters);
        
        // Update image with filtered data
        ctx.putImageData(imageData, 0, 0);
        
        // Apply CSS filters for blur and other effects
        const filterString = this.getCssFilterString(filters);
        if (filterString) {
            // Create another temp canvas for CSS filters
            const cssFilterCanvas = document.createElement('canvas');
            cssFilterCanvas.width = ctx.canvas.width;
            cssFilterCanvas.height = ctx.canvas.height;
            const cssFilterCtx = cssFilterCanvas.getContext('2d');
            
            // Apply CSS filters
            cssFilterCtx.filter = filterString;
            cssFilterCtx.drawImage(ctx.canvas, 0, 0);
            
            // Clear original and draw filtered result
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.drawImage(cssFilterCanvas, 0, 0);
        }
    }

    applyPixelFilters(data, filters) {
        const brightness = filters.brightness / 100;
        const contrast = filters.contrast / 100;
        const saturation = filters.saturation / 100;
        const grayscale = filters.grayscale / 100;
        const sepia = filters.sepia / 100;
        const invert = filters.invert / 100;
        
        // Process each pixel
        for (let i = 0; i < data.length; i += 4) {
            let r = data[i];
            let g = data[i + 1];
            let b = data[i + 2];
            
            // Apply brightness
            r *= brightness;
            g *= brightness;
            b *= brightness;
            
            // Apply contrast
            const factor = (contrast - 0.5) * 2 + 1;
            const intercept = 128 * (1 - factor);
            r = r * factor + intercept;
            g = g * factor + intercept;
            b = b * factor + intercept;
            
            // Apply saturation
            const gray = 0.2989 * r + 0.5870 * g + 0.1140 * b;
            r = r * saturation + gray * (1 - saturation);
            g = g * saturation + gray * (1 - saturation);
            b = b * saturation + gray * (1 - saturation);
            
            // Apply grayscale
            if (grayscale > 0) {
                const grayValue = 0.2989 * r + 0.5870 * g + 0.1140 * b;
                r = r * (1 - grayscale) + grayValue * grayscale;
                g = g * (1 - grayscale) + grayValue * grayscale;
                b = b * (1 - grayscale) + grayValue * grayscale;
            }
            
            // Apply sepia
            if (sepia > 0) {
                const sepiaR = (r * 0.393 + g * 0.769 + b * 0.189);
                const sepiaG = (r * 0.349 + g * 0.686 + b * 0.168);
                const sepiaB = (r * 0.272 + g * 0.534 + b * 0.131);
                r = r * (1 - sepia) + sepiaR * sepia;
                g = g * (1 - sepia) + sepiaG * sepia;
                b = b * (1 - sepia) + sepiaB * sepia;
            }
            
            // Apply invert
            if (invert > 0) {
                r = r * (1 - invert) + (255 - r) * invert;
                g = g * (1 - invert) + (255 - g) * invert;
                b = b * (1 - invert) + (255 - b) * invert;
            }
            
            // Clamp values
            data[i] = Math.max(0, Math.min(255, Math.round(r)));
            data[i + 1] = Math.max(0, Math.min(255, Math.round(g)));
            data[i + 2] = Math.max(0, Math.min(255, Math.round(b)));
        }
    }

    getCssFilterString(filters) {
        const cssFilters = [];
        
        // Add blur filter
        if (filters.blur > 0) {
            cssFilters.push(`blur(${filters.blur / 10}px)`);
        }
        
        return cssFilters.join(' ');
    }

    setLayerFilter(layerIndex, filterName, value) {
        if (layerIndex >= this.layers.length) return;
        
        this.layers[layerIndex].filters[filterName] = value;
        this.renderLayers();
        this.saveToHistory();
    }

    bindEvents() {
        this.canvas.addEventListener('mousedown', this.startDrawing.bind(this));
        this.canvas.addEventListener('mousemove', this.draw.bind(this));
        this.canvas.addEventListener('mouseup', this.stopDrawing.bind(this));
        this.canvas.addEventListener('mouseout', this.stopDrawing.bind(this));
        this.canvas.addEventListener('dblclick', this.handleDoubleClick.bind(this));
        
        // Add touch support
        this.canvas.addEventListener('touchstart', this.handleTouchStart.bind(this));
        this.canvas.addEventListener('touchmove', this.handleTouchMove.bind(this));
        this.canvas.addEventListener('touchend', this.stopDrawing.bind(this));
    }

    handleTouchStart(e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            this.startDrawing(mouseEvent);
        }
    }

    handleTouchMove(e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            this.draw(mouseEvent);
        }
    }

    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Command/Ctrl + Z for undo
            if ((e.metaKey || e.ctrlKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            }
            
            // Command/Ctrl + Shift + Z for redo
            if ((e.metaKey || e.ctrlKey) && e.key === 'z' && e.shiftKey) {
                e.preventDefault();
                this.redo();
            }
            
            // Command/Ctrl + C for copy
            if ((e.metaKey || e.ctrlKey) && e.key === 'c') {
                e.preventDefault();
                this.copySelection();
            }
            
            // Command/Ctrl + V for paste
            if ((e.metaKey || e.ctrlKey) && e.key === 'v') {
                e.preventDefault();
                this.pasteSelection();
            }
            
            // Delete key for removing selection
            if (e.key === 'Delete') {
                e.preventDefault();
                this.deleteSelection();
            }
            
            // B for brush tool
            if (e.key === 'b' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('brush');
            }
            
            // P for pencil tool
            if (e.key === 'p' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('pencil');
            }
            
            // E for eraser tool
            if (e.key === 'e' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('eraser');
            }
            
            // T for text tool
            if (e.key === 't' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('text');
            }
            
            // R for rectangle shape
            if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('shape_rectangle');
            }
            
            // C for circle shape
            if (e.key === 'c' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('shape_circle');
            }
            
            // L for line shape
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.setTool('shape_line');
            }
        });
    }

    startDrawing(e) {
        const rect = this.canvas.getBoundingClientRect();
        this.isDrawing = true;
        [this.lastX, this.lastY] = [
            e.clientX - rect.left,
            e.clientY - rect.top
        ];
        
        if (this.options.tool.startsWith('shape_')) {
            this.shapeStartX = this.lastX;
            this.shapeStartY = this.lastY;
            this.currentShape = {
                type: this.options.tool.split('_')[1],
                x: this.shapeStartX,
                y: this.shapeStartY
            };
        } else {
            const ctx = this.layers[this.activeLayer].canvas.getContext('2d');
            ctx.beginPath();
            ctx.moveTo(this.lastX, this.lastY);
        }
    }

    draw(e) {
        if (!this.isDrawing) return;

        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const ctx = this.layers[this.activeLayer].canvas.getContext('2d');

        if (this.options.tool.startsWith('shape_')) {
            this.drawShape(x, y, ctx);
        } else {
            switch (this.options.tool) {
                case 'pencil':
                    this.drawPencil(x, y, ctx);
                    break;
                case 'marker':
                    this.drawMarker(x, y, ctx);
                    break;
                case 'highlighter':
                    this.drawHighlighter(x, y, ctx);
                    break;
                case 'brush':
                    this.drawBrush(x, y, ctx);
                    break;
                case 'eraser':
                    this.erase(x, y, ctx);
                    break;
            }
        }

        this.renderLayers();
        [this.lastX, this.lastY] = [x, y];
    }

    stopDrawing() {
        if (!this.isDrawing) return;
        this.isDrawing = false;
        
        if (this.currentShape) {
            this.layers[this.activeLayer].objects.push(this.currentShape);
            this.currentShape = null;
        }
        
        this.saveToHistory();
    }

    drawShape(x, y, ctx) {
        // Clear the temporary drawing
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Set shape styles
        ctx.strokeStyle = this.options.strokeStyle;
        ctx.lineWidth = this.options.lineWidth;
        ctx.globalAlpha = this.options.opacity;
        
        switch (this.currentShape.type) {
            case 'rectangle':
                ctx.strokeRect(
                    this.shapeStartX,
                    this.shapeStartY,
                    x - this.shapeStartX,
                    y - this.shapeStartY
                );
                break;
                
            case 'circle':
                const radius = Math.sqrt(
                    Math.pow(x - this.shapeStartX, 2) +
                    Math.pow(y - this.shapeStartY, 2)
                );
                ctx.beginPath();
                ctx.arc(this.shapeStartX, this.shapeStartY, radius, 0, Math.PI * 2);
                ctx.stroke();
                break;
                
            case 'line':
                ctx.beginPath();
                ctx.moveTo(this.shapeStartX, this.shapeStartY);
                ctx.lineTo(x, y);
                ctx.stroke();
                break;
        }
    }

    drawPencil(x, y, ctx) {
        ctx.lineWidth = this.options.lineWidth;
        ctx.lineCap = this.options.lineCap;
        ctx.lineJoin = this.options.lineJoin;
        ctx.strokeStyle = this.options.strokeStyle;
        ctx.globalAlpha = this.options.opacity;
        
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    drawMarker(x, y, ctx) {
        ctx.lineWidth = this.options.lineWidth * 2;
        ctx.lineCap = 'square';
        ctx.lineJoin = 'miter';
        ctx.strokeStyle = this.options.strokeStyle;
        ctx.globalAlpha = 0.6;
        
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    drawHighlighter(x, y, ctx) {
        ctx.lineWidth = this.options.lineWidth * 3;
        ctx.lineCap = 'square';
        ctx.lineJoin = 'miter';
        ctx.strokeStyle = this.options.strokeStyle;
        ctx.globalAlpha = 0.3;
        
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    drawBrush(x, y, ctx) {
        const points = this.getBrushPoints(x, y);
        ctx.lineWidth = 1;
        ctx.lineCap = this.options.lineCap;
        ctx.lineJoin = this.options.lineJoin;
        ctx.strokeStyle = this.options.strokeStyle;
        ctx.globalAlpha = 0.1;

        points.forEach(point => {
            ctx.beginPath();
            ctx.moveTo(this.lastX, this.lastY);
            ctx.lineTo(point[0], point[1]);
            ctx.stroke();
        });
    }

    erase(x, y, ctx) {
        const eraserSize = this.options.lineWidth * 2;
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(x, y, eraserSize, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalCompositeOperation = 'source-over';
    }

    getBrushPoints(x, y) {
        const points = [];
        const brushSize = this.options.lineWidth * 2;
        const count = 10;
        
        for (let i = 0; i < count; i++) {
            const angle = (Math.PI * 2 * i) / count;
            points.push([
                x + Math.cos(angle) * brushSize,
                y + Math.sin(angle) * brushSize
            ]);
        }
        return points;
    }

    handleDoubleClick(e) {
        if (this.options.tool === 'text') {
            const rect = this.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            this.startTextEditing(x, y);
        }
    }

    startTextEditing(x, y) {
        // Create text input element
        this.textElement = document.createElement('div');
        this.textElement.contentEditable = true;
        this.textElement.style.position = 'absolute';
        this.textElement.style.left = x + 'px';
        this.textElement.style.top = y + 'px';
        this.textElement.style.minWidth = '100px';
        this.textElement.style.minHeight = '20px';
        this.textElement.style.fontFamily = this.options.fontFamily;
        this.textElement.style.fontSize = this.options.fontSize;
        this.textElement.style.color = this.options.strokeStyle;
        
        document.body.appendChild(this.textElement);
        this.textElement.focus();
        
        this.textElement.addEventListener('blur', () => {
            this.finalizeText();
        });
    }

    finalizeText() {
        if (!this.textElement) return;
        
        const ctx = this.layers[this.activeLayer].canvas.getContext('2d');
        ctx.font = `${this.options.fontSize} ${this.options.fontFamily}`;
        ctx.fillStyle = this.options.strokeStyle;
        ctx.textAlign = this.options.textAlign;
        ctx.globalAlpha = this.options.opacity;
        
        const rect = this.textElement.getBoundingClientRect();
        const canvasRect = this.canvas.getBoundingClientRect();
        
        ctx.fillText(
            this.textElement.textContent,
            rect.left - canvasRect.left,
            rect.top - canvasRect.top + parseInt(this.options.fontSize)
        );
        
        document.body.removeChild(this.textElement);
        this.textElement = null;
        this.saveToHistory();
    }

    // Selection and clipboard operations
    copySelection() {
        if (this.selectedObjects.length === 0) return;
        
        // Create temporary canvas for the selection
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = this.canvas.width;
        tempCanvas.height = this.canvas.height;
        const tempCtx = tempCanvas.getContext('2d');
        
        // Copy selected objects to temporary canvas
        this.selectedObjects.forEach(obj => {
            // Implement object-specific copying logic here
        });
        
        this.clipboard = tempCanvas;
    }

    pasteSelection() {
        if (!this.clipboard) return;
        
        const ctx = this.layers[this.activeLayer].canvas.getContext('2d');
        ctx.drawImage(this.clipboard, 0, 0);
        this.saveToHistory();
    }

    deleteSelection() {
        if (this.selectedObjects.length === 0) return;
        
        // Remove selected objects from their layers
        this.selectedObjects.forEach(obj => {
            const layer = this.layers[obj.layerIndex];
            const index = layer.objects.indexOf(obj);
            if (index !== -1) {
                layer.objects.splice(index, 1);
            }
        });
        
        this.selectedObjects = [];
        this.renderLayers();
        this.saveToHistory();
    }

    // Undo/Redo functionality
    undo() {
        if (this.historyIndex <= 0) return;
        this.historyIndex--;
        this.loadHistoryState();
    }

    redo() {
        if (this.historyIndex >= this.history.length - 1) return;
        this.historyIndex++;
        this.loadHistoryState();
    }

    // History management
    saveToHistory() {
        // Remove any redo steps
        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }

        // Save current state
        const historyState = this.layers.map(layer => {
            return {
                canvas: layer.canvas.toDataURL(),
                name: layer.name,
                visible: layer.visible,
                opacity: layer.opacity,
                filters: { ...layer.filters }
            };
        });

        this.history.push(historyState);
        
        // Limit history size
        if (this.history.length > this.maxHistorySteps) {
            this.history.shift();
        } else {
            this.historyIndex++;
        }
    }

    loadHistoryState() {
        if (this.historyIndex < 0 || this.historyIndex >= this.history.length) return;
        
        const historyState = this.history[this.historyIndex];

        // Create new layers from history state
        this.layers = [];
        
        historyState.forEach((layerData, index) => {
            const layer = {
                name: layerData.name,
                canvas: document.createElement('canvas'),
                visible: layerData.visible,
                opacity: layerData.opacity,
                objects: [],
                filters: layerData.filters || { ...this.filters }
            };
            
            layer.canvas.width = this.canvas.width;
            layer.canvas.height = this.canvas.height;
            
            // Load canvas data
            const img = new Image();
            img.onload = () => {
                const ctx = layer.canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                this.renderLayers();
            };
            img.src = layerData.canvas;
            
            this.layers.push(layer);
        });
        
        this.activeLayer = Math.min(this.activeLayer, this.layers.length - 1);
        this.renderLayers();
    }

    // Export functionality
    exportImage(type = 'image/png', quality = 0.9) {
        return this.canvas.toDataURL(type, quality);
    }

    // Export layers for saving state
    exportLayers() {
        return this.layers.map(layer => ({
            name: layer.name,
            canvas: layer.canvas.toDataURL(),
            visible: layer.visible,
            opacity: layer.opacity,
            filters: { ...layer.filters }
        }));
    }

    // Import image to layer
    importImage(src, layerIndex = this.activeLayer) {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const ctx = this.layers[layerIndex].canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);
            this.renderLayers();
            this.saveToHistory();
        };
        img.src = src;
    }

    // Import image from file
    importImageFromFile(file, layerIndex = this.activeLayer) {
        if (!file || !file.type.startsWith('image/')) {
            console.error('Invalid file type. Please provide an image file.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (e) => {
            this.importImage(e.target.result, layerIndex);
        };
        reader.readAsDataURL(file);
    }

    // Template management
    loadTemplatesFromStorage() {
        try {
            const templatesJson = localStorage.getItem('drawingTools_templates');
            if (templatesJson) {
                this.templates = JSON.parse(templatesJson);
            }
            
            // Add default templates if none exist
            if (Object.keys(this.templates).length === 0) {
                this.createDefaultTemplates();
            }
        } catch (e) {
            console.warn('Could not load templates from localStorage', e);
            this.createDefaultTemplates();
        }
    }
    
    createDefaultTemplates() {
        // Empty canvas template
        this.templates['empty'] = {
            name: 'Empty Canvas',
            description: 'Start with a blank canvas',
            thumbnail: null,
            layers: [{
                name: 'Background',
                canvas: null,
                visible: true,
                opacity: 1,
                filters: { ...this.filters }
            }, {
                name: 'Layer 1',
                canvas: null,
                visible: true,
                opacity: 1,
                filters: { ...this.filters }
            }]
        };
        
        // Grid template
        const gridCanvas = document.createElement('canvas');
        gridCanvas.width = this.canvas.width;
        gridCanvas.height = this.canvas.height;
        const gridCtx = gridCanvas.getContext('2d');
        
        // Draw grid
        gridCtx.strokeStyle = '#cccccc';
        gridCtx.lineWidth = 1;
        
        // Draw vertical lines
        for (let x = 0; x <= gridCanvas.width; x += 20) {
            gridCtx.beginPath();
            gridCtx.moveTo(x, 0);
            gridCtx.lineTo(x, gridCanvas.height);
            gridCtx.stroke();
        }
        
        // Draw horizontal lines
        for (let y = 0; y <= gridCanvas.height; y += 20) {
            gridCtx.beginPath();
            gridCtx.moveTo(0, y);
            gridCtx.lineTo(gridCanvas.width, y);
            gridCtx.stroke();
        }
        
        this.templates['grid'] = {
            name: 'Grid',
            description: 'Start with a grid background',
            thumbnail: gridCanvas.toDataURL(),
            layers: [{
                name: 'Background',
                canvas: gridCanvas.toDataURL(),
                visible: true,
                opacity: 1,
                filters: { ...this.filters }
            }, {
                name: 'Layer 1',
                canvas: null,
                visible: true,
                opacity: 1,
                filters: { ...this.filters }
            }]
        };
    }

    getTemplates() {
        return Object.keys(this.templates).map(key => ({
            id: key,
            name: this
