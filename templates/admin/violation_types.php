<?php require_once __DIR__ . '/../layout/admin_header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mt-3 mb-4">Manage Violation Types</h1>
            
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
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Violation Types</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($violationTypes)): ?>
                                <div class="alert alert-info">
                                    No violation types defined yet. Create your first one using the form.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Severity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($violationTypes as $type): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($type['description']); ?></td>
                                                    <td>
                                                        <span class="badge <?php 
                                                            echo $type['severity'] === 'high' ? 'bg-danger' : 
                                                                ($type['severity'] === 'medium' ? 'bg-warning text-dark' : 'bg-info text-dark'); 
                                                        ?>">
                                                            <?php echo ucfirst($type['severity']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary edit-violation-type" 
                                                                data-id="<?php echo $type['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($type['description']); ?>"
                                                                data-severity="<?php echo $type['severity']; ?>"
                                                                data-bs-toggle="modal" data-bs-target="#editTypeModal">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-violation-type"
                                                                data-id="<?php echo $type['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                                data-bs-toggle="modal" data-bs-target="#deleteTypeModal">
                                                            Delete
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
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Add New Violation Type</h5>
                        </div>
                        <div class="card-body">
                            <form action="/admin/save-violation-type" method="post">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           placeholder="e.g., inappropriate_content">
                                    <div class="form-text">Use snake_case for consistency</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required
                                              placeholder="Explain what this violation type covers"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="severity" class="form-label">Severity <span class="text-danger">*</span></label>
                                    <select class="form-select" id="severity" name="severity" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                    <div class="form-text">High severity violations are highlighted for reviewers</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">Add Violation Type</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Help & Guidelines</h5>
                        </div>
                        <div class="card-body">
                            <p>Violation types are used to categorize rejected advertisements. They help:</p>
                            <ul>
                                <li>Standardize rejection reasons</li>
                                <li>Track common issues</li>
                                <li>Provide clear feedback to advertisers</li>
                                <li>Maintain consistency across reviewers</li>
                            </ul>
                            <p><strong>Best practices:</strong></p>
                            <ul>
                                <li>Use descriptive names</li>
                                <li>Provide clear explanations</li>
                                <li>Set appropriate severity levels</li>
                                <li>Review and update periodically</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Violation Type Modal -->
<div class="modal fade" id="editTypeModal" tabindex="-1" aria-labelledby="editTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/save-violation-type" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editTypeModalLabel">Edit Violation Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-severity" class="form-label">Severity <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit-severity" name="severity" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <input type="hidden" id="edit-id" name="id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Violation Type Modal -->
<div class="modal fade" id="deleteTypeModal" tabindex="-1" aria-labelledby="deleteTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/delete-violation-type" method="post">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTypeModalLabel">Delete Violation Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the following violation type?</p>
                    <p><strong id="delete-type-name"></strong></p>
                    <p class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        This action cannot be undone. Historical references to this violation type will remain in the database.
                    </p>
                    
                    <input type="hidden" id="delete-id" name="id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set up edit modal
        const editButtons = document.querySelectorAll('.edit-violation-type');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit-id').value = this.dataset.id;
                document.getElementById('edit-name').value = this.dataset.name;
                document.getElementById('edit-description').value = this.dataset.description;
                document.getElementById('edit-severity').value = this.dataset.severity;
            });
        });
        
        // Set up delete modal
        const deleteButtons = document.querySelectorAll('.delete-violation-type');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('delete-id').value = this.dataset.id;
                document.getElementById('delete-type-name').textContent = this.dataset.name;
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../layout/admin_footer.php'; ?> 