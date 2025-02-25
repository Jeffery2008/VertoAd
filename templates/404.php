<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.5rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <div class="error-message">Page Not Found</div>
        <p class="text-muted">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <a href="/" class="btn btn-primary mt-3">Go to Homepage</a>
    </div>
</body>
</html> 