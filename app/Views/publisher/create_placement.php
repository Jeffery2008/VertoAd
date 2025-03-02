<!DOCTYPE html>
<html>
<head>
    <title>Create Ad Placement</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
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
        h1 {
            color: #333;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .button:hover {
            background: #45a049;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Create New Ad Placement</h1>
        </div>

        <div class="nav-links">
            <a href="/publisher/dashboard">Dashboard</a>
            <a href="/publisher/stats">Statistics</a>
            <a href="/admin/logout">Logout</a>
        </div>

        <form method="POST" action="/publisher/create-placement">
            <div class="form-group">
                <label for="name">Placement Name:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="width">Width (pixels):</label>
                <input type="number" id="width" name="width" required min="1">
            </div>

            <div class="form-group">
                <label for="height">Height (pixels):</label>
                <input type="number" id="height" name="height" required min="1">
            </div>

            <button type="submit" class="button">Create Placement</button>
        </form>
    </div>
</body>
</html> 