<?php require_once 'templates/partials/header.php'; ?>
<?php require_once 'templates/partials/advertiser_sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Segment Members: <?= htmlspecialchars($segment['name']) ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/advertiser/dashboard">Home</a></li>
                        <li class="breadcrumb-item"><a href="/advertiser/segments">Audience Segments</a></li>
                        <li class="breadcrumb-item active">Segment Members</li>
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
                            <h3 class="card-title">Segment Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <dl class="row">
                                        <dt class="col-sm-3">Name:</dt>
                                        <dd class="col-sm-9"><?= htmlspecialchars($segment['name']) ?></dd>
                                        
                                        <dt class="col-sm-3">Description:</dt>
                                        <dd class="col-sm-9"><?= htmlspecialchars($segment['description'] ?? '') ?></dd>
                                        
                                        <dt class="col-sm-3">Type:</dt>
                                        <dd class="col-sm-9">
                                            <?php if ($segment['is_dynamic']) : ?>
                                                <span class="badge badge-primary">Dynamic</span>
                                                <div class="small text-muted mt-1">
                                                    Criteria: <?= htmlspecialchars(json_decode($segment['criteria_summary'], true)) ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="badge badge-secondary">Static</span>
                                            <?php endif; ?>
                                        </dd>
                                        
                                        <dt class="col-sm-3">Created:</dt>
                                        <dd class="col-sm-9"><?= date('F j, Y, g:i a', strtotime($segment['created_at'])) ?></dd>
                                    </dl>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-box bg-light">
                                        <div class="info-box-content">
                                            <span class="info-box-text text-center text-muted">Total Members</span>
                                            <span class="info-box-number text-center text-muted mb-0"><?= number_format($totalCount) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($segment['is_dynamic']) : ?>
                                        <div class="text-center mt-3">
                                            <form method="post" action="/advertiser/update-segment-members/<?= $segment['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-sync-alt"></i> Update Members
                                                </button>
                                            </form>
                                            <div class="text-muted small mt-1">
                                                Refreshes members list based on current criteria
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Segment Members (Visitors)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($members)) : ?>
                                <div class="alert alert-info">
                                    <p>This segment doesn't have any members yet.</p>
                                    <?php if ($segment['is_dynamic']) : ?>
                                        <p>As visitors browse your site and match the defined criteria, they'll automatically be added to this segment.</p>
                                    <?php else : ?>
                                        <p>You can add members to this segment using the API or import functionality.</p>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Visitor ID</th>
                                                <th>First Visit</th>
                                                <th>Last Visit</th>
                                                <th>Location</th>
                                                <th>Device</th>
                                                <th>Visits</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($members as $member) : ?>
                                                <tr>
                                                    <td>
                                                        <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?= htmlspecialchars($member['visitor_id']) ?>">
                                                            <?= htmlspecialchars(substr($member['visitor_id'], 0, 8)) ?>...
                                                        </span>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($member['first_seen'])) ?></td>
                                                    <td><?= date('M j, Y', strtotime($member['last_seen'])) ?></td>
                                                    <td>
                                                        <?php 
                                                            $location = [];
                                                            if (!empty($member['geo_city'])) $location[] = $member['geo_city'];
                                                            if (!empty($member['geo_region'])) $location[] = $member['geo_region'];
                                                            if (!empty($member['geo_country'])) $location[] = $member['geo_country'];
                                                            echo !empty($location) ? htmlspecialchars(implode(', ', $location)) : 'Unknown';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($member['device_type'])) : ?>
                                                            <span class="badge badge-info"><?= htmlspecialchars(ucfirst($member['device_type'])) ?></span>
                                                        <?php else : ?>
                                                            <span class="badge badge-secondary">Unknown</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($member['browser'])) : ?>
                                                            <span class="badge badge-light"><?= htmlspecialchars($member['browser']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= number_format($member['visit_count']) ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-info view-visitor-profile" 
                                                                    data-visitor-id="<?= htmlspecialchars($member['visitor_id']) ?>"
                                                                    data-toggle="modal" 
                                                                    data-target="#visitorProfileModal">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <?php if (!$segment['is_dynamic']) : ?>
                                                                <a href="/advertiser/remove-segment-member/<?= $segment['id'] ?>/<?= htmlspecialchars($member['visitor_id']) ?>" 
                                                                   class="btn btn-sm btn-danger remove-confirm">
                                                                    <i class="fas fa-times"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($totalPages > 1) : ?>
                                    <div class="d-flex justify-content-center mt-4">
                                        <ul class="pagination">
                                            <?php if ($page > 1) : ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                
                                                if ($startPage > 1) {
                                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                                    if ($startPage > 2) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                }
                                                
                                                for ($i = $startPage; $i <= $endPage; $i++) {
                                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                            <a class="page-link" href="?page=' . $i . '">' . $i . '</a>
                                                          </li>';
                                                }
                                                
                                                if ($endPage < $totalPages) {
                                                    if ($endPage < $totalPages - 1) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                                }
                                            ?>
                                            
                                            <?php if ($page < $totalPages) : ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Visitor Profile Modal -->
<div class="modal fade" id="visitorProfileModal" tabindex="-1" role="dialog" aria-labelledby="visitorProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorProfileModalLabel">Visitor Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center py-3" id="visitorProfileLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading visitor profile...</p>
                </div>
                <div id="visitorProfileContent" style="display: none;">
                    <ul class="nav nav-tabs" id="visitorProfileTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true">Overview</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="events-tab" data-toggle="tab" href="#events" role="tab" aria-controls="events" aria-selected="false">Activity</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="segments-tab" data-toggle="tab" href="#segments" role="tab" aria-controls="segments" aria-selected="false">Segments</a>
                        </li>
                    </ul>
                    <div class="tab-content p-3" id="visitorProfileTabContent">
                        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Visitor Details</h6>
                                    <dl class="row">
                                        <dt class="col-sm-4">Visitor ID:</dt>
                                        <dd class="col-sm-8" id="profile-visitor-id"></dd>
                                        
                                        <dt class="col-sm-4">First Seen:</dt>
                                        <dd class="col-sm-8" id="profile-first-seen"></dd>
                                        
                                        <dt class="col-sm-4">Last Seen:</dt>
                                        <dd class="col-sm-8" id="profile-last-seen"></dd>
                                        
                                        <dt class="col-sm-4">Total Visits:</dt>
                                        <dd class="col-sm-8" id="profile-visit-count"></dd>
                                        
                                        <dt class="col-sm-4">Page Views:</dt>
                                        <dd class="col-sm-8" id="profile-page-views"></dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <h6>Technical Details</h6>
                                    <dl class="row">
                                        <dt class="col-sm-4">Device:</dt>
                                        <dd class="col-sm-8" id="profile-device"></dd>
                                        
                                        <dt class="col-sm-4">Browser:</dt>
                                        <dd class="col-sm-8" id="profile-browser"></dd>
                                        
                                        <dt class="col-sm-4">OS:</dt>
                                        <dd class="col-sm-8" id="profile-os"></dd>
                                        
                                        <dt class="col-sm-4">Language:</dt>
                                        <dd class="col-sm-8" id="profile-language"></dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Location</h6>
                                    <dl class="row">
                                        <dt class="col-sm-4">Country:</dt>
                                        <dd class="col-sm-8" id="profile-country"></dd>
                                        
                                        <dt class="col-sm-4">Region:</dt>
                                        <dd class="col-sm-8" id="profile-region"></dd>
                                        
                                        <dt class="col-sm-4">City:</dt>
                                        <dd class="col-sm-8" id="profile-city"></dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <h6>Traffic Source</h6>
                                    <dl class="row">
                                        <dt class="col-sm-4">First Referrer:</dt>
                                        <dd class="col-sm-8 text-truncate" id="profile-first-referrer"></dd>
                                        
                                        <dt class="col-sm-4">Last Referrer:</dt>
                                        <dd class="col-sm-8 text-truncate" id="profile-last-referrer"></dd>
                                        
                                        <dt class="col-sm-4">UTM Source:</dt>
                                        <dd class="col-sm-8" id="profile-utm-source"></dd>
                                        
                                        <dt class="col-sm-4">UTM Medium:</dt>
                                        <dd class="col-sm-8" id="profile-utm-medium"></dd>
                                        
                                        <dt class="col-sm-4">UTM Campaign:</dt>
                                        <dd class="col-sm-8" id="profile-utm-campaign"></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="events" role="tabpanel" aria-labelledby="events-tab">
                            <div class="text-center py-3" id="eventsLoading">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Loading visitor activity...</p>
                            </div>
                            <div id="eventsContent" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Event</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody id="events-table-body">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="segments" role="tabpanel" aria-labelledby="segments-tab">
                            <div class="text-center py-3" id="segmentsLoading">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Loading visitor segments...</p>
                            </div>
                            <div id="segmentsContent" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Segment Name</th>
                                                <th>Type</th>
                                                <th>Added On</th>
                                            </tr>
                                        </thead>
                                        <tbody id="segments-table-body">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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

<!-- Remove Confirmation Modal -->
<div class="modal fade" id="removeModal" tabindex="-1" role="dialog" aria-labelledby="removeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeModalLabel">Confirm Removal</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this visitor from the segment?</p>
                <p>This action cannot be undone, but the visitor can be added again later.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmRemove">Remove</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load visitor profile when modal is opened
    $('.view-visitor-profile').click(function() {
        var visitorId = $(this).data('visitor-id');
        
        // Reset modal content
        $('#visitorProfileContent').hide();
        $('#visitorProfileLoading').show();
        $('#eventsContent').hide();
        $('#eventsLoading').show();
        $('#segmentsContent').hide();
        $('#segmentsLoading').show();
        
        // Load visitor profile
        $.ajax({
            url: '/api/v1/visitor/' + encodeURIComponent(visitorId),
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    var profile = data.profile;
                    
                    // Fill profile data
                    $('#profile-visitor-id').text(profile.visitor_id);
                    $('#profile-first-seen').text(formatDateTime(profile.first_seen));
                    $('#profile-last-seen').text(formatDateTime(profile.last_seen));
                    $('#profile-visit-count').text(profile.visit_count);
                    $('#profile-page-views').text(profile.total_page_views);
                    
                    $('#profile-device').text(capitalizeFirstLetter(profile.device_type) || 'Unknown');
                    $('#profile-browser').text(profile.browser || 'Unknown');
                    $('#profile-os').text(profile.os || 'Unknown');
                    $('#profile-language').text(profile.language || 'Unknown');
                    
                    $('#profile-country').text(profile.geo_country || 'Unknown');
                    $('#profile-region').text(profile.geo_region || 'Unknown');
                    $('#profile-city').text(profile.geo_city || 'Unknown');
                    
                    $('#profile-first-referrer').text(profile.first_referrer || 'Direct');
                    $('#profile-last-referrer').text(profile.last_referrer || 'Direct');
                    $('#profile-utm-source').text(profile.last_utm_source || 'None');
                    $('#profile-utm-medium').text(profile.last_utm_medium || 'None');
                    $('#profile-utm-campaign').text(profile.last_utm_campaign || 'None');
                    
                    $('#visitorProfileLoading').hide();
                    $('#visitorProfileContent').show();
                    
                    // Load visitor events
                    loadVisitorEvents(visitorId);
                    
                    // Load visitor segments
                    loadVisitorSegments(visitorId);
                } else {
                    $('#visitorProfileLoading').html('<div class="alert alert-danger">Failed to load visitor profile</div>');
                }
            },
            error: function() {
                $('#visitorProfileLoading').html('<div class="alert alert-danger">Failed to load visitor profile</div>');
            }
        });
    });
    
    // Load visitor events
    function loadVisitorEvents(visitorId) {
        $.ajax({
            url: '/api/v1/visitor/' + encodeURIComponent(visitorId) + '/events',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    var events = data.events;
                    var tableBody = $('#events-table-body');
                    tableBody.empty();
                    
                    if (events.length === 0) {
                        tableBody.html('<tr><td colspan="3" class="text-center">No events recorded for this visitor</td></tr>');
                    } else {
                        $.each(events, function(i, event) {
                            var details = '';
                            
                            if (event.event_type === 'page_view') {
                                details = '<span class="text-truncate d-inline-block" style="max-width: 300px;" title="' + event.page_url + '">' + event.page_url + '</span>';
                            } else if (event.event_type === 'conversion') {
                                details = 'Conversion Type: ' + event.conversion_type + (event.value ? (', Value: ' + event.value) : '');
                            } else if (event.event_type === 'click') {
                                details = 'Ad ID: ' + event.ad_id;
                            } else if (event.event_type === 'impression') {
                                details = 'Ad ID: ' + event.ad_id;
                            }
                            
                            var row = '<tr>' +
                                '<td>' + formatDateTime(event.created_at) + '</td>' +
                                '<td><span class="badge badge-' + getEventBadgeClass(event.event_type) + '">' + capitalizeFirstLetter(event.event_type) + '</span></td>' +
                                '<td>' + details + '</td>' +
                                '</tr>';
                            
                            tableBody.append(row);
                        });
                    }
                    
                    $('#eventsLoading').hide();
                    $('#eventsContent').show();
                } else {
                    $('#eventsLoading').html('<div class="alert alert-danger">Failed to load visitor events</div>');
                }
            },
            error: function() {
                $('#eventsLoading').html('<div class="alert alert-danger">Failed to load visitor events</div>');
            }
        });
    }
    
    // Load visitor segments
    function loadVisitorSegments(visitorId) {
        $.ajax({
            url: '/api/v1/visitor/' + encodeURIComponent(visitorId) + '/segments',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    var segments = data.segments;
                    var tableBody = $('#segments-table-body');
                    tableBody.empty();
                    
                    if (segments.length === 0) {
                        tableBody.html('<tr><td colspan="3" class="text-center">Visitor is not in any segments</td></tr>');
                    } else {
                        $.each(segments, function(i, segment) {
                            var row = '<tr>' +
                                '<td>' + segment.name + '</td>' +
                                '<td>' + (segment.is_dynamic ? '<span class="badge badge-primary">Dynamic</span>' : '<span class="badge badge-secondary">Static</span>') + '</td>' +
                                '<td>' + formatDate(segment.added_at) + '</td>' +
                                '</tr>';
                            
                            tableBody.append(row);
                        });
                    }
                    
                    $('#segmentsLoading').hide();
                    $('#segmentsContent').show();
                } else {
                    $('#segmentsLoading').html('<div class="alert alert-danger">Failed to load visitor segments</div>');
                }
            },
            error: function() {
                $('#segmentsLoading').html('<div class="alert alert-danger">Failed to load visitor segments</div>');
            }
        });
    }
    
    // Format date and time
    function formatDateTime(dateString) {
        if (!dateString) return 'Unknown';
        var date = new Date(dateString);
        return date.toLocaleString();
    }
    
    // Format date
    function formatDate(dateString) {
        if (!dateString) return 'Unknown';
        var date = new Date(dateString);
        return date.toLocaleDateString();
    }
    
    // Capitalize first letter
    function capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    
    // Get badge class based on event type
    function getEventBadgeClass(eventType) {
        switch (eventType) {
            case 'page_view':
                return 'info';
            case 'conversion':
                return 'success';
            case 'click':
                return 'primary';
            case 'impression':
                return 'secondary';
            default:
                return 'light';
        }
    }
    
    // Remove confirmation
    $('.remove-confirm').click(function(e) {
        e.preventDefault();
        var removeUrl = $(this).attr('href');
        $('#confirmRemove').attr('href', removeUrl);
        $('#removeModal').modal('show');
    });
});
</script>

<?php require_once 'templates/partials/footer.php'; ?> 