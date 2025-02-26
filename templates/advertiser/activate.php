<?php 
$pageTitle = "Activate Product Key"; 
include __DIR__ . '/layout.php';
?>

<div class="activate-container">
    <div class="card">
        <div class="card-header">
            <h2>Activate Your Product Key</h2>
        </div>
        <div class="card-body">
            <div id="activation-form-container">
                <form id="activation-form">
                    <div class="key-input-container">
                        <label for="key-segments">Enter Your Product Key:</label>
                        <div class="key-segments">
                            <input type="text" id="segment1" class="key-segment" maxlength="5" required>
                            <span class="key-separator">-</span>
                            <input type="text" id="segment2" class="key-segment" maxlength="5" required>
                            <span class="key-separator">-</span>
                            <input type="text" id="segment3" class="key-segment" maxlength="5" required>
                            <span class="key-separator">-</span>
                            <input type="text" id="segment4" class="key-segment" maxlength="5" required>
                            <span class="key-separator">-</span>
                            <input type="text" id="segment5" class="key-segment" maxlength="5" required>
                        </div>
                        <input type="hidden" id="full-key" name="key">
                        <input type="hidden" id="csrf-token" name="csrf_token" value="<?php echo $auth->generateCsrfToken(); ?>">
                    </div>
                    
                    <div class="form-info">
                        <div class="info-icon"><i class="fas fa-info-circle"></i></div>
                        <div class="info-text">
                            <p>Your product key should be in the format: XXXXX-XXXXX-XXXXX-XXXXX-XXXXX</p>
                            <p>Keys are not case-sensitive. Each key can only be used once.</p>
                        </div>
                    </div>
                    
                    <div class="current-balance">
                        <p>Current Balance: <span id="user-balance"><?php echo number_format($user->getBalance(), 2); ?></span> USD</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="activate-button" class="btn-primary">
                            <span class="btn-text">Activate Key</span>
                            <span class="btn-loading" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Processing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
            
            <div id="result-container" style="display: none;">
                <div id="success-message" style="display: none;">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Key Activated Successfully!</h3>
                    <div class="result-details">
                        <p>Amount Added: <span id="amount-added">0.00</span> USD</p>
                        <p>New Balance: <span id="new-balance">0.00</span> USD</p>
                    </div>
                    <div class="next-steps">
                        <a href="/advertiser/dashboard" class="btn btn-outline">Go to Dashboard</a>
                        <button id="activate-another" class="btn-secondary">Activate Another Key</button>
                    </div>
                </div>
                
                <div id="error-message" style="display: none;">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3>Activation Failed</h3>
                    <p id="error-details"></p>
                    <button id="try-again" class="btn-primary">Try Again</button>
                </div>
            </div>
            
            <div class="recent-activations">
                <h3>Recent Activations</h3>
                <?php 
                $keyRedemptionService = new \VertoAD\Core\Services\KeyRedemptionService(
                    $db, 
                    $logger, 
                    new \VertoAD\Core\Services\KeyGenerationService($db, $logger),
                    new \VertoAD\Core\Services\AccountService($db, $logger)
                );
                $activations = $keyRedemptionService->getUserActivationHistory($user->getId(), 3);
                
                if (empty($activations)): 
                ?>
                <p class="no-records">No recent activations found.</p>
                <?php else: ?>
                <div class="activations-list">
                    <?php foreach ($activations as $activation): ?>
                    <div class="activation-item">
                        <div class="activation-date">
                            <?php echo date('M j, Y', strtotime($activation['created_at'])); ?>
                        </div>
                        <div class="activation-details">
                            <p>Key: <?php echo substr($activation['key_value'], 0, 5) . '...' . substr($activation['key_value'], -5); ?></p>
                            <p>Amount: <?php echo number_format($activation['amount'], 2); ?> USD</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="activation-help">
        <h3>Need Help?</h3>
        <ul>
            <li><a href="/help/activation-faq">Activation FAQ</a></li>
            <li><a href="/help/where-to-buy">Where to Buy Keys</a></li>
            <li><a href="/contact/support">Contact Support</a></li>
        </ul>
    </div>
</div>

<script src="/static/js/activate.js"></script>
