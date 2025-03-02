<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开发环境 - 系统错误</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            color: #343a40;
            line-height: 1.6;
        }
        .error-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin: 0 auto;
            max-width: 1000px;
        }
        .error-type {
            font-size: 1.2rem;
            padding: 8px 12px;
            background-color: #dc3545;
            color: white;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 15px;
        }
        h1 {
            margin-top: 0;
            font-size: 1.8rem;
            color: #343a40;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .error-details {
            margin-bottom: 20px;
        }
        .error-location {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #6c757d;
        }
        .stack-trace {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            overflow-x: auto;
            white-space: pre-wrap;
            font-family: Consolas, Monaco, 'Andale Mono', monospace;
            font-size: 0.9rem;
            border-left: 4px solid #007bff;
        }
        .request-data, .environment {
            margin-top: 30px;
        }
        .card {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .card-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        table th {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-type"><?php echo get_class($exception); ?></div>
        <h1><?php echo htmlspecialchars($exception->getMessage()); ?></h1>
        
        <div class="error-details">
            <div class="error-location">
                <strong>文件:</strong> <?php echo $exception->getFile(); ?> <br>
                <strong>行号:</strong> <?php echo $exception->getLine(); ?>
            </div>
            
            <h2>堆栈跟踪</h2>
            <div class="stack-trace"><?php echo htmlspecialchars($exception->getTraceAsString()); ?></div>
        </div>
        
        <div class="request-data">
            <h2>请求数据</h2>
            
            <?php if (!empty($_GET)): ?>
                <div class="card">
                    <div class="card-title">GET 参数</div>
                    <table>
                        <tr>
                            <th>键</th>
                            <th>值</th>
                        </tr>
                        <?php foreach ($_GET as $key => $value): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key); ?></td>
                                <td><?php echo is_array($value) ? 'Array' : htmlspecialchars($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($_POST)): ?>
                <div class="card">
                    <div class="card-title">POST 参数</div>
                    <table>
                        <tr>
                            <th>键</th>
                            <th>值</th>
                        </tr>
                        <?php foreach ($_POST as $key => $value): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key); ?></td>
                                <td><?php echo is_array($value) ? 'Array' : htmlspecialchars($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($_COOKIE)): ?>
                <div class="card">
                    <div class="card-title">COOKIE 数据</div>
                    <table>
                        <tr>
                            <th>键</th>
                            <th>值</th>
                        </tr>
                        <?php foreach ($_COOKIE as $key => $value): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key); ?></td>
                                <td><?php echo htmlspecialchars($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="environment">
            <h2>环境信息</h2>
            <div class="card">
                <div class="card-title">服务器信息</div>
                <table>
                    <tr>
                        <th>名称</th>
                        <th>值</th>
                    </tr>
                    <tr>
                        <td>PHP 版本</td>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td>服务器软件</td>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td>请求方法</td>
                        <td><?php echo $_SERVER['REQUEST_METHOD'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td>请求 URI</td>
                        <td><?php echo $_SERVER['REQUEST_URI'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td>HTTP 引用页</td>
                        <td><?php echo $_SERVER['HTTP_REFERER'] ?? 'None'; ?></td>
                    </tr>
                    <tr>
                        <td>用户代理</td>
                        <td><?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td>客户端 IP</td>
                        <td><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 