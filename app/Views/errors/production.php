<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统错误</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #343a40;
        }
        .error-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        h1 {
            margin-top: 0;
            font-size: 1.8rem;
            color: #343a40;
        }
        p {
            margin-bottom: 1.5rem;
            line-height: 1.6;
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0069d9;
        }
        .error-code {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>很抱歉，发生了错误</h1>
        <p>系统遇到了意外问题，已自动记录此错误，我们的技术团队将尽快解决。</p>
        <p>请尝试刷新页面，或稍后再试。</p>
        <a href="/" class="btn">返回首页</a>
        <?php if (isset($errorId)): ?>
            <div class="error-code">错误参考码: <?php echo $errorId; ?></div>
        <?php endif; ?>
    </div>
</body>
</html> 