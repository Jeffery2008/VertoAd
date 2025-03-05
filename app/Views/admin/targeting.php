<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">广告定向规则管理</h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <a href="/admin/targeting-stats" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> 查看统计数据
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>广告ID</th>
                                    <th>广告标题</th>
                                    <th>地理位置定向</th>
                                    <th>设备定向</th>
                                    <th>时间定向</th>
                                    <th>语言定向</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($targetingData as $adId => $data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($adId) ?></td>
                                    <td><?= htmlspecialchars($data['ad']['title']) ?></td>
                                    <td>
                                        <?php if (!empty($data['targeting']['geo'])): ?>
                                            <ul class="list-unstyled">
                                                <?php if (!empty($data['targeting']['geo']['provinces'])): ?>
                                                    <li>省份: <?= implode(', ', $data['targeting']['geo']['provinces']) ?></li>
                                                <?php endif; ?>
                                                <?php if (!empty($data['targeting']['geo']['regions'])): ?>
                                                    <li>地区: <?= implode(', ', $data['targeting']['geo']['regions']) ?></li>
                                                <?php endif; ?>
                                                <?php if (!empty($data['targeting']['geo']['cities'])): ?>
                                                    <li>城市: <?= implode(', ', $data['targeting']['geo']['cities']) ?></li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-muted">未设置</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($data['targeting']['devices'])): ?>
                                            <?= implode(', ', $data['targeting']['devices']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">未设置</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($data['targeting']['schedule'])): ?>
                                            时区: <?= $data['targeting']['schedule']['timezone'] ?? 'Asia/Shanghai' ?><br>
                                            时段: <?= implode(', ', $data['targeting']['schedule']['hours'] ?? []) ?>
                                        <?php else: ?>
                                            <span class="text-muted">未设置</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($data['targeting']['language'])): ?>
                                            <?= implode(', ', $data['targeting']['language']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">未设置</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-targeting" data-ad-id="<?= $adId ?>">
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
                <h5 class="modal-title">编辑定向规则</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="targetingForm">
                    <input type="hidden" id="editAdId" name="adId">
                    
                    <div class="form-group">
                        <label>地理位置定向</label>
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="provinces" placeholder="省份（多个用逗号分隔）">
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="regions" placeholder="地区代码">
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="cities" placeholder="城市代码">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>设备定向</label>
                        <select class="form-control" name="devices" multiple>
                            <option value="desktop">桌面设备</option>
                            <option value="mobile">移动设备</option>
                            <option value="tablet">平板设备</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>时间定向</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="timezone" value="Asia/Shanghai" placeholder="时区">
                            </div>
                            <div class="col-md-6">
                                <select class="form-control" name="hours" multiple>
                                    <?php for($i = 0; $i < 24; $i++): ?>
                                        <option value="<?= $i ?>"><?= sprintf('%02d:00', $i) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>语言定向</label>
                        <select class="form-control" name="language" multiple>
                            <option value="zh">中文</option>
                            <option value="en">英文</option>
                            <option value="ja">日文</option>
                            <option value="ko">韩文</option>
                        </select>
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
        const adId = $(this).data('ad-id');
        const targeting = <?= json_encode($targetingData) ?>[adId].targeting;
        
        $('#editAdId').val(adId);
        
        // 填充表单数据
        $('input[name="provinces"]').val(targeting.geo.provinces.join(','));
        $('input[name="regions"]').val(targeting.geo.regions.join(','));
        $('input[name="cities"]').val(targeting.geo.cities.join(','));
        
        $('select[name="devices"]').val(targeting.devices).trigger('change');
        $('input[name="timezone"]').val(targeting.schedule.timezone);
        $('select[name="hours"]').val(targeting.schedule.hours).trigger('change');
        $('select[name="language"]').val(targeting.language).trigger('change');
        
        $('#editTargetingModal').modal('show');
    });
    
    // 保存定向规则
    $('#saveTargeting').click(function() {
        const adId = $('#editAdId').val();
        const targeting = {
            geo: {
                provinces: $('input[name="provinces"]').val().split(',').filter(Boolean),
                regions: $('input[name="regions"]').val().split(',').filter(Boolean),
                cities: $('input[name="cities"]').val().split(',').filter(Boolean)
            },
            devices: $('select[name="devices"]').val(),
            schedule: {
                timezone: $('input[name="timezone"]').val(),
                hours: $('select[name="hours"]').val().map(Number)
            },
            language: $('select[name="language"]').val()
        };
        
        $.ajax({
            url: '/admin/update-targeting',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                ads: {
                    [adId]: targeting
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