<?php require_once 'templates/partials/header.php'; ?>
<?php require_once 'templates/partials/advertiser_sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Audience Segments</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/advertiser/dashboard">Home</a></li>
                        <li class="breadcrumb-item active">Audience Segments</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['flash'])) : ?>
                <div class="alert alert-<?= $_SESSION['flash']['type'] ?>">
                    <?= $_SESSION['flash']['message'] ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Manage Audience Segments</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#segmentModal">
                                    <i class="fas fa-plus"></i> Create New Segment
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($segments)) : ?>
                                <div class="alert alert-info">
                                    <p>You haven't created any audience segments yet. Segments allow you to group visitors based on behavior, demographics, or other attributes for better ad targeting.</p>
                                    <p>Click the "Create New Segment" button to get started.</p>
                                </div>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th style="width: 5%">ID</th>
                                                <th style="width: 20%">Name</th>
                                                <th style="width: 30%">Description</th>
                                                <th style="width: 15%">Type</th>
                                                <th style="width: 10%">Members</th>
                                                <th style="width: 10%">Created</th>
                                                <th style="width: 10%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($segments as $segment) : ?>
                                                <tr>
                                                    <td><?= $segment['id'] ?></td>
                                                    <td><?= htmlspecialchars($segment['name']) ?></td>
                                                    <td><?= htmlspecialchars($segment['description'] ?? '') ?></td>
                                                    <td>
                                                        <?php if ($segment['is_dynamic']) : ?>
                                                            <span class="badge badge-primary">Dynamic</span>
                                                            <div class="small text-muted mt-1">
                                                                <?= htmlspecialchars($segment['criteria_summary']) ?>
                                                            </div>
                                                        <?php else : ?>
                                                            <span class="badge badge-secondary">Static</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="/advertiser/segment-members/<?= $segment['id'] ?>">
                                                            <?= number_format($segment['member_count']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($segment['created_at'])) ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-primary edit-segment" 
                                                                    data-id="<?= $segment['id'] ?>"
                                                                    data-name="<?= htmlspecialchars($segment['name']) ?>"
                                                                    data-description="<?= htmlspecialchars($segment['description'] ?? '') ?>"
                                                                    data-isdynamic="<?= $segment['is_dynamic'] ?>"
                                                                    data-criteria='<?= htmlspecialchars(json_encode(json_decode($segment['criteria'], true))) ?>'>
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <a href="/advertiser/delete-segment/<?= $segment['id'] ?>" class="btn btn-sm btn-danger delete-confirm">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
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
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">About Audience Segments</h3>
                        </div>
                        <div class="card-body">
                            <h5>What are audience segments?</h5>
                            <p>Audience segments are groups of visitors who share specific traits or behaviors. By creating segments, you can:</p>
                            <ul>
                                <li>Target ads to specific audience groups</li>
                                <li>Compare performance across different audience segments</li>
                                <li>Better understand your visitor demographics</li>
                                <li>Create personalized ad experiences</li>
                            </ul>

                            <h5>Types of Segments</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="callout callout-primary">
                                        <h6>Dynamic Segments</h6>
                                        <p>Automatically include visitors who match specific criteria. As visitors browse your site, they'll be added or removed from the segment based on these rules.</p>
                                        <p><strong>Example:</strong> All visitors from the United States who have visited more than 3 times.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="callout callout-secondary">
                                        <h6>Static Segments</h6>
                                        <p>Manually defined groups of visitors. You'll need to add visitors to these segments through the API or other integrations.</p>
                                        <p><strong>Example:</strong> A list of specific visitors imported from your CRM system.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Segment Modal -->
<div class="modal fade" id="segmentModal" tabindex="-1" role="dialog" aria-labelledby="segmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="/advertiser/save-segment" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="segmentModalLabel">Create New Segment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="segment_id">
                    
                    <div class="form-group">
                        <label for="segment_name">Segment Name*</label>
                        <input type="text" class="form-control" id="segment_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="segment_description">Description</label>
                        <textarea class="form-control" id="segment_description" name="description" rows="2"></textarea>
                        <small class="form-text text-muted">Provide a brief description of this segment's purpose.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Segment Type</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="type_static" name="is_dynamic" value="0" class="custom-control-input" checked>
                            <label class="custom-control-label" for="type_static">Static Segment</label>
                            <small class="form-text text-muted">Manually add specific visitors to this segment.</small>
                        </div>
                        <div class="custom-control custom-radio mt-2">
                            <input type="radio" id="type_dynamic" name="is_dynamic" value="1" class="custom-control-input">
                            <label class="custom-control-label" for="type_dynamic">Dynamic Segment</label>
                            <small class="form-text text-muted">Automatically include visitors who match specific criteria.</small>
                        </div>
                    </div>
                    
                    <div id="criteria_container" style="display: none;">
                        <hr>
                        <h5>Segment Criteria</h5>
                        <p class="text-muted">Define rules for including visitors in this segment. Visitors must match ALL criteria to be included.</p>
                        
                        <div id="criteria_rows">
                            <div class="criteria-row row mb-2">
                                <div class="col-md-3">
                                    <select class="form-control" name="criteria_field[]">
                                        <option value="">Select Field</option>
                                        <optgroup label="Demographics">
                                            <option value="geo_country">Country</option>
                                            <option value="geo_region">Region/State</option>
                                            <option value="geo_city">City</option>
                                            <option value="language">Language</option>
                                        </optgroup>
                                        <optgroup label="Technology">
                                            <option value="device_type">Device Type</option>
                                            <option value="browser">Browser</option>
                                            <option value="os">Operating System</option>
                                        </optgroup>
                                        <optgroup label="Behavior">
                                            <option value="visit_count">Visit Count</option>
                                            <option value="total_page_views">Total Page Views</option>
                                            <option value="first_seen">First Seen Date</option>
                                            <option value="last_seen">Last Seen Date</option>
                                        </optgroup>
                                        <optgroup label="Traffic Source">
                                            <option value="first_referrer">First Referrer</option>
                                            <option value="last_referrer">Last Referrer</option>
                                            <option value="first_utm_source">First UTM Source</option>
                                            <option value="first_utm_medium">First UTM Medium</option>
                                            <option value="first_utm_campaign">First UTM Campaign</option>
                                            <option value="last_utm_source">Last UTM Source</option>
                                            <option value="last_utm_medium">Last UTM Medium</option>
                                            <option value="last_utm_campaign">Last UTM Campaign</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-control" name="criteria_operator[]">
                                        <option value="">Select Operator</option>
                                        <option value="equals">Equals</option>
                                        <option value="not_equals">Not Equals</option>
                                        <option value="contains">Contains</option>
                                        <option value="starts_with">Starts With</option>
                                        <option value="greater_than">Greater Than</option>
                                        <option value="less_than">Less Than</option>
                                        <option value="between">Between</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="row">
                                        <div class="col value-container">
                                            <input type="text" class="form-control" name="criteria_value[]" placeholder="Value">
                                        </div>
                                        <div class="col value2-container" style="display: none;">
                                            <input type="text" class="form-control" name="criteria_value2[]" placeholder="Second Value">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-danger remove-criteria"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="add_criteria" class="btn btn-sm btn-secondary mt-2">
                            <i class="fas fa-plus"></i> Add Another Criterion
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Segment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this segment? This action cannot be undone.</p>
                <p>Any ads currently targeting this segment will no longer be able to target these visitors.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmDelete">Delete Segment</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle criteria container based on segment type
    $('input[name="is_dynamic"]').change(function() {
        if ($(this).val() == '1') {
            $('#criteria_container').show();
        } else {
            $('#criteria_container').hide();
        }
    });
    
    // Add new criteria row
    $('#add_criteria').click(function() {
        var newRow = $('.criteria-row').first().clone();
        newRow.find('input, select').val('');
        newRow.find('.value2-container').hide();
        $('#criteria_rows').append(newRow);
    });
    
    // Remove criteria row
    $(document).on('click', '.remove-criteria', function() {
        if ($('.criteria-row').length > 1) {
            $(this).closest('.criteria-row').remove();
        } else {
            $(this).closest('.criteria-row').find('input, select').val('');
        }
    });
    
    // Show/hide second value field based on operator
    $(document).on('change', 'select[name="criteria_operator[]"]', function() {
        var value2Container = $(this).closest('.criteria-row').find('.value2-container');
        if ($(this).val() === 'between') {
            value2Container.show();
        } else {
            value2Container.hide();
        }
    });
    
    // Edit segment button
    $('.edit-segment').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var description = $(this).data('description');
        var isDynamic = $(this).data('isdynamic');
        var criteria = $(this).data('criteria');
        
        $('#segmentModalLabel').text('Edit Segment');
        $('#segment_id').val(id);
        $('#segment_name').val(name);
        $('#segment_description').val(description);
        
        if (isDynamic == 1) {
            $('#type_dynamic').prop('checked', true);
            $('#criteria_container').show();
            
            // Clear existing criteria rows except the first one
            $('.criteria-row:not(:first)').remove();
            $('.criteria-row:first').find('input, select').val('');
            $('.value2-container').hide();
            
            // Add criteria rows
            if (criteria && criteria.length > 0) {
                for (var i = 0; i < criteria.length; i++) {
                    if (i > 0) {
                        $('#add_criteria').click();
                    }
                    
                    var criteriaRow = $('.criteria-row').eq(i);
                    criteriaRow.find('select[name="criteria_field[]"]').val(criteria[i].field);
                    criteriaRow.find('select[name="criteria_operator[]"]').val(criteria[i].operator);
                    criteriaRow.find('input[name="criteria_value[]"]').val(criteria[i].value);
                    
                    if (criteria[i].operator === 'between' && criteria[i].value2 !== undefined) {
                        criteriaRow.find('.value2-container').show();
                        criteriaRow.find('input[name="criteria_value2[]"]').val(criteria[i].value2);
                    }
                }
            }
        } else {
            $('#type_static').prop('checked', true);
            $('#criteria_container').hide();
        }
        
        $('#segmentModal').modal('show');
    });
    
    // Delete confirmation
    $('.delete-confirm').click(function(e) {
        e.preventDefault();
        var deleteUrl = $(this).attr('href');
        $('#confirmDelete').attr('href', deleteUrl);
        $('#deleteModal').modal('show');
    });
    
    // Reset modal when closed
    $('#segmentModal').on('hidden.bs.modal', function() {
        $('#segmentModalLabel').text('Create New Segment');
        $('#segment_id').val('');
        $('#segment_name').val('');
        $('#segment_description').val('');
        $('#type_static').prop('checked', true);
        $('#criteria_container').hide();
        
        // Clear existing criteria rows except the first one
        $('.criteria-row:not(:first)').remove();
        $('.criteria-row:first').find('input, select').val('');
        $('.value2-container').hide();
    });
});
</script>

<?php require_once 'templates/partials/footer.php'; ?> 