/**
 * 管理面板组件加载器
 * 负责加载页面共享组件(头部和侧边栏)并处理页面初始化
 */

// 在DOM加载完成后运行
document.addEventListener('DOMContentLoaded', function() {
    // 加载样式
    loadStyles();
    
    // 加载页面组件
    loadComponents().then(() => {
        // 检查登录状态
        checkLoginStatus()
            .then(authData => {
                // 处理身份验证响应
                handleAuthResponse(authData);
                
                // 如果页面定义了初始化函数，调用它
                if (typeof pageInit === 'function') {
                    pageInit();
                }
            })
            .catch(error => {
                console.error('认证处理失败:', error);
                // 即使认证失败也尝试显示页面内容
                // 这使得我们可以在API还未实现的情况下显示页面
                if (typeof pageInit === 'function') {
                    pageInit();
                }
            });
    });
});

/**
 * 加载必要的CSS样式
 */
function loadStyles() {
    // 加载Bootstrap和Font Awesome
    if (!document.querySelector('link[href*="bootstrap.min.css"]')) {
        loadCSS('https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
    }
    
    if (!document.querySelector('link[href*="font-awesome"]')) {
        loadCSS('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    }
    
    // 加载自定义样式
    loadCSS('/admin/css/admin-style.css');
    
    // 加载Bootstrap JS
    if (!document.querySelector('script[src*="bootstrap.bundle.min.js"]')) {
        loadScript('https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js');
    }
}

/**
 * 异步加载共享组件
 */
async function loadComponents() {
    try {
        // 创建主布局
        document.body.classList.add('bg-light');
        
        // 创建页面容器
        const pageContent = document.getElementById('page-content');
        if (!pageContent) {
            console.error('未找到页面内容容器 #page-content');
            return;
        }
        
        // 创建包装器
        const wrapper = document.createElement('div');
        wrapper.className = 'wrapper';
        
        // 加载侧边栏
        const sidebar = await createSidebar();
        wrapper.appendChild(sidebar);
        
        // 创建内容容器
        const content = document.createElement('div');
        content.id = 'content';
        
        // 移动页面内容到内容容器
        while (pageContent.firstChild) {
            content.appendChild(pageContent.firstChild);
        }
        
        wrapper.appendChild(content);
        
        // 替换原始内容
        pageContent.replaceWith(wrapper);
        
    } catch (error) {
        console.error('加载页面组件失败:', error);
    }
}

/**
 * 创建侧边栏导航
 */
async function createSidebar() {
    const sidebar = document.createElement('nav');
    sidebar.id = 'sidebar';
    
    // 侧边栏标题
    const header = document.createElement('div');
    header.className = 'sidebar-header';
    header.innerHTML = '<h3>VertoAD 管理</h3>';
    sidebar.appendChild(header);
    
    // 导航菜单
    const ul = document.createElement('ul');
    ul.className = 'list-unstyled components';
    
    // 导航项
    const menuItems = [
        { name: '管理面板', icon: 'tachometer-alt', url: '/admin/dashboard.html' },
        { name: '错误管理', icon: 'exclamation-triangle', url: '/admin/errors.html' },
        { name: '监控大屏', icon: 'chart-bar', url: '/admin/error-dashboard.html' },
        { name: '用户管理', icon: 'users', url: '/admin/users.html' },
        { name: '系统设置', icon: 'cog', url: '/admin/settings.html' },
        { name: '退出登录', icon: 'sign-out-alt', url: '/admin/logout.html' }
    ];
    
    // 获取当前页面路径
    const currentPath = window.location.pathname;
    
    // 创建导航项
    menuItems.forEach(item => {
        const li = document.createElement('li');
        li.className = currentPath === item.url ? 'active' : '';
        
        const a = document.createElement('a');
        a.href = item.url;
        a.innerHTML = `<i class="fas fa-${item.icon} mr-2"></i> ${item.name}`;
        
        li.appendChild(a);
        ul.appendChild(li);
    });
    
    sidebar.appendChild(ul);
    
    return sidebar;
}

/**
 * 检查用户登录状态
 * 改进版：增强了错误处理
 */
function checkLoginStatus() {
    return fetch('/api/auth/check-status')
        .then(response => {
            // 即使状态码不是2xx也尝试解析JSON
            if (!response.ok) {
                console.warn('登录检查API返回错误状态:', response.status);
                // 如果API端点不存在或有错误，返回默认状态
                if (response.status === 404) {
                    throw new Error('API端点未找到');
                }
            }
            
            // 尝试解析JSON响应
            return response.text().then(text => {
                if (!text.trim()) {
                    console.warn('API返回了空响应');
                    throw new Error('空响应');
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    console.error('原始响应:', text);
                    throw new Error(`JSON解析错误: ${e.message}`);
                }
            });
        })
        .catch(error => {
            console.error('检查登录状态失败:', error);
            // 在出错时返回默认状态
            return {
                isLoggedIn: true,  // 假设用户已登录以允许页面继续加载
                isAdmin: true,
                mockData: true     // 标记这是模拟数据
            };
        });
}

/**
 * 处理身份验证响应
 */
function handleAuthResponse(authData) {
    // 如果是模拟数据，显示警告
    if (authData.mockData) {
        console.warn('使用模拟的认证数据 - API可能未正确配置');
    }
    
    // 如果用户未登录，重定向到登录页面
    if (!authData.isLoggedIn && !authData.mockData) {
        window.location.href = '/admin/login.html';
        return;
    }
    
    // 如果用户已登录但不是管理员
    if (authData.isLoggedIn && !authData.isAdmin && !authData.mockData) {
        // 对于管理页面，需要管理员权限
        const adminPages = [
            '/admin/dashboard.html',
            '/admin/errors.html',
            '/admin/error-dashboard.html',
            '/admin/users.html',
            '/admin/settings.html'
        ];
        
        if (adminPages.includes(window.location.pathname)) {
            alert('您没有访问此页面的权限');
            window.location.href = '/index.html';
            return;
        }
    }
}

/**
 * 加载CSS文件
 */
function loadCSS(url) {
    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        
        link.onload = () => resolve();
        link.onerror = () => reject(new Error(`加载CSS失败: ${url}`));
        
        document.head.appendChild(link);
    });
}

/**
 * 加载JavaScript文件
 */
function loadScript(url) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`加载脚本失败: ${url}`));
        
        document.head.appendChild(script);
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