<?php require_once 'layout.php'; ?>

<div class="container mt-4">
    <h2>Create New Advertisement</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['error']); ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="/advertiser/create-ad" method="post">
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        
        <div class="mb-3">
            <label for="position_id" class="form-label">Ad Position</label>
            <select class="form-control" id="position_id" name="position_id" required>
                <option value="">Select Ad Position</option>
                <?php foreach ($positions as $position): ?>
                    <option value="<?php echo htmlspecialchars($position['id']); ?>">
                        <?php echo htmlspecialchars($position['name']); ?> 
                        (<?php echo htmlspecialchars($position['width']); ?>x<?php echo htmlspecialchars($position['height']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" required>
        </div>
        
        <div class="mb-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date">
            <small class="form-text text-muted">Optional. Leave blank for indefinite.</small>
        </div>
        
        <div class="mb-3">
            <label for="budget" class="form-label">Budget</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="budget" name="budget" min="1" step="0.01" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="bid_amount" class="form-label">Bid Amount (per 1000 impressions)</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="bid_amount" name="bid_amount" min="0.1" step="0.01" required>
            </div>
        </div>

        <div class="mb-3">
            <p>After creating the ad, you'll be able to design its content using our canvas tool.</p>
        </div>
        
        <div class="mb-3">
            <button type="submit" class="btn btn-primary">Create Advertisement</button>
            <a href="/advertiser/ads" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
    // Add date validation
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        // Set minimum start date to today
        const today = new Date().toISOString().split('T')[0];
        startDateInput.min = today;
        
        // Update end date minimum when start date changes
        startDateInput.addEventListener('change', function() {
            endDateInput.min = startDateInput.value;
            
            // If end date is earlier than start date, reset it
            if (endDateInput.value && endDateInput.value < startDateInput.value) {
                endDateInput.value = '';
            }
        });
    });
</script> 