<?php require_once __DIR__ . '/../partials/header.php'; ?>
<?php require_once __DIR__ . '/../partials/navigation.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Conversion Analytics</h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="get" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="conversion_type_id">Conversion Type</label>
                                    <select id="conversion_type_id" name="conversion_type_id" class="form-control">
                                        <option value="">All Types</option>
                                        <?php foreach ($conversion_types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" <?php echo ($filters['conversion_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="<?php echo BASE_URL; ?>/analytics/conversions" class="btn btn-secondary ml-2">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <?php 
                        $totalConversions = 0;
                        $totalValue = 0;
                        $avgConversionRate = 0;
                        $adCount = count($conversion_data);
                        
                        foreach ($conversion_data as $adData) {
                            $totalConversions += $adData['total_count'];
                            $totalValue += $adData['total_value'];
                            $avgConversionRate += $adData['conversion_rate'];
                        }
                        
                        $avgConversionRate = $adCount > 0 ? $avgConversionRate / $adCount : 0;
                        ?>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Conversions</h5>
                                    <h2 class="text-primary"><?php echo number_format($totalConversions); ?></h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Value</h5>
                                    <h2 class="text-success">$<?php echo number_format($totalValue, 2); ?></h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Avg. Conversion Rate</h5>
                                    <h2 class="text-info"><?php echo number_format($avgConversionRate, 2); ?>%</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversion Data Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Ad</th>
                                    <th>Conversions</th>
                                    <th>Conversion Rate</th>
                                    <th>Total Value</th>
                                    <th>Avg. Value</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversion_data as $adId => $adData): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($adData['ad']['title']); ?></td>
                                        <td><?php echo number_format($adData['total_count']); ?></td>
                                        <td><?php echo number_format($adData['conversion_rate'], 2); ?>%</td>
                                        <td>$<?php echo number_format($adData['total_value'], 2); ?></td>
                                        <td>$<?php echo $adData['total_count'] > 0 ? number_format($adData['total_value'] / $adData['total_count'], 2) : '0.00'; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#conversionDetails<?php echo $adId; ?>">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Conversion Details Modals -->
<?php foreach ($conversion_data as $adId => $adData): ?>
    <div class="modal fade" id="conversionDetails<?php echo $adId; ?>" tabindex="-1" role="dialog" aria-labelledby="conversionDetailsLabel<?php echo $adId; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="conversionDetailsLabel<?php echo $adId; ?>">
                        Conversion Details: <?php echo htmlspecialchars($adData['ad']['title']); ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if (empty($adData['conversions'])): ?>
                        <p class="text-center">No conversion data available for this ad.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Order ID</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adData['conversions'] as $conversion): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($conversion['conversion_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($conversion['conversion_type_name']); ?></td>
                                            <td>$<?php echo number_format($conversion['conversion_value'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($conversion['order_id'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php 
                                                $location = [];
                                                if (!empty($conversion['ip_address'])) {
                                                    $location[] = $conversion['ip_address'];
                                                }
                                                echo implode(', ', $location);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add any JavaScript for charts or interactivity here
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?> 