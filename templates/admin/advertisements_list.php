<?php require_once 'layout.php'; ?>

<?php /** @var array $advertisements */ ?>

<div class="container">
    <h2>Advertisements</h2>
    <div class="mb-3">
        <a href="/admin/advertisements/create" class="btn btn-primary">Create New Advertisement</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Advertiser ID</th>
                <th>Position ID</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Budget</th>
                <th>Bid Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($advertisements)): ?>
                <tr>
                    <td colspan="9">No advertisements found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($advertisements as $ad): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ad['id']); ?></td>
                        <td><?php echo htmlspecialchars($ad['advertiser_id']); ?></td>
                        <td><?php echo htmlspecialchars($ad['position_id']); ?></td>
                        <td><?php echo htmlspecialchars($ad['status']); ?></td>
                        <td><?php echo htmlspecialchars($ad['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($ad['end_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($ad['budget']); ?></td>
                        <td><?php echo htmlspecialchars($ad['bid_amount']); ?></td>
                        <td>
                            <a href="/admin/advertisements/edit?id=<?php echo htmlspecialchars($ad['id']); ?>">Edit</a>
                            <a href="#" onclick="deleteAd(<?php echo htmlspecialchars($ad['id']); ?>)">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function deleteAd(adId) {
    if (confirm('Are you sure you want to delete this advertisement?')) {
        fetch('/admin/advertisements/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + adId,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload(); // Reload page to update the list
            } else {
                alert('Error deleting advertisement: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the advertisement.');
        });
    }
}
</script>
