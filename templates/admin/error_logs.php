<?php include __DIR__ . '/../includes/admin_header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Error Logs</h1>
        <div>
            <a href="/admin/errors/dashboard" class="btn btn-primary">
                <i class="fas fa-chart-bar me-2"></i>Dashboard
            </a>
            <a href="/admin/errors/categories" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-tags me-2"></i>Categories
            </a>
            <a href="/admin/errors/subscriptions" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-bell me-2"></i>Notifications
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter Error Logs
            <button class="btn btn-sm btn-link float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="collapse <?= !empty($filters) ? 'show' : '' ?>" id="filterCollapse">
            <div class="card-body">
                <form method="get" action="/admin/errors">
                    <div class="row g-3">
                        <!-- Error Type -->
                        <div class="col-md-3">
                            <label for="error_type" class="form-label">Error Type</label>
                            <select class="form-select" id="error_type" name="error_type">
                                <option value="">All Types</option>
                                <?php foreach ($errorTypes as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= isset($filters['error_type']) && $filters['error_type'] === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Severity -->
                        <div class="col-md-2">
                            <label for="severity" class="form-label">Severity</label>
                            <select class="form-select" id="severity" name="severity">
                                <option value="">All Severities</option>
                                <?php foreach ($severities as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= isset($filters['severity']) && $filters['severity'] === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= isset($filters['status']) && $filters['status'] === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- IP Address -->
                        <div class="col-md-2">
                            <label for="ip_address" class="form-label">IP Address</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" value="<?= isset($filters['ip_address']) ? htmlspecialchars($filters['ip_address']) : '' ?>">
                        </div>
                        
                        <!-- User ID -->
                        <div class="col-md-2">
                            <label for="user_id" class="form-label">User ID</label>
                            <input type="text" class="form-control" id="user_id" name="user_id" value="<?= isset($filters['user_id']) ? htmlspecialchars($filters['user_id']) : '' ?>">
                        </div>
                        
                        <!-- Date From -->
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= isset($filters['date_from']) ? htmlspecialchars($filters['date_from']) : '' ?>">
                        </div>
                        
                        <!-- Date To -->
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= isset($filters['date_to']) ? htmlspecialchars($filters['date_to']) : '' ?>">
                        </div>
                        
                        <!-- Search -->
                        <div class="col-md-5">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search in error messages, files, or stack traces" value="<?= isset($filters['search']) ? htmlspecialchars($filters['search']) : '' ?>">
                        </div>
                        
                        <!-- Buttons -->
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Apply Filters
                            </button>
                            <a href="/admin/errors" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Error Logs Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-exclamation-triangle me-1"></i>
                Error Logs
                <?php if (!empty($filters)): ?>
                    <span class="badge bg-info ms-2">Filtered</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="me-3">
                    Showing <?= ($page - 1) * 20 + 1 ?>-<?= min($page * 20, $totalLogs) ?> of <?= $totalLogs ?> logs
                </span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info m-3 mb-0">
                    No error logs found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 60px;">ID</th>
                                <th scope="col" style="width: 100px;">Type</th>
                                <th scope="col">Message</th>
                                <th scope="col" style="width: 120px;">Severity</th>
                                <th scope="col" style="width: 120px;">Status</th>
                                <th scope="col" style="width: 180px;">Date</th>
                                <th scope="col" style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= $log['id'] ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($log['error_type']) ?></span>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 500px;" title="<?= htmlspecialchars($log['error_message']) ?>">
                                            <?= htmlspecialchars($log['error_message']) ?>
                                        </div>
                                        <?php if (!empty($log['file'])): ?>
                                            <small class="text-muted d-block text-truncate" style="max-width: 500px;">
                                                <?= htmlspecialchars($log['file']) ?>:<?= htmlspecialchars($log['line'] ?? 'N/A') ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $severityClass = '';
                                        switch ($log['severity']) {
                                            case 'critical':
                                                $severityClass = 'bg-danger';
                                                break;
                                            case 'high':
                                                $severityClass = 'bg-warning text-dark';
                                                break;
                                            case 'medium':
                                                $severityClass = 'bg-info text-dark';
                                                break;
                                            case 'low':
                                                $severityClass = 'bg-success';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $severityClass ?>"><?= ucfirst(htmlspecialchars($log['severity'])) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch ($log['status']) {
                                            case 'open':
                                                $statusClass = 'bg-danger';
                                                break;
                                            case 'resolved':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'ignored':
                                                $statusClass = 'bg-secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span>
                                    </td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <a href="/admin/errors/view/<?= $log['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($log['status'] === 'open'): ?>
                                            <button type="button" class="btn btn-sm btn-success quick-resolve" data-id="<?= $log['id'] ?>" title="Quick Resolve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center py-3">
                        <nav aria-label="Error logs pagination">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $this->buildPaginationUrl($urlParams, $page - 1) ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">&laquo;</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                // Always show first page
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="' . $this->buildPaginationUrl($urlParams, 1) . '">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                // Pages around current
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i == $page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="' . $this->buildPaginationUrl($urlParams, $i) . '">' . $i . '</a></li>';
                                    }
                                }
                                
                                // Always show last page
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="' . $this->buildPaginationUrl($urlParams, $totalPages) . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $this->buildPaginationUrl($urlParams, $page + 1) ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">&raquo;</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Resolve Modal -->
<div class="modal fade" id="quickResolveModal" tabindex="-1" aria-labelledby="quickResolveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="quickResolveForm" action="" method="post">
                <input type="hidden" name="ajax" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickResolveModalLabel">Quick Resolve Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to mark this error as resolved?</p>
                    <div class="mb-3">
                        <label for="quick-resolve-notes" class="form-label">Resolution Notes (optional)</label>
                        <textarea class="form-control" id="quick-resolve-notes" name="notes" rows="3" placeholder="Enter any notes about how this error was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Resolve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle refresh button
    document.getElementById('refreshBtn').addEventListener('click', function() {
        window.location.reload();
    });
    
    // Handle quick resolve buttons
    const quickResolveButtons = document.querySelectorAll('.quick-resolve');
    const quickResolveModal = new bootstrap.Modal(document.getElementById('quickResolveModal'));
    const quickResolveForm = document.getElementById('quickResolveForm');
    
    quickResolveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const errorId = this.getAttribute('data-id');
            quickResolveForm.action = `/admin/errors/resolve/${errorId}`;
            quickResolveModal.show();
        });
    });
    
    // Handle quick resolve form submission
    quickResolveForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            quickResolveModal.hide();
            
            if (data.status === 'success') {
                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertDiv);
                
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Show error message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertDiv);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            quickResolveModal.hide();
            
            // Show error message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                An error occurred while processing your request. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('.container-fluid').prepend(alertDiv);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?> 