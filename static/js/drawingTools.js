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
            objects: []  // Store vector objects for this layer
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
                this.ctx.globalAlpha = layer.opacity;
                this.ctx.drawImage(layer.canvas, 0, 0);
            }
        });
        
        this.ctx.globalAlpha = 1;
    }

    bindEvents() {
        this.canvas.addEventListener('mousedown', this.startDrawing.bind(this));
        this.canvas.addEventListener('mousemove', this.draw.bind(this));
        this.canvas.addEventListener('mouseup', this.stopDrawing.bind(this));
        this.canvas.addEventListener('mouseout', this.stopDrawing.bind(this));
        this.canvas.addEventListener('dblclick', this.handleDoubleClick.bind(this));
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
                opacity: layer.opacity
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
                objects: []
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

    // Template management
    saveAsTemplate(name) {
        const template = {
            layers: this.layers.map(layer => {
                return {
                    canvas: layer.canvas.toDataURL(),
                    name: layer.name,
                    visible: layer.visible,
                    opacity: layer.opacity
                };
            })
        };
        
        this.templates[name] = template;
        
        // Also save to localStorage for persistence
        try {
            const templatesJson = JSON.stringify(this.templates);
            localStorage.setItem('drawingTools_templates', templatesJson);
        } catch (e) {
            console.warn('Could not save template to localStorage', e);
        }
        
        return template;
    }

    loadTemplate(name) {
        const template = this.templates[name];
        if (!template) return false;
        
        // Clear current layers
        this.layers = [];
        
        // Load template layers
        template.layers.forEach(layerData => {
            const layer = {
                name: layerData.name,
                canvas: document.createElement('canvas'),
                visible: layerData.visible,
                opacity: layerData.opacity,
                objects: []
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
        
        this.activeLayer = 0;
        this.renderLayers();
        this.saveToHistory();
        return true;
    }

    // Auto-save functionality
    autoSave() {
        const autoSaveData = {
            timestamp: Date.now(),
            layers: this.layers.map(layer => {
                return {
                    canvas: layer.canvas.toDataURL(),
                    name: layer.name,
                    visible: layer.visible,
                    opacity: layer.opacity
                };
            })
        };

        try {
            localStorage.setItem('drawingTools_autoSave', JSON.stringify(autoSaveData));
        } catch (e) {
            console.warn('AutoSave failed', e);
        }
    }

    restoreAutoSave() {
        try {
            const savedData = localStorage.getItem('drawingTools_autoSave');
            if (!savedData) return false;
            
            const autoSaveData = JSON.parse(savedData);
            
            // Clear current layers
            this.layers = [];
            
            // Load auto-saved layers
            autoSaveData.layers.forEach(layerData => {
                const layer = {
                    name: layerData.name,
                    canvas: document.createElement('canvas'),
                    visible: layerData.visible,
                    opacity: layerData.opacity,
                    objects: []
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
            
            this.activeLayer = 0;
            this.renderLayers();
            this.saveToHistory();
            return true;
        } catch (e) {
            console.warn('Could not restore auto-save', e);
            return false;
        }
    }

    // Utility methods
    clearLayer(layerIndex = this.activeLayer) {
        const ctx = this.layers[layerIndex].canvas.getContext('2d');
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.renderLayers();
        this.saveToHistory();
    }

    clearAllLayers() {
        this.layers.forEach((layer, index) => {
            const ctx = layer.canvas.getContext('2d');
            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        });
        this.renderLayers();
        this.saveToHistory();
    }

    resize(width, height, scaleContent = true) {
        if (scaleContent) {
            // Create temporary canvas to store current content
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = this.canvas.width;
            tempCanvas.height = this.canvas.height;
            const tempCtx = tempCanvas.getContext('2d');
            tempCtx.drawImage(this.canvas, 0, 0);
            
            // Resize main canvas
            this.canvas.width = width;
            this.canvas.height = height;
            
            // Resize all layer canvases
            this.layers.forEach(layer => {
                const layerContent = layer.canvas.toDataURL();
                layer.canvas.width = width;
                layer.canvas.height = height;
                
                // Restore and scale content
                const img = new Image();
                img.onload = () => {
                    const ctx = layer.canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    this.renderLayers();
                };
                img.src = layerContent;
            });
        } else {
            // Simple resize without scaling
            this.canvas.width = width;
            this.canvas.height = height;
            
            this.layers.forEach(layer => {
                layer.canvas.width = width;
                layer.canvas.height = height;
            });
            
            this.renderLayers();
        }
        
        this.saveToHistory();
    }
    
    // Clean up resources
    destroy() {
        // Clear auto-save interval
        clearInterval(this.autoSaveInterval);
        
        // Remove event listeners
        this.canvas.removeEventListener('mousedown', this.startDrawing);
        this.canvas.removeEventListener('mousemove', this.draw);
        this.canvas.removeEventListener('mouseup', this.stopDrawing);
        this.canvas.removeEventListener('mouseout', this.stopDrawing);
        this.canvas.removeEventListener('dblclick', this.handleDoubleClick);
        
        // Remove any active text editing elements
        if (this.textElement && this.textElement.parentNode) {
            this.textElement.parentNode.removeChild(this.textElement);
        }
    }
}

// Export for different module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DrawingTools;
} else if (typeof define === 'function' && define.amd) {
    define([], function() { return DrawingTools; });
} else {
    window.DrawingTools = DrawingTools;
}
