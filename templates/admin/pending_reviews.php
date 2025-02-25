<?php require_once __DIR__ . '/../layout/admin_header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mt-3 mb-4">Advertisements Pending Review</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Pending Reviews (<?php echo $totalPending; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendingAds)): ?>
                                <div class="alert alert-info">
                                    No advertisements pending review at this time.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Title</th>
                                                <th>Position</th>
                                                <th>Advertiser</th>
                                                <th>Submitted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingAds as $ad): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ad['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($ad['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($ad['position_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($ad['advertiser_name']); ?></td>
                                                    <td>
                                                        <?php echo date('M j, Y g:i a', strtotime($ad['created_at'])); ?>
                                                        <small class="text-muted d-block">
                                                            <?php 
                                                                $submittedTime = new DateTime($ad['created_at']);
                                                                $now = new DateTime();
                                                                $interval = $submittedTime->diff($now);
                                                                
                                                                if ($interval->days > 0) {
                                                                    echo $interval->days . ' days ago';
                                                                } elseif ($interval->h > 0) {
                                                                    echo $interval->h . ' hours ago';
                                                                } else {
                                                                    echo $interval->i . ' minutes ago';
                                                                }
                                                            ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <a href="/admin/review/<?php echo $ad['id']; ?>" class="btn btn-sm btn-primary">Review</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="/admin/reviews?page=<?php echo $i; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentActivity)): ?>
                                <div class="alert alert-info">
                                    No recent review activity.
                                </div>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['ad_title']); ?></strong>
                                                    <span class="badge <?php echo $activity['status'] === 'approved' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo ucfirst($activity['status']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j g:i a', strtotime($activity['updated_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="mt-1">
                                                <small>By <?php echo htmlspecialchars($activity['reviewer_name']); ?></small>
                                            </div>
                                            <?php if (!empty($activity['comments'])): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted"><?php echo htmlspecialchars($activity['comments']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Review Guides</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <a href="/admin/violation-types">Manage Violation Types</a>
                                </li>
                                <li class="list-group-item">
                                    <a href="#">Review Policy</a>
                                </li>
                                <li class="list-group-item">
                                    <a href="#">Content Guidelines</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/admin_footer.php'; ?> 