<?php require_once TEMPLATES_PATH . '/advertiser/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Conversion Analytics</h1>
            
            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form id="filter-form" method="get" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="start_date" class="mr-2">From:</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        
                        <div class="form-group mr-3 mb-2">
                            <label for="end_date" class="mr-2">To:</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        
                        <div class="form-group mr-3 mb-2">
                            <label for="type_id" class="mr-2">Conversion Type:</label>
                            <select id="type_id" name="type_id" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= $typeId == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mb-2">Apply Filters</button>
                        <a href="<?= URL_ROOT ?>/advertiser/conversions" class="btn btn-outline-secondary mb-2 ml-2">Reset</a>
                    </form>
                </div>
            </div>
            
            <?php if (empty($adIds)): ?>
                <div class="alert alert-info">
                    <p>You don't have any ads yet. Create ads to start tracking conversions.</p>
                    <a href="<?= URL_ROOT ?>/advertiser/ads/create" class="btn btn-primary">Create Ad</a>
                </div>
            <?php else: ?>
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-primary h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title text-muted">Total Conversions</h5>
                                <h2 class="card-text text-primary"><?= number_format($totalConversions) ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-success h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title text-muted">Conversion Value</h5>
                                <h2 class="card-text text-success">$<?= number_format($totalValue, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-info h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title text-muted">Conversion Rate</h5>
                                <h2 class="card-text text-info"><?= number_format($conversionRate, 2) ?>%</h2>
                                <small class="text-muted"><?= number_format($totalConversions) ?> of <?= number_format($totalClicks) ?> clicks</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-warning h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title text-muted">Avg. Value Per Conversion</h5>
                                <h2 class="card-text text-warning">
                                    $<?= $totalConversions > 0 ? number_format($totalValue / $totalConversions, 2) : '0.00' ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row mb-4">
                    <!-- Daily Conversions Chart -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Daily Conversions</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyConversionsChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conversion Types Pie Chart -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Conversion Types</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($typeData)): ?>
                                    <div class="alert alert-info">No conversion data available for the selected period.</div>
                                <?php else: ?>
                                    <canvas id="conversionTypesChart" height="300"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Conversions Table -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Recent Conversions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentConversions)): ?>
                            <div class="alert alert-info">No conversions found for the selected period.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Advertisement</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Order ID</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentConversions as $conversion): ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i:s', strtotime($conversion['conversion_time'])) ?></td>
                                                <td><?= htmlspecialchars($conversion['ad_title']) ?></td>
                                                <td><?= htmlspecialchars($conversion['type_name']) ?></td>
                                                <td>$<?= number_format($conversion['value'], 2) ?></td>
                                                <td><?= $conversion['order_id'] ? htmlspecialchars($conversion['order_id']) : '<em class="text-muted">N/A</em>' ?></td>
                                                <td><?= htmlspecialchars($conversion['ip_address']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Daily Conversions Chart
        <?php if (!empty($dailyData)): ?>
            const dailyCtx = document.getElementById('dailyConversionsChart').getContext('2d');
            
            const dates = <?= json_encode(array_column($dailyData, 'date')) ?>;
            const conversions = <?= json_encode(array_column($dailyData, 'conversions')) ?>;
            const values = <?= json_encode(array_column($dailyData, 'value')) ?>;
            
            new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Conversions',
                            data: conversions,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Value ($)',
                            data: values,
                            type: 'line',
                            fill: false,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            tension: 0.1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Conversions'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Value ($)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        // Conversion Types Pie Chart
        <?php if (!empty($typeData)): ?>
            const typesCtx = document.getElementById('conversionTypesChart').getContext('2d');
            
            const typeNames = <?= json_encode(array_column($typeData, 'name')) ?>;
            const typeCounts = <?= json_encode(array_column($typeData, 'conversions')) ?>;
            
            // Generate colors
            const backgroundColors = [];
            const borderColors = [];
            
            for (let i = 0; i < typeNames.length; i++) {
                const hue = (i * 137) % 360; // Use golden angle for nice color distribution
                backgroundColors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
                borderColors.push(`hsla(${hue}, 70%, 50%, 1)`);
            }
            
            new Chart(typesCtx, {
                type: 'doughnut',
                data: {
                    labels: typeNames,
                    datasets: [{
                        data: typeCounts,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        <?php endif; ?>
    });
</script>

<?php require_once TEMPLATES_PATH . '/advertiser/footer.php'; ?> 