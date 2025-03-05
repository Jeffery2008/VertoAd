/**
 * VertoAD 广告嵌入脚本
 * 使用方法：
 * 1. 在网页中引入此脚本
 * 2. 在需要显示广告的位置添加带有 data-verto-zone 属性的 div 元素
 * 
 * 示例：
 * <div data-verto-zone="your-zone-id"></div>
 */

(function() {
    // 配置
    const config = {
        baseUrl: 'https://your-domain.com', // 将在生产环境中替换为实际域名
        version: '1.0.0'
    };

    // 初始化函数
    function init() {
        // 查找所有广告位
        const adContainers = document.querySelectorAll('[data-verto-zone]');
        
        // 为每个广告位创建iframe
        adContainers.forEach(container => {
            createAdFrame(container);
        });
    }

    // 创建广告iframe
    function createAdFrame(container) {
        const zoneId = container.getAttribute('data-verto-zone');
        if (!zoneId) return;

        // 获取容器尺寸
        const width = container.getAttribute('data-verto-width') || '100%';
        const height = container.getAttribute('data-verto-height') || '100%';

        // 创建iframe
        const iframe = document.createElement('iframe');
        iframe.src = `${config.baseUrl}/ad/display.html?zone=${encodeURIComponent(zoneId)}`;
        iframe.style.width = width;
        iframe.style.height = height;
        iframe.style.border = 'none';
        iframe.style.overflow = 'hidden';
        iframe.scrolling = 'no';
        iframe.frameBorder = '0';
        iframe.allowTransparency = 'true';
        
        // 添加到容器
        container.appendChild(iframe);

        // 设置响应式尺寸
        if (width === '100%' || height === '100%') {
            makeResponsive(container, iframe);
        }
    }

    // 使广告自适应容器大小
    function makeResponsive(container, iframe) {
        const observer = new ResizeObserver(entries => {
            entries.forEach(entry => {
                const width = entry.contentRect.width;
                const height = entry.contentRect.height;
                
                // 更新iframe尺寸
                iframe.style.width = `${width}px`;
                iframe.style.height = `${height}px`;
            });
        });

        observer.observe(container);
    }

    // 当DOM加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(); 