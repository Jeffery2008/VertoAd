<?php require_once __DIR__ . '/../layout/admin_header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mt-3 mb-4">
                Ad Review History
                <small class="text-muted">for <?php echo htmlspecialchars($ad['title']); ?></small>
            </h1>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Advertisement Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($ad['id']); ?></p>
                                    <p><strong>Title:</strong> <?php echo htmlspecialchars($ad['title']); ?></p>
                                    <p><strong>Current Status:</strong> 
                                        <span class="badge <?php echo $ad['status'] === 'active' ? 'bg-success' : ($ad['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($ad['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Advertiser:</strong> <?php echo htmlspecialchars($ad['advertiser_name']); ?></p>
                                    <p><strong>Position:</strong> <?php echo htmlspecialchars($ad['position_name']); ?></p>
                                    <p><strong>Size:</strong> <?php echo $ad['width']; ?> x <?php echo $ad['height']; ?> pixels</p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Created:</strong> <?php echo date('M j, Y g:i a', strtotime($ad['created_at'])); ?></p>
                                    <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($ad['start_date'])); ?></p>
                                    <p><strong>End Date:</strong> <?php echo $ad['end_date'] ? date('M j, Y', strtotime($ad['end_date'])) : 'No end date'; ?></p>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-grid gap-2">
                                        <a href="/admin/review/<?php echo $ad['id']; ?>" class="btn btn-primary">Review This Ad</a>
                                        <a href="/admin/ads" class="btn btn-outline-secondary">Back to Ads</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Review History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($reviewHistory)): ?>
                                <div class="alert alert-info">
                                    No review history found for this advertisement.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Reviewer</th>
                                                <th>Status</th>
                                                <th>Violation</th>
                                                <th>Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reviewHistory as $review): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y g:i a', strtotime($review['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($review['reviewer_name']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $review['status'] === 'approved' ? 'bg-success' : ($review['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'); ?>">
                                                            <?php echo ucfirst($review['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($review['violation_type'])): ?>
                                                            <span class="text-danger"><?php echo htmlspecialchars($review['violation_type']); ?></span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($review['comments'])): ?>
                                                            <?php echo htmlspecialchars($review['comments']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">No comments</span>
                                                        <?php endif; ?>
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
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Audit Log</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($reviewLogs)): ?>
                                <div class="alert alert-info">
                                    No audit logs found for this advertisement.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Action</th>
                                                <th>User</th>
                                                <th>Status Change</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reviewLogs as $log): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php 
                                                            echo $log['action'] === 'approve' ? 'bg-success' : 
                                                                ($log['action'] === 'reject' ? 'bg-danger' : 
                                                                    ($log['action'] === 'start_review' ? 'bg-info' : 'bg-secondary')); 
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['actor_name']); ?></td>
                                                    <td>
                                                        <?php if ($log['old_status'] && $log['new_status']): ?>
                                                            <?php echo ucfirst($log['old_status']); ?> → <?php echo ucfirst($log['new_status']); ?>
                                                        <?php elseif ($log['new_status']): ?>
                                                            Set to <?php echo ucfirst($log['new_status']); ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($log['comments'])): ?>
                                                            <small><?php echo htmlspecialchars($log['comments']); ?></small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No notes</small>
                                                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../layout/admin_footer.php'; ?> 