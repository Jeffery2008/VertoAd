<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">广告位定向效果统计</h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <a href="/publisher/zone-targeting" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> 返回定向规则管理
                        </a>
                        
                        <form class="form-inline float-right">
                            <div class="form-group mx-2">
                                <label class="mr-2">开始日期</label>
                                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                            </div>
                            <div class="form-group mx-2">
                                <label class="mr-2">结束日期</label>
                                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> 筛选
                            </button>
                        </form>
                    </div>

                    <?php foreach ($stats as $zoneId => $data): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                广告位：<?= htmlspecialchars($data['zone']['name']) ?>
                                <small class="text-muted">(ID: <?= $zoneId ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- 广告类型统计 -->
                                <div class="col-md-6">
                                    <h6>广告类型分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>广告类型</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                    <th>收入</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $typeStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    if (!isset($typeStats[$stat['ad_type']])) {
                                                        $typeStats[$stat['ad_type']] = [
                                                            'views' => 0,
                                                            'clicks' => 0,
                                                            'revenue' => 0
                                                        ];
                                                    }
                                                    $typeStats[$stat['ad_type']]['views'] += $stat['total_views'];
                                                    $typeStats[$stat['ad_type']]['clicks'] += $stat['total_clicks'];
                                                    $typeStats[$stat['ad_type']]['revenue'] += $stat['total_revenue'];
                                                }
                                                foreach ($typeStats as $type => $stat):
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($type) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                    <td>¥<?= number_format($stat['revenue'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- 广告主统计 -->
                                <div class="col-md-6">
                                    <h6>广告主分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>广告主</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                    <th>收入</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $advertiserStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    if (!isset($advertiserStats[$stat['advertiser_id']])) {
                                                        $advertiserStats[$stat['advertiser_id']] = [
                                                            'name' => $stat['advertiser_name'],
                                                            'views' => 0,
                                                            'clicks' => 0,
                                                            'revenue' => 0
                                                        ];
                                                    }
                                                    $advertiserStats[$stat['advertiser_id']]['views'] += $stat['total_views'];
                                                    $advertiserStats[$stat['advertiser_id']]['clicks'] += $stat['total_clicks'];
                                                    $advertiserStats[$stat['advertiser_id']]['revenue'] += $stat['total_revenue'];
                                                }
                                                foreach ($advertiserStats as $advertiserId => $stat):
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($stat['name']) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                    <td>¥<?= number_format($stat['revenue'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <!-- 内容类别统计 -->
                                <div class="col-md-6">
                                    <h6>内容类别分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>类别</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                    <th>收入</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $categoryStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    if (!isset($categoryStats[$stat['category']])) {
                                                        $categoryStats[$stat['category']] = [
                                                            'views' => 0,
                                                            'clicks' => 0,
                                                            'revenue' => 0
                                                        ];
                                                    }
                                                    $categoryStats[$stat['category']]['views'] += $stat['total_views'];
                                                    $categoryStats[$stat['category']]['clicks'] += $stat['total_clicks'];
                                                    $categoryStats[$stat['category']]['revenue'] += $stat['total_revenue'];
                                                }
                                                foreach ($categoryStats as $category => $stat):
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($category) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                    <td>¥<?= number_format($stat['revenue'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- 每日趋势 -->
                                <div class="col-md-6">
                                    <h6>每日趋势</h6>
                                    <canvas id="dailyTrend-<?= $zoneId ?>" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 日期选择器初始化
    $('input[type="date"]').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // 初始化每日趋势图表
    <?php foreach ($stats as $zoneId => $data): ?>
    new Chart(document.getElementById('dailyTrend-<?= $zoneId ?>').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($data['stats'], 'date')) ?>,
            datasets: [{
                label: '展示次数',
                data: <?= json_encode(array_column($data['stats'], 'total_views')) ?>,
                borderColor: '#4e73df',
                fill: false
            }, {
                label: '点击次数',
                data: <?= json_encode(array_column($data['stats'], 'total_clicks')) ?>,
                borderColor: '#1cc88a',
                fill: false
            }, {
                label: '收入',
                data: <?= json_encode(array_column($data['stats'], 'total_revenue')) ?>,
                borderColor: '#f6c23e',
                fill: false,
                yAxisID: 'revenue'
            }]
        },
        options: {
            responsive: true,
            scales: {
                yAxes: [{
                    id: 'counts',
                    position: 'left',
                    ticks: {
                        beginAtZero: true
                    }
                }, {
                    id: 'revenue',
                    position: 'right',
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            return '¥' + value;
                        }
                    }
                }]
            }
        }
    });
    <?php endforeach; ?>
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?> 