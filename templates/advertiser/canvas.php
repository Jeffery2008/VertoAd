<?php 
// Initialize variables
$ad = $ad ?? null;
$mode = $mode ?? 'create';
$positions = $positions ?? [];
$selectedPosition = $selectedPosition ?? ($positions[0]['id'] ?? null);
$template = $template ?? null;
?>

<div id="adCanvas" class="bg-white rounded-lg shadow" x-data="adDesigner">
    <!-- Previous HTML content remains the same until the script tag -->

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adDesigner', () => ({
        adInfo: {
            title: '',
            position_id: <?= $selectedPosition ?>,
            content: {}
        },
        canvasWidth: 800,
        canvasHeight: 600,
        currentTool: 'pencil',
        toolSettings: {
            color: '#000000',
            lineWidth: 2,
            opacity: 100
        },
        layers: [],
        selectedLayer: null,
        drawingTools: null,
        history: [],
        historyIndex: -1,
        autoSaveInterval: null,
        
        init() {
            this.initCanvas();
            this.createInitialLayer();
            this.bindEvents();
            this.setupAutoSave();
            
            if (<?= json_encode($ad !== null) ?>) {
                this.loadExistingAd(<?= json_encode($ad) ?>);
            }
        },

        setupAutoSave() {
            // Auto save every 30 seconds
            this.autoSaveInterval = setInterval(() => {
                this.saveAsDraft();
            }, 30000);
        },
        
        initCanvas() {
            const canvas = document.getElementById('mainCanvas');
            this.drawingTools = new DrawingTools(canvas, {
                strokeStyle: this.toolSettings.color,
                lineWidth: this.toolSettings.lineWidth,
                opacity: this.toolSettings.opacity / 100,
                tool: this.currentTool
            });
        },
        
        // Previous methods remain the same until saveToHistory

        saveToHistory() {
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1);
            }
            
            this.history.push(JSON.stringify(this.layers));
            this.historyIndex++;

            // Limit history size
            if (this.history.length > 50) {
                this.history.shift();
                this.historyIndex--;
            }
        },

        undo() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.loadHistoryState();
            }
        },

        redo() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.loadHistoryState();
            }
        },

        loadHistoryState() {
            const state = JSON.parse(this.history[this.historyIndex]);
            this.layers = state;
            this.redrawLayers();
        },

        async saveAsDraft() {
            try {
                const adData = this.prepareAdData();
                adData.status = 'draft';

                const response = await fetch('/api/v1/ads/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(adData)
                });

                if (!response.ok) throw new Error('Failed to save draft');

                // Show success notification
                this.showNotification('草稿已保存', 'success');
            } catch (error) {
                console.error('Error saving draft:', error);
                this.showNotification('保存草稿失败', 'error');
            }
        },

        async publishAd() {
            try {
                const adData = this.prepareAdData();
                adData.status = 'pending';

                const response = await fetch('/api/v1/ads/publish', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(adData)
                });

                if (!response.ok) throw new Error('Failed to publish ad');

                // Show success notification and redirect
                this.showNotification('广告已提交审核', 'success');
                setTimeout(() => {
                    window.location.href = '/advertiser/ads';
                }, 2000);
            } catch (error) {
                console.error('Error publishing ad:', error);
                this.showNotification('发布广告失败', 'error');
            }
        },

        prepareAdData() {
            // Prepare the structured JSON data for storage
            return {
                title: this.adInfo.title,
                position_id: this.adInfo.position_id,
                content: {
                    version: '1.0',
                    canvas: {
                        width: this.canvasWidth,
                        height: this.canvasHeight
                    },
                    layers: this.layers.map(layer => ({
                        ...layer,
                        content: layer.content ? layer.content.toString() : null
                    })),
                    history: this.history
                },
                type: 'canvas'
            };
        },

        loadExistingAd(ad) {
            this.adInfo.title = ad.title;
            this.adInfo.position_id = ad.position_id;

            if (ad.content) {
                const content = typeof ad.content === 'string' ? 
                    JSON.parse(ad.content) : ad.content;

                // Load canvas dimensions
                if (content.canvas) {
                    this.canvasWidth = content.canvas.width;
                    this.canvasHeight = content.canvas.height;
                    this.initCanvas(); // Reinitialize canvas with new dimensions
                }

                // Load layers
                if (content.layers) {
                    this.layers = content.layers.map(layer => ({
                        ...layer,
                        content: layer.content ? 
                            new Image(layer.content) : null
                    }));
                    this.redrawLayers();
                }

                // Load history
                if (content.history) {
                    this.history = content.history;
                    this.historyIndex = this.history.length - 1;
                }
            }
        },

        preview() {
            // Create a preview window
            const previewWindow = window.open('', '_blank');
            const adData = this.prepareAdData();

            // Generate preview HTML
            const html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>广告预览 - ${adData.title}</title>
                    <style>
                        body { margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f0f0f0; }
                        .preview-container { background: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    </style>
                </head>
                <body>
                    <div class="preview-container">
                        <img src="${this.drawingTools.toImage()}" 
                             width="${this.canvasWidth}" 
                             height="${this.canvasHeight}"
                             style="display: block;">
                    </div>
                </body>
                </html>
            `;

            previewWindow.document.write(html);
            previewWindow.document.close();
        },

        showNotification(message, type = 'info') {
            // You can replace this with your preferred notification system
            const div = document.createElement('div');
            div.className = `fixed top-4 right-4 p-4 rounded shadow ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            } text-white`;
            div.textContent = message;
            document.body.appendChild(div);

            setTimeout(() => {
                div.remove();
            }, 3000);
        },

        // Clean up on component destroy
        destroy() {
            if (this.autoSaveInterval) {
                clearInterval(this.autoSaveInterval);
            }
        }
    }));
});
</script>
</div>
