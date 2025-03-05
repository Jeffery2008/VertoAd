class Modal {
    constructor() {
        this.createModalElement();
        this.bindEvents();
    }

    createModalElement() {
        // 创建模态框HTML结构
        const modalHTML = `
            <div class="modal-overlay">
                <div class="modal">
                    <div class="modal-header">
                        <h3 class="modal-title"></h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-content"></div>
                    <div class="modal-actions">
                        <button class="modal-btn modal-btn-primary">确定</button>
                    </div>
                </div>
            </div>
        `;

        // 将模态框添加到body
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // 获取元素引用
        this.overlay = document.querySelector('.modal-overlay');
        this.modal = document.querySelector('.modal');
        this.title = document.querySelector('.modal-title');
        this.content = document.querySelector('.modal-content');
        this.closeBtn = document.querySelector('.modal-close');
        this.confirmBtn = document.querySelector('.modal-btn-primary');
    }

    bindEvents() {
        // 关闭按钮事件
        this.closeBtn.addEventListener('click', () => this.hide());
        
        // 点击遮罩层关闭
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.hide();
            }
        });

        // ESC键关闭
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.style.display === 'block') {
                this.hide();
            }
        });
    }

    show(options = {}) {
        const {
            title = '提示',
            content = '',
            confirmText = '确定',
            showConfirm = true,
            onConfirm = null
        } = options;

        // 设置内容
        this.title.textContent = title;
        this.content.innerHTML = content;
        this.confirmBtn.textContent = confirmText;
        this.confirmBtn.style.display = showConfirm ? 'block' : 'none';

        // 绑定确认按钮事件
        if (onConfirm) {
            this.confirmBtn.onclick = () => {
                onConfirm();
                this.hide();
            };
        } else {
            this.confirmBtn.onclick = () => this.hide();
        }

        // 显示模态框
        this.overlay.style.display = 'block';
    }

    hide() {
        this.overlay.style.display = 'none';
    }

    success(message, duration = 2000) {
        this.show({
            title: '成功',
            content: message,
            showConfirm: false
        });

        if (duration > 0) {
            setTimeout(() => this.hide(), duration);
        }
    }

    error(message, duration = 0) {
        this.show({
            title: '错误',
            content: message,
            confirmText: '知道了'
        });

        if (duration > 0) {
            setTimeout(() => this.hide(), duration);
        }
    }
}

// 等待 DOM 加载完成后再初始化模态框
document.addEventListener('DOMContentLoaded', () => {
    window.modal = new Modal();
}); 