<?php
// Check if headers have already been sent, if not, start session
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>广告管理系统</title>
    <!-- 引入 TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- 引入 Vue.js -->
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14"></script>
    <!-- 引入 ECharts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
</head>
<body class="bg-gray-100">
    <!-- 顶部导航栏 -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/advertiser/dashboard" class="text-xl font-bold text-blue-600">广告管理系统</a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="/advertiser/dashboard" class="<?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'border-blue-500' : 'border-transparent' ?> hover:border-gray-300 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            控制台
                        </a>
                        <a href="/advertiser/ads" class="<?= strpos($_SERVER['REQUEST_URI'], 'ads') !== false ? 'border-blue-500' : 'border-transparent' ?> hover:border-gray-300 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            我的广告
                        </a>
                        <a href="/advertiser/canvas" class="<?= strpos($_SERVER['REQUEST_URI'], 'canvas') !== false ? 'border-blue-500' : 'border-transparent' ?> hover:border-gray-300 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            创建广告
                        </a>
                        <a href="/advertiser/analytics" class="<?= strpos($_SERVER['REQUEST_URI'], 'analytics') !== false ? 'border-blue-500' : 'border-transparent' ?> hover:border-gray-300 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            数据分析
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-4">
                            <span class="text-gray-700"><?= htmlspecialchars($advertiser['name'] ?? '') ?></span>
                            <a href="/advertiser/settings" class="text-gray-600 hover:text-gray-900">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </a>
                            <a href="/advertiser/logout" class="text-red-600 hover:text-red-900">退出</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主要内容区域 -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?= $content ?>
    </main>

    <!-- 页脚 -->
    <footer class="bg-white mt-12 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?= date('Y') ?> 广告管理系统. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- 全局错误提示框 -->
    <?php if (isset($_SESSION['error'])): ?>
    <div id="error-alert" class="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg class="fill-current h-6 w-6 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- 全局成功提示框 -->
    <?php if (isset($_SESSION['success'])): ?>
    <div id="success-alert" class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['success']) ?></span>
        <button onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg class="fill-current h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <!-- CSRF Token -->
    <script>
        window.csrfToken = '<?= htmlspecialchars($csrf_token ?? '') ?>';
    </script>
</body>
</html>
