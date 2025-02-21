<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Designer</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .canvas-container {
            touch-action: none;
            user-select: none;
        }
        .tool-button.active {
            background-color: #60A5FA;
            color: white;
        }
        .layer-item.active {
            background-color: #EFF6FF;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Top Toolbar -->
        <div class="bg-white shadow-sm p-4">
            <div class="flex justify-between items-center max-w-7xl mx-auto">
                <div class="flex items-center space-x-4">
                    <button onclick="saveAd()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Save Ad
                    </button>
                    <button onclick="previewAd()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        Preview
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="drawingCanvas.undo()" class="p-2 text-gray-600 hover:bg-gray-100 rounded">
                        Undo
                    </button>
                    <button onclick="drawingCanvas.redo()" class="p-2 text-gray-600 hover:bg-gray-100 rounded">
                        Redo
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto p-6 flex gap-6">
            <!-- Left Sidebar - Tools -->
            <div class="w-64 bg-white rounded-lg shadow p-4 space-y-4">
                <h2 class="text-lg font-semibold mb-4">Tools</h2>
                
                <!-- Drawing Tools -->
                <div class="grid grid-cols-2 gap-2">
                    <button class="tool-button p-2 rounded text-sm" data-tool="pencil">
                        Pencil
                    </button>
                    <button class="tool-button p-2 rounded text-sm" data-tool="marker">
                        Marker
                    </button>
                    <button class="tool-button p-2 rounded text-sm" data-tool="highlighter">
                        Highlighter
                    </button>
                    <button class="tool-button p-2 rounded text-sm" data-tool="eraser">
                        Eraser
                    </button>
                    <button class="tool-button p-2 rounded text-sm" data-tool="line">
                        Line
                    </button>
                    <button class="tool-button p-2 rounded text-sm" data-tool="rectangle">
                        Rectangle
                    </button>
                    <button class="tool-button p-2 rounded text-sm" data-tool="circle">
                        Circle
                    </button>
                </div>

                <!-- Color Picker -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Color</label>
                    <input type="color" id="colorPicker" class="w-full h-10" 
                           onchange="drawingCanvas.setColor(this.value)">
                </div>

                <!-- Line Width -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Line Width</label>
                    <input type="range" min="1" max="50" value="2" class="w-full" 
                           onchange="drawingCanvas.setLineWidth(this.value)">
                </div>

                <!-- Opacity -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Opacity</label>
                    <input type="range" min="0" max="100" value="100" class="w-full" 
                           onchange="drawingCanvas.setOpacity(this.value / 100)">
                </div>
            </div>

            <!-- Main Canvas Area -->
            <div class="flex-1">
                <div class="bg-white rounded-lg shadow p-4">
                    <div id="canvasContainer" class="w-full bg-gray-50 rounded border border-gray-200"
                         style="aspect-ratio: 16/9;">
                    </div>
                </div>
            </div>

            <!-- Right Sidebar - Layers -->
            <div class="w-64 bg-white rounded-lg shadow p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Layers</h2>
                    <button onclick="drawingCanvas.addLayer()" 
                            class="p-1 text-blue-600 hover:bg-blue-50 rounded">
                        Add Layer
                    </button>
                </div>

                <div id="layersList" class="space-y-2">
                    <!-- Layers will be inserted here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center">
        <div class="bg-white rounded-lg max-w-3xl w-full m-6">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-semibold">Preview Ad</h3>
                <button onclick="closePreview()" class="text-gray-500 hover:text-gray-700">
                    ✕
                </button>
            </div>
            <div class="p-4">
                <img id="previewImage" class="w-full" alt="Ad Preview">
            </div>
            <div class="p-4 border-t flex justify-end space-x-4">
                <button onclick="closePreview()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                    Close
                </button>
                <button onclick="downloadAd()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Download
                </button>
            </div>
        </div>
    </div>

    <script src="/static/js/drawingTools.js"></script>
    <script>
        // Initialize drawing canvas
        const container = document.getElementById('canvasContainer');
        const drawingCanvas = new DrawingCanvas(container);

        // Tool buttons
        document.querySelectorAll('.tool-button').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                document.querySelectorAll('.tool-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                button.classList.add('active');
                
                // Set the tool
                drawingCanvas.setTool(button.dataset.tool);
            });
        });

        // Set initial active tool
        document.querySelector('[data-tool="pencil"]').classList.add('active');

        // Layer management
        function updateLayersList() {
            const layersList = document.getElementById('layersList');
            layersList.innerHTML = '';

            drawingCanvas.layers.forEach((layer, index) => {
                const layerItem = document.createElement('div');
                layerItem.className = `layer-item p-2 rounded flex items-center justify-between ${
                    layer === drawingCanvas.currentLayer ? 'active' : ''
                }`;

                const leftSide = document.createElement('div');
                leftSide.className = 'flex items-center space-x-2';

                // Visibility toggle
                const visibilityBtn = document.createElement('button');
                visibilityBtn.innerHTML = layer.visible ? '👁' : '👁‍🗨';
                visibilityBtn.className = 'text-sm';
                visibilityBtn.onclick = () => {
                    drawingCanvas.toggleLayerVisibility(index);
                    updateLayersList();
                };

                // Layer name
                const nameSpan = document.createElement('span');
                nameSpan.textContent = `Layer ${index + 1}`;
                nameSpan.className = 'text-sm';
                nameSpan.onclick = () => {
                    drawingCanvas.setCurrentLayer(index);
                    updateLayersList();
                };

                leftSide.appendChild(visibilityBtn);
                leftSide.appendChild(nameSpan);
                layerItem.appendChild(leftSide);

                // Layer controls
                const controls = document.createElement('div');
                controls.className = 'flex items-center space-x-2';

                // Lock toggle
                const lockBtn = document.createElement('button');
                lockBtn.innerHTML = layer.locked ? '🔒' : '🔓';
                lockBtn.className = 'text-sm';
                lockBtn.onclick = () => {
                    drawingCanvas.toggleLayerLock(index);
                    updateLayersList();
                };

                // Delete button (if not the only layer)
                if (drawingCanvas.layers.length > 1) {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.innerHTML = '🗑';
                    deleteBtn.className = 'text-sm text-red-600';
                    deleteBtn.onclick = () => {
                        drawingCanvas.deleteLayer(index);
                        updateLayersList();
                    };
                    controls.appendChild(deleteBtn);
                }

                controls.appendChild(lockBtn);
                layerItem.appendChild(controls);
                layersList.appendChild(layerItem);
            });
        }

        // Initial layers list
        updateLayersList();

        // Preview functions
        function previewAd() {
            const modal = document.getElementById('previewModal');
            const previewImage = document.getElementById('previewImage');
            previewImage.src = drawingCanvas.exportImage();
            modal.style.display = 'flex';
        }

        function closePreview() {
            const modal = document.getElementById('previewModal');
            modal.style.display = 'none';
        }

        function downloadAd() {
            const link = document.createElement('a');
            link.download = 'advertisement.png';
            link.href = drawingCanvas.exportImage();
            link.click();
        }

        async function saveAd() {
            try {
                const response = await fetch('/api/v1/ads', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        image: drawingCanvas.exportImage(),
                        state: drawingCanvas.saveState()
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to save advertisement');
                }

                alert('Advertisement saved successfully!');
            } catch (error) {
                console.error('Error saving advertisement:', error);
                alert('Failed to save advertisement. Please try again.');
            }
        }

        // Handle keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.metaKey || e.ctrlKey) {
                switch (e.key.toLowerCase()) {
                    case 'z':
                        if (e.shiftKey) {
                            drawingCanvas.redo();
                        } else {
                            drawingCanvas.undo();
                        }
                        e.preventDefault();
                        break;
                    case 's':
                        saveAd();
                        e.preventDefault();
                        break;
                }
            }
        });
    </script>
</body>
</html>
