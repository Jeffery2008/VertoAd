<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">
                广告位广告管理
                <small class="text-muted"><?= htmlspecialchars($zone['name']) ?></small>
            </h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <a href="/publisher/zones" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> 返回广告位列表
                        </a>
                    </div>

                    <!-- 广告筛选器 -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">广告筛选</h5>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <div class="form-group mx-2">
                                    <label class="mr-2">广告类型</label>
                                    <select class="form-control" name="type">
                                        <option value="">全部</option>
                                        <option value="image">图片广告</option>
                                        <option value="text">文字广告</option>
                                        <option value="video">视频广告</option>
                                        <option value="rich">富媒体广告</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mx-2">
                                    <label class="mr-2">广告主</label>
                                    <select class="form-control" name="advertiser">
                                        <option value="">全部</option>
                                        <?php foreach ($advertisers ?? [] as $advertiser): ?>
                                        <option value="<?= $advertiser['id'] ?>"><?= htmlspecialchars($advertiser['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group mx-2">
                                    <label class="mr-2">内容类别</label>
                                    <select class="form-control" name="category">
                                        <option value="">全部</option>
                                        <option value="technology">科技</option>
                                        <option value="fashion">时尚</option>
                                        <option value="food">美食</option>
                                        <option value="travel">旅游</option>
                                        <option value="education">教育</option>
                                        <option value="finance">金融</option>
                                        <option value="game">游戏</option>
                                        <option value="other">其他</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary mx-2">
                                    <i class="fas fa-filter"></i> 筛选
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- 广告列表 -->
                    <div class="row" id="adList">
                        <?php foreach ($ads as $ad): ?>
                        <div class="col-md-4 mb-4 ad-item" 
                             data-type="<?= $ad['type'] ?>"
                             data-advertiser="<?= $ad['advertiser_id'] ?>"
                             data-category="<?= $ad['category'] ?>">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($ad['title']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($ad['description']) ?></p>
                                    <ul class="list-unstyled">
                                        <li><strong>类型：</strong><?= htmlspecialchars($ad['type']) ?></li>
                                        <li><strong>尺寸：</strong><?= htmlspecialchars($ad['size']) ?></li>
                                        <li><strong>广告主：</strong><?= htmlspecialchars($ad['advertiser_name']) ?></li>
                                        <li><strong>类别：</strong><?= htmlspecialchars($ad['category']) ?></li>
                                        <li><strong>预算：</strong>¥<?= number_format($ad['budget'], 2) ?></li>
                                    </ul>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input ad-selector" 
                                               id="ad-<?= $ad['id'] ?>" 
                                               value="<?= $ad['id'] ?>"
                                               <?= in_array($ad['id'], $selectedAds) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="ad-<?= $ad['id'] ?>">选择此广告</label>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-sm btn-info preview-ad" data-ad-id="<?= $ad['id'] ?>">
                                        <i class="fas fa-eye"></i> 预览
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 广告预览模态框 -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">广告预览</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 广告筛选
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        
        const type = $('[name="type"]').val();
        const advertiser = $('[name="advertiser"]').val();
        const category = $('[name="category"]').val();
        
        $('.ad-item').each(function() {
            const $item = $(this);
            let show = true;
            
            if (type && $item.data('type') !== type) show = false;
            if (advertiser && $item.data('advertiser') !== advertiser) show = false;
            if (category && $item.data('category') !== category) show = false;
            
            $item.toggle(show);
        });
    });
    
    // 选择广告
    $('.ad-selector').on('change', function() {
        const selectedAds = $('.ad-selector:checked').map(function() {
            return $(this).val();
        }).get();
        
        $.ajax({
            url: '/publisher/update-zone-ads/<?= $zone['id'] ?>',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                ad_ids: selectedAds
            }),
            success: function(response) {
                if (!response.success) {
                    alert('保存失败：' + response.error);
                }
            },
            error: function() {
                alert('保存失败，请重试');
            }
        });
    });
    
    // 预览广告
    $('.preview-ad').click(function() {
        const adId = $(this).data('ad-id');
        const ad = <?= json_encode($ads) ?>.find(a => a.id === adId);
        
        let previewHtml = '';
        switch (ad.type) {
            case 'image':
                previewHtml = `<img src="${ad.content}" class="img-fluid" alt="${ad.title}">`;
                break;
            case 'text':
                previewHtml = `<div class="p-3 border">${ad.content}</div>`;
                break;
            case 'video':
                previewHtml = `<video controls class="w-100"><source src="${ad.content}" type="video/mp4"></video>`;
                break;
            case 'rich':
                previewHtml = ad.content;
                break;
        }
        
        $('#previewContent').html(previewHtml);
        $('#previewModal').modal('show');
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?> 