<?php require_once ROOT_PATH . '/app/Views/admin/header.php'; ?>

<h1 class="mb-4">管理员面板</h1>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">系统用户</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (isset($users) && !empty($users)): ?>
                        <table class="table table-hover">
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
                    <?php else: ?>
                        <div class="alert alert-info">没有找到用户。</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">快速操作</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <a href="/admin/generate-keys" class="btn btn-primary me-2">生成激活码</a>
                    <a href="/admin/errors" class="btn btn-warning me-2">错误管理</a>
                    <a href="/admin/users" class="btn btn-info me-2">用户管理</a>
                    <a href="/admin/settings" class="btn btn-secondary me-2">系统设置</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/app/Views/admin/footer.php'; ?> 