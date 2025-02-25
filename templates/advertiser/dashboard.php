<?php require_once 'layout.php'; ?>

<?php
/** @var array $advertiser */
?>

<div class="container">
    <h2>Advertiser Dashboard</h2>
    <p>Welcome, <?php echo htmlspecialchars($advertiser['username']); ?>!</p>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-light mb-3">
                <div class="card-header">Account Balance</div>
                <div class="card-body">
                    <h4 class="card-title">$<?php // TODO: Display actual balance ?>0.00</h4>
                    <a href="/advertiser/account" class="btn btn-primary btn-sm">View Account</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light mb-3">
                <div class="card-header">Ad Performance</div>
                <div class="card-body">
                    <p class="card-text">Impressions: <?php // TODO: Display actual impressions ?>0</p>
                    <p class="card-text">Clicks: <?php // TODO: Display actual clicks ?>0</p>
                    <a href="/advertiser/ads" class="btn btn-primary btn-sm">View Ads</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-light mb-3">
                <div class="card-header">Create New Ad</div>
                <div class="card-body">
                    <p class="card-text">Start a new campaign</p>
                    <a href="/advertiser/create-ad" class="btn btn-success btn-sm">Create Ad</a>
                </div>
            </div>
        </div>
    </div>
    
    <h3>Recent Activity</h3>
    <p>TODO: Display recent ad activity, stats, etc.</p>
</div>
