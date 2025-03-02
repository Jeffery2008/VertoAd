<?php require_once ROOT_PATH . '/app/Views/admin/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>错误详情 <span class="text-muted">#<?php echo $error['id']; ?></span></h1>
        <div>
            <a href="/admin/errors" class="btn btn-outline-secondary me-2">返回列表</a>
            <a href="/admin/errors/dashboard" class="btn btn-primary">返回监控大屏</a>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <!-- 错误基本信息 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">错误信息</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error['message']); ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">错误类型:</div>
                        <div class="col-md-9">
                            <span class="badge bg-danger"><?php echo $error['type']; ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">文件:</div>
                        <div class="col-md-9"><?php echo $error['file']; ?></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">行号:</div>
                        <div class="col-md-9"><?php echo $error['line']; ?></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">发生时间:</div>
                        <div class="col-md-9"><?php echo $error['created_at']; ?></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">状态:</div>
                        <div class="col-md-9">
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
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 堆栈跟踪 -->
            <?php if (!empty($error['trace'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">堆栈跟踪</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded"><?php echo htmlspecialchars($error['trace']); ?></pre>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 请求数据 -->
            <?php if (!empty($error['request_data'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">请求数据</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $requestData = json_decode($error['request_data'], true);
                    if ($requestData):
                    ?>
                        <ul class="nav nav-tabs" id="requestDataTab" role="tablist">
                            <?php if (!empty($requestData['GET'])): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="get-tab" data-bs-toggle="tab" data-bs-target="#get" type="button" role="tab" aria-controls="get" aria-selected="true">GET</button>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['POST'])): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo empty($requestData['GET']) ? 'active' : ''; ?>" id="post-tab" data-bs-toggle="tab" data-bs-target="#post" type="button" role="tab" aria-controls="post" aria-selected="false">POST</button>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['COOKIE'])): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="cookie-tab" data-bs-toggle="tab" data-bs-target="#cookie" type="button" role="tab" aria-controls="cookie" aria-selected="false">COOKIE</button>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['SESSION'])): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="session-tab" data-bs-toggle="tab" data-bs-target="#session" type="button" role="tab" aria-controls="session" aria-selected="false">SESSION</button>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['SERVER'])): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="server-tab" data-bs-toggle="tab" data-bs-target="#server" type="button" role="tab" aria-controls="server" aria-selected="false">SERVER</button>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="tab-content p-3 bg-light rounded-bottom" id="requestDataTabContent">
                            <?php if (!empty($requestData['GET'])): ?>
                            <div class="tab-pane fade show active" id="get" role="tabpanel" aria-labelledby="get-tab">
                                <pre><?php echo htmlspecialchars(json_encode($requestData['GET'], JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['POST'])): ?>
                            <div class="tab-pane fade <?php echo empty($requestData['GET']) ? 'show active' : ''; ?>" id="post" role="tabpanel" aria-labelledby="post-tab">
                                <pre><?php echo htmlspecialchars(json_encode($requestData['POST'], JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['COOKIE'])): ?>
                            <div class="tab-pane fade" id="cookie" role="tabpanel" aria-labelledby="cookie-tab">
                                <pre><?php echo htmlspecialchars(json_encode($requestData['COOKIE'], JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['SESSION'])): ?>
                            <div class="tab-pane fade" id="session" role="tabpanel" aria-labelledby="session-tab">
                                <pre><?php echo htmlspecialchars(json_encode($requestData['SESSION'], JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($requestData['SERVER'])): ?>
                            <div class="tab-pane fade" id="server" role="tabpanel" aria-labelledby="server-tab">
                                <pre><?php echo htmlspecialchars(json_encode($requestData['SERVER'], JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">请求数据格式无效。</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- 状态更新表单 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">更新状态</h5>
                </div>
                <div class="card-body">
                    <form action="/admin/errors/update-status/<?php echo $error['id']; ?>" method="POST">
                        <div class="mb-3">
                            <label for="status" class="form-label">状态</label>
                            <select name="status" id="status" class="form-select">
                                <option value="new" <?php echo $error['status'] === 'new' ? 'selected' : ''; ?>>新建</option>
                                <option value="in_progress" <?php echo $error['status'] === 'in_progress' ? 'selected' : ''; ?>>处理中</option>
                                <option value="resolved" <?php echo $error['status'] === 'resolved' ? 'selected' : ''; ?>>已解决</option>
                                <option value="ignored" <?php echo $error['status'] === 'ignored' ? 'selected' : ''; ?>>已忽略</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">备注</label>
                            <textarea name="notes" id="notes" class="form-control" rows="5"><?php echo htmlspecialchars($error['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">更新</button>
                    </form>
                </div>
            </div>
            
            <!-- 用户信息 -->
            <?php if (!empty($error['user_id'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">用户信息</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>用户ID:</strong> <?php echo $error['user_id']; ?>
                    </div>
                    
                    <a href="/admin/users/view/<?php echo $error['user_id']; ?>" class="btn btn-outline-primary btn-sm">查看用户资料</a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 客户端信息 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">客户端信息</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>IP地址:</strong> <?php echo $error['ip_address'] ?? 'Unknown'; ?>
                    </div>
                    
                    <?php if (!empty($error['user_agent'])): ?>
                    <div class="mb-3">
                        <strong>用户代理:</strong> <br>
                        <small class="text-muted"><?php echo htmlspecialchars($error['user_agent']); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 相关错误 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">相关错误</h5>
                </div>
                <div class="card-body">
                    <?php
                    // 同类型错误查询示例 - 实际使用时应替换为控制器提供的数据
                    $similarErrors = []; // 这里应该是控制器提供的
                    
                    if (empty($similarErrors)):
                    ?>
                        <div class="alert alert-info">没有找到相关错误。</div>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($similarErrors as $similar): ?>
                            <li class="list-group-item">
                                <a href="/admin/errors/view/<?php echo $similar['id']; ?>">
                                    #<?php echo $similar['id']; ?> - <?php echo substr($similar['message'], 0, 30) . '...'; ?>
                                </a>
                                <span class="float-end text-muted small"><?php echo $similar['created_at']; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/app/Views/admin/footer.php'; ?> 