<?php include __DIR__ . '/../includes/admin_header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Error Log Detail</h1>
        <div>
            <a href="/admin/errors" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Error Logs
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
    
    <!-- Error Summary Card -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">
            <div>
                <i class="fas fa-exclamation-circle me-1"></i>
                Error Summary
            </div>
            <div>
                <?php
                $statusBadgeClass = '';
                switch ($log['status']) {
                    case 'open':
                        $statusBadgeClass = 'bg-danger';
                        break;
                    case 'resolved':
                        $statusBadgeClass = 'bg-success';
                        break;
                    case 'ignored':
                        $statusBadgeClass = 'bg-secondary';
                        break;
                }
                
                $severityBadgeClass = '';
                switch ($log['severity']) {
                    case 'critical':
                        $severityBadgeClass = 'bg-danger';
                        break;
                    case 'high':
                        $severityBadgeClass = 'bg-warning text-dark';
                        break;
                    case 'medium':
                        $severityBadgeClass = 'bg-info text-dark';
                        break;
                    case 'low':
                        $severityBadgeClass = 'bg-success';
                        break;
                }
                ?>
                <span class="badge <?= $statusBadgeClass ?> me-2">Status: <?= ucfirst($log['status']) ?></span>
                <span class="badge <?= $severityBadgeClass ?>">Severity: <?= ucfirst($log['severity']) ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-9">
                    <h5 class="card-title"><?= htmlspecialchars($log['error_message']) ?></h5>
                    <p class="card-text text-muted">
                        <strong>Type:</strong> <?= htmlspecialchars($log['error_type']) ?> |
                        <strong>Time:</strong> <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?> |
                        <?php if (!empty($log['error_code'])): ?>
                            <strong>Error Code:</strong> <?= htmlspecialchars($log['error_code']) ?> |
                        <?php endif; ?>
                        <?php if (!empty($log['file'])): ?>
                            <strong>File:</strong> <?= htmlspecialchars($log['file']) ?> |
                        <?php endif; ?>
                        <?php if (!empty($log['line'])): ?>
                            <strong>Line:</strong> <?= htmlspecialchars($log['line']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3 text-end">
                    <?php if ($log['status'] === 'open'): ?>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal">
                            <i class="fas fa-check me-2"></i>Resolve
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#ignoreModal">
                            <i class="fas fa-ban me-2"></i>Ignore
                        </button>
                    <?php else: ?>
                        <span class="text-muted">
                            <?= $log['status'] === 'resolved' ? 'Resolved by' : 'Ignored by' ?>: 
                            <?= !empty($log['user_id']) ? htmlspecialchars($log['user_name'] ?? 'User #' . $log['user_id']) : 'System' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Error Details Column -->
        <div class="col-md-8">
            <!-- Stack Trace Card -->
            <?php if (!empty($log['stack_trace'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-layer-group me-1"></i>
                        Stack Trace
                    </div>
                    <div class="card-body">
                        <?php if (is_array($log['stack_trace'])): ?>
                            <pre class="bg-light p-3 mb-0" style="max-height: 300px; overflow-y: auto;"><?= json_encode($log['stack_trace'], JSON_PRETTY_PRINT) ?></pre>
                        <?php else: ?>
                            <pre class="bg-light p-3 mb-0" style="max-height: 300px; overflow-y: auto;"><?= htmlspecialchars($log['stack_trace']) ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Additional Data Card -->
            <?php if (!empty($log['additional_data'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Additional Data
                    </div>
                    <div class="card-body">
                        <?php if (is_array($log['additional_data'])): ?>
                            <?php foreach ($log['additional_data'] as $key => $value): ?>
                                <h6><?= htmlspecialchars($key) ?></h6>
                                <?php if (is_array($value)): ?>
                                    <pre class="bg-light p-2 mb-3"><?= json_encode($value, JSON_PRETTY_PRINT) ?></pre>
                                <?php else: ?>
                                    <p class="mb-3"><?= htmlspecialchars($value) ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <pre class="bg-light p-3 mb-0"><?= htmlspecialchars($log['additional_data']) ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Context Information Column -->
        <div class="col-md-4">
            <!-- Request Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-globe me-1"></i>
                    Request Information
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <?php if (!empty($log['url'])): ?>
                            <dt class="col-sm-4">URL</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($log['url']) ?></dd>
                        <?php endif; ?>
                        
                        <?php if (!empty($log['ip_address'])): ?>
                            <dt class="col-sm-4">IP Address</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($log['ip_address']) ?></dd>
                        <?php endif; ?>
                        
                        <?php if (!empty($log['user_agent'])): ?>
                            <dt class="col-sm-4">User Agent</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($log['user_agent']) ?></dd>
                        <?php endif; ?>
                        
                        <?php if (!empty($log['session_id'])): ?>
                            <dt class="col-sm-4">Session ID</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($log['session_id']) ?></dd>
                        <?php endif; ?>
                        
                        <?php if (!empty($log['user_id'])): ?>
                            <dt class="col-sm-4">User ID</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($log['user_id']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            
            <!-- Notifications Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bell me-1"></i>
                    Notifications Sent
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted mb-0">No notifications were sent for this error.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <i class="fas <?= $notification['method'] === 'email' ? 'fa-envelope' : ($notification['method'] === 'sms' ? 'fa-mobile-alt' : 'fa-comment-alt') ?> me-2"></i>
                                            <?= ucfirst($notification['method']) ?>
                                        </h6>
                                        <small><?= date('M d, H:i', strtotime($notification['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php 
                                        $statusBadge = '';
                                        switch ($notification['status']) {
                                            case 'sent':
                                                $statusBadge = '<span class="badge bg-success">Sent</span>';
                                                break;
                                            case 'failed':
                                                $statusBadge = '<span class="badge bg-danger">Failed</span>';
                                                break;
                                            case 'pending':
                                                $statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                                                break;
                                            default:
                                                $statusBadge = '<span class="badge bg-secondary">' . ucfirst($notification['status']) . '</span>';
                                        }
                                        ?>
                                        <?= $statusBadge ?> 
                                        To: <?= htmlspecialchars($notification['user_name'] ?? 'User #' . $notification['user_id']) ?>
                                    </p>
                                    <?php if (!empty($notification['message'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars($notification['message']) ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Similar Errors Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-link me-1"></i>
                    Similar Errors
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Find similar errors by:</p>
                    <div class="d-grid gap-2">
                        <a href="/admin/errors?error_type=<?= urlencode($log['error_type']) ?>" class="btn btn-sm btn-outline-primary">
                            Same Error Type
                        </a>
                        <?php if (!empty($log['error_code'])): ?>
                            <a href="/admin/errors?error_code=<?= urlencode($log['error_code']) ?>" class="btn btn-sm btn-outline-primary">
                                Same Error Code
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($log['file'])): ?>
                            <a href="/admin/errors?file=<?= urlencode($log['file']) ?>" class="btn btn-sm btn-outline-primary">
                                Same File
                            </a>
                        <?php endif; ?>
                        <a href="/admin/errors?search=<?= urlencode($log['error_message']) ?>" class="btn btn-sm btn-outline-primary">
                            Similar Message
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/resolve/<?= $log['id'] ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="resolveModalLabel">Resolve Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to mark this error as resolved?</p>
                    <div class="mb-3">
                        <label for="resolve-notes" class="form-label">Resolution Notes (optional)</label>
                        <textarea class="form-control" id="resolve-notes" name="notes" rows="3" placeholder="Enter any notes about how this error was resolved..."></textarea>
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

<!-- Ignore Modal -->
<div class="modal fade" id="ignoreModal" tabindex="-1" aria-labelledby="ignoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/ignore/<?= $log['id'] ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="ignoreModalLabel">Ignore Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to ignore this error? Ignored errors will not appear in the main error list.</p>
                    <div class="mb-3">
                        <label for="ignore-notes" class="form-label">Reason for Ignoring (optional)</label>
                        <textarea class="form-control" id="ignore-notes" name="notes" rows="3" placeholder="Enter the reason for ignoring this error..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Ignore</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?> 