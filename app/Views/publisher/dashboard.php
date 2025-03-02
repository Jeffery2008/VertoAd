<!DOCTYPE html>
<html>
<head>
    <title>Publisher Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background: #45a049;
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
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
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
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Publisher Dashboard</h1>
            <a href="/publisher/create-placement" class="button">Create New Placement</a>
        </div>

        <div class="nav-links">
            <a href="/publisher/dashboard">Dashboard</a>
            <a href="/publisher/stats">Statistics</a>
            <a href="/admin/logout">Logout</a>
        </div>

        <h2>Your Ad Placements</h2>
        <?php if (!empty($placements)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($placements as $placement): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($placement['name']); ?></td>
                            <td>
                                <textarea readonly rows="2"><?php echo htmlspecialchars($placement['code']); ?></textarea>
                            </td>
                            <td><?php echo $placement['created_at']; ?></td>
                            <td>
                                <a href="/publisher/placement/<?php echo $placement['id']; ?>/stats" class="button">View Stats</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No ad placements yet. Create your first one!</p>
                <a href="/publisher/create-placement" class="button">Create Ad Placement</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 