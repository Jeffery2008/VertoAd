/**
 * Drawing Tools for Ad Designer
 * Features:
 * - Layer management
 * - Rich drawing tools (pencil, marker, highlighter)
 * - Text editing with font controls
 * - Shape tools
 * - Image import/edit
 * - Filters
 * - History/undo
 * - Auto-save
 * - Templates
 * - Keyboard shortcuts
 */

class AdDesigner {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        
        // Initialize layers
        this.layers = [];
        this.activeLayer = null;
        this.history = [];
        this.historyIndex = -1;
        
        // Tool states
        this.currentTool = 'pencil';
        this.color = '#000000';
        this.lineWidth = 2;
        this.fontSize = 16;
        this.fontFamily = 'Arial';
        this.textAlign = 'left';
        
        // Auto-save interval (5 seconds)
        this.autoSaveInterval = 5000;
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.createDefaultLayer();
        this.startAutoSave();
        this.initKeyboardShortcuts();
    }
    
    // Layer Management
    createDefaultLayer() {
        const layer = {
            id: 'layer-' + Date.now(),
            name: 'Layer 1',
            visible: true,
            locked: false,
            canvas: document.createElement('canvas'),
            opacity: 1
        };
        
        layer.canvas.width = this.canvas.width;
        layer.canvas.height = this.canvas.height;
        
        this.layers.push(layer);
        this.activeLayer = layer;
        this.saveToHistory();
    }
    
    addLayer() {
        const layer = {
            id: 'layer-' + Date.now(),
            name: 'Layer ' + (this.layers.length + 1),
            visible: true,
            locked: false,
            canvas: document.createElement('canvas'),
            opacity: 1
        };
        
        layer.canvas.width = this.canvas.width;
        layer.canvas.height = this.canvas.height;
        
        this.layers.push(layer);
        this.activeLayer = layer;
        this.saveToHistory();
        this.render();
    }
    
    // Drawing Tools
    setPencilTool() {
        this.currentTool = 'pencil';
        this.lineWidth = 2;
    }
    
    setMarkerTool() {
        this.currentTool = 'marker';
        this.lineWidth = 8;
    }
    
    setHighlighterTool() {
        this.currentTool = 'highlighter';
        this.lineWidth = 20;
        this.ctx.globalAlpha = 0.3;
    }
    
    // Text Tool
    setText(text, x, y) {
        const ctx = this.activeLayer.canvas.getContext('2d');
        ctx.font = `${this.fontSize}px ${this.fontFamily}`;
        ctx.fillStyle = this.color;
        ctx.textAlign = this.textAlign;
        ctx.fillText(text, x, y);
        this.saveToHistory();
        this.render();
    }
    
    // Shape Tools
    drawRect(x1, y1, x2, y2, filled = false) {
        const ctx = this.activeLayer.canvas.getContext('2d');
        ctx.beginPath();
        ctx.rect(x1, y1, x2 - x1, y2 - y1);
        if (filled) {
            ctx.fill();
        } else {
            ctx.stroke();
        }
        this.saveToHistory();
        this.render();
    }
    
    drawCircle(x1, y1, x2, y2, filled = false) {
        const ctx = this.activeLayer.canvas.getContext('2d');
        const radius = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
        ctx.beginPath();
        ctx.arc(x1, y1, radius, 0, Math.PI * 2);
        if (filled) {
            ctx.fill();
        } else {
            ctx.stroke();
        }
        this.saveToHistory();
        this.render();
    }
    
    drawLine(x1, y1, x2, y2) {
        const ctx = this.activeLayer.canvas.getContext('2d');
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        this.saveToHistory();
        this.render();
    }
    
    // Image Tools
    importImage(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const layer = {
                    id: 'layer-' + Date.now(),
                    name: 'Image Layer',
                    visible: true,
                    locked: false,
                    canvas: document.createElement('canvas'),
                    opacity: 1
                };
                
                layer.canvas.width = this.canvas.width;
                layer.canvas.height = this.canvas.height;
                
                const ctx = layer.canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                this.layers.push(layer);
                this.activeLayer = layer;
                this.saveToHistory();
                this.render();
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
    // Filter Effects
    applyFilter(filterType) {
        const ctx = this.activeLayer.canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        const pixels = imageData.data;
        
        switch (filterType) {
            case 'grayscale':
                for (let i = 0; i < pixels.length; i += 4) {
                    const avg = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
                    pixels[i] = avg;
                    pixels[i + 1] = avg;
                    pixels[i + 2] = avg;
                }
                break;
                
            case 'sepia':
                for (let i = 0; i < pixels.length; i += 4) {
                    const r = pixels[i];
                    const g = pixels[i + 1];
                    const b = pixels[i + 2];
                    pixels[i] = (r * 0.393) + (g * 0.769) + (b * 0.189);
                    pixels[i + 1] = (r * 0.349) + (g * 0.686) + (b * 0.168);
                    pixels[i + 2] = (r * 0.272) + (g * 0.534) + (b * 0.131);
                }
                break;
                
            case 'invert':
                for (let i = 0; i < pixels.length; i += 4) {
                    pixels[i] = 255 - pixels[i];
                    pixels[i + 1] = 255 - pixels[i + 1];
                    pixels[i + 2] = 255 - pixels[i + 2];
                }
                break;
        }
        
        ctx.putImageData(imageData, 0, 0);
        this.saveToHistory();
        this.render();
    }
    
    // History Management
    saveToHistory() {
        // Remove any forward history if we're not at the latest state
        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }
        
        // Save current state
        const state = this.layers.map(layer => {
            return {
                id: layer.id,
                name: layer.name,
                visible: layer.visible,
                locked: layer.locked,
                imageData: layer.canvas.toDataURL(),
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
            this.restoreHistory();
        }
    }
    
    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.restoreHistory();
        }
    }
    
    restoreHistory() {
        const state = this.history[this.historyIndex];
        this.layers = state.map(layerState => {
            const img = new Image();
            img.src = layerState.imageData;
            
            const layer = {
                id: layerState.id,
                name: layerState.name,
                visible: layerState.visible,
                locked: layerState.locked,
                canvas: document.createElement('canvas'),
                opacity: layerState.opacity
            };
            
            layer.canvas.width = this.canvas.width;
            layer.canvas.height = this.canvas.height;
            
            const ctx = layer.canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            return layer;
        });
        
        this.render();
    }
    
    // Auto-save
    startAutoSave() {
        setInterval(() => {
            const state = {
                layers: this.layers.map(layer => ({
                    id: layer.id,
                    name: layer.name,
                    visible: layer.visible,
                    locked: layer.locked,
                    imageData: layer.canvas.toDataURL(),
                    opacity: layer.opacity
                })),
                activeLayerId: this.activeLayer.id
            };
            
            localStorage.setItem('adDesignerState', JSON.stringify(state));
        }, this.autoSaveInterval);
    }
    
    loadAutoSave() {
        const savedState = localStorage.getItem('adDesignerState');
        if (savedState) {
            const state = JSON.parse(savedState);
            
            this.layers = state.layers.map(layerState => {
                const img = new Image();
                img.src = layerState.imageData;
                
                const layer = {
                    id: layerState.id,
                    name: layerState.name,
                    visible: layerState.visible,
                    locked: layerState.locked,
                    canvas: document.createElement('canvas'),
                    opacity: layerState.opacity
                };
                
                layer.canvas.width = this.canvas.width;
                layer.canvas.height = this.canvas.height;
                
                const ctx = layer.canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                return layer;
            });
            
            this.activeLayer = this.layers.find(layer => layer.id === state.activeLayerId);
            this.render();
        }
    }
    
    // Template System
    saveAsTemplate(name) {
        const template = {
            name,
            width: this.canvas.width,
            height: this.canvas.height,
            layers: this.layers.map(layer => ({
                name: layer.name,
                imageData: layer.canvas.toDataURL(),
                opacity: layer.opacity
            }))
        };
        
        let templates = JSON.parse(localStorage.getItem('adTemplates') || '[]');
        templates.push(template);
        localStorage.setItem('adTemplates', JSON.stringify(templates));
    }
    
    loadTemplate(templateName) {
        const templates = JSON.parse(localStorage.getItem('adTemplates') || '[]');
        const template = templates.find(t => t.name === templateName);
        
        if (template) {
            this.canvas.width = template.width;
            this.canvas.height = template.height;
            
            this.layers = template.layers.map(layerData => {
                const img = new Image();
                img.src = layerData.imageData;
                
                const layer = {
                    id: 'layer-' + Date.now(),
                    name: layerData.name,
                    visible: true,
                    locked: false,
                    canvas: document.createElement('canvas'),
                    opacity: layerData.opacity
                };
                
                layer.canvas.width = this.canvas.width;
                layer.canvas.height = this.canvas.height;
                
                const ctx = layer.canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                return layer;
            });
            
            this.activeLayer = this.layers[0];
            this.saveToHistory();
            this.render();
        }
    }
    
    // Keyboard Shortcuts
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key.toLowerCase()) {
                    case 'z':
                        if (e.shiftKey) {
                            this.redo();
                        } else {
                            this.undo();
                        }
                        e.preventDefault();
                        break;
                        
                    case 'y':
                        this.redo();
                        e.preventDefault();
                        break;
                        
                    case 's':
                        this.saveToHistory();
                        e.preventDefault();
                        break;
                }
            }
        });
    }
    
    // Event Listeners
    setupEventListeners() {
        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;
        
        this.canvas.addEventListener('mousedown', (e) => {
            isDrawing = true;
            [lastX, lastY] = [e.offsetX, e.offsetY];
        });
        
        this.canvas.addEventListener('mousemove', (e) => {
            if (!isDrawing || this.activeLayer.locked) return;
            
            const ctx = this.activeLayer.canvas.getContext('2d');
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.strokeStyle = this.color;
            ctx.lineWidth = this.lineWidth;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
            
            [lastX, lastY] = [e.offsetX, e.offsetY];
            this.render();
        });
        
        this.canvas.addEventListener('mouseup', () => {
            if (isDrawing) {
                isDrawing = false;
                this.saveToHistory();
            }
        });
        
        this.canvas.addEventListener('mouseleave', () => {
            if (isDrawing) {
                isDrawing = false;
                this.saveToHistory();
            }
        });
    }
    
    // Rendering
    render() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        this.layers.forEach(layer => {
            if (layer.visible) {
                this.ctx.globalAlpha = layer.opacity;
                this.ctx.drawImage(layer.canvas, 0, 0);
            }
        });
        
        // Reset global alpha after drawing
        this.ctx.globalAlpha = 1.0;
    }
    
    // Export functions
    exportAsImage(format = 'png') {
        return this.canvas.toDataURL(`image/${format}`);
    }
    
    // Utility functions
    clearLayer() {
        if (!this.activeLayer.locked) {
            const ctx = this.activeLayer.canvas.getContext('2d');
            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.saveToHistory();
            this.render();
        }
    }
    
    resizeCanvas(width, height) {
        // Create temp canvas to save current state
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
            const oldCanvas = layer.canvas;
            const newCanvas = document.createElement('canvas');
            newCanvas.width = width;
            newCanvas.height = height;
            
            const ctx = newCanvas.getContext('2d');
            ctx.drawImage(oldCanvas, 0, 0);
            
            layer.canvas = newCanvas;
        });
        
        this.saveToHistory();
        this.render();
    }
}

// Helper functions for the Ad Designer UI
const AdDesignerUI = {
    initToolbar(designer, toolbarId) {
        const toolbar = document.getElementById(toolbarId);
        if (!toolbar) return;
        
        // Create color picker
        const colorPicker = document.createElement('input');
        colorPicker.type = 'color';
        colorPicker.value = designer.color;
        colorPicker.addEventListener('change', (e) => {
            designer.color = e.target.value;
        });
        
        // Create tool buttons
        const tools = [
            { name: 'Pencil', action: () => designer.setPencilTool() },
            { name: 'Marker', action: () => designer.setMarkerTool() },
            { name: 'Highlighter', action: () => designer.setHighlighterTool() },
            { name: 'Line', action: () => designer.currentTool = 'line' },
            { name: 'Rectangle', action: () => designer.currentTool = 'rectangle' },
            { name: 'Circle', action: () => designer.currentTool = 'circle' },
            { name: 'Text', action: () => designer.currentTool = 'text' },
            { name: 'Eraser', action: () => { designer.currentTool = 'eraser'; designer.color = '#FFFFFF'; } },
            { name: 'Clear', action: () => designer.clearLayer() },
            { name: 'Undo', action: () => designer.undo() },
            { name: 'Redo', action: () => designer.redo() }
        ];
        
        tools.forEach(tool => {
            const button = document.createElement('button');
            button.textContent = tool.name;
            button.addEventListener('click', tool.action);
            toolbar.appendChild(button);
        });
        
        toolbar.appendChild(colorPicker);
        
        // Add font controls if text tool is selected
        const fontControls = document.createElement('div');
        fontControls.style.display = 'none';
        
        const fontSelect = document.createElement('select');
        ['Arial', 'Times New Roman', 'Courier New', 'Georgia', 'Verdana'].forEach(font => {
            const option = document.createElement('option');
            option.value = font;
            option.textContent = font;
            fontSelect.appendChild(option);
        });
        
        const fontSizeInput = document.createElement('input');
        fontSizeInput.type = 'number';
        fontSizeInput.min = '8';
        fontSizeInput.max = '72';
        fontSizeInput.value = designer.fontSize;
        
        fontSelect.addEventListener('change', (e) => {
            designer.fontFamily = e.target.value;
        });
        
        fontSizeInput.addEventListener('change', (e) => {
            designer.fontSize = parseInt(e.target.value, 10);
        });
        
        fontControls.appendChild(fontSelect);
        fontControls.appendChild(fontSizeInput);
        toolbar.appendChild(fontControls);
    }
};

// Export the AdDesigner class
window.AdDesigner = AdDesigner;
window.AdDesignerUI = AdDesignerUI;
