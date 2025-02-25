<?php require_once __DIR__ . '/../layout/admin_header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mt-3 mb-4">Review Advertisement</h1>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Ad Preview: <?php echo htmlspecialchars($ad['title']); ?></h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="ad-preview-container mb-3" style="width: <?php echo $ad['width']; ?>px; height: <?php echo $ad['height']; ?>px; margin: 0 auto; border: 1px solid #ddd; overflow: hidden;">
                                <?php
                                    $content = json_decode($ad['content'], true);
                                    if (!empty($content['html'])) {
                                        echo $content['html'];
                                    } else {
                                        echo '<div class="alert alert-warning">No content available for preview</div>';
                                    }
                                ?>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Size:</strong> <?php echo $ad['width']; ?> x <?php echo $ad['height']; ?> pixels
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Ad Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($ad['id']); ?></p>
                                    <p><strong>Title:</strong> <?php echo htmlspecialchars($ad['title']); ?></p>
                                    <p><strong>Position:</strong> <?php echo htmlspecialchars($ad['position_name']); ?></p>
                                    <p><strong>Advertiser:</strong> <?php echo htmlspecialchars($ad['advertiser_name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Created:</strong> <?php echo date('M j, Y g:i a', strtotime($ad['created_at'])); ?></p>
                                    <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($ad['start_date'])); ?></p>
                                    <p><strong>End Date:</strong> <?php echo $ad['end_date'] ? date('M j, Y', strtotime($ad['end_date'])) : 'No end date'; ?></p>
                                    <p><strong>Budget:</strong> $<?php echo number_format($ad['budget'], 2); ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($targeting)): ?>
                                <hr>
                                <h6>Targeting Criteria:</h6>
                                <div class="row">
                                    <?php if (!empty($targeting['location'])): ?>
                                        <div class="col-md-4">
                                            <strong>Locations:</strong>
                                            <ul class="list-unstyled">
                                                <?php foreach ($targeting['location'] as $location): ?>
                                                    <li><?php echo htmlspecialchars($location); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($targeting['device'])): ?>
                                        <div class="col-md-4">
                                            <strong>Devices:</strong>
                                            <ul class="list-unstyled">
                                                <?php foreach ($targeting['device'] as $device): ?>
                                                    <li><?php echo htmlspecialchars($device); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($targeting['browser']) || !empty($targeting['os'])): ?>
                                        <div class="col-md-4">
                                            <?php if (!empty($targeting['browser'])): ?>
                                                <strong>Browsers:</strong>
                                                <ul class="list-unstyled">
                                                    <?php foreach ($targeting['browser'] as $browser): ?>
                                                        <li><?php echo htmlspecialchars($browser); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($targeting['os'])): ?>
                                                <strong>Operating Systems:</strong>
                                                <ul class="list-unstyled">
                                                    <?php foreach ($targeting['os'] as $os): ?>
                                                        <li><?php echo htmlspecialchars($os); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Review Decision</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2 mb-3">
                                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    <i class="fas fa-check-circle"></i> Approve Ad
                                </button>
                                <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle"></i> Reject Ad
                                </button>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Once approved, this ad will be immediately eligible to be served to users based on its targeting criteria.
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($reviewHistory)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">Review History</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <?php foreach ($reviewHistory as $review): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <span class="badge <?php echo $review['status'] === 'approved' ? 'bg-success' : ($review['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst($review['status']); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="mt-1">
                                                <small>By <?php echo htmlspecialchars($review['reviewer_name']); ?></small>
                                            </div>
                                            <?php if (!empty($review['comments'])): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted"><?php echo htmlspecialchars($review['comments']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($review['violation_type'])): ?>
                                                <div class="mt-1">
                                                    <small class="text-danger">Violation: <?php echo htmlspecialchars($review['violation_type']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="mt-3">
                                    <a href="/admin/review-history/<?php echo $ad['id']; ?>" class="btn btn-sm btn-outline-secondary">View Full History</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/approve-ad" method="post">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="approveModalLabel">Approve Advertisement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this advertisement?</p>
                    <p>Title: <strong><?php echo htmlspecialchars($ad['title']); ?></strong></p>
                    
                    <div class="mb-3">
                        <label for="approve-comments" class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" id="approve-comments" name="comments" rows="3" placeholder="Add any comments about this approval"></textarea>
                    </div>
                    
                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/reject-ad" method="post">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Advertisement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please specify the reason for rejecting this advertisement.</p>
                    <p>Title: <strong><?php echo htmlspecialchars($ad['title']); ?></strong></p>
                    
                    <div class="mb-3">
                        <label for="violation-type" class="form-label">Violation Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="violation-type" name="violation_type" required>
                            <option value="">Select a violation type</option>
                            <?php foreach ($violationTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['name']); ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                    <?php if ($type['severity'] == 'high'): ?>
                                        (High Severity)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reject-comments" class="form-label">Comments <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject-comments" name="comments" rows="3" placeholder="Explain why this ad is being rejected" required></textarea>
                    </div>
                    
                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/admin_footer.php'; ?> 