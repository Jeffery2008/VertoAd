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

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="my-4">Error Dashboard</h1>
            <p class="text-muted">Monitor and manage system errors and exceptions</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <!-- Critical Errors Card -->
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">Critical Errors</h5>
                    <h2 class="display-4"><?= $errorStats['by_severity']['critical'] ?? 0 ?></h2>
                    <p class="card-text">Total critical errors</p>
                </div>
            </div>
        </div>
        
        <!-- High Severity Card -->
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning">High Severity</h5>
                    <h2 class="display-4"><?= $errorStats['by_severity']['high'] ?? 0 ?></h2>
                    <p class="card-text">Total high severity errors</p>
                </div>
            </div>
        </div>
        
        <!-- Unresolved Errors Card -->
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <h5 class="card-title text-info">Unresolved</h5>
                    <h2 class="display-4"><?= ($errorStats['by_status']['new'] ?? 0) + ($errorStats['by_status']['in_progress'] ?? 0) ?></h2>
                    <p class="card-text">Total unresolved errors</p>
                </div>
            </div>
        </div>
        
        <!-- Resolved Errors Card -->
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <h5 class="card-title text-success">Resolved</h5>
                    <h2 class="display-4"><?= $errorStats['by_status']['resolved'] ?? 0 ?></h2>
                    <p class="card-text">Total resolved errors</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Errors by Severity Chart -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Errors by Severity
                </div>
                <div class="card-body">
                    <canvas id="severityChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Errors by Date Chart -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Error Trend (Last 30 Days)
                </div>
                <div class="card-body">
                    <canvas id="trendChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    Filter Errors
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <!-- Severity Filter -->
                        <div class="col-md-2">
                            <label for="severity" class="form-label">Severity</label>
                            <select name="severity" id="severity" class="form-select">
                                <option value="">All</option>
                                <option value="critical" <?= (isset($_GET['severity']) && $_GET['severity'] === 'critical') ? 'selected' : '' ?>>Critical</option>
                                <option value="high" <?= (isset($_GET['severity']) && $_GET['severity'] === 'high') ? 'selected' : '' ?>>High</option>
                                <option value="medium" <?= (isset($_GET['severity']) && $_GET['severity'] === 'medium') ? 'selected' : '' ?>>Medium</option>
                                <option value="low" <?= (isset($_GET['severity']) && $_GET['severity'] === 'low') ? 'selected' : '' ?>>Low</option>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All</option>
                                <option value="new" <?= (isset($_GET['status']) && $_GET['status'] === 'new') ? 'selected' : '' ?>>New</option>
                                <option value="in_progress" <?= (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="resolved" <?= (isset($_GET['status']) && $_GET['status'] === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                                <option value="ignored" <?= (isset($_GET['status']) && $_GET['status'] === 'ignored') ? 'selected' : '' ?>>Ignored</option>
                            </select>
                        </div>
                        
                        <!-- Date Range Filters -->
                        <div class="col-md-2">
                            <label for="from_date" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="from_date" name="from_date" value="<?= $_GET['from_date'] ?? '' ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="to_date" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="to_date" name="to_date" value="<?= $_GET['to_date'] ?? '' ?>">
                        </div>
                        
                        <!-- Search Field -->
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Term</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search in errors..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Logs Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Error Logs</span>
                    <a href="?<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>" class="btn btn-sm btn-secondary">Refresh</a>
                </div>
                <div class="card-body">
                    <?php if (empty($errorLogs['data'])): ?>
                        <div class="alert alert-info">No errors found matching your criteria.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Message</th>
                                        <th>File</th>
                                        <th>Line</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($errorLogs['data'] as $error): ?>
                                        <tr class="<?= $this->getRowClass($error['severity']) ?>">
                                            <td><?= htmlspecialchars($error['id']) ?></td>
                                            <td><?= htmlspecialchars($error['error_type']) ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($error['error_message']) ?>">
                                                    <?= htmlspecialchars($error['error_message']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($error['error_file']) ?>">
                                                    <?= htmlspecialchars($error['error_file']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($error['error_line']) ?></td>
                                            <td>
                                                <span class="badge <?= $this->getSeverityBadgeClass($error['severity']) ?>">
                                                    <?= ucfirst(htmlspecialchars($error['severity'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm error-status-select" data-error-id="<?= $error['id'] ?>">
                                                    <option value="new" <?= $error['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                                    <option value="in_progress" <?= $error['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="resolved" <?= $error['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                                    <option value="ignored" <?= $error['status'] === 'ignored' ? 'selected' : '' ?>>Ignored</option>
                                                </select>
                                            </td>
                                            <td><?= date('Y-m-d H:i:s', strtotime($error['created_at'])) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-details" data-bs-toggle="modal" data-bs-target="#errorDetailModal" data-error-id="<?= $error['id'] ?>">
                                                    Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($errorLogs['pages'] > 1): ?>
                            <nav aria-label="Error log pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($errorLogs['page'] > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $errorLogs['page'] - 1 ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $errorLogs['page'] - 2); $i <= min($errorLogs['pages'], $errorLogs['page'] + 2); $i++): ?>
                                        <li class="page-item <?= $i === $errorLogs['page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($errorLogs['page'] < $errorLogs['pages']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $errorLogs['page'] + 1 ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Detail Modal -->
<div class="modal fade" id="errorDetailModal" tabindex="-1" aria-labelledby="errorDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorDetailModalLabel">Error Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Error Information</div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>ID:</th>
                                        <td id="error-id"></td>
                                    </tr>
                                    <tr>
                                        <th>Type:</th>
                                        <td id="error-type"></td>
                                    </tr>
                                    <tr>
                                        <th>Message:</th>
                                        <td id="error-message"></td>
                                    </tr>
                                    <tr>
                                        <th>File:</th>
                                        <td id="error-file"></td>
                                    </tr>
                                    <tr>
                                        <th>Line:</th>
                                        <td id="error-line"></td>
                                    </tr>
                                    <tr>
                                        <th>Date:</th>
                                        <td id="error-date"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Request Information</div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>URI:</th>
                                        <td id="request-uri"></td>
                                    </tr>
                                    <tr>
                                        <th>Method:</th>
                                        <td id="request-method"></td>
                                    </tr>
                                    <tr>
                                        <th>IP Address:</th>
                                        <td id="client-ip"></td>
                                    </tr>
                                    <tr>
                                        <th>User Agent:</th>
                                        <td id="user-agent"></td>
                                    </tr>
                                    <tr>
                                        <th>User ID:</th>
                                        <td id="user-id"></td>
                                    </tr>
                                    <tr>
                                        <th>Session ID:</th>
                                        <td id="session-id"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">Stack Trace</div>
                            <div class="card-body">
                                <pre id="error-trace" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for charts and error status updates -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Helper function to get error details by ID
function getErrorDetails(errorId) {
    fetch(`/api/v1/errors.php?action=logs&id=${errorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.data && data.data.length > 0) {
                const error = data.data[0];
                document.getElementById('error-id').textContent = error.id;
                document.getElementById('error-type').textContent = error.error_type;
                document.getElementById('error-message').textContent = error.error_message;
                document.getElementById('error-file').textContent = error.error_file;
                document.getElementById('error-line').textContent = error.error_line;
                document.getElementById('error-date').textContent = new Date(error.created_at).toLocaleString();
                document.getElementById('request-uri').textContent = error.request_uri || 'N/A';
                document.getElementById('request-method').textContent = error.request_method || 'N/A';
                document.getElementById('client-ip').textContent = error.client_ip || 'N/A';
                document.getElementById('user-agent').textContent = error.user_agent || 'N/A';
                document.getElementById('user-id').textContent = error.user_id || 'N/A';
                document.getElementById('session-id').textContent = error.session_id || 'N/A';
                document.getElementById('error-trace').textContent = error.error_trace || 'No stack trace available';
            }
        })
        .catch(error => console.error('Error fetching error details:', error));
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Severity chart
    const severityData = <?= json_encode($errorStats['by_severity'] ?? []) ?>;
    const severityLabels = Object.keys(severityData);
    const severityCounts = Object.values(severityData);
    
    const severityCtx = document.getElementById('severityChart').getContext('2d');
    new Chart(severityCtx, {
        type: 'pie',
        data: {
            labels: severityLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
            datasets: [{
                data: severityCounts,
                backgroundColor: [
                    '#dc3545', // critical - danger
                    '#ffc107', // high - warning
                    '#0dcaf0', // medium - info
                    '#6c757d'  // low - secondary
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Trend chart
    const trendData = <?= json_encode($errorStats['by_date'] ?? []) ?>;
    const dates = Object.keys(trendData);
    const counts = Object.values(trendData);
    
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Errors',
                data: counts,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Handle error details button clicks
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const errorId = this.getAttribute('data-error-id');
            getErrorDetails(errorId);
        });
    });
    
    // Handle error status changes
    document.querySelectorAll('.error-status-select').forEach(select => {
        select.addEventListener('change', function() {
            const errorId = this.getAttribute('data-error-id');
            const newStatus = this.value;
            
            const formData = new FormData();
            formData.append('error_id', errorId);
            formData.append('status', newStatus);
            
            fetch('/api/v1/errors.php?action=update_status', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success notification
                    alert('Status updated successfully');
                } else {
                    // Error notification
                    alert('Failed to update status: ' + data.message);
                    // Revert to previous value
                    this.value = this.getAttribute('data-original-value');
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
                alert('An error occurred while updating the status');
                // Revert to previous value
                this.value = this.getAttribute('data-original-value');
            });
            
            // Store original value for reverting if needed
            this.setAttribute('data-original-value', newStatus);
        });
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