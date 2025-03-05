<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">广告位定向规则管理</h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <a href="/publisher/zone-targeting-stats" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> 查看统计数据
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>广告位ID</th>
                                    <th>广告位名称</th>
                                    <th>广告类型限制</th>
                                    <th>广告主限制</th>
                                    <th>内容类别限制</th>
                                    <th>预算限制</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($targetingData as $zoneId => $data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($zoneId) ?></td>
                                    <td><?= htmlspecialchars($data['zone']['name']) ?></td>
                                    <td>
                                        <?php if (!empty($data['targeting']['ad_types'])): ?>
                                            <?= implode(', ', $data['targeting']['ad_types']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">不限制</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($data['targeting']['advertisers'])): ?>
                                            <?= implode(', ', $data['targeting']['advertisers']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">不限制</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($data['targeting']['categories'])): ?>
                                            <?= implode(', ', $data['targeting']['categories']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">不限制</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($data['targeting']['budget'])): ?>
                                            最低: ¥<?= number_format($data['targeting']['budget']['min'], 2) ?><br>
                                            最高: ¥<?= number_format($data['targeting']['budget']['max'], 2) ?>
                                        <?php else: ?>
                                            <span class="text-muted">不限制</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-targeting" data-zone-id="<?= $zoneId ?>">
                                            <i class="fas fa-edit"></i> 编辑
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

<!-- 编辑定向规则模态框 -->
<div class="modal fade" id="editTargetingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑广告位定向规则</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="targetingForm">
                    <input type="hidden" id="editZoneId" name="zoneId">
                    
                    <div class="form-group">
                        <label>广告类型限制</label>
                        <select class="form-control" name="ad_types" multiple>
                            <option value="image">图片广告</option>
                            <option value="text">文字广告</option>
                            <option value="video">视频广告</option>
                            <option value="rich">富媒体广告</option>
                        </select>
                        <small class="form-text text-muted">不选择则表示接受所有类型的广告</small>
                    </div>
                    
                    <div class="form-group">
                        <label>广告主限制</label>
                        <select class="form-control" name="advertisers" multiple>
                            <?php foreach ($advertisers ?? [] as $advertiser): ?>
                            <option value="<?= $advertiser['id'] ?>"><?= htmlspecialchars($advertiser['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">不选择则表示接受所有广告主的广告</small>
                    </div>
                    
                    <div class="form-group">
                        <label>内容类别限制</label>
                        <select class="form-control" name="categories" multiple>
                            <option value="technology">科技</option>
                            <option value="fashion">时尚</option>
                            <option value="food">美食</option>
                            <option value="travel">旅游</option>
                            <option value="education">教育</option>
                            <option value="finance">金融</option>
                            <option value="game">游戏</option>
                            <option value="other">其他</option>
                        </select>
                        <small class="form-text text-muted">不选择则表示接受所有类别的广告</small>
                    </div>
                    
                    <div class="form-group">
                        <label>预算限制</label>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="small">最低预算</label>
                                <input type="number" class="form-control" name="budget_min" min="0" step="0.01" placeholder="最低预算">
                            </div>
                            <div class="col-md-6">
                                <label class="small">最高预算</label>
                                <input type="number" class="form-control" name="budget_max" min="0" step="0.01" placeholder="最高预算">
                            </div>
                        </div>
                        <small class="form-text text-muted">留空则表示不限制预算范围</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="saveTargeting">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 初始化多选框
    $('select[multiple]').select2({
        width: '100%',
        placeholder: '请选择'
    });
    
    // 编辑按钮点击事件
    $('.edit-targeting').click(function() {
        const zoneId = $(this).data('zone-id');
        const targeting = <?= json_encode($targetingData) ?>[zoneId].targeting;
        
        $('#editZoneId').val(zoneId);
        
        // 填充表单数据
        $('select[name="ad_types"]').val(targeting.ad_types || []).trigger('change');
        $('select[name="advertisers"]').val(targeting.advertisers || []).trigger('change');
        $('select[name="categories"]').val(targeting.categories || []).trigger('change');
        $('input[name="budget_min"]').val(targeting.budget ? targeting.budget.min : '');
        $('input[name="budget_max"]').val(targeting.budget ? targeting.budget.max : '');
        
        $('#editTargetingModal').modal('show');
    });
    
    // 保存定向规则
    $('#saveTargeting').click(function() {
        const zoneId = $('#editZoneId').val();
        const targeting = {
            ad_types: $('select[name="ad_types"]').val(),
            advertisers: $('select[name="advertisers"]').val(),
            categories: $('select[name="categories"]').val(),
            budget: {
                min: parseFloat($('input[name="budget_min"]').val()) || null,
                max: parseFloat($('input[name="budget_max"]').val()) || null
            }
        };
        
        // 如果预算限制都为空，则移除预算对象
        if (targeting.budget.min === null && targeting.budget.max === null) {
            delete targeting.budget;
        }
        
        $.ajax({
            url: '/publisher/update-zone-targeting',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                zones: {
                    [zoneId]: targeting
                }
            }),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('保存失败：' + response.message);
                }
            },
            error: function() {
                alert('保存失败，请重试');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?> 