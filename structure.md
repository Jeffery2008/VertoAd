增强广告设计器功能: ✓
Canvas绘图工具增强版(使用结构化储存设计好的广告) ✓
- 图层管理系统 ✓
- 丰富的绘图工具(铅笔、马克笔、荧光笔等) ✓
- 文本编辑(字体、大小、颜色、对齐等) ✓
- 形状工具(矩形、圆形、线条等) ✓
- 图片导入和编辑 ✓
- 滤镜效果 ✓
- 历史记录/撤销 ✓
- 自动保存 ✓
- 模板系统 ✓
- 快捷键支持 ✓

扩展管理员功能:
A. 广告管理 ✓
- 广告位管理 ✓
  * 位置设置 ✓
  * 尺寸配置 ✓
  * 投放策略 ✓
  * A/B测试 ✓
- 广告审核流程 ✓
  * 多级审核 ✓
  * 审核记录 ✓
  * 违规检测 ✓
- 定价系统 ✓
  * 时间段定价 ✓
  * 位置定价 ✓
  * 竞价系统 ✓
  * 折扣管理 ✓
- 投放控制 ✓
  * 精准投放 ✓
  * 地域投放 ✓
  ps:获取地域信息可以通过这个api实现：https://whois.pconline.com.cn/ipJson.jsp?ip=120.24.212.59&json=true
返回的json是{"ip":"120.24.212.59","pro":"广东省","proCode":"440000","city":"深圳市","cityCode":"440300","region":"","regionCode":"0","addr":"广东省深圳市 电信","regionNames":"","err":""}
  * 设备投放 ✓
  * 时间投放 ✓
  
B. 用户管理 ✓
- 广告主管理 ✓
  * 信用评级 ✓
  * 消费等级 ✓
  * 投放限制 ✓
- 管理员权限 ✓
  * 角色管理 ✓
  * 权限分配 ✓
  * 操作日志 ✓
  
C. 财务管理 ✓
- 订单系统 ✓
- 计费规则 ✓
- 退款处理 ✓
- 发票管理 ✓
- 收支报表 ✓

D. 系统配置
- 广告类型配置
- 投放策略配置
- 违规词管理
- 系统通知
- 备份恢复

数据分析指标:
A. 广告效果分析 ✓
- 展示量(PV) ✓
  * 按时间统计 ✓
  * 按地域统计 ✓
  * 按设备统计 ✓
- 点击率(CTR) ✓
  * 实时点击率 ✓
  * 历史趋势 ✓
  * 对比分析 ✓
- 转化率 ✓
  * 转化漏斗 ✓
  * 转化路径 ✓
  * ROI分析 ✓
- 互动数据 ✓
  * 停留时间 ✓
  * 互动行为 ✓
  * 热力图 ✓

B. 受众分析 ✓
- 用户画像 ✓
  * 年龄分布 ✓
  * 性别比例 ✓
  * 兴趣标签 ✓
- 地域分布 ✓
  * 省市分布 ✓
  * 热门城市 ✓
- 设备分析 ✓
  * 设备类型 ✓
  * 浏览器占比 ✓
  * 分辨率分布 ✓

D. 财务分析 ✓
- 收入趋势 ✓
- 客户价值 ✓
- 广告位ROI ✓
- 定价优化 ✓

API接口设计:
RESTful API: ✓

A. 广告管理API ✓
- 创建广告 ✓
- 修改广告 ✓
- 查询广告 ✓
- 删除广告 ✓
- 广告状态管理 ✓

B. 数据API ✓
- 获取展示数据 ✓
- 获取点击数据 ✓
- 获取转化数据 ✓
- 自定义报表 ✓

C. 广告位API ✓
- 获取可用广告位 ✓
- 预定广告位 ✓
- 查询价格 ✓
- 投放状态 ✓

D. 账户API
- 账户管理
- 余额查询
- 消费记录
- 订单管理

认证与安全:
- OAuth2.0认证
- API密钥管理
- 访问频率限制
- 数据加密传输
- 所有php报错都要用自定义函数记录在数据库中，生成一个数据可视化大屏，方便运维查看和定位错误
- 使用csrf和Proof of Work加强安保

ad-system/
├── api/
│   ├── v1/
│   │   ├── ads.php ✓
│   │   ├── positions.php ✓
│   │   ├── statistics.php ✓
│   │   └── auth.php ✓
├── config/
│   ├── database.php ✓
│   └── config.php ✓
├── public/
│   ├── index.php
│   ├── admin/
│   │   └── index.php
│   └── advertiser/
│       └── index.php
├── src/
│   ├── Controllers/
│   │   ├── AdminController.php ✓
│   │   ├── AdvertiserController.php ✓
│   │   ├── AdController.php ✓
│   │   └── StatisticsController.php ✓
│   ├── Models/
│   │   ├── Advertisement.php ✓
│   │   ├── AdPosition.php ✓
│   │   ├── Advertiser.php ✓
│   │   └── Statistics.php ✓
│   ├── Services/
│   │   ├── AuthService.php ✓
│   │   ├── AdService.php ✓
│   │   └── StatisticsService.php ✓
│   └── Utils/
│       ├── Database.php ✓
│       ├── Logger.php ✓
│       └── Validator.php ✓
├── templates/
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── ads.php
│   │   └── statistics.php
│   └── advertiser/
│       ├── dashboard.php
│       ├── canvas.php ✓
│       └── statistics.php
└── vendor/

# Ad Server System Structure

## Directory Structure
```
/
├── api/v1/                    # API endpoints
│   ├── accounts.php           # Account management API
│   ├── ads.php               # Ad management API
│   ├── auth/                 # Authentication endpoints
│   │   ├── challenge.php
│   │   └── login.php
│   ├── competition.php       # Competition system API
│   ├── positions.php         # Ad position management API
│   ├── serve.php            # Ad serving endpoint
│   └── track.php            # Tracking endpoint
├── config/                   # Configuration files
│   ├── config.php           # Main configuration
│   └── database.php         # Database configuration
├── src/
│   ├── Controllers/         # Controller classes
│   ├── Models/              # Model classes
│   ├── Services/            # Business logic services
│   └── Utils/              # Utility classes
├── static/                  # Static assets
│   └── js/
│       ├── adclient.js     # Client-side ad loader
│       └── drawingTools.js # Ad creation tools
└── templates/               # View templates
    ├── admin/              # Admin panel views
    └── advertiser/         # Advertiser panel views
```

## Database Schema

### User Management
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'advertiser', 'publisher') NOT NULL,
    status ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'pending',
    balance DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_activity_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Product Key System
```sql
CREATE TABLE product_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_hash VARCHAR(64) NOT NULL UNIQUE,  -- SHA256 hash of the key
    key_value VARCHAR(29) NOT NULL UNIQUE, -- Format: XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
    batch_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    status ENUM('active', 'used', 'revoked') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    used_by BIGINT UNSIGNED NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (used_by) REFERENCES users(id),
    INDEX idx_key_hash (key_hash),
    INDEX idx_key_value (key_value),
    INDEX idx_batch_status (batch_id, status)
);

CREATE TABLE key_batches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    batch_name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE key_activation_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    balance_before DECIMAL(15,4) NOT NULL,
    balance_after DECIMAL(15,4) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (key_id) REFERENCES product_keys(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Ad Management
```sql
CREATE TABLE ad_positions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    site_id VARCHAR(100) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE advertisements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    content JSON NOT NULL,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NULL,
    status ENUM('pending', 'active', 'paused', 'completed', 'rejected') NOT NULL,
    budget DECIMAL(15,4) NOT NULL,
    bid_amount DECIMAL(15,4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES users(id),
    FOREIGN KEY (position_id) REFERENCES ad_positions(id)
);
```

### Performance Tracking
```sql
CREATE TABLE impressions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    viewer_id VARCHAR(64),
    ip_address VARCHAR(45),
    user_agent TEXT,
    cost DECIMAL(15,4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    FOREIGN KEY (position_id) REFERENCES ad_positions(id)
);

CREATE TABLE clicks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    impression_id BIGINT UNSIGNED NOT NULL,
    viewer_id VARCHAR(64),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (impression_id) REFERENCES impressions(id)
);
```

## Key Components

### Services

#### KeyGenerationService
- Generates unique Windows-style product keys
- Implements collision detection
- Manages key batch creation
- Validates key format and checksums

#### KeyManagementService
- Handles key activation
- Tracks key usage and status
- Manages key blacklisting
- Provides key analytics

#### AccountService
- Manages user balances
- Processes key activations
- Tracks transaction history
- Handles balance adjustments

### Controllers

#### AdminController
- Key generation interface
- User management
- System configuration
- Performance monitoring

#### AdvertiserController
- Campaign management
- Creative upload
- Performance tracking
- Balance management

### Utils

#### KeyValidator
- Key format validation
- Checksum verification
- Anti-tampering checks
- Rate limiting

#### SecurityUtils
- Input sanitization
- CSRF protection
- XSS prevention
- Rate limiting
