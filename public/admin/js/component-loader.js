/**
 * 管理面板组件加载器
 * 负责加载页面共享组件(头部和侧边栏)并处理页面初始化
 */

// 在DOM加载完成后运行
document.addEventListener('DOMContentLoaded', function() {
    // 如果是登录页面，不需要加载组件和检查权限
    if (window.location.pathname.includes('/admin/login')) {
        return;
    }

    // 加载样式
    loadStyles();
    
    // 加载页面组件并检查权限
    loadComponents()
        .then(() => {
            // 检查登录状态
            return fetch('/api/auth/check-status')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(response.status === 401 ? '请先登录' : 
                                      response.status === 403 ? '无权限访问' : 
                                      'API请求失败');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || !data.isLoggedIn) {
                        throw new Error('请先登录');
                    }
                    if (!data.isAdmin) {
                        throw new Error('您没有管理员权限');
                    }

                    // 更新用户信息显示
                    updateUserInfo({
                        username: data.username,
                        role: 'admin'
                    });

                    // 高亮当前导航项
                    highlightCurrentNavItem();

                    // 如果页面定义了初始化函数，调用它
                    if (typeof pageInit === 'function') {
                        pageInit();
                    }
                });
        })
        .catch(error => {
            console.error('认证处理失败:', error);
            window.location.href = '/admin/login?error=' + encodeURIComponent(error.message);
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
        // 加载 header
        const headerResponse = await fetch('/admin/components/header.html');
        const headerText = await headerResponse.text();
        const headerDoc = new DOMParser().parseFromString(headerText, 'text/html');
        
        // 提取 header 的 head 内容
        const sourceHead = headerDoc.head;
        const targetHead = document.head;
        
        // 复制 meta 标签
        sourceHead.querySelectorAll('meta').forEach(meta => {
            if (!targetHead.querySelector(`meta[name="${meta.getAttribute('name')}"]`)) {
                targetHead.appendChild(meta.cloneNode(true));
            }
        });
        
        // 复制样式表链接
        sourceHead.querySelectorAll('link').forEach(link => {
            if (!targetHead.querySelector(`link[href="${link.getAttribute('href')}"]`)) {
                targetHead.appendChild(link.cloneNode(true));
            }
        });
        
        // 复制样式
        sourceHead.querySelectorAll('style').forEach(style => {
            targetHead.appendChild(style.cloneNode(true));
        });
        
        // 复制标题
        if (!targetHead.querySelector('title')) {
            const title = sourceHead.querySelector('title');
            if (title) {
                targetHead.appendChild(title.cloneNode(true));
            }
        }

        // 插入 header 的 body 内容
        const headerContent = headerDoc.body.innerHTML;
        document.body.insertAdjacentHTML('afterbegin', headerContent);

        // 加载 footer
        const footerResponse = await fetch('/admin/components/footer.html');
        const footerText = await footerResponse.text();
        const footerDoc = new DOMParser().parseFromString(footerText, 'text/html');
        
        // 提取 footer 内容（排除结束标签）
        const footerContent = footerText.substring(
            footerText.indexOf('<script'),
            footerText.lastIndexOf('</body>')
        );
        
        // 插入 footer 内容
        document.body.insertAdjacentHTML('beforeend', footerContent);

        // 执行 footer 中的脚本
        const scripts = footerDoc.querySelectorAll('script');
        scripts.forEach(script => {
            if (script.src) {
                // 外部脚本
                if (!document.querySelector(`script[src="${script.src}"]`)) {
                    const newScript = document.createElement('script');
                    newScript.src = script.src;
                    document.body.appendChild(newScript);
                }
            } else {
                // 内联脚本
                const newScript = document.createElement('script');
                newScript.textContent = script.textContent;
                document.body.appendChild(newScript);
            }
        });

    } catch (error) {
        console.error('Error loading components:', error);
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