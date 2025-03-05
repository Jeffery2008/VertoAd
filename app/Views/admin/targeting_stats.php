<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">广告定向效果统计</h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <a href="/admin/targeting" class="btn btn-secondary">
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

                    <?php foreach ($stats as $adId => $data): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                广告：<?= htmlspecialchars($data['ad']['title']) ?>
                                <small class="text-muted">(ID: <?= $adId ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- 地理位置统计 -->
                                <div class="col-md-6">
                                    <h6>地理位置分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>省份</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $geoStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    $key = $stat['region'] . '|' . $stat['city'];
                                                    if (!isset($geoStats[$key])) {
                                                        $geoStats[$key] = [
                                                            'views' => 0,
                                                            'clicks' => 0
                                                        ];
                                                    }
                                                    $geoStats[$key]['views'] += $stat['total_views'];
                                                    $geoStats[$key]['clicks'] += $stat['total_clicks'];
                                                }
                                                foreach ($geoStats as $key => $stat):
                                                    list($region, $city) = explode('|', $key);
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($region) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- 设备类型统计 -->
                                <div class="col-md-6">
                                    <h6>设备类型分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>设备类型</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $deviceStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    if (!isset($deviceStats[$stat['device']])) {
                                                        $deviceStats[$stat['device']] = [
                                                            'views' => 0,
                                                            'clicks' => 0
                                                        ];
                                                    }
                                                    $deviceStats[$stat['device']]['views'] += $stat['total_views'];
                                                    $deviceStats[$stat['device']]['clicks'] += $stat['total_clicks'];
                                                }
                                                foreach ($deviceStats as $device => $stat):
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($device) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <!-- 时间分布统计 -->
                                <div class="col-md-6">
                                    <h6>时间分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>时段</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $hourStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    if (!isset($hourStats[$stat['hour']])) {
                                                        $hourStats[$stat['hour']] = [
                                                            'views' => 0,
                                                            'clicks' => 0
                                                        ];
                                                    }
                                                    $hourStats[$stat['hour']]['views'] += $stat['total_views'];
                                                    $hourStats[$stat['hour']]['clicks'] += $stat['total_clicks'];
                                                }
                                                ksort($hourStats);
                                                foreach ($hourStats as $hour => $stat):
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= sprintf('%02d:00', $hour) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- 语言分布统计 -->
                                <div class="col-md-6">
                                    <h6>语言分布</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>语言</th>
                                                    <th>展示次数</th>
                                                    <th>点击次数</th>
                                                    <th>点击率</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $langStats = [];
                                                foreach ($data['stats'] as $stat) {
                                                    if (!isset($langStats[$stat['language']])) {
                                                        $langStats[$stat['language']] = [
                                                            'views' => 0,
                                                            'clicks' => 0
                                                        ];
                                                    }
                                                    $langStats[$stat['language']]['views'] += $stat['total_views'];
                                                    $langStats[$stat['language']]['clicks'] += $stat['total_clicks'];
                                                }
                                                foreach ($langStats as $lang => $stat):
                                                    $ctr = $stat['views'] > 0 ? round($stat['clicks'] / $stat['views'] * 100, 2) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($lang) ?></td>
                                                    <td><?= number_format($stat['views']) ?></td>
                                                    <td><?= number_format($stat['clicks']) ?></td>
                                                    <td><?= $ctr ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?> 