// 组件加载器
document.addEventListener('DOMContentLoaded', function() {
    // 加载导航栏
    loadNavbar();
    
    // 加载侧边栏（如果存在）
    if (document.querySelector('.sidebar-container')) {
        loadSidebar();
    }
});

// 加载导航栏
function loadNavbar() {
    const navbar = document.createElement('nav');
    navbar.className = 'admin-navbar';
    navbar.innerHTML = `
        <div class="navbar-container">
            <div class="navbar-brand">
                <a href="/admin/dashboard">VertoAD</a>
            </div>
            <div class="navbar-menu">
                <a href="/admin/dashboard" class="nav-item">仪表盘</a>
                <a href="/admin/users" class="nav-item">用户管理</a>
                <a href="/admin/settings" class="nav-item">系统设置</a>
                <a href="/admin/errors" class="nav-item">错误日志</a>
            </div>
            <div class="navbar-user">
                <span class="user-name">${getUserName()}</span>
                <a href="/admin/logout" class="logout-btn">退出</a>
            </div>
        </div>
    `;
    
    // 插入到页面顶部
    document.body.insertBefore(navbar, document.body.firstChild);
    
    // 添加导航栏样式
    const style = document.createElement('style');
    style.textContent = `
        .admin-navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            margin-bottom: 20px;
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .navbar-brand a {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .navbar-menu {
            display: flex;
            gap: 20px;
        }
        
        .nav-item {
            color: var(--dark-color);
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .nav-item:hover {
            background-color: #f5f7fa;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .logout-btn {
            color: var(--danger-color);
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .logout-btn:hover {
            background-color: #fff5f5;
        }
    `;
    document.head.appendChild(style);
}

// 获取用户名
function getUserName() {
    // 这里应该从 session 或其他地方获取用户名
    // 暂时返回默认值
    return '管理员';
}

// 加载侧边栏
function loadSidebar() {
    const sidebar = document.querySelector('.sidebar-container');
    if (!sidebar) return;
    
    sidebar.innerHTML = `
        <div class="sidebar-header">
            <h3>快速导航</h3>
        </div>
        <div class="sidebar-menu">
            <a href="/admin/dashboard" class="sidebar-item">
                <i class="icon">📊</i>
                <span>仪表盘</span>
            </a>
            <a href="/admin/users" class="sidebar-item">
                <i class="icon">👥</i>
                <span>用户管理</span>
            </a>
            <a href="/admin/ads" class="sidebar-item">
                <i class="icon">📢</i>
                <span>广告管理</span>
            </a>
            <a href="/admin/settings" class="sidebar-item">
                <i class="icon">⚙️</i>
                <span>系统设置</span>
            </a>
            <a href="/admin/errors" class="sidebar-item">
                <i class="icon">⚠️</i>
                <span>错误日志</span>
            </a>
        </div>
    `;
    
    // 添加侧边栏样式
    const style = document.createElement('style');
    style.textContent = `
        .sidebar-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 250px;
        }
        
        .sidebar-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .sidebar-header h3 {
            margin: 0;
            color: var(--dark-color);
            font-size: 16px;
        }
        
        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .sidebar-item:hover {
            background-color: #f5f7fa;
        }
        
        .sidebar-item .icon {
            font-size: 18px;
        }
    `;
    document.head.appendChild(style);
} 