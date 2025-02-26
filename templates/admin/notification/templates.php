<?php include __DIR__ . '/../header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">通知模板管理</h3>
                    <div class="card-tools">
                        <a href="/admin/notification/templates/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> 创建模板
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>名称</th>
                                    <th>代码</th>
                                    <th>标题</th>
                                    <th>支持渠道</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($template['name']); ?></td>
                                    <td><?php echo htmlspecialchars($template['code']); ?></td>
                                    <td><?php echo htmlspecialchars($template['title']); ?></td>
                                    <td>
                                        <?php
                                        $supportedChannels = json_decode($template['supported_channels'], true);
                                        foreach ($supportedChannels as $channel) {
                                            $channelName = '';
                                            $channelClass = '';
                                            switch ($channel) {
                                                case 'email':
                                                    $channelName = '邮件';
                                                    $channelClass = 'badge-primary';
                                                    break;
                                                case 'sms':
                                                    $channelName = '短信';
                                                    $channelClass = 'badge-success';
                                                    break;
                                                case 'in_app':
                                                    $channelName = '站内信';
                                                    $channelClass = 'badge-info';
                                                    break;
                                            }
                                            echo '<span class="badge ' . $channelClass . ' mr-1">' . $channelName . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $template['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $template['status'] === 'active' ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-info preview-template" 
                                                data-id="<?php echo $template['id']; ?>"
                                                data-variables='<?php echo htmlspecialchars($template['variables']); ?>'>
                                                预览
                                            </button>
                                            <a href="/admin/notification/templates/edit?id=<?php echo $template['id']; ?>" 
                                                class="btn btn-sm btn-primary">
                                                编辑
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-template" 
                                                data-id="<?php echo $template['id']; ?>">
                                                删除
                                            </button>
                                        </div>
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

<!-- 预览模态框 -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">模板预览</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="previewForm">
                    <input type="hidden" name="template_id" id="templateId">
                    <div id="variableInputs"></div>
                    <div class="preview-content mt-3" style="display: none;">
                        <h6>预览结果：</h6>
                        <div class="alert alert-info">
                            <h6 id="previewTitle"></h6>
                            <div id="previewContent"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                <button type="button" class="btn btn-primary" id="generatePreview">生成预览</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 预览模板
    $('.preview-template').click(function() {
        const templateId = $(this).data('id');
        const variables = JSON.parse($(this).data('variables'));
        
        // 清空并隐藏预览内容
        $('.preview-content').hide();
        $('#previewTitle, #previewContent').empty();
        
        // 设置模板ID
        $('#templateId').val(templateId);
        
        // 生成变量输入框
        const $inputs = $('#variableInputs').empty();
        variables.forEach(variable => {
            $inputs.append(`
                <div class="form-group">
                    <label>${variable.description}</label>
                    <input type="text" class="form-control" name="variables[${variable.name}]" 
                        placeholder="请输入${variable.description}">
                </div>
            `);
        });
        
        // 显示模态框
        $('#previewModal').modal('show');
    });
    
    // 生成预览
    $('#generatePreview').click(function() {
        const templateId = $('#templateId').val();
        const $form = $('#previewForm');
        const variables = {};
        
        // 收集变量值
        $form.find('input[name^="variables["]').each(function() {
            const name = $(this).attr('name').match(/variables\[(.*?)\]/)[1];
            variables[name] = $(this).val();
        });
        
        // 发送预览请求
        $.ajax({
            url: '/admin/notification/templates/preview',
            type: 'POST',
            data: {
                template_id: templateId,
                variables: JSON.stringify(variables)
            },
            success: function(response) {
                if (response.success) {
                    $('#previewTitle').text(response.data.title);
                    $('#previewContent').html(response.data.content);
                    $('.preview-content').show();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('预览失败');
            }
        });
    });
    
    // 删除模板
    $('.delete-template').click(function() {
        const templateId = $(this).data('id');
        
        if (confirm('确定要删除这个模板吗？')) {
            $.ajax({
                url: '/admin/notification/templates/delete',
                type: 'POST',
                data: { id: templateId },
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
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?> 