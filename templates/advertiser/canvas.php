<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Designer Canvas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            overflow: hidden;
        }
        
        .container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .toolbar {
            background-color: #333;
            color: white;
            padding: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .toolbar button {
            background-color: #555;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .toolbar button:hover {
            background-color: #777;
        }
        
        .toolbar button.active {
            background-color: #4CAF50;
        }
        
        .toolbar input[type="color"] {
            width: 40px;
            height: 30px;
            padding: 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .toolbar input[type="number"] {
            width: 60px;
            padding: 4px;
            border: 1px solid #555;
            border-radius: 4px;
            background: #444;
            color: white;
        }
        
        .toolbar select {
            padding: 4px;
            border: 1px solid #555;
            border-radius: 4px;
            background: #444;
            color: white;
        }
        
        .main-area {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .layers-panel {
            width: 200px;
            background-color: #444;
            color: white;
            padding: 10px;
            overflow-y: auto;
        }
        
        .layer-item {
            padding: 8px;
            margin-bottom: 4px;
            background-color: #555;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .layer-item.active {
            background-color: #666;
            border: 1px solid #888;
        }
        
        .layer-item input[type="checkbox"] {
            margin: 0;
        }
        
        .layer-item .layer-name {
            flex: 1;
        }
        
        .layer-item .layer-opacity {
            width: 60px;
        }
        
        .canvas-container {
            flex: 1;
            position: relative;
            overflow: auto;
            background-color: #666;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        #adCanvas {
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }
        
        .templates-panel {
            width: 200px;
            background-color: #444;
            color: white;
            padding: 10px;
            overflow-y: auto;
        }
        
        .template-item {
            padding: 8px;
            margin-bottom: 4px;
            background-color: #555;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .template-item:hover {
            background-color: #666;
        }
        
        .separator {
            width: 1px;
            height: 24px;
            background-color: #555;
            margin: 0 8px;
        }
        
        .font-controls {
            display: none;
            gap: 8px;
            align-items: center;
        }
        
        .font-controls.active {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar" id="toolbar">
            <!-- Toolbar buttons will be added by JavaScript -->
        </div>
        
        <div class="main-area">
            <div class="layers-panel">
                <h3>Layers</h3>
                <button onclick="designer.addLayer()">Add Layer</button>
                <div id="layersList">
                    <!-- Layers will be added by JavaScript -->
                </div>
            </div>
            
            <div class="canvas-container">
                <canvas id="adCanvas"></canvas>
            </div>
            
            <div class="templates-panel">
                <h3>Templates</h3>
                <button onclick="saveCurrentAsTemplate()">Save As Template</button>
                <div id="templatesList">
                    <!-- Templates will be added by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="/static/js/drawingTools.js"></script>
    <script>
        // Initialize designer
        const canvas = document.getElementById('adCanvas');
        canvas.width = 800;
        canvas.height = 600;
        
        const designer = new AdDesigner('adCanvas');
        AdDesignerUI.initToolbar(designer, 'toolbar');
        
        // Layer management UI
        function updateLayersList() {
            const layersList = document.getElementById('layersList');
            layersList.innerHTML = '';
            
            designer.layers.forEach((layer, index) => {
                const layerItem = document.createElement('div');
                layerItem.className = `layer-item${layer === designer.activeLayer ? ' active' : ''}`;
                
                const visibilityToggle = document.createElement('input');
                visibilityToggle.type = 'checkbox';
                visibilityToggle.checked = layer.visible;
                visibilityToggle.onchange = () => {
                    layer.visible = visibilityToggle.checked;
                    designer.render();
                };
                
                const nameLabel = document.createElement('span');
                nameLabel.className = 'layer-name';
                nameLabel.textContent = layer.name;
                nameLabel.onclick = () => {
                    designer.activeLayer = layer;
                    updateLayersList();
                };
                
                const opacityInput = document.createElement('input');
                opacityInput.type = 'number';
                opacityInput.className = 'layer-opacity';
                opacityInput.min = 0;
                opacityInput.max = 100;
                opacityInput.value = Math.round(layer.opacity * 100);
                opacityInput.onchange = () => {
                    layer.opacity = opacityInput.value / 100;
                    designer.render();
                };
                
                layerItem.appendChild(visibilityToggle);
                layerItem.appendChild(nameLabel);
                layerItem.appendChild(opacityInput);
                layersList.appendChild(layerItem);
            });
        }
        
        // Template management
        function saveCurrentAsTemplate() {
            const name = prompt('Enter template name:');
            if (name) {
                designer.saveAsTemplate(name);
                updateTemplatesList();
            }
        }
        
        function updateTemplatesList() {
            const templatesList = document.getElementById('templatesList');
            templatesList.innerHTML = '';
            
            const templates = JSON.parse(localStorage.getItem('adTemplates') || '[]');
            templates.forEach(template => {
                const templateItem = document.createElement('div');
                templateItem.className = 'template-item';
                templateItem.textContent = template.name;
                templateItem.onclick = () => designer.loadTemplate(template.name);
                templatesList.appendChild(templateItem);
            });
        }
        
        // Initial update
        updateLayersList();
        updateTemplatesList();
        
        // Load auto-saved state if exists
        designer.loadAutoSave();
    </script>
</body>
</html>
