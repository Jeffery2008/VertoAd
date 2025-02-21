/**
 * Drawing Tools for Ad Canvas
 * Provides tools for creating and editing ad creative content
 */

class DrawingTools {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.elements = [];
        this.selectedElement = null;
        this.undoStack = [];
        this.redoStack = [];
        this.currentTool = 'select';
        this.history = [];
        
        // Set default options
        this.options = {
            strokeColor: '#000000',
            fillColor: '#ffffff',
            lineWidth: 2,
            fontSize: 16,
            fontFamily: 'Arial',
            ...options
        };
        
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        // Mouse events
        this.canvas.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.canvas.addEventListener('mousemove', this.handleMouseMove.bind(this));
        this.canvas.addEventListener('mouseup', this.handleMouseUp.bind(this));
        
        // Touch events for mobile
        this.canvas.addEventListener('touchstart', this.handleTouchStart.bind(this));
        this.canvas.addEventListener('touchmove', this.handleTouchMove.bind(this));
        this.canvas.addEventListener('touchend', this.handleTouchEnd.bind(this));
        
        // Keyboard events
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
    }
    
    // Tool Selection Methods
    setTool(toolName) {
        this.currentTool = toolName;
        this.canvas.style.cursor = this.getToolCursor(toolName);
    }
    
    getToolCursor(toolName) {
        const cursors = {
            'select': 'default',
            'rectangle': 'crosshair',
            'circle': 'crosshair',
            'text': 'text',
            'image': 'cell',
            'draw': 'crosshair'
        };
        return cursors[toolName] || 'default';
    }
    
    // Drawing Methods
    addElement(element) {
        this.elements.push(element);
        this.saveState();
        this.redraw();
    }
    
    removeElement(element) {
        const index = this.elements.indexOf(element);
        if (index > -1) {
            this.elements.splice(index, 1);
            this.saveState();
            this.redraw();
        }
    }
    
    updateElement(element, properties) {
        Object.assign(element, properties);
        this.saveState();
        this.redraw();
    }
    
    // Shape Drawing Methods
    drawRectangle(x, y, width, height, style = {}) {
        const element = {
            type: 'rectangle',
            x, y, width, height,
            strokeColor: style.strokeColor || this.options.strokeColor,
            fillColor: style.fillColor || this.options.fillColor,
            lineWidth: style.lineWidth || this.options.lineWidth
        };
        this.addElement(element);
    }
    
    drawCircle(x, y, radius, style = {}) {
        const element = {
            type: 'circle',
            x, y, radius,
            strokeColor: style.strokeColor || this.options.strokeColor,
            fillColor: style.fillColor || this.options.fillColor,
            lineWidth: style.lineWidth || this.options.lineWidth
        };
        this.addElement(element);
    }
    
    addText(text, x, y, style = {}) {
        const element = {
            type: 'text',
            text, x, y,
            fontSize: style.fontSize || this.options.fontSize,
            fontFamily: style.fontFamily || this.options.fontFamily,
            color: style.color || this.options.strokeColor
        };
        this.addElement(element);
    }
    
    addImage(imageUrl, x, y, width, height) {
        const img = new Image();
        img.src = imageUrl;
        img.onload = () => {
            const element = {
                type: 'image',
                x, y, width, height,
                image: img
            };
            this.addElement(element);
        };
    }
    
    // Event Handlers
    handleMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        this.isDrawing = true;
        this.startX = x;
        this.startY = y;
        
        if (this.currentTool === 'select') {
            this.selectElementAt(x, y);
        }
    }
    
    handleMouseMove(e) {
        if (!this.isDrawing) return;
        
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        switch (this.currentTool) {
            case 'select':
                if (this.selectedElement) {
                    this.moveElement(this.selectedElement, x - this.startX, y - this.startY);
                }
                break;
                
            case 'rectangle':
                this.previewRectangle(this.startX, this.startY, x - this.startX, y - this.startY);
                break;
                
            case 'circle':
                const radius = Math.sqrt(Math.pow(x - this.startX, 2) + Math.pow(y - this.startY, 2));
                this.previewCircle(this.startX, this.startY, radius);
                break;
                
            case 'draw':
                this.addPoint(x, y);
                break;
        }
        
        this.startX = x;
        this.startY = y;
    }
    
    handleMouseUp() {
        this.isDrawing = false;
        this.saveState();
    }
    
    // Touch Event Handlers
    handleTouchStart(e) {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent('mousedown', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        this.handleMouseDown(mouseEvent);
    }
    
    handleTouchMove(e) {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent('mousemove', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        this.handleMouseMove(mouseEvent);
    }
    
    handleTouchEnd(e) {
        e.preventDefault();
        this.handleMouseUp();
    }
    
    // Keyboard Event Handler
    handleKeyDown(e) {
        if (this.selectedElement) {
            const step = e.shiftKey ? 10 : 1;
            
            switch (e.key) {
                case 'ArrowLeft':
                    this.moveElement(this.selectedElement, -step, 0);
                    break;
                case 'ArrowRight':
                    this.moveElement(this.selectedElement, step, 0);
                    break;
                case 'ArrowUp':
                    this.moveElement(this.selectedElement, 0, -step);
                    break;
                case 'ArrowDown':
                    this.moveElement(this.selectedElement, 0, step);
                    break;
                case 'Delete':
                    this.removeElement(this.selectedElement);
                    this.selectedElement = null;
                    break;
            }
            
            e.preventDefault();
        }
    }
    
    // Element Manipulation
    moveElement(element, dx, dy) {
        element.x += dx;
        element.y += dy;
        this.redraw();
    }
    
    selectElementAt(x, y) {
        this.selectedElement = this.elements.find(element => this.isPointInElement(x, y, element));
        this.redraw();
    }
    
    isPointInElement(x, y, element) {
        switch (element.type) {
            case 'rectangle':
                return x >= element.x && x <= element.x + element.width &&
                       y >= element.y && y <= element.y + element.height;
                       
            case 'circle':
                const dx = x - element.x;
                const dy = y - element.y;
                return Math.sqrt(dx * dx + dy * dy) <= element.radius;
                
            case 'text':
                this.ctx.font = `${element.fontSize}px ${element.fontFamily}`;
                const metrics = this.ctx.measureText(element.text);
                return x >= element.x && x <= element.x + metrics.width &&
                       y >= element.y - element.fontSize && y <= element.y;
                       
            case 'image':
                return x >= element.x && x <= element.x + element.width &&
                       y >= element.y && y <= element.y + element.height;
        }
        return false;
    }
    
    // History Management
    saveState() {
        this.history.push(JSON.stringify(this.elements));
        if (this.history.length > 50) {
            this.history.shift();
        }
    }
    
    undo() {
        if (this.history.length > 1) {
            this.redoStack.push(this.history.pop());
            this.elements = JSON.parse(this.history[this.history.length - 1]);
            this.redraw();
        }
    }
    
    redo() {
        if (this.redoStack.length > 0) {
            const state = this.redoStack.pop();
            this.history.push(state);
            this.elements = JSON.parse(state);
            this.redraw();
        }
    }
    
    // Canvas Operations
    clear() {
        this.elements = [];
        this.selectedElement = null;
        this.saveState();
        this.redraw();
    }
    
    redraw() {
        // Clear canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw all elements
        this.elements.forEach(element => {
            this.drawElement(element);
            
            // Draw selection indicator
            if (element === this.selectedElement) {
                this.drawSelectionBox(element);
            }
        });
    }
    
    drawElement(element) {
        switch (element.type) {
            case 'rectangle':
                this.ctx.beginPath();
                this.ctx.strokeStyle = element.strokeColor;
                this.ctx.fillStyle = element.fillColor;
                this.ctx.lineWidth = element.lineWidth;
                this.ctx.rect(element.x, element.y, element.width, element.height);
                this.ctx.fill();
                this.ctx.stroke();
                break;
                
            case 'circle':
                this.ctx.beginPath();
                this.ctx.strokeStyle = element.strokeColor;
                this.ctx.fillStyle = element.fillColor;
                this.ctx.lineWidth = element.lineWidth;
                this.ctx.arc(element.x, element.y, element.radius, 0, Math.PI * 2);
                this.ctx.fill();
                this.ctx.stroke();
                break;
                
            case 'text':
                this.ctx.font = `${element.fontSize}px ${element.fontFamily}`;
                this.ctx.fillStyle = element.color;
                this.ctx.fillText(element.text, element.x, element.y);
                break;
                
            case 'image':
                if (element.image.complete) {
                    this.ctx.drawImage(element.image, element.x, element.y, element.width, element.height);
                }
                break;
        }
    }
    
    drawSelectionBox(element) {
        this.ctx.strokeStyle = '#0088ff';
        this.ctx.lineWidth = 1;
        this.ctx.setLineDash([5, 5]);
        
        switch (element.type) {
            case 'rectangle':
            case 'image':
                this.ctx.strokeRect(
                    element.x - 5,
                    element.y - 5,
                    element.width + 10,
                    element.height + 10
                );
                break;
                
            case 'circle':
                this.ctx.beginPath();
                this.ctx.arc(element.x, element.y, element.radius + 5, 0, Math.PI * 2);
                this.ctx.stroke();
                break;
                
            case 'text':
                const metrics = this.ctx.measureText(element.text);
                this.ctx.strokeRect(
                    element.x - 5,
                    element.y - element.fontSize - 5,
                    metrics.width + 10,
                    element.fontSize + 10
                );
                break;
        }
        
        this.ctx.setLineDash([]);
    }
    
    // Export Methods
    toDataURL() {
        return this.canvas.toDataURL();
    }
    
    toJSON() {
        return JSON.stringify(this.elements);
    }
    
    fromJSON(json) {
        this.elements = JSON.parse(json);
        this.saveState();
        this.redraw();
    }
}
