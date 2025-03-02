<!DOCTYPE html>
<html>
<head>
    <title>Publisher Statistics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        h1, h2 {
            color: #333;
        }
        .nav-links {
            margin-bottom: 20px;
        }
        .nav-links a {
            margin-right: 15px;
            color: #666;
            text-decoration: none;
        }
        .nav-links a:hover {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            color: #333;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stats-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stats-card h3 {
            margin: 0;
            color: #666;
            font-size: 16px;
        }
        .stats-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Publisher Statistics</h1>
        </div>

        <div class="nav-links">
            <a href="/publisher/dashboard">Dashboard</a>
            <a href="/publisher/stats">Statistics</a>
            <a href="/admin/logout">Logout</a>
        </div>

        <div class="stats-summary">
            <div class="stats-card">
                <h3>Total Views</h3>
                <div class="value"><?php echo number_format(array_sum(array_column($views, 'total_views') ?? [0])); ?></div>
            </div>
            <div class="stats-card">
                <h3>Total Earnings</h3>
                <div class="value">$<?php echo number_format(array_sum(array_column($views, 'total_cost') ?? [0]), 2); ?></div>
            </div>
        </div>

        <h2>Recent Views</h2>
        <?php if (!empty($views)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Ad ID</th>
                        <th>Viewer IP</th>
                        <th>Cost</th>
                        <th>Viewed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($views as $view): ?>
                        <tr>
                            <td><?php echo $view['ad_id']; ?></td>
                            <td><?php echo htmlspecialchars($view['viewer_ip']); ?></td>
                            <td>$<?php echo number_format($view['cost'], 2); ?></td>
                            <td><?php echo $view['viewed_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No views recorded yet.</p>
        <?php endif; ?>
    </div>
</body>
</html> 