<?php
/**
 * Error Dashboard Template
 * Displays error logs and statistics in a user-friendly dashboard
 */

// Ensure this file is not accessed directly
if (!defined('AUTHORIZED_ACCESS') && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

// Page title
$pageTitle = 'Error Dashboard';

// Include header
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Error Dashboard</h1>
        <div>
            <a href="/admin/errors" class="btn btn-primary">
                <i class="fas fa-list me-2"></i>View All Errors
            </a>
            <button type="button" class="btn btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#generateTestErrorModal">
                <i class="fas fa-bug me-2"></i>Generate Test Error
            </button>
        </div>
    </div>
    
    <!-- Stats Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Unresolved Errors</h5>
                    <h2 class="display-4"><?= $unresolvedCount ?></h2>
                    <p class="card-text">Errors requiring attention</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="/admin/errors?status=open" class="text-white">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Critical Errors</h5>
                    <h2 class="display-4"><?= count($criticalErrors) ?></h2>
                    <p class="card-text">Highest severity issues</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="/admin/errors?severity=critical" class="text-white">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Recent Errors</h5>
                    <h2 class="display-4"><?= isset($dailyErrorStats[0]['count']) ? $dailyErrorStats[0]['count'] : 0 ?></h2>
                    <p class="card-text">Errors in the last 24 hours</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="/admin/errors?date_from=<?= date('Y-m-d', strtotime('-1 day')) ?>" class="text-white">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Categories</h5>
                    <h2 class="display-4"><?= count($errorTypeStats) ?></h2>
                    <p class="card-text">Types of errors tracked</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="/admin/errors/categories" class="text-white">Manage Categories</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-area me-1"></i>
                    Daily Error Trend (Last 30 Days)
                </div>
                <div class="card-body">
                    <canvas id="dailyErrorsChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Error Types Distribution
                </div>
                <div class="card-body">
                    <canvas id="errorTypesChart" width="100%" height="50"></canvas>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Error Severity Distribution
                </div>
                <div class="card-body">
                    <canvas id="severityChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Latest Errors Tables -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-exclamation-circle text-danger me-1"></i>
                        Latest Critical Errors
                    </div>
                    <a href="/admin/errors?severity=critical" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Error</th>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($criticalErrors)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3">No critical errors found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($criticalErrors as $error): ?>
                                        <tr>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($error['error_message']) ?>">
                                                    <?= htmlspecialchars($error['error_message']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($error['error_type']) ?></span>
                                            </td>
                                            <td><?= date('M d, H:i', strtotime($error['created_at'])) ?></td>
                                            <td>
                                                <a href="/admin/errors/view/<?= $error['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                        Latest High Severity Errors
                    </div>
                    <a href="/admin/errors?severity=high" class="btn btn-sm btn-outline-warning">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Error</th>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($highSeverityErrors)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3">No high severity errors found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($highSeverityErrors as $error): ?>
                                        <tr>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($error['error_message']) ?>">
                                                    <?= htmlspecialchars($error['error_message']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($error['error_type']) ?></span>
                                            </td>
                                            <td><?= date('M d, H:i', strtotime($error['created_at'])) ?></td>
                                            <td>
                                                <a href="/admin/errors/view/<?= $error['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Test Error Modal -->
<div class="modal fade" id="generateTestErrorModal" tabindex="-1" aria-labelledby="generateTestErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/generate-test-error" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="generateTestErrorModalLabel">Generate Test Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="error_type" class="form-label">Error Type</label>
                        <select class="form-select" id="error_type" name="error_type" required>
                            <option value="php">PHP Error</option>
                            <option value="database">Database Error</option>
                            <option value="application" selected>Application Error</option>
                            <option value="validation">Validation Error</option>
                            <option value="api">API Error</option>
                            <option value="javascript">JavaScript Error</option>
                            <option value="security">Security Issue</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="severity" class="form-label">Severity</label>
                        <select class="form-select" id="severity" name="severity" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Error Message</label>
                        <input type="text" class="form-control" id="message" name="message" value="This is a test error message" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will create a test error in the error tracking system. Use this feature to test error notifications and dashboard functionality.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Error</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for daily errors chart
    const dailyErrorData = <?= json_encode(array_reverse($dailyErrorStats)) ?>;
    const labels = dailyErrorData.map(item => item.date);
    const counts = dailyErrorData.map(item => item.count);
    
    // Daily Errors Chart
    const dailyCtx = document.getElementById('dailyErrorsChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Errors',
                data: counts,
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderColor: 'rgba(0, 123, 255, 1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
    
    // Error Types Chart
    const errorTypeData = <?= json_encode($errorTypeStats) ?>;
    const typeCtx = document.getElementById('errorTypesChart').getContext('2d');
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: errorTypeData.map(item => item.error_type),
            datasets: [{
                data: errorTypeData.map(item => item.count),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(199, 199, 199, 0.8)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Severity Chart
    const severityData = <?= json_encode($severityStats) ?>;
    const sevCtx = document.getElementById('severityChart').getContext('2d');
    new Chart(sevCtx, {
        type: 'doughnut',
        data: {
            labels: severityData.map(item => item.severity),
            datasets: [{
                data: severityData.map(item => item.count),
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)', // low (green)
                    'rgba(23, 162, 184, 0.8)', // medium (blue)
                    'rgba(255, 193, 7, 0.8)', // high (yellow)
                    'rgba(220, 53, 69, 0.8)'  // critical (red)
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
});
</script>

<?php
/**
 * Helper function to get row class based on severity
 */
function getRowClass($severity) {
    switch ($severity) {
        case 'critical':
            return 'table-danger';
        case 'high':
            return 'table-warning';
        case 'medium':
            return 'table-info';
        case 'low':
            return 'table-light';
        default:
            return '';
    }
}

/**
 * Helper function to get badge class based on severity
 */
function getSeverityBadgeClass($severity) {
    switch ($severity) {
        case 'critical':
            return 'bg-danger';
        case 'high':
            return 'bg-warning text-dark';
        case 'medium':
            return 'bg-info text-dark';
        case 'low':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

// Include footer
include __DIR__ . '/../includes/admin_footer.php';
?> 