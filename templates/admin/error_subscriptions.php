<?php include __DIR__ . '/../includes/admin_header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Error Notification Subscriptions</h1>
        <div>
            <a href="/admin/errors/dashboard" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar me-2"></i>Dashboard
            </a>
            <a href="/admin/errors" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-list me-2"></i>Error Logs
            </a>
            <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
                <i class="fas fa-plus me-2"></i>Add Subscription
            </button>
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
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Subscriptions Table Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bell me-1"></i>
                    Active Subscriptions
                </div>
                <div class="card-body p-0">
                    <?php if (empty($subscriptions)): ?>
                        <div class="alert alert-info m-3 mb-0">
                            No notification subscriptions found. Create your first subscription to receive alerts for critical errors.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Method</th>
                                        <th>Min. Severity</th>
                                        <th>Error Types</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $subscription): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($subscription['user_name'] ?? 'User #' . $subscription['user_id']) ?></td>
                                            <td>
                                                <?php 
                                                $methodIcon = '';
                                                switch ($subscription['notification_method']) {
                                                    case 'email':
                                                        $methodIcon = '<i class="fas fa-envelope me-1"></i>';
                                                        break;
                                                    case 'sms':
                                                        $methodIcon = '<i class="fas fa-mobile-alt me-1"></i>';
                                                        break;
                                                    case 'dashboard':
                                                        $methodIcon = '<i class="fas fa-bell me-1"></i>';
                                                        break;
                                                }
                                                echo $methodIcon . ucfirst($subscription['notification_method']);
                                                ?>
                                                <?php if (!empty($subscription['notification_target'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($subscription['notification_target']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $severityClass = '';
                                                switch ($subscription['min_severity']) {
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
                                                <span class="badge <?= $severityClass ?>">
                                                    <?= ucfirst(htmlspecialchars($subscription['min_severity'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (empty($subscription['error_types'])): ?>
                                                    <span class="text-muted">All types</span>
                                                <?php else: ?>
                                                    <?php 
                                                    $types = explode(',', $subscription['error_types']);
                                                    foreach ($types as $type): 
                                                    ?>
                                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($type) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $subscription['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= $subscription['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-subscription" 
                                                        data-id="<?= $subscription['id'] ?>"
                                                        data-user="<?= $subscription['user_id'] ?>"
                                                        data-method="<?= htmlspecialchars($subscription['notification_method']) ?>"
                                                        data-target="<?= htmlspecialchars($subscription['notification_target'] ?? '') ?>"
                                                        data-severity="<?= htmlspecialchars($subscription['min_severity']) ?>"
                                                        data-types="<?= htmlspecialchars($subscription['error_types'] ?? '') ?>"
                                                        data-active="<?= $subscription['is_active'] ? '1' : '0' ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-subscription" 
                                                        data-id="<?= $subscription['id'] ?>"
                                                        data-user="<?= htmlspecialchars($subscription['user_name'] ?? 'User #' . $subscription['user_id']) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Notifications Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history me-1"></i>
                    Recent Notifications
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_notifications)): ?>
                        <div class="alert alert-info m-3 mb-0">
                            No notifications have been sent recently.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Error</th>
                                        <th>Recipient</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_notifications as $notification): ?>
                                        <tr>
                                            <td><?= date('Y-m-d H:i', strtotime($notification['created_at'])) ?></td>
                                            <td>
                                                <a href="/admin/errors/view/<?= $notification['error_log_id'] ?>" class="text-truncate d-inline-block" style="max-width: 250px;" title="<?= htmlspecialchars($notification['error_message']) ?>">
                                                    <?= htmlspecialchars($notification['error_message']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($notification['user_name'] ?? 'User #' . $notification['user_id']) ?></td>
                                            <td><?= ucfirst($notification['notification_method']) ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                switch ($notification['status']) {
                                                    case 'sent':
                                                        $statusClass = 'bg-success';
                                                        break;
                                                    case 'failed':
                                                        $statusClass = 'bg-danger';
                                                        break;
                                                    case 'pending':
                                                        $statusClass = 'bg-warning text-dark';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?= $statusClass ?>">
                                                    <?= ucfirst($notification['status']) ?>
                                                </span>
                                                <?php if ($notification['status'] === 'failed' && !empty($notification['error_message'])): ?>
                                                    <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="<?= htmlspecialchars($notification['error_message']) ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($recent_notifications)): ?>
                    <div class="card-footer text-end">
                        <a href="/admin/errors/notifications/history" class="btn btn-sm btn-outline-secondary">View Full History</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Configuration Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-cog me-1"></i>
                    Notification Settings
                </div>
                <div class="card-body">
                    <form action="/admin/errors/settings" method="post">
                        <div class="mb-3">
                            <label for="notification_enabled" class="form-label">Enable Notifications</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="notification_enabled" name="notification_enabled" <?= $settings['notification_enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notification_enabled">
                                    <?= $settings['notification_enabled'] ? 'Notifications are enabled' : 'Notifications are disabled' ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="throttle_notifications" class="form-label">Throttle Notifications</label>
                            <div class="input-group">
                                <span class="input-group-text">Max</span>
                                <input type="number" class="form-control" id="throttle_limit" name="throttle_limit" value="<?= $settings['throttle_limit'] ?? 10 ?>" min="1" max="100">
                                <span class="input-group-text">per</span>
                                <input type="number" class="form-control" id="throttle_period" name="throttle_period" value="<?= $settings['throttle_period'] ?? 15 ?>" min="1" max="1440">
                                <span class="input-group-text">minutes</span>
                            </div>
                            <div class="form-text">Limits the number of notifications sent in a time period to prevent flooding</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="group_similar" class="form-label">Group Similar Errors</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="group_similar" name="group_similar" <?= $settings['group_similar'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="group_similar">
                                    Combine similar errors in notifications
                                </label>
                            </div>
                            <div class="form-text">When enabled, similar errors will be grouped in a single notification</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_from" class="form-label">Email From Address</label>
                            <input type="email" class="form-control" id="email_from" name="email_from" value="<?= htmlspecialchars($settings['email_from'] ?? '') ?>" placeholder="errors@yourdomain.com">
                            <div class="form-text">The address that error notification emails will be sent from</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
            
            <!-- Help Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-question-circle me-1"></i>
                    About Notifications
                </div>
                <div class="card-body">
                    <h5>Notification Methods</h5>
                    <ul>
                        <li><strong>Dashboard:</strong> Shows notifications in the admin dashboard only</li>
                        <li><strong>Email:</strong> Sends detailed error information to the specified email address</li>
                        <li><strong>SMS:</strong> Sends brief error alerts to the specified phone number</li>
                    </ul>
                    
                    <h5>Severity Levels</h5>
                    <p>When you set a minimum severity level, you'll receive notifications for all errors at or above that level:</p>
                    <ul>
                        <li><span class="badge bg-danger">Critical</span> - System-breaking errors that need immediate attention</li>
                        <li><span class="badge bg-warning text-dark">High</span> - Serious errors affecting functionality</li>
                        <li><span class="badge bg-info text-dark">Medium</span> - Moderate issues that may affect users</li>
                        <li><span class="badge bg-success">Low</span> - Minor issues, warnings, and notices</li>
                    </ul>
                    
                    <h5>Error Types</h5>
                    <p>Specify which types of errors should trigger notifications. Leave blank to receive all types.</p>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        We recommend setting up at least one notification subscription for critical errors to ensure you're alerted about important issues.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Subscription Modal -->
<div class="modal fade" id="addSubscriptionModal" tabindex="-1" aria-labelledby="addSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/subscriptions" method="post">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSubscriptionModalLabel">Add Notification Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the user who will receive notifications</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notification_method" class="form-label">Notification Method</label>
                        <select class="form-select" id="notification_method" name="notification_method" required>
                            <option value="dashboard">Dashboard</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 notification-target-group d-none">
                        <label for="notification_target" class="form-label">Notification Target</label>
                        <input type="text" class="form-control" id="notification_target" name="notification_target">
                        <div class="form-text target-help"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_severity" class="form-label">Minimum Severity</label>
                        <select class="form-select" id="min_severity" name="min_severity" required>
                            <option value="low">Low (All Errors)</option>
                            <option value="medium">Medium</option>
                            <option value="high" selected>High</option>
                            <option value="critical">Critical Only</option>
                        </select>
                        <div class="form-text">You'll receive notifications for errors at or above this severity level</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="error_types" class="form-label">Error Types (Optional)</label>
                        <select class="form-select" id="error_types" name="error_types[]" multiple data-placeholder="Select error types or leave empty for all">
                            <?php foreach ($error_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty to receive notifications for all error types, or select specific types</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                        <div class="form-text">You can disable this subscription temporarily without deleting it</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subscription Modal -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1" aria-labelledby="editSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/subscriptions" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSubscriptionModalLabel">Edit Notification Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-user-id" class="form-label">User</label>
                        <select class="form-select" id="edit-user-id" name="user_id" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-notification-method" class="form-label">Notification Method</label>
                        <select class="form-select" id="edit-notification-method" name="notification_method" required>
                            <option value="dashboard">Dashboard</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 edit-notification-target-group d-none">
                        <label for="edit-notification-target" class="form-label">Notification Target</label>
                        <input type="text" class="form-control" id="edit-notification-target" name="notification_target">
                        <div class="form-text edit-target-help"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-min-severity" class="form-label">Minimum Severity</label>
                        <select class="form-select" id="edit-min-severity" name="min_severity" required>
                            <option value="low">Low (All Errors)</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical Only</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-error-types" class="form-label">Error Types (Optional)</label>
                        <select class="form-select" id="edit-error-types" name="error_types[]" multiple data-placeholder="Select error types or leave empty for all">
                            <?php foreach ($error_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty to receive notifications for all error types</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit-is-active" name="is_active">
                            <label class="form-check-label" for="edit-is-active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Subscription Modal -->
<div class="modal fade" id="deleteSubscriptionModal" tabindex="-1" aria-labelledby="deleteSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/subscriptions" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSubscriptionModalLabel">Delete Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the notification subscription for <span id="delete-user"></span>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. This user will no longer receive error notifications.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide notification target field based on method
    const methodSelect = document.getElementById('notification_method');
    const targetGroup = document.querySelector('.notification-target-group');
    const targetHelp = document.querySelector('.target-help');
    
    function updateTargetField() {
        const method = methodSelect.value;
        
        if (method === 'email') {
            targetGroup.classList.remove('d-none');
            targetHelp.textContent = 'Enter the email address to send notifications to';
            document.getElementById('notification_target').type = 'email';
            document.getElementById('notification_target').placeholder = 'user@example.com';
        } else if (method === 'sms') {
            targetGroup.classList.remove('d-none');
            targetHelp.textContent = 'Enter the phone number to send SMS notifications to';
            document.getElementById('notification_target').type = 'tel';
            document.getElementById('notification_target').placeholder = '+1234567890';
        } else {
            targetGroup.classList.add('d-none');
            document.getElementById('notification_target').value = '';
        }
    }
    
    methodSelect.addEventListener('change', updateTargetField);
    updateTargetField();
    
    // Edit subscription modal logic
    const editMethodSelect = document.getElementById('edit-notification-method');
    const editTargetGroup = document.querySelector('.edit-notification-target-group');
    const editTargetHelp = document.querySelector('.edit-target-help');
    
    function updateEditTargetField() {
        const method = editMethodSelect.value;
        
        if (method === 'email') {
            editTargetGroup.classList.remove('d-none');
            editTargetHelp.textContent = 'Enter the email address to send notifications to';
            document.getElementById('edit-notification-target').type = 'email';
            document.getElementById('edit-notification-target').placeholder = 'user@example.com';
        } else if (method === 'sms') {
            editTargetGroup.classList.remove('d-none');
            editTargetHelp.textContent = 'Enter the phone number to send SMS notifications to';
            document.getElementById('edit-notification-target').type = 'tel';
            document.getElementById('edit-notification-target').placeholder = '+1234567890';
        } else {
            editTargetGroup.classList.add('d-none');
            document.getElementById('edit-notification-target').value = '';
        }
    }
    
    editMethodSelect.addEventListener('change', updateEditTargetField);
    
    // Handle edit subscription buttons
    const editSubscriptionButtons = document.querySelectorAll('.edit-subscription');
    const editSubscriptionModal = new bootstrap.Modal(document.getElementById('editSubscriptionModal'));
    
    editSubscriptionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const userId = this.getAttribute('data-user');
            const method = this.getAttribute('data-method');
            const target = this.getAttribute('data-target');
            const severity = this.getAttribute('data-severity');
            const types = this.getAttribute('data-types');
            const active = this.getAttribute('data-active') === '1';
            
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-notification-method').value = method;
            document.getElementById('edit-notification-target').value = target;
            document.getElementById('edit-min-severity').value = severity;
            
            // Set the error types
            const typeSelect = document.getElementById('edit-error-types');
            if (types) {
                const typeArray = types.split(',');
                
                for (let i = 0; i < typeSelect.options.length; i++) {
                    typeSelect.options[i].selected = typeArray.includes(typeSelect.options[i].value);
                }
            } else {
                // Clear all selections
                for (let i = 0; i < typeSelect.options.length; i++) {
                    typeSelect.options[i].selected = false;
                }
            }
            
            document.getElementById('edit-is-active').checked = active;
            
            // Update the target field visibility
            updateEditTargetField();
            
            editSubscriptionModal.show();
        });
    });
    
    // Handle delete subscription buttons
    const deleteSubscriptionButtons = document.querySelectorAll('.delete-subscription');
    const deleteSubscriptionModal = new bootstrap.Modal(document.getElementById('deleteSubscriptionModal'));
    
    deleteSubscriptionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const user = this.getAttribute('data-user');
            
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-user').textContent = user;
            
            deleteSubscriptionModal.show();
        });
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?> 