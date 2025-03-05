<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">广告位管理</h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#createZoneModal">
                            <i class="fas fa-plus"></i> 创建新广告位
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名称</th>
                                    <th>尺寸</th>
                                    <th>类型</th>
                                    <th>状态</th>
                                    <th>已选广告数</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zones as $zone): ?>
                                <tr>
                                    <td><?= htmlspecialchars($zone['id']) ?></td>
                                    <td><?= htmlspecialchars($zone['name']) ?></td>
                                    <td><?= htmlspecialchars($zone['size']) ?></td>
                                    <td><?= htmlspecialchars($zone['type']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $zone['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $zone['status'] === 'active' ? '启用' : '停用' ?>
                                        </span>
                                    </td>
                                    <td><?= count($zone['selected_ads'] ?? []) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info edit-zone" data-zone-id="<?= $zone['id'] ?>">
                                                <i class="fas fa-edit"></i> 编辑
                                            </button>
                                            <a href="/publisher/zone-ads/<?= $zone['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-ad"></i> 管理广告
                                            </a>
                                            <button class="btn btn-sm btn-danger delete-zone" data-zone-id="<?= $zone['id'] ?>">
                                                <i class="fas fa-trash"></i> 删除
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

<!-- 创建广告位模态框 -->
<div class="modal fade" id="createZoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">创建新广告位</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createZoneForm">
                    <div class="form-group">
                        <label>广告位名称</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>广告位尺寸</label>
                        <select class="form-control" name="size" required>
                            <option value="728x90">横幅广告 (728x90)</option>
                            <option value="300x250">中矩形广告 (300x250)</option>
                            <option value="160x600">竖幅广告 (160x600)</option>
                            <option value="320x50">移动横幅 (320x50)</option>
                            <option value="300x600">大矩形广告 (300x600)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>广告位类型</label>
                        <select class="form-control" name="type" required>
                            <option value="display">展示广告</option>
                            <option value="text">文字广告</option>
                            <option value="native">原生广告</option>
                            <option value="video">视频广告</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>网站URL</label>
                        <input type="url" class="form-control" name="website_url">
                    </div>
                    
                    <div class="form-group">
                        <label>描述</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="saveZone">保存</button>
            </div>
        </div>
    </div>
</div>

<!-- 编辑广告位模态框 -->
<div class="modal fade" id="editZoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑广告位</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editZoneForm">
                    <input type="hidden" name="zone_id">
                    
                    <div class="form-group">
                        <label>广告位名称</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>广告位尺寸</label>
                        <select class="form-control" name="size" required>
                            <option value="728x90">横幅广告 (728x90)</option>
                            <option value="300x250">中矩形广告 (300x250)</option>
                            <option value="160x600">竖幅广告 (160x600)</option>
                            <option value="320x50">移动横幅 (320x50)</option>
                            <option value="300x600">大矩形广告 (300x600)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>广告位类型</label>
                        <select class="form-control" name="type" required>
                            <option value="display">展示广告</option>
                            <option value="text">文字广告</option>
                            <option value="native">原生广告</option>
                            <option value="video">视频广告</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>状态</label>
                        <select class="form-control" name="status">
                            <option value="active">启用</option>
                            <option value="inactive">停用</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>网站URL</label>
                        <input type="url" class="form-control" name="website_url">
                    </div>
                    
                    <div class="form-group">
                        <label>描述</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="updateZone">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 创建广告位
    $('#saveZone').click(function() {
        const formData = {};
        $('#createZoneForm').serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });
        
        $.ajax({
            url: '/publisher/create-zone',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('创建失败：' + response.error);
                }
            },
            error: function() {
                alert('创建失败，请重试');
            }
        });
    });
    
    // 编辑广告位
    $('.edit-zone').click(function() {
        const zoneId = $(this).data('zone-id');
        const zone = <?= json_encode($zones) ?>.find(z => z.id === zoneId);
        
        const form = $('#editZoneForm');
        form.find('[name="zone_id"]').val(zone.id);
        form.find('[name="name"]').val(zone.name);
        form.find('[name="size"]').val(zone.size);
        form.find('[name="type"]').val(zone.type);
        form.find('[name="status"]').val(zone.status);
        form.find('[name="website_url"]').val(zone.website_url);
        form.find('[name="description"]').val(zone.description);
        
        $('#editZoneModal').modal('show');
    });
    
    // 更新广告位
    $('#updateZone').click(function() {
        const formData = {};
        $('#editZoneForm').serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });
        
        const zoneId = formData.zone_id;
        delete formData.zone_id;
        
        $.ajax({
            url: '/publisher/update-zone/' + zoneId,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('更新失败：' + response.error);
                }
            },
            error: function() {
                alert('更新失败，请重试');
            }
        });
    });
    
    // 删除广告位
    $('.delete-zone').click(function() {
        if (!confirm('确定要删除这个广告位吗？')) {
            return;
        }
        
        const zoneId = $(this).data('zone-id');
        
        $.ajax({
            url: '/publisher/delete-zone/' + zoneId,
            method: 'POST',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('删除失败：' + response.error);
                }
            },
            error: function() {
                alert('删除失败，请重试');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?> 