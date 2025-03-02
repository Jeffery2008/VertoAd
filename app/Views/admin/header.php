<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>管理后台</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
  <style>
    body {
      font-family: "Microsoft YaHei", Arial, sans-serif;
      overflow-x: hidden;
      background-color: #f8f9fa;
    }
    
    .wrapper {
      display: flex;
      min-height: 100vh;
    }
    
    .sidebar {
      width: 250px;
      background-color: #343a40;
      color: #fff;
      position: fixed;
      height: 100vh;
      left: 0;
      top: 0;
      z-index: 100;
      padding-top: 20px;
      transition: all 0.3s;
    }
    
    .sidebar h3 {
      padding: 0 15px 20px 15px;
      text-align: center;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar .nav-link {
      padding: 12px 15px;
      color: #fff;
      border-radius: 0;
      margin-bottom: 5px;
    }
    
    .sidebar .nav-link:hover {
      background-color: rgba(255,255,255,0.1);
    }
    
    .sidebar .nav-link.active {
      background-color: #007bff;
    }
    
    .content {
      width: calc(100% - 250px);
      margin-left: 250px;
      padding: 20px;
      transition: all 0.3s;
    }
    
    .card {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      margin-bottom: 20px;
      border-radius: 0.5rem;
      overflow: hidden;
    }
    
    .card-header {
      background-color: rgba(0, 0, 0, 0.03);
      border-bottom: 1px solid rgba(0, 0, 0, 0.125);
      padding: 0.75rem 1rem;
    }
    
    @media (max-width: 768px) {
      .sidebar {
        margin-left: -250px;
      }
      .content {
        width: 100%;
        margin-left: 0;
      }
      .sidebar.active {
        margin-left: 0;
      }
      .content.active {
        margin-left: 250px;
        width: calc(100% - 250px);
      }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <!-- 侧边栏 -->
    <nav class="sidebar">
      <h3>Admin Panel</h3>
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
    </nav>
    
    <!-- 页面内容 -->
    <div class="content">
      <!-- 内容将在这里插入 -->
    </div>
  </div>
</body>
</html> 