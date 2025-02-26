<?php require_once TEMPLATES_PATH . '/advertiser/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= URL_ROOT ?>/advertiser/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= URL_ROOT ?>/advertiser/conversion-pixels">Conversion Pixels</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pixel Code</li>
                </ol>
            </nav>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Conversion Pixel Code: <?= htmlspecialchars($pixelName) ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Pixel Type:</strong> <?= htmlspecialchars($typeName) ?>
                    </div>
                    
                    <h5 class="mb-3">Implementation Instructions</h5>
                    <p>Copy and paste the following code into your website's conversion page (e.g., checkout confirmation, sign-up success page) just before the closing <code>&lt;/body&gt;</code> tag:</p>
                    
                    <div class="form-group">
                        <label for="pixelCodeBox"><strong>Basic Tracking Code</strong></label>
                        <div class="input-group">
                            <textarea id="pixelCodeBox" class="form-control code-textarea" rows="10" readonly><?= htmlspecialchars($pixelCode) ?></textarea>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary copy-btn" data-target="pixelCodeBox" type="button">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">This code will automatically pick up ad_id and click_id parameters from the URL.</small>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mt-4 mb-3">Advanced Implementation</h5>
                    
                    <div class="form-group">
                        <label for="advancedCodeBox"><strong>Manual Implementation</strong></label>
                        <div class="input-group">
                            <textarea id="advancedCodeBox" class="form-control code-textarea" rows="6" readonly>&lt;script type="text/javascript" src="<?= URL_ROOT ?>/static/js/hfi-pixel.js" data-pixel-id="<?= htmlspecialchars($pixel['pixel_id']) ?>" data-auto-track="true"&gt;&lt;/script&gt;

&lt;!-- Or trigger conversion manually --&gt;
&lt;script&gt;
  // Call this when a conversion happens
  HFITrack({
    value: '99.99',  // Optional: Conversion value
    order_id: 'ORDER123'  // Optional: Your order/transaction ID
  });
&lt;/script&gt;</textarea>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary copy-btn" data-target="advancedCodeBox" type="button">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Use this if you need more control over when and how conversions are tracked.</small>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Implementation Examples</h5>
                    
                    <div class="accordion" id="implementationExamples">
                        <div class="card">
                            <div class="card-header" id="headingOne">
                                <h2 class="mb-0">
                                    <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        E-commerce Purchase Tracking (with Order Value)
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#implementationExamples">
                                <div class="card-body">
                                    <pre class="p-3 bg-light"><code>&lt;script type="text/javascript" src="<?= URL_ROOT ?>/static/js/hfi-pixel.js" data-pixel-id="<?= htmlspecialchars($pixel['pixel_id']) ?>"&gt;&lt;/script&gt;

&lt;script&gt;
  // On your order confirmation page
  document.addEventListener('DOMContentLoaded', function() {
    HFITrack({
      value: '<?php echo "<?= \$order->total_amount ?>"; ?>',  // Dynamic order value
      order_id: '<?php echo "<?= \$order->order_id ?>"; ?>'  // Order ID for deduplication
    });
  });
&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header" id="headingTwo">
                                <h2 class="mb-0">
                                    <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                        Lead Form Submission Tracking
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#implementationExamples">
                                <div class="card-body">
                                    <pre class="p-3 bg-light"><code>&lt;script type="text/javascript" src="<?= URL_ROOT ?>/static/js/hfi-pixel.js" data-pixel-id="<?= htmlspecialchars($pixel['pixel_id']) ?>"&gt;&lt;/script&gt;

&lt;script&gt;
  document.getElementById('leadForm').addEventListener('submit', function(e) {
    // Store form submission
    var formData = new FormData(this);
    
    // Track the conversion
    HFITrack({
      value: '10.00'  // Value of a lead
    });
  });
&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Testing Your Conversion Pixel</h5>
                </div>
                <div class="card-body">
                    <p>To test your conversion pixel implementation:</p>
                    
                    <ol>
                        <li>Add the pixel code to your conversion page</li>
                        <li>Click on one of your ads or add <code>?ad_id=TEST&click_id=TEST</code> to your conversion page URL</li>
                        <li>Complete the conversion process on your website</li>
                        <li>Check the <a href="<?= URL_ROOT ?>/advertiser/conversions">Conversions Dashboard</a> to verify the test conversion was recorded</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Test conversions will appear in your reports. Delete test data before analyzing your actual conversion performance.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .code-textarea {
        font-family: monospace;
        font-size: 0.9rem;
        white-space: pre;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Copy code functionality
        const copyButtons = document.querySelectorAll('.copy-btn');
        
        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const textarea = document.getElementById(targetId);
                
                textarea.select();
                document.execCommand('copy');
                
                // Change button text temporarily
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                }, 2000);
            });
        });
    });
</script>

<?php require_once TEMPLATES_PATH . '/advertiser/footer.php'; ?> 