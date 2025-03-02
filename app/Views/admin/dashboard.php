<?php require_once ROOT_PATH . '/app/Views/admin/header.php'; ?>

<!-- 页面标题 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>管理员面板</h1>
    <div>
        <a href="/admin/generate-keys" class="btn btn-primary">生成激活码</a>
    </div>
</div>

<!-- 系统状态提示 -->
<div class="alert alert-success mb-4">
    这是通过MVC架构渲染的dashboard页面 - 版本2.0
</div>

<!-- 系统用户 -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">系统用户</h5>
    </div>
    <div class="card-body">
        <?php if (isset($users) && !empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>邮箱</th>
                            <th>角色</th>
                            <th>余额</th>
                            <th>创建时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo ($user['role'] === 'admin') ? 'bg-danger' : 
                                            (($user['role'] === 'publisher') ? 'bg-primary' : 'bg-success'); 
                                    ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['balance']); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">没有找到用户。</div>
        <?php endif; ?>
    </div>
</div>

<!-- 快速操作 -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">快速操作</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3 col-sm-6">
                <a href="/admin/generate-keys" class="btn btn-primary w-100 d-flex flex-column align-items-center py-3">
                    <i class="fs-3 mb-2">🔑</i>
                    <span>生成激活码</span>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="/admin/errors" class="btn btn-warning w-100 d-flex flex-column align-items-center py-3">
                    <i class="fs-3 mb-2">⚠️</i>
                    <span>错误管理</span>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="/admin/users" class="btn btn-info w-100 d-flex flex-column align-items-center py-3">
                    <i class="fs-3 mb-2">👥</i>
                    <span>用户管理</span>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="/admin/settings" class="btn btn-secondary w-100 d-flex flex-column align-items-center py-3">
                    <i class="fs-3 mb-2">⚙️</i>
                    <span>系统设置</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/app/Views/admin/footer.php'; ?> 