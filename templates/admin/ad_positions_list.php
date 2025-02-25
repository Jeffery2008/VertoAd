<h1>Ad Positions</h1>

<a href="/admin/positions/create">Create New Ad Position</a>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Format</th>
            <th>Width</th>
            <th>Height</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($adPositions)): ?>
        <tr>
            <td colspan="8">No ad positions found.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($adPositions as $position): ?>
        <tr>
            <td><?php echo htmlspecialchars($position['id']); ?></td>
            <td><?php echo htmlspecialchars($position['name']); ?></td>
            <td><?php echo htmlspecialchars($position['slug']); ?></td>
            <td><?php echo htmlspecialchars($position['format']); ?></td>
            <td><?php echo htmlspecialchars($position['width']); ?></td>
            <td><?php echo htmlspecialchars($position['height']); ?></td>
            <td><?php echo htmlspecialchars($position['status']); ?></td>
            <td>
                <a href="/admin/positions/edit?id=<?php echo htmlspecialchars($position['id']); ?>">Edit</a>
                <form action="/admin/positions/delete" method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($position['id']); ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this position?')">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
