/**
 * 组件加载器 - 用于加载共享的头部和底部组件
 */
document.addEventListener('DOMContentLoaded', function() {
    // 加载CSS依赖
    loadStylesheets([
        'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
        '/admin/css/admin-style.css'
    ]);
    
    // 加载头部组件
    loadHeader();
    
    // 加载底部组件
    loadFooter();
    
    // 检查登录状态
    checkLoginStatus();
});

/**
 * 加载样式表文件
 * @param {Array} stylesheets 要加载的样式表URL数组
 */
function loadStylesheets(stylesheets) {
    stylesheets.forEach(url => {
        if (!document.querySelector(`link[href="${url}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            document.head.appendChild(link);
        }
    });
}

/**
 * 加载头部组件
 */
function loadHeader() {
    fetch('/admin/components/header.html')
        .then(response => response.text())
        .then(html => {
            // 创建临时元素来解析HTML
            const temp = document.createElement('div');
            temp.innerHTML = html;
            
            // 在body开始处插入sidebar
            const sidebar = temp.querySelector('#sidebar');
            if (sidebar) {
                document.body.insertBefore(sidebar, document.body.firstChild);
            }
            
            // 在sidebar后插入topbar
            const topbar = temp.querySelector('#topbar');
            if (topbar && sidebar) {
                document.body.insertBefore(topbar, sidebar.nextSibling);
            }
            
            // 包装页面内容
            const pageContent = document.getElementById('page-content');
            if (pageContent) {
                const container = document.createElement('div');
                container.className = 'container-fluid py-4';
                container.id = 'main-container';
                
                // 移动pageContent到新容器
                const parent = pageContent.parentNode;
                parent.insertBefore(container, pageContent);
                container.appendChild(pageContent);
                
                // 设置布局类
                document.body.classList.add('d-flex', 'flex-column', 'min-vh-100');
                
                // 设置main-content区域
                const mainContent = document.createElement('div');
                mainContent.className = 'main-content';
                mainContent.appendChild(container);
                
                if (sidebar) {
                    sidebar.after(mainContent);
                } else {
                    document.body.appendChild(mainContent);
                }
            }
            
            // 设置当前页面的活动导航
            highlightCurrentNavItem();
        })
        .catch(error => {
            console.error('无法加载头部组件:', error);
        });
}

/**
 * 加载底部组件
 */
function loadFooter() {
    fetch('/admin/components/footer.html')
        .then(response => response.text())
        .then(html => {
            // 将footer HTML附加到body末尾
            const temp = document.createElement('div');
            temp.innerHTML = html;
            
            const footer = temp.querySelector('footer');
            if (footer) {
                document.body.appendChild(footer);
            }
        })
        .catch(error => {
            console.error('无法加载底部组件:', error);
        });
}

/**
 * 检查用户登录状态
 */
function checkLoginStatus() {
    fetch('/api/auth/check-status')
        .then(response => response.json())
        .then(data => {
            if (!data.isLoggedIn) {
                // 如果未登录且当前不在登录页，重定向到登录页
                if (!window.location.pathname.includes('/login.html')) {
                    window.location.href = '/admin/login.html';
                }
            } else {
                // 已登录，如果有定义页面初始化函数则调用它
                if (typeof pageInit === 'function') {
                    pageInit();
                }
                
                // 更新用户信息
                updateUserInfo(data.user);
            }
        })
        .catch(error => {
            console.error('检查登录状态失败:', error);
        });
}

/**
 * 更新用户信息显示
 * @param {Object} user 用户信息对象
 */
function updateUserInfo(user) {
    const userNameElements = document.querySelectorAll('.user-name');
    const userRoleElements = document.querySelectorAll('.user-role');
    const userAvatarElements = document.querySelectorAll('.user-avatar');
    
    if (user) {
        // 更新用户名
        userNameElements.forEach(element => {
            element.textContent = user.username || 'User';
        });
        
        // 更新用户角色
        userRoleElements.forEach(element => {
            element.textContent = user.role || 'User';
        });
        
        // 更新头像
        userAvatarElements.forEach(element => {
            // 设置默认头像或用户头像
            if (element.tagName.toLowerCase() === 'img') {
                element.src = user.avatar || '/admin/img/default-avatar.png';
                element.alt = user.username || 'User';
            }
        });
    }
}

/**
 * 高亮当前页面对应的导航项
 */
function highlightCurrentNavItem() {
    const pathname = window.location.pathname;
    
    // 根据路径确定应该高亮的导航项
    let navSelector = '';
    
    if (pathname.includes('/dashboard')) {
        navSelector = '.nav-link[href*="dashboard"]';
    } else if (pathname.includes('/error')) {
        navSelector = '.nav-link[href*="error"]';
    } else if (pathname.includes('/user')) {
        navSelector = '.nav-link[href*="user"]';
    } else if (pathname.includes('/setting')) {
        navSelector = '.nav-link[href*="setting"]';
    }
    
    // 移除所有活动状态并设置当前项
    if (navSelector) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        const activeLink = document.querySelector(navSelector);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }
}

/**
 * 登出函数
 */
function logout() {
    fetch('/api/auth/logout')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/admin/login.html';
            } else {
                alert('登出失败: ' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            console.error('登出请求失败:', error);
            alert('登出请求失败');
        });
} 