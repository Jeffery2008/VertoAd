<?php require_once 'layout.php'; ?>

<?php
/** @var array $advertisements */
?>

<div class="container">
    <h2>My Advertisements</h2>
    <div class="mb-3">
        <a href="/advertiser/create-ad" class="btn btn-primary">Create New Advertisement</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($advertisements)): ?>
                <tr>
                    <td colspan="4">No advertisements found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($advertisements as $ad): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ad['id']); ?></td>
                        <td><?php echo htmlspecialchars($ad['title'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($ad['status']); ?></td>
                        <td>
                            <a href="/advertiser/edit-ad?id=<?php echo htmlspecialchars($ad['id']); ?>">Edit</a>
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
        // TODO: Implement delete functionality
        alert('Delete functionality is not yet implemented.');
    }
}
</script>
