/**
 * HFI Analytics JavaScript
 * Handles chart initialization and data updates for the analytics dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all charts
    initializeTimeSeriesChart();
    initializeDeviceChart();
    initializeGeoMap();
    
    // Set up event listeners
    setupEventListeners();
});

/**
 * Initialize the time series chart
 */
function initializeTimeSeriesChart() {
    const ctx = document.getElementById('timeSeriesChart').getContext('2d');
    
    // Prepare data for the chart
    const labels = [];
    const impressionsData = [];
    const clicksData = [];
    
    // Combine data from all ads
    Object.values(analyticsData).forEach(data => {
        data.impressions.forEach(imp => {
            const dateIndex = labels.indexOf(imp.date);
            if (dateIndex === -1) {
                labels.push(imp.date);
                impressionsData.push(imp.impressions);
                // Find matching click data
                const clickData = data.clicks.find(c => c.date === imp.date);
                clicksData.push(clickData ? clickData.clicks : 0);
            } else {
                impressionsData[dateIndex] += imp.impressions;
                const clickData = data.clicks.find(c => c.date === imp.date);
                clicksData[dateIndex] += clickData ? clickData.clicks : 0;
            }
        });
    });
    
    // Sort dates
    const sortedIndices = labels.map((_, i) => i).sort((a, b) => 
        new Date(labels[a]) - new Date(labels[b])
    );
    
    labels.sort((a, b) => new Date(a) - new Date(b));
    impressionsData.sort((_, __, i) => sortedIndices[i]);
    clicksData.sort((_, __, i) => sortedIndices[i]);
    
    // Create the chart
    window.timeSeriesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Impressions',
                    data: impressionsData,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Clicks',
                    data: clicksData,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Impressions & Clicks Over Time'
                }
            }
        }
    });
}

/**
 * Initialize the device distribution chart
 */
function initializeDeviceChart() {
    const ctx = document.getElementById('deviceChart').getContext('2d');
    
    // Aggregate device data
    const deviceData = {};
    Object.values(analyticsData).forEach(data => {
        data.device_distribution.forEach(device => {
            if (!deviceData[device.device_type]) {
                deviceData[device.device_type] = 0;
            }
            deviceData[device.device_type] += device.count;
        });
    });
    
    // Prepare data for the chart
    const labels = Object.keys(deviceData);
    const data = Object.values(deviceData);
    
    // Create the chart
    window.deviceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Device Distribution'
                }
            }
        }
    });
}

/**
 * Initialize the geographic distribution map
 */
function initializeGeoMap() {
    // Create the map
    const map = L.map('geoMap').setView([35.0, 105.0], 4); // Center on China
    
    // Add tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Aggregate location data
    const locationData = {};
    Object.values(analyticsData).forEach(data => {
        data.geo_distribution.forEach(geo => {
            const key = `${geo.location_city}, ${geo.location_country}`;
            if (!locationData[key]) {
                locationData[key] = {
                    count: 0,
                    lat: getLatLngForCity(geo.location_city), // You'll need to implement this
                    lng: getLngForCity(geo.location_city)  // You'll need to implement this
                };
            }
            locationData[key].count += geo.count;
        });
    });
    
    // Add markers for each location
    Object.entries(locationData).forEach(([location, data]) => {
        if (data.lat && data.lng) {
            L.circle([data.lat, data.lng], {
                color: 'red',
                fillColor: '#f03',
                fillOpacity: 0.5,
                radius: Math.sqrt(data.count) * 1000 // Scale circle size based on count
            })
            .bindPopup(`${location}<br>Impressions: ${data.count}`)
            .addTo(map);
        }
    });
}

/**
 * Set up event listeners for the dashboard
 */
function setupEventListeners() {
    // Refresh button
    document.getElementById('refreshData').addEventListener('click', function() {
        location.reload();
    });
    
    // Filter form
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const params = new URLSearchParams(formData);
        window.location.href = window.location.pathname + '?' + params.toString();
    });
    
    // Date range validation
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });
    
    endDate.addEventListener('change', function() {
        startDate.max = this.value;
        if (startDate.value && startDate.value > this.value) {
            startDate.value = this.value;
        }
    });
}

/**
 * Get latitude for a city (placeholder implementation)
 * In a production environment, you would use a proper geocoding service
 * 
 * @param {string} city City name
 * @return {number|null} Latitude or null if not found
 */
function getLatLngForCity(city) {
    // This is a simplified implementation
    // In production, you would use a geocoding service or maintain a database of coordinates
    const cityCoordinates = {
        '深圳市': [22.5431, 114.0579],
        '广州市': [23.1291, 113.2644],
        '北京市': [39.9042, 116.4074],
        '上海市': [31.2304, 121.4737],
        // Add more cities as needed
    };
    
    return cityCoordinates[city] ? cityCoordinates[city][0] : null;
}

/**
 * Get longitude for a city (placeholder implementation)
 * 
 * @param {string} city City name
 * @return {number|null} Longitude or null if not found
 */
function getLngForCity(city) {
    const cityCoordinates = {
        '深圳市': [22.5431, 114.0579],
        '广州市': [23.1291, 113.2644],
        '北京市': [39.9042, 116.4074],
        '上海市': [31.2304, 121.4737],
        // Add more cities as needed
    };
    
    return cityCoordinates[city] ? cityCoordinates[city][1] : null;
} 