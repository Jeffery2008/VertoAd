<?php require_once ROOT_PATH . '/app/Views/admin/header.php'; ?>

<h1 class="mb-4">错误监控大屏</h1>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">总错误数</h5>
                <h2 class="display-4"><?php echo isset($dashboard['stats']['totalErrors']) ? $dashboard['stats']['totalErrors'] : 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">未解决错误</h5>
                <h2 class="display-4"><?php echo isset($dashboard['stats']['unresolvedErrors']) ? $dashboard['stats']['unresolvedErrors'] : 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">24小时内错误</h5>
                <h2 class="display-4"><?php echo isset($dashboard['stats']['last24HoursErrors']) ? $dashboard['stats']['last24HoursErrors'] : 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">解决率</h5>
                <h2 class="display-4">
                    <?php
                    if (isset($dashboard['stats']['totalErrors']) && $dashboard['stats']['totalErrors'] > 0) {
                        echo round(($dashboard['stats']['totalErrors'] - $dashboard['stats']['unresolvedErrors']) / $dashboard['stats']['totalErrors'] * 100) . '%';
                    } else {
                        echo '0%';
                    }
                    ?>
                </h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">最近7天错误趋势</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyErrorsChart" width="400" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">错误类型分布</h5>
            </div>
            <div class="card-body">
                <canvas id="errorTypeChart" width="400" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">最近错误</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>类型</th>
                                <th>消息</th>
                                <th>文件</th>
                                <th>行号</th>
                                <th>时间</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($dashboard['recentErrors']) && is_array($dashboard['recentErrors']) && !empty($dashboard['recentErrors'])): ?>
                                <?php foreach ($dashboard['recentErrors'] as $error): ?>
                                <tr>
                                    <td><?php echo $error['id']; ?></td>
                                    <td><span class="badge bg-danger"><?php echo $error['type']; ?></span></td>
                                    <td><?php echo substr($error['message'], 0, 50) . (strlen($error['message']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo basename($error['file']); ?></td>
                                    <td><?php echo $error['line']; ?></td>
                                    <td><?php echo $error['created_at']; ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch ($error['status']) {
                                            case 'new':
                                                $statusClass = 'bg-danger';
                                                break;
                                            case 'in_progress':
                                                $statusClass = 'bg-warning';
                                                break;
                                            case 'resolved':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'ignored':
                                                $statusClass = 'bg-secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $error['status']; ?></span>
                                    </td>
                                    <td>
                                        <a href="/admin/errors/view/<?php echo $error['id']; ?>" class="btn btn-sm btn-info">查看</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">暂无最近错误记录</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <a href="/admin/errors" class="btn btn-primary">查看所有错误</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">常见错误消息</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>错误消息</th>
                                <th>出现次数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($dashboard['commonMessages']) && is_array($dashboard['commonMessages']) && !empty($dashboard['commonMessages'])): ?>
                                <?php foreach ($dashboard['commonMessages'] as $message): ?>
                                <tr>
                                    <td><?php echo substr($message['message'], 0, 100) . (strlen($message['message']) > 100 ? '...' : ''); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $message['count']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">暂无常见错误消息</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">24小时内错误分布</h5>
            </div>
            <div class="card-body">
                <canvas id="hourlyErrorsChart" width="400" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- 实时数据刷新 -->
<script>
// 每60秒刷新一次实时数据
let realtimeRefreshInterval = setInterval(function() {
    fetch('/admin/errors/stats')
        .then(response => response.json())
        .then(data => {
            updateHourlyChart(data.hourly);
            // 其他实时数据更新...
        })
        .catch(error => console.error('Error fetching stats:', error));
}, 60000);

// 页面离开时清除定时器
window.addEventListener('beforeunload', function() {
    clearInterval(realtimeRefreshInterval);
});
</script>

<!-- Chart.js 图表初始化 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 每日错误趋势图
    const dailyCtx = document.getElementById('dailyErrorsChart').getContext('2d');
    const dailyLabels = [
        <?php 
        if (isset($dashboard['daily']) && is_array($dashboard['daily'])) {
            foreach ($dashboard['daily'] as $day) {
                echo "'" . date('m-d', strtotime($day['date'])) . "',";
            }
        }
        ?>
    ];
    const dailyData = [
        <?php 
        if (isset($dashboard['daily']) && is_array($dashboard['daily'])) {
            foreach ($dashboard['daily'] as $day) {
                echo $day['count'] . ",";
            }
        }
        ?>
    ];
    
    const dailyErrorsChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyLabels.length ? dailyLabels : ['无数据'],
            datasets: [{
                label: '每日错误数',
                data: dailyData.length ? dailyData : [0],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // 错误类型分布图
    const typeCtx = document.getElementById('errorTypeChart').getContext('2d');
    const typeLabels = [
        <?php 
        if (isset($dashboard['errorsByType']) && is_array($dashboard['errorsByType'])) {
            foreach ($dashboard['errorsByType'] as $type) {
                echo "'" . $type['type'] . "',";
            }
        }
        ?>
    ];
    const typeData = [
        <?php 
        if (isset($dashboard['errorsByType']) && is_array($dashboard['errorsByType'])) {
            foreach ($dashboard['errorsByType'] as $type) {
                echo $type['count'] . ",";
            }
        }
        ?>
    ];
    
    const errorTypeChart = new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: typeLabels.length ? typeLabels : ['无数据'],
            datasets: [{
                label: '错误类型',
                data: typeData.length ? typeData : [1],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(199, 199, 199, 0.6)',
                    'rgba(83, 102, 255, 0.6)',
                    'rgba(40, 159, 64, 0.6)',
                    'rgba(210, 199, 199, 0.6)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // 初始化小时错误图 (将从API获取数据)
    const hourlyCtx = document.getElementById('hourlyErrorsChart').getContext('2d');
    window.hourlyErrorsChart = new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => (i < 10 ? '0' : '') + i + ':00'),
            datasets: [{
                label: '每小时错误数',
                data: Array(24).fill(0),
                backgroundColor: 'rgba(54, 162, 235, 0.6)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // 加载24小时内的错误数据
    fetch('/admin/errors/stats')
        .then(response => response.json())
        .then(data => {
            if (data && data.hourly) {
                updateHourlyChart(data.hourly);
            }
        })
        .catch(error => console.error('Error fetching hourly stats:', error));
});

// 更新小时错误图表
function updateHourlyChart(hourlyData) {
    if (!hourlyData || !Array.isArray(hourlyData)) {
        console.error('Invalid hourly data format');
        return;
    }
    
    // 重置数据
    const hourCounts = Array(24).fill(0);
    
    // 填充数据
    hourlyData.forEach(item => {
        if (item && item.hour) {
            const hour = parseInt(item.hour.split(' ')[1].split(':')[0]);
            hourCounts[hour] = parseInt(item.count || 0);
        }
    });
    
    // 更新图表
    if (window.hourlyErrorsChart) {
        window.hourlyErrorsChart.data.datasets[0].data = hourCounts;
        window.hourlyErrorsChart.update();
    }
}
</script>

<?php require_once ROOT_PATH . '/app/Views/admin/footer.php'; ?> 