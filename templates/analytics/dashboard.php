<?php require_once __DIR__ . '/../layout.php'; ?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Analytics Dashboard</h2>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" id="refreshData">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <a href="/analytics/export-csv<?php echo isset($_GET['start_date']) ? '?start_date=' . htmlspecialchars($_GET['start_date']) . '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>" 
               class="btn btn-outline-primary">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-3">
                    <label for="interval" class="form-label">Interval</label>
                    <select class="form-control" id="interval" name="interval">
                        <option value="day" <?php echo $interval === 'day' ? 'selected' : ''; ?>>Daily</option>
                        <option value="week" <?php echo $interval === 'week' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="month" <?php echo $interval === 'month' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Impressions</h6>
                    <h3 class="card-title"><?php echo number_format($summaryMetrics['total_impressions']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Clicks</h6>
                    <h3 class="card-title"><?php echo number_format($summaryMetrics['total_clicks']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Average CTR</h6>
                    <h3 class="card-title"><?php echo number_format($summaryMetrics['average_ctr'], 2); ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Cost</h6>
                    <h3 class="card-title">$<?php echo number_format($summaryMetrics['total_cost'], 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Time Series Chart -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Performance Over Time</h5>
                    <canvas id="timeSeriesChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Device Distribution -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Device Distribution</h5>
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Geographic Distribution -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Geographic Distribution</h5>
            <div id="geoMap" style="height: 400px;"></div>
        </div>
    </div>

    <!-- Detailed Metrics Table -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Detailed Metrics</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Ad Title</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>CTR</th>
                            <th>Cost</th>
                            <th>Top Locations</th>
                            <th>Top Devices</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analyticsData as $adId => $data): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($data['ad']['title']); ?></td>
                            <td><?php echo number_format($data['total_impressions']); ?></td>
                            <td><?php echo number_format($data['total_clicks']); ?></td>
                            <td><?php echo number_format($data['ctr'], 2); ?>%</td>
                            <td>$<?php echo number_format($data['total_cost'], 2); ?></td>
                            <td>
                                <?php 
                                $topLocations = array_slice($data['geo_distribution'], 0, 3);
                                foreach ($topLocations as $geo) {
                                    echo htmlspecialchars($geo['location_city'] . ', ' . $geo['location_country']) . '<br>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $topDevices = array_slice($data['device_distribution'], 0, 3);
                                foreach ($topDevices as $device) {
                                    echo htmlspecialchars($device['device_type']) . '<br>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Load required JavaScript libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.css">

<!-- Initialize charts -->
<script>
// Prepare data for charts
const analyticsData = <?php echo json_encode($analyticsData); ?>;
const summaryMetrics = <?php echo json_encode($summaryMetrics); ?>;

// Time series chart data preparation and initialization will be in analytics.js
// Device distribution chart data preparation and initialization will be in analytics.js
// Geographic map initialization will be in analytics.js
</script>

<!-- Load our custom analytics JavaScript -->
<script src="/static/js/analytics.js"></script> 