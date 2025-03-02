<?php require_once ROOT_PATH . '/app/Views/admin/header.php'; ?>

<div class="container py-5">
    <div class="card border-primary shadow">
        <div class="card-header bg-primary text-white">
            <h1 class="h3 mb-0"><?php echo $title; ?></h1>
        </div>
        <div class="card-body text-center py-5">
            <div class="mb-4">
                <i class="fas fa-tools" style="font-size: 4rem; color: #6c757d;"></i>
                <!-- 如果没有FontAwesome，使用一个简单的emoji替代 -->
                <div style="font-size: 4rem; color: #6c757d;">🔧</div>
            </div>
            <h2 class="h4 mb-3"><?php echo $message; ?></h2>
            <p class="text-muted">该功能尚未实现，敬请期待！</p>
            <a href="/admin/dashboard" class="btn btn-primary mt-3">返回仪表盘</a>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/app/Views/admin/footer.php'; ?> 