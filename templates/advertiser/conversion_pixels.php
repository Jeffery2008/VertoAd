<?php require_once TEMPLATES_PATH . '/advertiser/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Conversion Tracking Pixels</h1>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newPixelModal">
                    <i class="fas fa-plus"></i> Create New Pixel
                </button>
            </div>
            
            <?php if (isset($_SESSION['flash'])): ?>
                <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show">
                    <?= $_SESSION['flash']['message'] ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Your Conversion Pixels</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pixels)): ?>
                        <div class="alert alert-info">
                            You haven't created any conversion tracking pixels yet. Create your first pixel to start tracking conversions.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Conversion Type</th>
                                        <th>Pixel ID</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pixels as $pixel): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pixel['name']) ?></td>
                                            <td><?= htmlspecialchars($pixel['type_name']) ?></td>
                                            <td><code><?= htmlspecialchars($pixel['pixel_id']) ?></code></td>
                                            <td>
                                                <?php if ($pixel['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($pixel['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= URL_ROOT ?>/advertiser/pixel-code/<?= $pixel['pixel_id'] ?>" class="btn btn-info" title="Get Code">
                                                        <i class="fas fa-code"></i> Get Code
                                                    </a>
                                                    <button class="btn btn-danger delete-pixel" data-id="<?= $pixel['pixel_id'] ?>" data-name="<?= htmlspecialchars($pixel['name']) ?>" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
            
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">How to Use Conversion Tracking</h5>
                </div>
                <div class="card-body">
                    <p>Follow these steps to implement conversion tracking on your website:</p>
                    
                    <ol>
                        <li>Create a conversion tracking pixel for each conversion type you want to track (e.g., purchases, sign-ups, etc.)</li>
                        <li>Add the tracking pixel code to the confirmation page of your website where the conversion happens</li>
                        <li>View your conversion data in the <a href="<?= URL_ROOT ?>/advertiser/conversions">Conversions Dashboard</a></li>
                    </ol>
                    
                    <p>To properly track conversions, make sure your ads are set up with our tracking parameters. The tracking pixel will automatically detect these parameters when a user converts.</p>
                    
                    <div class="alert alert-warning">
                        <strong>Important:</strong> For accurate tracking, do not modify the tracking pixel code or remove the ad_id and click_id parameters from your URLs.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Pixel Modal -->
<div class="modal fade" id="newPixelModal" tabindex="-1" role="dialog" aria-labelledby="newPixelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= URL_ROOT ?>/advertiser/generate-pixel" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="newPixelModalLabel">Create New Conversion Pixel</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Pixel Name</label>
                        <input type="text" class="form-control" id="name" name="name" required maxlength="100" placeholder="e.g., Purchase Tracking">
                        <small class="form-text text-muted">Give your pixel a descriptive name to identify its purpose.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="conversion_type_id">Conversion Type</label>
                        <select class="form-control" id="conversion_type_id" name="conversion_type_id" required>
                            <option value="">-- Select Conversion Type --</option>
                            <?php foreach ($conversionTypes as $type): ?>
                                <option value="<?= $type['id'] ?>">
                                    <?= htmlspecialchars($type['name']) ?> 
                                    <?php if ($type['value_type'] === 'fixed'): ?>
                                        (Fixed Value: $<?= number_format($type['default_value'], 2) ?>)
                                    <?php else: ?>
                                        (Variable Value)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the type of conversion this pixel will track.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Pixel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Pixel Confirmation Modal -->
<div class="modal fade" id="deletePixelModal" tabindex="-1" role="dialog" aria-labelledby="deletePixelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deletePixelModalLabel">Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the conversion pixel "<span id="delete-pixel-name"></span>"?</p>
                <p class="mb-0 text-danger"><strong>Warning:</strong> This action cannot be undone and will stop all conversion tracking for this pixel.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="confirm-delete-btn" class="btn btn-danger">Delete Pixel</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set up delete pixel confirmation
        const deleteButtons = document.querySelectorAll('.delete-pixel');
        const deleteNameSpan = document.getElementById('delete-pixel-name');
        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const pixelId = this.getAttribute('data-id');
                const pixelName = this.getAttribute('data-name');
                
                deleteNameSpan.textContent = pixelName;
                confirmDeleteBtn.setAttribute('href', '<?= URL_ROOT ?>/advertiser/delete-pixel/' + pixelId);
                
                $('#deletePixelModal').modal('show');
            });
        });
    });
</script>

<?php require_once TEMPLATES_PATH . '/advertiser/footer.php'; ?> 