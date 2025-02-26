<?php include __DIR__ . '/../header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">通知偏好设置</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>通知类型</th>
                                    <?php foreach ($channels as $channel): ?>
                                    <th class="text-center">
                                        <?php
                                        switch ($channel['channel_type']) {
                                            case 'email':
                                                echo '邮件通知';
                                                break;
                                            case 'sms':
                                                echo '短信通知';
                                                break;
                                            case 'in_app':
                                                echo '站内信';
                                                break;
                                        }
                                        ?>
                                    </th>
                                    <?php endforeach; ?>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $templateId => $template): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($template['name']); ?></td>
                                    <?php foreach ($channels as $channel): ?>
                                    <td class="text-center">
                                        <?php if (isset($template['channels'][$channel['channel_type']])): ?>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input channel-toggle"
                                                id="channel_<?php echo $templateId; ?>_<?php echo $channel['channel_type']; ?>"
                                                data-template-id="<?php echo $templateId; ?>"
                                                data-channel-type="<?php echo $channel['channel_type']; ?>"
                                                <?php echo $template['channels'][$channel['channel_type']] ? 'checked' : ''; ?>
                                                <?php echo $channel['channel_type'] === 'in_app' ? 'disabled' : ''; ?>>
                                            <label class="custom-control-label" 
                                                for="channel_<?php echo $templateId; ?>_<?php echo $channel['channel_type']; ?>">
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-secondary reset-preferences"
                                            data-template-id="<?php echo $templateId; ?>">
                                            重置为默认
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

<script>
$(document).ready(function() {
    // 切换通知渠道状态
    $('.channel-toggle').change(function() {
        const templateId = $(this).data('template-id');
        const channelType = $(this).data('channel-type');
        const isEnabled = $(this).prop('checked');
        
        // 收集当前模板的所有渠道状态
        const channels = {};
        $(`input[data-template-id="${templateId}"]`).each(function() {
            channels[$(this).data('channel-type')] = $(this).prop('checked');
        });
        
        $.ajax({
            url: '/user/notification/preferences/update',
            type: 'POST',
            data: {
                template_id: templateId,
                channels: channels
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
    
    // 重置为默认设置
    $('.reset-preferences').click(function() {
        const templateId = $(this).data('template-id');
        const $button = $(this);
        
        if (confirm('确定要重置此通知类型的设置吗？')) {
            $.ajax({
                url: '/user/notification/preferences/reset',
                type: 'POST',
                data: {
                    template_id: templateId
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('操作失败');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?> 