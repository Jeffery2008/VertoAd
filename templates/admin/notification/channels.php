<?php include __DIR__ . '/../header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">通知渠道管理</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>渠道名称</th>
                                    <th>状态</th>
                                    <th>配置</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($channels as $channel): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($channel['name']); ?></td>
                                    <td>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input channel-status" 
                                                id="status_<?php echo $channel['channel_type']; ?>"
                                                data-type="<?php echo $channel['channel_type']; ?>"
                                                <?php echo $channel['is_enabled'] ? 'checked' : ''; ?>
                                                <?php echo $channel['channel_type'] === 'in_app' ? 'disabled' : ''; ?>>
                                            <label class="custom-control-label" for="status_<?php echo $channel['channel_type']; ?>">
                                                <?php echo $channel['is_enabled'] ? '已启用' : '已禁用'; ?>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $config = json_decode($channel['config'], true);
                                        switch ($channel['channel_type']) {
                                            case 'email':
                                                echo "SMTP: " . htmlspecialchars($config['smtp_host'] ?? '未配置');
                                                break;
                                            case 'sms':
                                                echo "API: " . htmlspecialchars($config['api_url'] ?? '未配置');
                                                break;
                                            case 'in_app':
                                                echo "队列: " . htmlspecialchars($config['queue'] ?? 'notifications_in_app');
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-config" 
                                            data-type="<?php echo $channel['channel_type']; ?>"
                                            data-config='<?php echo htmlspecialchars(json_encode($config)); ?>'>
                                            配置
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 配置模态框 -->
<div class="modal fade" id="configModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">渠道配置</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="configForm">
                    <input type="hidden" name="channel_type" id="channelType">
                    
                    <!-- 邮件配置 -->
                    <div id="emailConfig" style="display: none;">
                        <div class="form-group">
                            <label>SMTP主机</label>
                            <input type="text" class="form-control" name="smtp_host">
                        </div>
                        <div class="form-group">
                            <label>SMTP端口</label>
                            <input type="text" class="form-control" name="smtp_port">
                        </div>
                        <div class="form-group">
                            <label>SMTP用户名</label>
                            <input type="text" class="form-control" name="smtp_user">
                        </div>
                        <div class="form-group">
                            <label>SMTP密码</label>
                            <input type="password" class="form-control" name="smtp_pass">
                        </div>
                        <div class="form-group">
                            <label>发件人邮箱</label>
                            <input type="email" class="form-control" name="from_email">
                        </div>
                        <div class="form-group">
                            <label>发件人名称</label>
                            <input type="text" class="form-control" name="from_name">
                        </div>
                    </div>
                    
                    <!-- 短信配置 -->
                    <div id="smsConfig" style="display: none;">
                        <div class="form-group">
                            <label>API地址</label>
                            <input type="text" class="form-control" name="api_url">
                        </div>
                        <div class="form-group">
                            <label>API密钥</label>
                            <input type="text" class="form-control" name="api_key">
                        </div>
                        <div class="form-group">
                            <label>API密钥</label>
                            <input type="password" class="form-control" name="api_secret">
                        </div>
                    </div>
                    
                    <!-- 站内信配置 -->
                    <div id="inAppConfig" style="display: none;">
                        <div class="form-group">
                            <label>队列名称</label>
                            <input type="text" class="form-control" name="queue" value="notifications_in_app" readonly>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="saveConfig">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 状态切换
    $('.channel-status').change(function() {
        const channelType = $(this).data('type');
        const isEnabled = $(this).prop('checked');
        
        $.ajax({
            url: '/admin/notification/channels/update-status',
            type: 'POST',
            data: {
                channel_type: channelType,
                is_enabled: isEnabled
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                    // 恢复原状态
                    $(this).prop('checked', !isEnabled);
                }
            },
            error: function() {
                toastr.error('操作失败');
                // 恢复原状态
                $(this).prop('checked', !isEnabled);
            }
        });
    });
    
    // 编辑配置
    $('.edit-config').click(function() {
        const channelType = $(this).data('type');
        const config = $(this).data('config');
        
        // 重置表单
        $('#configForm')[0].reset();
        
        // 设置渠道类型
        $('#channelType').val(channelType);
        
        // 隐藏所有配置区域
        $('#emailConfig, #smsConfig, #inAppConfig').hide();
        
        // 显示对应的配置区域
        $(`#${channelType}Config`).show();
        
        // 填充配置
        if (config) {
            Object.keys(config).forEach(key => {
                $(`#configForm [name="${key}"]`).val(config[key]);
            });
        }
        
        // 显示模态框
        $('#configModal').modal('show');
    });
    
    // 保存配置
    $('#saveConfig').click(function() {
        const channelType = $('#channelType').val();
        const $form = $(`#${channelType}Config :input`).serializeArray();
        
        // 构建配置对象
        const config = {};
        $form.forEach(item => {
            config[item.name] = item.value;
        });
        
        // 添加队列配置
        config.queue = `notifications_${channelType}`;
        
        $.ajax({
            url: '/admin/notification/channels/update-config',
            type: 'POST',
            data: {
                channel_type: channelType,
                config: JSON.stringify(config)
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#configModal').modal('hide');
                    // 刷新页面
                    setTimeout(() => location.reload(), 1500);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('操作失败');
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?> 