<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Models/Ad.php';

$userModel = new \App\Models\User();
$adModel = new \App\Models\Ad();

// Get pending ads
$pendingAds = $adModel->listPendingAds();

// Get system stats
$stats = $adModel->db->query(
    "SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'advertiser') as advertiser_count,
        (SELECT COUNT(*) FROM users WHERE role = 'publisher') as publisher_count,
        (SELECT COUNT(*) FROM ads WHERE status = 'approved') as active_ads,
        (SELECT COUNT(*) FROM ad_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as views_24h,
        (SELECT SUM(cost) FROM ad_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as revenue_24h"
)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VertoAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-card h3 {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .stats-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
            margin: 10px 0;
        }
        
        .ad-preview {
            max-width: 300px;
            max-height: 200px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">VertoAD Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="/admin/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/users">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/ads">Ads</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/reports">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/logout">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>Advertisers</h3>
                    <div class="value"><?php echo number_format($stats['advertiser_count']); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>Publishers</h3>
                    <div class="value"><?php echo number_format($stats['publisher_count']); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>Active Ads</h3>
                    <div class="value"><?php echo number_format($stats['active_ads']); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>24h Revenue</h3>
                    <div class="value">$<?php echo number_format($stats['revenue_24h'], 2); ?></div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Pending Ads</h5>
                        <?php if (empty($pendingAds)): ?>
                            <p class="text-muted">No ads pending review.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Advertiser</th>
                                            <th>Title</th>
                                            <th>Preview</th>
                                            <th>Budget</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingAds as $ad): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ad['id']); ?></td>
                                                <td><?php echo htmlspecialchars($ad['advertiser_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ad['title']); ?></td>
                                                <td>
                                                    <div class="ad-preview">
                                                        <?php echo $ad['content']; ?>
                                                    </div>
                                                </td>
                                                <td>$<?php echo number_format($ad['budget'], 2); ?></td>
                                                <td>
                                                    <button class="btn btn-success btn-sm" onclick="approveAd(<?php echo $ad['id']; ?>)">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="rejectAd(<?php echo $ad['id']; ?>)">
                                                        <i class="fas fa-times"></i> Reject
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
        </div>
    </div>

    <script>
        function approveAd(id) {
            if (confirm('Are you sure you want to approve this ad?')) {
                fetch(`/api/ads/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.message) {
                        alert('Ad approved successfully');
                        location.reload();
                    } else {
                        alert('Error approving ad');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error approving ad');
                });
            }
        }

        function rejectAd(id) {
            const reason = prompt('Please enter a reason for rejection:');
            if (reason) {
                fetch(`/api/ads/${id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason: reason })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.message) {
                        alert('Ad rejected successfully');
                        location.reload();
                    } else {
                        alert('Error rejecting ad');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error rejecting ad');
                });
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 