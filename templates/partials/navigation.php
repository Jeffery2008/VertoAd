<!-- Analytics Dropdown -->
<?php if ($isAdmin || $isAdvertiser): ?>
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="analyticsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-chart-line"></i> Analytics
    </a>
    <div class="dropdown-menu" aria-labelledby="analyticsDropdown">
        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/analytics/dashboard">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/analytics/conversions">
            <i class="fas fa-exchange-alt"></i> Conversions
        </a>
        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/analytics/roi">
            <i class="fas fa-chart-pie"></i> ROI Analysis
        </a>
        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/analytics/export-csv">
            <i class="fas fa-file-csv"></i> Export Data
        </a>
    </div>
</li>
<?php endif; ?> 