<?php require_once 'templates/advertiser/layout.php'; ?>

<div class="windows-activation-wrapper">
    <div class="windows-dialog-outer">
        <div class="windows-dialog">
            <!-- Title Bar -->
            <div class="windows-titlebar">
                <div class="windows-titlebar-text">Activation Success</div>
            </div>

            <!-- Content -->
            <div class="windows-content">
                <div class="windows-header">
                    <div class="success-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                            <circle cx="24" cy="24" r="22" fill="#0dad0d"/>
                            <path d="M14 24l8 8 12-12" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h1>Activation completed</h1>
                </div>

                <div class="windows-body">
                    <div class="success-message">
                        <p>Your account has been activated successfully!</p>
                        <p class="amount-added">$<?= number_format($_GET['amount'], 2) ?> has been added to your balance.</p>
                    </div>

                    <div class="balance-info">
                        <div class="balance-row">
                            <span>Previous Balance:</span>
                            <span>$<?= number_format($previousBalance, 2) ?></span>
                        </div>
                        <div class="balance-row amount-added">
                            <span>Amount Added:</span>
                            <span>+ $<?= number_format($_GET['amount'], 2) ?></span>
                        </div>
                        <div class="balance-row total">
                            <span>New Balance:</span>
                            <span>$<?= number_format($newBalance, 2) ?></span>
                        </div>
                    </div>

                    <div class="windows-actions">
                        <button type="button" onclick="location.href='/advertiser/dashboard'" class="windows-button windows-button-primary">
                            Continue
                        </button>
                        <button type="button" onclick="location.href='/advertiser/activate'" class="windows-button">
                            Activate Another Key
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Base Windows Styles */
.windows-activation-wrapper {
    min-height: 100vh;
    background: #f0f0f0;
    padding: 40px 20px;
    font-family: "Segoe UI", sans-serif;
}

.windows-dialog-outer {
    max-width: 600px;
    margin: 0 auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.windows-dialog {
    background: white;
    border: 1px solid #d1d1d1;
    border-radius: 6px;
    overflow: hidden;
}

.windows-titlebar {
    background: linear-gradient(to bottom, #e9e9e9, #dfdfdf);
    padding: 8px 12px;
    border-bottom: 1px solid #d1d1d1;
}

.windows-titlebar-text {
    font-size: 12px;
    color: #333;
}

.windows-content {
    padding: 20px;
}

.windows-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.success-icon {
    width: 48px;
    height: 48px;
}

.windows-header h1 {
    font-size: 24px;
    font-weight: normal;
    color: #0dad0d;
    margin: 0;
}

.windows-body {
    color: #333;
    line-height: 1.5;
}

/* Success Message Styles */
.success-message {
    text-align: center;
    margin-bottom: 24px;
}

.success-message p {
    margin: 8px 0;
}

.amount-added {
    color: #0dad0d;
    font-weight: 600;
}

/* Balance Info Styles */
.balance-info {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 16px;
    margin: 24px 0;
}

.balance-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.balance-row:last-child {
    border-bottom: none;
}

.balance-row.total {
    font-weight: bold;
    font-size: 1.1em;
    padding-top: 16px;
    margin-top: 8px;
    border-top: 2px solid #e0e0e0;
}

.balance-row.amount-added {
    color: #0dad0d;
}

/* Button Styles */
.windows-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
}

.windows-button {
    padding: 8px 20px;
    border: 1px solid #d1d1d1;
    background: linear-gradient(to bottom, #ffffff, #f4f4f4);
    border-radius: 4px;
    cursor: pointer;
    font-family: "Segoe UI", sans-serif;
    font-size: 14px;
}

.windows-button-primary {
    background: linear-gradient(to bottom, #0dad0d, #0a900a);
    border: 1px solid #0a900a;
    color: white;
}

.windows-button-primary:hover {
    background: linear-gradient(to bottom, #0fc10f, #0ba40b);
}

.windows-button:hover {
    background: linear-gradient(to bottom, #f8f8f8, #ececec);
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.windows-dialog {
    animation: fadeIn 0.3s ease-out;
}
</style>
