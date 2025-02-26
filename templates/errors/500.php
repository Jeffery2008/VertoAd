<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - 服务器错误 | VertoAD</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }
        .error-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #e74c3c;
            margin: 0;
        }
        .error-message {
            font-size: 24px;
            margin: 1rem 0 2rem;
        }
        .error-details {
            color: #666;
            margin-bottom: 2rem;
        }
        .back-link {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #2980b9;
        }
        .error-stack {
            text-align: left;
            background-color: #f9f9f9;
            padding: 1rem;
            border-radius: 4px;
            font-family: monospace;
            overflow: auto;
            max-height: 300px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code">500</h1>
        <h2 class="error-message">服务器错误</h2>
        <p class="error-details">
            抱歉，服务器在处理您的请求时遇到了问题。
            我们的技术团队已经被通知，将尽快解决此问题。
        </p>
        <a href="/" class="back-link">返回首页</a>
        
        <?php if (getenv('APP_ENV') === 'development' && isset($e)): ?>
        <div class="error-stack">
            <h3>错误详情（仅开发环境可见）：</h3>
            <p><?php echo $e->getMessage(); ?></p>
            <pre><?php echo $e->getTraceAsString(); ?></pre>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 