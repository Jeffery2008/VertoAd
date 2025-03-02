<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>管理后台 - 错误管理</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: "Microsoft YaHei", Arial, sans-serif;
      line-height: 1.6;
      margin: 0;
      padding: 0;
      background-color: #f8f9fa;
    }
    .sidebar {
      background-color: #343a40;
      color: #fff;
      height: 100vh;
      position: fixed;
      width: 250px;
      padding-top: 20px;
      left: 0;
      top: 0;
      z-index: 100;
    }
    .sidebar a {
      color: #fff;
      padding: 10px 15px;
      text-decoration: none;
      display: block;
      font-size: 16px;
    }
    .sidebar a:hover {
      background-color: #495057;
    }
    .main-content {
      margin-left: 250px;
      padding: 20px;
      width: calc(100% - 250px);
    }
    .active {
      background-color: #007bff;
    }
    .nav-item {
      margin-bottom: 5px;
    }
    .card {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      margin-bottom: 20px;
    }
    .alert {
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3 class="text-center mb-4">Admin Panel</h3>
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link" href="/admin/dashboard">Dashboard</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/admin/errors">Error Management</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/admin/users">User Management</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/admin/settings">System Settings</a>
      </li>
      <li class="nav-item mt-5">
        <a class="nav-link" href="/logout">Logout</a>
      </li>
    </ul>
  </div>
  
  <div class="main-content">
    <!-- 主要内容区域 -->
  </div>
</body>
</html> 