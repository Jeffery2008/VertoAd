<?php
session_start();

// Check if user is logged in and is a publisher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'publisher') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Models/Ad.php';

$userModel = new \App\Models\User();
$adModel = new \App\Models\Ad();

// Get publisher stats
$stats = $adModel->db->query(
    "SELECT 
        DATE(viewed_at) as date,
        COUNT(*) as views,
        SUM(cost) as earnings
    FROM ad_views 
    WHERE publisher_id = ?
        AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY date
    ORDER BY date DESC",
    [$_SESSION['user_id']]
)->fetchAll();

// Calculate totals
$totalViews = 0;
$totalEarnings = 0;
foreach ($stats as $day) {
    $totalViews += $day['views'];
    $totalEarnings += $day['earnings'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publisher Dashboard - VertoAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .code-block {
            background: #272822;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            margin: 10px 0;
        }
        
        .copy-btn {
            float: right;
            background: transparent;
            border: 1px solid #f8f8f2;
            color: #f8f8f2;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .copy-btn:hover {
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">VertoAD Publisher</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="/publisher/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/publisher/sites">My Sites</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/publisher/payments">Payments</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/logout">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="row">
            <div class="col-md-6">
                <div class="stats-card">
                    <h3>Total Views (30 Days)</h3>
                    <div class="value"><?php echo number_format($totalViews); ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card">
                    <h3>Total Earnings (30 Days)</h3>
                    <div class="value">$<?php echo number_format($totalEarnings, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Daily Performance</h5>
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ad Integration</h5>
                        <p>Copy and paste this code where you want ads to appear on your site:</p>
                        
                        <div class="code-block">
                            <button class="copy-btn" onclick="copyCode(this)">Copy</button>
                            &lt;!-- VertoAD Script --&gt;
                            &lt;script src="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/embed.js"&gt;&lt;/script&gt;
                            
                            &lt;!-- Ad Placement --&gt;
                            &lt;div class="verto-ad" data-size="300x250"&gt;&lt;/div&gt;
                        </div>
                        
                        <h6 class="mt-4">Available Ad Sizes:</h6>
                        <ul>
                            <li>Medium Rectangle: 300x250</li>
                            <li>Leaderboard: 728x90</li>
                            <li>Wide Skyscraper: 160x600</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize chart
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column(array_reverse($stats), 'date')); ?>,
                datasets: [
                    {
                        label: 'Views',
                        data: <?php echo json_encode(array_column(array_reverse($stats), 'views')); ?>,
                        borderColor: '#0d6efd',
                        tension: 0.1
                    },
                    {
                        label: 'Earnings ($)',
                        data: <?php echo json_encode(array_column(array_reverse($stats), 'earnings')); ?>,
                        borderColor: '#198754',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Copy code function
        function copyCode(button) {
            const codeBlock = button.parentElement;
            const code = codeBlock.innerText.replace('Copy', '').trim();
            
            navigator.clipboard.writeText(code).then(() => {
                button.innerText = 'Copied!';
                setTimeout(() => {
                    button.innerText = 'Copy';
                }, 2000);
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 