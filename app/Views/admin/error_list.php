<?php require_once ROOT_PATH . '/app/Views/admin/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>错误日志</h1>
    <a href="/admin/errors/dashboard" class="btn btn-primary">返回监控大屏</a>
</div>

<!-- 系统状态提示 -->
<div class="alert alert-success mb-4">
    这是通过MVC架构渲染的错误日志页面 - 版本2.0
</div>

<!-- 筛选表单 -->
<div class="card mb-4">
    <div class="card-body">
        <form action="/admin/errors" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">状态</label>
                <select name="status" id="status" class="form-select">
                    <option value="">所有状态</option>
                    <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>新建</option>
                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>处理中</option>
                    <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>已解决</option>
                    <option value="ignored" <?php echo $status === 'ignored' ? 'selected' : ''; ?>>已忽略</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">错误类型</label>
                <select name="type" id="type" class="form-select">
                    <option value="">所有类型</option>
                    <?php foreach ($types as $errorType): ?>
                        <option value="<?php echo $errorType['type']; ?>" <?php echo $type === $errorType['type'] ? 'selected' : ''; ?>>
                            <?php echo $errorType['type']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">搜索</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="搜索错误消息或文件..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">筛选</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量操作 -->
<div class="card mb-4">
    <div class="card-body">
        <form action="/admin/errors/bulk-update" method="POST" id="bulkForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">批量操作...</option>
                        <option value="in_progress">标记为处理中</option>
                        <option value="resolved">标记为已解决</option>
                        <option value="ignored">标记为已忽略</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning" disabled id="bulkActionBtn">应用</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 错误列表 -->
<div class="card">
    <div class="card-body">
        <?php if (empty($errors)): ?>
            <div class="alert alert-info">没有找到匹配的错误记录。</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>ID</th>
                            <th>类型</th>
                            <th>消息</th>
                            <th>文件</th>
                            <th>行号</th>
                            <th>时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" form="bulkForm" value="<?php echo $error['id']; ?>" class="form-check-input error-checkbox">
                            </td>
                            <td><?php echo $error['id']; ?></td>
                            <td><span class="badge bg-danger"><?php echo $error['type']; ?></span></td>
                            <td><?php echo substr($error['message'], 0, 50) . (strlen($error['message']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo basename($error['file']); ?></td>
                            <td><?php echo $error['line']; ?></td>
                            <td><?php echo $error['created_at']; ?></td>
                            <td>
                                <?php 
                                $statusClass = '';
                                switch ($error['status']) {
                                    case 'new':
                                        $statusClass = 'bg-danger';
                                        break;
                                    case 'in_progress':
                                        $statusClass = 'bg-warning';
                                        break;
                                    case 'resolved':
                                        $statusClass = 'bg-success';
                                        break;
                                    case 'ignored':
                                        $statusClass = 'bg-secondary';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $error['status']; ?></span>
                            </td>
                            <td>
                                <a href="/admin/errors/view/<?php echo $error['id']; ?>" class="btn btn-sm btn-info">查看</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="/admin/errors?page=<?php echo ($page - 1); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>">上一页</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">上一页</span>
                        </li>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="/admin/errors?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="/admin/errors?page=<?php echo ($page + 1); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>">下一页</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">下一页</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <div class="text-muted text-center mt-2">
                显示 <?php echo count($errors); ?> 条记录，共 <?php echo $total; ?> 条
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 全选功能
    const selectAllCheckbox = document.getElementById('selectAll');
    const errorCheckboxes = document.querySelectorAll('.error-checkbox');
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            
            errorCheckboxes.forEach(function(checkbox) {
                checkbox.checked = isChecked;
            });
            
            updateBulkActionButton();
        });
    }
    
    // 单个选择变化时更新批量操作按钮状态
    if (errorCheckboxes.length > 0) {
        errorCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateBulkActionButton();
                
                // 如果有任一复选框未选中，取消"全选"
                if (!this.checked && selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                
                // 如果所有复选框都选中，选中"全选"
                if (Array.from(errorCheckboxes).every(cb => cb.checked) && selectAllCheckbox) {
                    selectAllCheckbox.checked = true;
                }
            });
        });
    }
    
    // 更新批量操作按钮状态
    function updateBulkActionButton() {
        if (bulkActionBtn) {
            const hasChecked = Array.from(errorCheckboxes).some(checkbox => checkbox.checked);
            bulkActionBtn.disabled = !hasChecked;
        }
    }
    
    // 表单提交前验证
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const statusSelect = this.querySelector('select[name="status"]');
            const hasChecked = Array.from(errorCheckboxes).some(checkbox => checkbox.checked);
            
            if (!statusSelect.value || !hasChecked) {
                e.preventDefault();
                alert('请选择要执行的操作和至少一条错误记录。');
            }
        });
    }
});
</script>

<?php require_once ROOT_PATH . '/app/Views/admin/footer.php'; ?> 