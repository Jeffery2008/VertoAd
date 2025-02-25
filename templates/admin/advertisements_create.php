<?php require_once 'layout.php'; ?>

<?php
/** @var array $adPositions */
?>

<div class="container">
    <h2>Create New Advertisement</h2>
    <form action="/admin/advertisements/create" method="post">
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="advertiser_id" class="form-label">Advertiser ID</label>
            <input type="text" class="form-control" id="advertiser_id" name="advertiser_id" required>
        </div>
        <div class="mb-3">
            <label for="position_id" class="form-label">Ad Position</label>
            <select class="form-select" id="position_id" name="position_id" required>
                <option value="">Select Ad Position</option>
                <?php foreach ($adPositions as $position): ?>
                    <option value="<?php echo htmlspecialchars($position['id']); ?>">
                        <?php echo htmlspecialchars($position['name']); ?> (<?php echo htmlspecialchars($position['slug']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="content" class="form-label">Content (JSON)</label>
            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
        </div>
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
        </div>
        <div class="mb-3">
            <label for="end_date" class="form-label">End Date (Optional)</label>
            <input type="datetime-local" class="form-control" id="end_date" name="end_date">
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
                <option value="pending">Pending</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="completed">Completed</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="budget" class="form-label">Budget</label>
            <input type="number" class="form-control" id="budget" name="budget" step="0.01" required>
        </div>
        <div class="mb-3">
            <label for="bid_amount" class="form-label">Bid Amount</label>
            <input type="number" class="form-control" id="bid_amount" name="bid_amount" step="0.01" required>
        </div>
        <button type="submit" class="btn btn-primary">Create Advertisement</button>
    </form>
</div>
