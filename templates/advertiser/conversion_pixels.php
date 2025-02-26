<?php require_once __DIR__ . '/../partials/header.php'; ?>
<?php require_once __DIR__ . '/../partials/navigation.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Conversion Tracking Pixels</h5>
                    <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#createPixelModal">
                        <i class="fas fa-plus"></i> Create New Pixel
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash_message_type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['flash_message']; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['flash_message'], $_SESSION['flash_message_type']); ?>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Conversion Type</th>
                                    <th>Pixel ID</th>
                                    <th>Status</th>
                                    <?php if ($is_admin): ?>
                                        <th>User</th>
                                    <?php endif; ?>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pixels)): ?>
                                    <tr>
                                        <td colspan="<?php echo $is_admin ? 7 : 6; ?>" class="text-center">
                                            No conversion pixels found. Create your first pixel to start tracking conversions.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pixels as $pixel): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pixel['name']); ?></td>
                                            <td><?php echo htmlspecialchars($pixel['conversion_type_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($pixel['pixel_id']); ?></code></td>
                                            <td>
                                                <?php if ($pixel['active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($is_admin): ?>
                                                <td><?php echo htmlspecialchars($pixel['user_name']); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo date('Y-m-d', strtotime($pixel['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-toggle="modal" 
                                                        data-target="#pixelCodeModal" 
                                                        data-pixel-id="<?php echo htmlspecialchars($pixel['pixel_id']); ?>"
                                                        data-pixel-name="<?php echo htmlspecialchars($pixel['name']); ?>">
                                                    <i class="fas fa-code"></i> Get Code
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        data-toggle="modal" 
                                                        data-target="#editPixelModal" 
                                                        data-id="<?php echo $pixel['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($pixel['name']); ?>"
                                                        data-type-id="<?php echo $pixel['conversion_type_id']; ?>"
                                                        data-active="<?php echo $pixel['active']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        data-toggle="modal" 
                                                        data-target="#deletePixelModal" 
                                                        data-id="<?php echo $pixel['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($pixel['name']); ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
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
    
    <!-- Conversion Tracking Guide -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">How to Implement Conversion Tracking</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p><strong>What is conversion tracking?</strong></p>
                        <p>Conversion tracking allows you to measure how effectively your ads lead to valuable customer actions, such as purchases, sign-ups, or other important actions on your website.</p>
                    </div>
                    
                    <h5>Implementation Steps:</h5>
                    <ol>
                        <li>Create a conversion pixel for each type of conversion you want to track (e.g., purchase, signup, etc.)</li>
                        <li>Add the tracking code to the page that loads after a successful conversion (e.g., order confirmation page)</li>
                        <li>View your conversion data in the Analytics section</li>
                    </ol>
                    
                    <h5>Implementation Options:</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">Option 1: Basic Implementation</div>
                                <div class="card-body">
                                    <p>Add this code to your conversion page:</p>
                                    <pre><code>&lt;script src="<?php echo BASE_URL; ?>/static/js/vertoad-pixel.js" 
        data-pixel-id="YOUR_PIXEL_ID"&gt;&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">Option 2: Advanced Implementation (with value)</div>
                                <div class="card-body">
                                    <p>For tracking conversion value (e.g., order amount):</p>
                                    <pre><code>&lt;script src="<?php echo BASE_URL; ?>/static/js/vertoad-pixel.js" 
        data-pixel-id="YOUR_PIXEL_ID"
        data-value="ORDER_VALUE"
        data-order-id="ORDER_ID"&gt;&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">Option 3: JavaScript API</div>
                                <div class="card-body">
                                    <p>For more control, use the JavaScript API:</p>
                                    <pre><code>&lt;script src="<?php echo BASE_URL; ?>/static/js/vertoad-pixel.js"&gt;&lt;/script&gt;
&lt;script&gt;
  // Track conversion when ready
  VertoADPixel.trackConversion('YOUR_PIXEL_ID', {
    value: 99.99,
    orderId: 'ORDER123',
    onSuccess: function() {
      console.log('Conversion tracked successfully');
    }
  });
&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">Testing Your Implementation</div>
                                <div class="card-body">
                                    <p>To verify your implementation:</p>
                                    <ol>
                                        <li>Add the tracking code to your conversion page</li>
                                        <li>Visit your ad through our platform</li>
                                        <li>Complete a conversion on your site</li>
                                        <li>Check the Analytics section to see if the conversion was recorded</li>
                                    </ol>
                                    <p>Conversions should appear in your analytics within a few minutes.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Pixel Modal -->
<div class="modal fade" id="createPixelModal" tabindex="-1" role="dialog" aria-labelledby="createPixelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo BASE_URL; ?>/advertiser/conversion-pixels">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPixelModalLabel">Create Conversion Pixel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="name">Pixel Name</label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g., Purchase Confirmation, Newsletter Signup">
                        <small class="form-text text-muted">Give your pixel a descriptive name to identify its purpose.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="conversion_type_id">Conversion Type</label>
                        <select class="form-control" id="conversion_type_id" name="conversion_type_id" required>
                            <option value="">Select a conversion type</option>
                            <?php foreach ($conversion_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the type of conversion this pixel will track.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Pixel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Pixel Modal -->
<div class="modal fade" id="editPixelModal" tabindex="-1" role="dialog" aria-labelledby="editPixelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo BASE_URL; ?>/advertiser/conversion-pixels">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPixelModalLabel">Edit Conversion Pixel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-group">
                        <label for="edit_name">Pixel Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_conversion_type_id">Conversion Type</label>
                        <select class="form-control" id="edit_conversion_type_id" name="conversion_type_id" required>
                            <?php foreach ($conversion_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="edit_active" name="active">
                            <label class="custom-control-label" for="edit_active">Active</label>
                        </div>
                        <small class="form-text text-muted">Inactive pixels will not record conversions.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Pixel Modal -->
<div class="modal fade" id="deletePixelModal" tabindex="-1" role="dialog" aria-labelledby="deletePixelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo BASE_URL; ?>/advertiser/conversion-pixels">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePixelModalLabel">Delete Conversion Pixel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p>Are you sure you want to delete the pixel "<span id="delete_name"></span>"?</p>
                    <p class="text-danger">This action cannot be undone. All conversion data will remain, but no new conversions will be tracked.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Pixel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pixel Code Modal -->
<div class="modal fade" id="pixelCodeModal" tabindex="-1" role="dialog" aria-labelledby="pixelCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pixelCodeModalLabel">Conversion Tracking Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Add this code to your conversion page (e.g., order confirmation page) to track conversions for <strong id="code_pixel_name"></strong>:</p>
                
                <div class="form-group">
                    <label>Basic Implementation:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="basic_code" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('basic_code')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>With Value Tracking:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="value_code" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('value_code')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">Replace ORDER_VALUE with your actual order value (e.g., 99.99) and ORDER_ID with your order identifier.</small>
                </div>
                
                <div class="form-group">
                    <label>JavaScript API Implementation:</label>
                    <div class="input-group">
                        <textarea class="form-control" id="js_code" rows="6" readonly></textarea>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('js_code')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle Edit Pixel Modal
    $('#editPixelModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const name = button.data('name');
        const typeId = button.data('type-id');
        const active = button.data('active');
        
        const modal = $(this);
        modal.find('#edit_id').val(id);
        modal.find('#edit_name').val(name);
        modal.find('#edit_conversion_type_id').val(typeId);
        modal.find('#edit_active').prop('checked', active == 1);
    });
    
    // Handle Delete Pixel Modal
    $('#deletePixelModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const name = button.data('name');
        
        const modal = $(this);
        modal.find('#delete_id').val(id);
        modal.find('#delete_name').text(name);
    });
    
    // Handle Pixel Code Modal
    $('#pixelCodeModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const pixelId = button.data('pixel-id');
        const pixelName = button.data('pixel-name');
        
        const modal = $(this);
        modal.find('#code_pixel_name').text(pixelName);
        
        // Basic implementation
        modal.find('#basic_code').val(`<script src="<?php echo BASE_URL; ?>/static/js/vertoad-pixel.js" data-pixel-id="${pixelId}"><\/script>`);
        
        // Value tracking implementation
        modal.find('#value_code').val(`<script src="<?php echo BASE_URL; ?>/static/js/vertoad-pixel.js" data-pixel-id="${pixelId}" data-value="ORDER_VALUE" data-order-id="ORDER_ID"><\/script>`);
        
        // JavaScript API implementation
        modal.find('#js_code').val(`<script src="<?php echo BASE_URL; ?>/static/js/vertoad-pixel.js"><\/script>
<script>
  // Track conversion when ready
  VertoADPixel.trackConversion('${pixelId}', {
    value: 99.99,
    orderId: 'ORDER123',
    onSuccess: function() {
      console.log('Conversion tracked successfully');
    }
  });
<\/script>`);
    });
    
    // Copy to clipboard function
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        element.select();
        document.execCommand('copy');
        
        // Show feedback
        const button = document.querySelector(`button[onclick="copyToClipboard('${elementId}')"]`);
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        
        setTimeout(function() {
            button.innerHTML = originalText;
        }, 2000);
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>