<?php require_once TEMPLATES_PATH . '/admin/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <?php require_once TEMPLATES_PATH . '/admin/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Conversion Types Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newTypeModal">
                        <i class="fas fa-plus"></i> Add New Type
                    </button>
                </div>
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
                    <h5 class="mb-0">Conversion Types</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($types)): ?>
                        <div class="alert alert-info">
                            No conversion types have been created yet. Click "Add New Type" to create your first conversion type.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Value Type</th>
                                        <th>Default Value</th>
                                        <th>Conversions</th>
                                        <th>Total Value</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($types as $type): ?>
                                        <tr>
                                            <td><?= $type['id'] ?></td>
                                            <td><?= htmlspecialchars($type['name']) ?></td>
                                            <td>
                                                <?php if ($type['value_type'] === 'fixed'): ?>
                                                    <span class="badge badge-info">Fixed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Variable</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?= number_format($type['default_value'], 2) ?></td>
                                            <td>
                                                <?= isset($countsMap[$type['id']]) ? number_format($countsMap[$type['id']]['count']) : '0' ?>
                                            </td>
                                            <td>
                                                $<?= isset($countsMap[$type['id']]) ? number_format($countsMap[$type['id']]['total_value'], 2) : '0.00' ?>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($type['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-primary edit-type" 
                                                            data-id="<?= $type['id'] ?>"
                                                            data-name="<?= htmlspecialchars($type['name']) ?>"
                                                            data-description="<?= htmlspecialchars($type['description']) ?>"
                                                            data-value-type="<?= $type['value_type'] ?>"
                                                            data-default-value="<?= $type['default_value'] ?>"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (!isset($countsMap[$type['id']]) || $countsMap[$type['id']]['count'] == 0): ?>
                                                        <button class="btn btn-danger delete-type" 
                                                                data-id="<?= $type['id'] ?>" 
                                                                data-name="<?= htmlspecialchars($type['name']) ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
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
                    <h5 class="mb-0">About Conversion Types</h5>
                </div>
                <div class="card-body">
                    <p>Conversion types define the different kinds of conversion events that can be tracked in the system. Examples include:</p>
                    
                    <ul>
                        <li><strong>Purchases</strong> - When a user completes a purchase on an advertiser's site</li>
                        <li><strong>Sign-ups</strong> - When a user creates an account or subscribes to a service</li>
                        <li><strong>Lead Generation</strong> - When a user submits a form or requests information</li>
                        <li><strong>App Downloads</strong> - When a user downloads an app</li>
                    </ul>
                    
                    <p><strong>Value types:</strong></p>
                    <ul>
                        <li><strong>Fixed</strong> - The conversion always has the same value (e.g., a lead is always worth $10)</li>
                        <li><strong>Variable</strong> - The conversion value can vary (e.g., purchases with different cart values)</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Conversion types that have been used in conversions cannot be deleted to maintain data integrity.
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- New/Edit Conversion Type Modal -->
<div class="modal fade" id="typeModal" tabindex="-1" role="dialog" aria-labelledby="typeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= URL_ROOT ?>/admin/save-conversion-type" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="typeModalLabel">Add New Conversion Type</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="type-id" name="id" value="">
                    
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="value_type">Value Type *</label>
                        <select class="form-control" id="value_type" name="value_type" required>
                            <option value="fixed">Fixed Value</option>
                            <option value="variable">Variable Value</option>
                        </select>
                        <small class="form-text text-muted">
                            Fixed: Same value for all conversions. Variable: Value specified during conversion.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_value">Default Value ($) *</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="default_value" name="default_value" required>
                        <small class="form-text text-muted">
                            For fixed values, this is the conversion value. For variable values, this is used when no value is provided.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Type Confirmation Modal -->
<div class="modal fade" id="deleteTypeModal" tabindex="-1" role="dialog" aria-labelledby="deleteTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteTypeModalLabel">Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the conversion type "<span id="delete-type-name"></span>"?</p>
                <p class="mb-0 text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="confirm-delete-btn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show new type modal
        document.querySelector('button[data-target="#newTypeModal"]').addEventListener('click', function() {
            // Reset form
            document.getElementById('type-id').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('value_type').value = 'fixed';
            document.getElementById('default_value').value = '0.00';
            
            // Set modal title
            document.getElementById('typeModalLabel').innerText = 'Add New Conversion Type';
            
            // Show modal
            $('#typeModal').modal('show');
        });
        
        // Set up edit type buttons
        const editButtons = document.querySelectorAll('.edit-type');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const valueType = this.getAttribute('data-value-type');
                const defaultValue = this.getAttribute('data-default-value');
                
                // Fill form
                document.getElementById('type-id').value = id;
                document.getElementById('name').value = name;
                document.getElementById('description').value = description;
                document.getElementById('value_type').value = valueType;
                document.getElementById('default_value').value = defaultValue;
                
                // Set modal title
                document.getElementById('typeModalLabel').innerText = 'Edit Conversion Type';
                
                // Show modal
                $('#typeModal').modal('show');
            });
        });
        
        // Set up delete type confirmation
        const deleteButtons = document.querySelectorAll('.delete-type');
        const deleteNameSpan = document.getElementById('delete-type-name');
        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const typeId = this.getAttribute('data-id');
                const typeName = this.getAttribute('data-name');
                
                deleteNameSpan.textContent = typeName;
                confirmDeleteBtn.setAttribute('href', '<?= URL_ROOT ?>/admin/delete-conversion-type/' + typeId);
                
                $('#deleteTypeModal').modal('show');
            });
        });
    });
</script>

<?php require_once TEMPLATES_PATH . '/admin/footer.php'; ?> 