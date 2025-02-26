<?php require_once __DIR__ . '/../partials/header.php'; ?>
<?php require_once __DIR__ . '/../partials/navigation.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">ROI Analytics</h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="get" class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="<?php echo BASE_URL; ?>/analytics/roi" class="btn btn-secondary ml-2">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <?php 
                        $totalImpressions = 0;
                        $totalClicks = 0;
                        $totalConversions = 0;
                        $totalCost = 0;
                        $totalRevenue = 0;
                        $totalRoi = 0;
                        $adCount = count($roi_data);
                        
                        foreach ($roi_data as $adData) {
                            $metrics = $adData['metrics'];
                            $totalImpressions += $metrics['impressions'];
                            $totalClicks += $metrics['clicks'];
                            $totalConversions += $metrics['conversions'];
                            $totalCost += $metrics['cost'];
                            $totalRevenue += $metrics['revenue'];
                        }
                        
                        $totalRoi = $totalCost > 0 ? (($totalRevenue - $totalCost) / $totalCost) * 100 : 0;
                        $avgCpc = $totalClicks > 0 ? $totalCost / $totalClicks : 0;
                        $avgCpa = $totalConversions > 0 ? $totalCost / $totalConversions : 0;
                        ?>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Cost</h5>
                                    <h2 class="text-danger">$<?php echo number_format($totalCost, 2); ?></h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Revenue</h5>
                                    <h2 class="text-success">$<?php echo number_format($totalRevenue, 2); ?></h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Overall ROI</h5>
                                    <h2 class="<?php echo $totalRoi >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($totalRoi, 2); ?>%
                                    </h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Profit</h5>
                                    <h2 class="<?php echo ($totalRevenue - $totalCost) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        $<?php echo number_format($totalRevenue - $totalCost, 2); ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Metrics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Avg. CPC</h5>
                                    <h3 class="text-info">$<?php echo number_format($avgCpc, 4); ?></h3>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Avg. CPA</h5>
                                    <h3 class="text-info">$<?php echo number_format($avgCpa, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Conversions</h5>
                                    <h3 class="text-primary"><?php echo number_format($totalConversions); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ROI Data Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Ad</th>
                                    <th>Impressions</th>
                                    <th>Clicks</th>
                                    <th>Conversions</th>
                                    <th>Cost</th>
                                    <th>Revenue</th>
                                    <th>ROI</th>
                                    <th>CPC</th>
                                    <th>CPA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roi_data as $adId => $adData): ?>
                                    <?php $metrics = $adData['metrics']; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($adData['ad']['title']); ?></td>
                                        <td><?php echo number_format($metrics['impressions']); ?></td>
                                        <td><?php echo number_format($metrics['clicks']); ?></td>
                                        <td><?php echo number_format($metrics['conversions']); ?></td>
                                        <td>$<?php echo number_format($metrics['cost'], 2); ?></td>
                                        <td>$<?php echo number_format($metrics['revenue'], 2); ?></td>
                                        <td class="<?php echo $metrics['roi'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($metrics['roi'], 2); ?>%
                                        </td>
                                        <td>$<?php echo number_format($metrics['cpc'], 4); ?></td>
                                        <td>$<?php echo $metrics['conversions'] > 0 ? number_format($metrics['cpa'], 2) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ROI Chart -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">ROI Visualization</h5>
                </div>
                <div class="card-body">
                    <canvas id="roiChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Prepare data for ROI chart
        const adLabels = [];
        const roiData = [];
        const costData = [];
        const revenueData = [];
        
        <?php foreach ($roi_data as $adId => $adData): ?>
            adLabels.push('<?php echo addslashes($adData['ad']['title']); ?>');
            roiData.push(<?php echo $adData['metrics']['roi']; ?>);
            costData.push(<?php echo $adData['metrics']['cost']; ?>);
            revenueData.push(<?php echo $adData['metrics']['revenue']; ?>);
        <?php endforeach; ?>
        
        // Create ROI chart
        const ctx = document.getElementById('roiChart').getContext('2d');
        const roiChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: adLabels,
                datasets: [
                    {
                        label: 'ROI (%)',
                        data: roiData,
                        backgroundColor: roiData.map(value => value >= 0 ? 'rgba(40, 167, 69, 0.5)' : 'rgba(220, 53, 69, 0.5)'),
                        borderColor: roiData.map(value => value >= 0 ? 'rgba(40, 167, 69, 1)' : 'rgba(220, 53, 69, 1)'),
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Cost ($)',
                        data: costData,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(220, 53, 69, 0.7)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Revenue ($)',
                        data: revenueData,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(40, 167, 69, 0.7)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'ROI (%)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'Amount ($)'
                        }
                    }
                }
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>