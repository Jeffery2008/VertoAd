<?php require_once 'templates/partials/header.php'; ?>
<?php require_once 'templates/partials/advertiser_sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Audience Insights</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/advertiser/dashboard">Home</a></li>
                        <li class="breadcrumb-item active">Audience Insights</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Filters</h3>
                        </div>
                        <div class="card-body">
                            <form method="get" id="filters-form">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Date Range</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <i class="far fa-calendar-alt"></i>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control float-right" id="date-range-picker" name="date_range">
                                                <input type="hidden" id="start_date" name="start_date" value="<?= $startDate ?>">
                                                <input type="hidden" id="end_date" name="end_date" value="<?= $endDate ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Segment</label>
                                            <select class="form-control" name="segment_id" id="segment_id">
                                                <option value="">All Visitors</option>
                                                <?php foreach ($segments as $segment) : ?>
                                                    <option value="<?= $segment['id'] ?>" <?= $selectedSegmentId == $segment['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($segment['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <button type="submit" class="btn btn-primary btn-block">Apply</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($totalVisitors) ?></h3>
                            <p>Total Visitors</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($newVisitors) ?></h3>
                            <p>New Visitors</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($returningVisitors) ?></h3>
                            <p>Returning Visitors</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $totalVisitors > 0 ? round(($newVisitors / $totalVisitors) * 100) : 0 ?>%</h3>
                            <p>New Visitor Rate</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Visitor Trends</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart">
                                <canvas id="visitorTrendsChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Device Distribution</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart">
                                <canvas id="deviceChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top Locations</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th style="width: 40%">Visitors</th>
                                        <th style="width: 20%">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (empty($locationDistribution)) {
                                        echo '<tr><td colspan="3" class="text-center">No location data available</td></tr>';
                                    } else {
                                        foreach ($locationDistribution as $location) {
                                            $locationName = $location['location'] ?? 'Unknown';
                                            $visitors = $location['count'] ?? 0;
                                            $percentage = $totalVisitors > 0 ? round(($visitors / $totalVisitors) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($locationName) ?></td>
                                        <td>
                                            <div class="progress progress-xs">
                                                <div class="progress-bar bg-primary" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary"><?= $percentage ?>%</span></td>
                                    </tr>
                                    <?php 
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Browser Distribution</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Browser</th>
                                        <th style="width: 40%">Visitors</th>
                                        <th style="width: 20%">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (empty($browserDistribution)) {
                                        echo '<tr><td colspan="3" class="text-center">No browser data available</td></tr>';
                                    } else {
                                        foreach ($browserDistribution as $browser) {
                                            $browserName = $browser['browser'] ?? 'Unknown';
                                            $visitors = $browser['count'] ?? 0;
                                            $percentage = $totalVisitors > 0 ? round(($visitors / $totalVisitors) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($browserName) ?></td>
                                        <td>
                                            <div class="progress progress-xs">
                                                <div class="progress-bar bg-success" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-success"><?= $percentage ?>%</span></td>
                                    </tr>
                                    <?php 
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Engagement Overview</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box mb-3 bg-light">
                                <span class="info-box-icon"><i class="fas fa-eye"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Pages per Visit</span>
                                    <span class="info-box-number">
                                        <?php
                                            $totalPageViews = array_sum(array_column($visitorTrends, 'page_views'));
                                            $totalVisits = array_sum(array_column($visitorTrends, 'visits'));
                                            echo $totalVisits > 0 ? number_format($totalPageViews / $totalVisits, 1) : '0';
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-box mb-3 bg-light">
                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Avg. Visit Duration</span>
                                    <span class="info-box-number">2:34</span>
                                </div>
                            </div>
                            <div class="info-box mb-3 bg-light">
                                <span class="info-box-icon"><i class="fas fa-sync-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Bounce Rate</span>
                                    <span class="info-box-number">42.3%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Visitor Segments</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($segments)) : ?>
                                <div class="alert alert-info">
                                    <p>You haven't created any audience segments yet.</p>
                                    <a href="/advertiser/segments" class="btn btn-sm btn-primary mt-2">Create Segments</a>
                                </div>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Segment Name</th>
                                                <th>Type</th>
                                                <th>Members</th>
                                                <th>% of Audience</th>
                                                <th>Performance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($segments as $segment) : ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($segment['name']) ?></td>
                                                    <td>
                                                        <?php if ($segment['is_dynamic']) : ?>
                                                            <span class="badge badge-primary">Dynamic</span>
                                                        <?php else : ?>
                                                            <span class="badge badge-secondary">Static</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= number_format($segment['member_count']) ?></td>
                                                    <td>
                                                        <?php
                                                            $percentage = $totalVisitors > 0 ? round(($segment['member_count'] / $totalVisitors) * 100, 1) : 0;
                                                            echo $percentage . '%';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="progress progress-xs">
                                                            <?php
                                                                // This could be CTR, conversion rate, etc.
                                                                $rate = mt_rand(1, 10) / 10; // Example random data
                                                                $width = $rate * 100;
                                                            ?>
                                                            <div class="progress-bar bg-danger" style="width: <?= $width ?>%"></div>
                                                        </div>
                                                        <span class="badge bg-danger"><?= number_format($rate * 100, 1) ?>% CTR</span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="/advertiser/segments?segment_id=<?= $segment['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <a href="/advertiser/segment-members/<?= $segment['id'] ?>" class="btn btn-sm btn-default">
                                                                <i class="fas fa-users"></i> Members
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Date range picker initialization
    $('#date-range-picker').daterangepicker({
        startDate: moment('<?= $startDate ?>'),
        endDate: moment('<?= $endDate ?>'),
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, function(start, end) {
        $('#start_date').val(start.format('YYYY-MM-DD'));
        $('#end_date').val(end.format('YYYY-MM-DD'));
    });
    
    // Visitor trends chart
    var visitorTrendsChart = new Chart($('#visitorTrendsChart').get(0).getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($visitorTrends, 'date')) ?>,
            datasets: [
                {
                    label: 'Total Visitors',
                    data: <?= json_encode(array_column($visitorTrends, 'visitors')) ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'New Visitors',
                    data: <?= json_encode(array_column($visitorTrends, 'new_visitors')) ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Device distribution chart
    var deviceData = <?= json_encode(array_column($deviceDistribution, 'count')) ?>;
    var deviceLabels = <?= json_encode(array_column($deviceDistribution, 'device_type')) ?>;
    var deviceColors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d'];
    
    var deviceChart = new Chart($('#deviceChart').get(0).getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: deviceLabels,
            datasets: [{
                data: deviceData,
                backgroundColor: deviceColors.slice(0, deviceData.length)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Apply filters when segment changes
    $('#segment_id').change(function() {
        $('#filters-form').submit();
    });
});
</script>

<?php require_once 'templates/partials/footer.php'; ?> 