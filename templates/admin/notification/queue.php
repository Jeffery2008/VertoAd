<?php include __DIR__ . '/../../header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">通知队列监控</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- 健康状态 -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-<?php echo $health['status'] === 'healthy' ? 'success' : ($health['status'] === 'warning' ? 'warning' : 'danger'); ?>">
                                <h5><i class="icon fas fa-<?php echo $health['status'] === 'healthy' ? 'check' : 'exclamation-triangle'; ?>"></i> 队列状态</h5>
                                <?php if ($health['status'] === 'healthy'): ?>
                                    <p>队列运行正常</p>
                                <?php else: ?>
                                    <?php foreach ($health['issues'] as $issue): ?>
                                        <p><?php echo htmlspecialchars($issue['message']); ?></p>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 总体统计 -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['overall']['total']); ?></h3>
                                    <p>总通知数</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['overall']['pending']); ?></h3>
                                    <p>待处理</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['overall']['completed']); ?></h3>
                                    <p>已完成</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['overall']['failed']); ?></h3>
                                    <p>失败</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 按渠道统计 -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">渠道统计</h3>
                                </div>
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-hover text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>渠道</th>
                                                <th>总数</th>
                                                <th>完成</th>
                                                <th>失败</th>
                                                <th>平均处理时间</th>
                                                <th>成功率</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['by_channel'] as $channel): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($channel['channel_type']); ?></td>
                                                    <td><?php echo number_format($channel['total']); ?></td>
                                                    <td><?php echo number_format($channel['completed']); ?></td>
                                                    <td><?php echo number_format($channel['failed']); ?></td>
                                                    <td><?php echo number_format($channel['avg_processing_time'], 2); ?>秒</td>
                                                    <td>
                                                        <?php
                                                        $successRate = ($channel['completed'] / $channel['total']) * 100;
                                                        $colorClass = $successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger');
                                                        ?>
                                                        <div class="progress progress-xs">
                                                            <div class="progress-bar bg-<?php echo $colorClass; ?>" style="width: <?php echo $successRate; ?>%"></div>
                                                        </div>
                                                        <span class="badge bg-<?php echo $colorClass; ?>"><?php echo number_format($successRate, 2); ?>%</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 24小时趋势图 -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">24小时趋势</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="hourlyChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 问题模板 -->
                    <?php if (!empty($stats['problem_templates'])): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">问题模板</h3>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>模板名称</th>
                                                    <th>总尝试次数</th>
                                                    <th>失败次数</th>
                                                    <th>失败率</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats['problem_templates'] as $template): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($template['template_name']); ?></td>
                                                        <td><?php echo number_format($template['total_attempts']); ?></td>
                                                        <td><?php echo number_format($template['failed_attempts']); ?></td>
                                                        <td>
                                                            <span class="badge bg-danger">
                                                                <?php echo number_format($template['failure_rate'], 2); ?>%
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 准备24小时趋势图数据
    var hourlyData = <?php echo json_encode($stats['hourly']); ?>;
    var labels = hourlyData.map(function(item) { return item.hour; });
    var totalData = hourlyData.map(function(item) { return item.total; });
    var completedData = hourlyData.map(function(item) { return item.completed; });
    var failedData = hourlyData.map(function(item) { return item.failed; });
    
    // 创建图表
    var ctx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '总数',
                data: totalData,
                borderColor: '#17a2b8',
                fill: false
            }, {
                label: '完成',
                data: completedData,
                borderColor: '#28a745',
                fill: false
            }, {
                label: '失败',
                data: failedData,
                borderColor: '#dc3545',
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
    
    // 自动刷新
    setInterval(function() {
        location.reload();
    }, 60000); // 每分钟刷新一次
});
</script>

<?php include __DIR__ . '/../../footer.php'; ?> 