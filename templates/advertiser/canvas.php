<?php 
// Initialize variables
$ad = $ad ?? null;
$mode = $mode ?? 'create';
$positions = $positions ?? [];
$selectedPosition = $selectedPosition ?? ($positions[0]['id'] ?? null);
$template = $template ?? null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>广告设计器</title>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="/static/js/drawingTools.js"></script>
</head>
<body>
<div id="adCanvas" class="bg-white min-h-screen" x-data="adDesigner">
    <!-- Previous content remains until script tag -->
    <!-- [Previous HTML content remains unchanged] -->

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
            opacity: 100,
            fontFamily: 'Arial',
            fontSize: 16
        },
        layers: [],
        selectedLayer: 0,
        drawingTools: null,

        init() {
            this.initCanvas();
            if (<?= json_encode($ad !== null) ?>) {
                this.loadExistingAd(<?= json_encode($ad) ?>);
            }
            this.setupAutoSave();
        },

        get canvasContainerStyle() {
            return {
                width: `${this.canvasWidth}px`,
                height: `${this.canvasHeight}px`
            };
        },

        initCanvas() {
            const canvas = document.getElementById('mainCanvas');
            this.drawingTools = new DrawingTools(canvas, {
                strokeStyle: this.toolSettings.color,
                lineWidth: this.toolSettings.lineWidth,
                opacity: this.toolSettings.opacity / 100,
                tool: this.currentTool,
                fontFamily: this.toolSettings.fontFamily,
                fontSize: `${this.toolSettings.fontSize}px`
            });

            // Initialize layer data from DrawingTools
            this.layers = this.drawingTools.layers.map(layer => ({
                name: layer.name,
                visible: layer.visible,
                opacity: layer.opacity * 100
            }));
        },

        setTool(tool) {
            this.currentTool = tool;
            this.drawingTools.setTool(tool);
        },

        updateToolSettings() {
            this.drawingTools.setColor(this.toolSettings.color);
            this.drawingTools.setLineWidth(this.toolSettings.lineWidth);
            this.drawingTools.setOpacity(this.toolSettings.opacity / 100);
            
            if (this.currentTool === 'text') {
                this.drawingTools.setFontOptions({
                    fontFamily: this.toolSettings.fontFamily,
                    fontSize: `${this.toolSettings.fontSize}px`
                });
            }
        },

        addLayer() {
            const index = this.drawingTools.addLayer(`图层 ${this.layers.length + 1}`);
            this.layers = this.drawingTools.layers.map(layer => ({
                name: layer.name,
                visible: layer.visible,
                opacity: layer.opacity * 100
            }));
            this.selectedLayer = index;
        },

        deleteLayer(index) {
            this.drawingTools.removeLayer(index);
            this.layers = this.drawingTools.layers.map(layer => ({
                name: layer.name,
                visible: layer.visible,
                opacity: layer.opacity * 100
            }));
            if (this.selectedLayer >= this.layers.length) {
                this.selectedLayer = this.layers.length - 1;
            }
        },

        toggleLayerVisibility(index) {
            this.drawingTools.setLayerVisibility(index, this.layers[index].visible);
        },

        updateLayerOpacity(index) {
            this.drawingTools.setLayerOpacity(index, this.layers[index].opacity / 100);
        },

        undo() {
            this.drawingTools.undo();
            this.syncLayersFromDrawingTools();
        },

        redo() {
            this.drawingTools.redo();
            this.syncLayersFromDrawingTools();
        },

        syncLayersFromDrawingTools() {
            this.layers = this.drawingTools.layers.map(layer => ({
                name: layer.name,
                visible: layer.visible,
                opacity: layer.opacity * 100
            }));
        },

        setupAutoSave() {
            setInterval(() => {
                this.saveAsDraft();
            }, 30000);
        },

        prepareAdData() {
            return {
                id: <?= $ad['id'] ?? 'null' ?>,
                title: this.adInfo.title,
                position_id: this.adInfo.position_id,
                content: {
                    width: this.canvasWidth,
                    height: this.canvasHeight,
                    image: this.drawingTools.exportImage(),
                    layers: this.drawingTools.layers.map(layer => ({
                        name: layer.name,
                        visible: layer.visible,
                        opacity: layer.opacity,
                        content: layer.canvas.toDataURL()
                    })),
                    version: '1.0'
                }
            };
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
                this.showNotification('草稿已保存', 'success');
            } catch (error) {
                console.error('Error saving draft:', error);
                this.showNotification('保存草稿失败', 'error');
            }
        },

        async publishAd() {
            try {
                if (!this.adInfo.title.trim()) {
                    this.showNotification('请输入广告标题', 'error');
                    return;
                }

                const adData = this.prepareAdData();
                adData.status = 'pending';

                const response = await fetch('/api/v1/ads/publish', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(adData)
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to publish ad');
                }

                this.showNotification('广告已提交审核', 'success');
                
                // Redirect to ad list after successful publish
                setTimeout(() => {
                    window.location.href = '/advertiser/ads';
                }, 1500);
            } catch (error) {
                console.error('Error publishing ad:', error);
                this.showNotification(error.message || '发布广告失败', 'error');
            }
        },

        preview() {
            // Create preview window with current canvas state
            const previewWindow = window.open('', '_blank');
            const adData = this.prepareAdData();
            
            const html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>广告预览 - ${this.adInfo.title}</title>
                    <style>
                        body { margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f0f0f0; }
                        .preview-container { background: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    </style>
                </head>
                <body>
                    <div class="preview-container">
                        <h2 style="margin-bottom: 1rem;">${this.adInfo.title}</h2>
                        <img src="${adData.content.image}" 
                             width="${this.canvasWidth}" 
                             height="${this.canvasHeight}"
                             style="display: block; border: 1px solid #eee;">
                    </div>
                </body>
                </html>
            `;

            previewWindow.document.write(html);
            previewWindow.document.close();
        },

        loadExistingAd(ad) {
            this.adInfo.title = ad.title;
            this.adInfo.position_id = ad.position_id;
            
            if (ad.content) {
                // Set canvas dimensions
                this.canvasWidth = ad.content.width || 800;
                this.canvasHeight = ad.content.height || 600;
                
                this.initCanvas(); // Reinitialize canvas with new dimensions
                
                // Load layers
                if (ad.content.layers) {
                    ad.content.layers.forEach((layerData, index) => {
                        if (index < this.drawingTools.layers.length) {
                            // Update existing layer
                            this.drawingTools.layers[index].name = layerData.name;
                            this.drawingTools.layers[index].visible = layerData.visible;
                            this.drawingTools.layers[index].opacity = layerData.opacity;
                            this.drawingTools.importImage(layerData.content, index);
                        } else {
                            // Create new layer
                            const newIndex = this.drawingTools.addLayer(layerData.name);
                            this.drawingTools.layers[newIndex].visible = layerData.visible;
                            this.drawingTools.layers[newIndex].opacity = layerData.opacity;
                            this.drawingTools.importImage(layerData.content, newIndex);
                        }
                    });
                    
                    this.syncLayersFromDrawingTools();
                }
            }
        },

        showNotification(message, type = 'info') {
            const div = document.createElement('div');
            div.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            div.textContent = message;
            document.body.appendChild(div);
            
            setTimeout(() => {
                div.remove();
            }, 3000);
        }
    }));
});
</script>
</body>
</html>
