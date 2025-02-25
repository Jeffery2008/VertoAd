<?php include __DIR__ . '/../includes/admin_header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Error Categories</h1>
        <div>
            <a href="/admin/errors/dashboard" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar me-2"></i>Dashboard
            </a>
            <a href="/admin/errors" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-list me-2"></i>Error Logs
            </a>
            <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
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
            <!-- Categories Table Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tags me-1"></i>
                    Error Categories
                </div>
                <div class="card-body p-0">
                    <?php if (empty($categories)): ?>
                        <div class="alert alert-info m-3 mb-0">
                            No error categories found. Create your first category to help classify errors.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Default Severity</th>
                                        <th>Auto-Assign Pattern</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($category['name']) ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($category['description']) ?>">
                                                    <?= htmlspecialchars($category['description']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $severityClass = '';
                                                switch ($category['default_severity']) {
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
                                                    <?= ucfirst(htmlspecialchars($category['default_severity'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($category['auto_assign_pattern'])): ?>
                                                    <code><?= htmlspecialchars($category['auto_assign_pattern']) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-category" 
                                                        data-id="<?= $category['id'] ?>"
                                                        data-name="<?= htmlspecialchars($category['name']) ?>"
                                                        data-description="<?= htmlspecialchars($category['description']) ?>"
                                                        data-severity="<?= htmlspecialchars($category['default_severity']) ?>"
                                                        data-pattern="<?= htmlspecialchars($category['auto_assign_pattern'] ?? '') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-category" 
                                                        data-id="<?= $category['id'] ?>"
                                                        data-name="<?= htmlspecialchars($category['name']) ?>">
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
        </div>
        
        <div class="col-lg-4">
            <!-- Help Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-question-circle me-1"></i>
                    About Error Categories
                </div>
                <div class="card-body">
                    <h5>What are Error Categories?</h5>
                    <p>Error categories help you classify and organize errors in your system. Each category can have:</p>
                    <ul>
                        <li><strong>Name:</strong> A descriptive name for the category</li>
                        <li><strong>Description:</strong> Details about what kinds of errors belong in this category</li>
                        <li><strong>Default Severity:</strong> The default severity level for errors in this category</li>
                        <li><strong>Auto-Assign Pattern:</strong> A pattern that automatically assigns errors to this category</li>
                    </ul>
                    
                    <h5>Auto-Assign Patterns</h5>
                    <p>Auto-assign patterns use simple text matching to categorize errors automatically. For example:</p>
                    <ul>
                        <li><code>PDOException</code> - Matches any error containing "PDOException"</li>
                        <li><code>auth|login|permission</code> - Matches errors containing any of these words</li>
                        <li><code>^PHP Fatal</code> - Matches errors starting with "PHP Fatal"</li>
                    </ul>
                    <p class="mb-0">Patterns are checked in order, and the first match is used.</p>
                </div>
            </div>
            
            <!-- Best Practices Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-lightbulb me-1"></i>
                    Best Practices
                </div>
                <div class="card-body">
                    <h5>Creating Effective Categories</h5>
                    <ul>
                        <li>Use meaningful names that describe the type of error</li>
                        <li>Keep the number of categories manageable (10-15 max)</li>
                        <li>Make categories distinct with minimal overlap</li>
                        <li>Set appropriate default severity levels for each category</li>
                        <li>Use specific auto-assign patterns to reduce manual categorization</li>
                    </ul>
                    
                    <h5>Recommended Base Categories</h5>
                    <ul>
                        <li>Database Errors</li>
                        <li>Authentication & Authorization</li>
                        <li>API Errors</li>
                        <li>Frontend/JavaScript Errors</li>
                        <li>Performance Issues</li>
                        <li>Security Concerns</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/categories" method="post">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add Error Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="form-text">Choose a concise, descriptive name</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        <div class="form-text">Describe what types of errors belong in this category</div>
                    </div>
                    <div class="mb-3">
                        <label for="default_severity" class="form-label">Default Severity</label>
                        <select class="form-select" id="default_severity" name="default_severity" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <div class="form-text">Default severity level for errors in this category</div>
                    </div>
                    <div class="mb-3">
                        <label for="auto_assign_pattern" class="form-label">Auto-Assign Pattern (optional)</label>
                        <input type="text" class="form-control" id="auto_assign_pattern" name="auto_assign_pattern">
                        <div class="form-text">Pattern to automatically assign errors to this category</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/categories" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Error Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-default-severity" class="form-label">Default Severity</label>
                        <select class="form-select" id="edit-default-severity" name="default_severity" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-auto-assign-pattern" class="form-label">Auto-Assign Pattern (optional)</label>
                        <input type="text" class="form-control" id="edit-auto-assign-pattern" name="auto_assign_pattern">
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

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/errors/categories" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Error Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category "<span id="delete-name"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. Existing errors in this category will remain but will no longer be categorized.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit category buttons
    const editCategoryButtons = document.querySelectorAll('.edit-category');
    const editCategoryModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    
    editCategoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const severity = this.getAttribute('data-severity');
            const pattern = this.getAttribute('data-pattern');
            
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-description').value = description;
            document.getElementById('edit-default-severity').value = severity;
            document.getElementById('edit-auto-assign-pattern').value = pattern;
            
            editCategoryModal.show();
        });
    });
    
    // Handle delete category buttons
    const deleteCategoryButtons = document.querySelectorAll('.delete-category');
    const deleteCategoryModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
    
    deleteCategoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-name').textContent = name;
            
            deleteCategoryModal.show();
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?> 