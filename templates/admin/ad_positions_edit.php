<h1>Edit Ad Position</h1>

<?php if (isset($_GET['error'])): ?>
    <p style="color:red;">Error: <?php echo htmlspecialchars($_GET['error']); ?></p>
<?php endif; ?>

<form action="/admin/positions/edit" method="post">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($adPosition['id']); ?>">
    <div>
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($adPosition['name']); ?>" required>
    </div>
    <div>
        <label for="slug">Slug:</label>
        <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($adPosition['slug']); ?>" required>
    </div>
    <div>
        <label for="format">Format:</label>
        <select id="format" name="format" required>
            <option value="banner" <?php if ($adPosition['format'] === 'banner') echo 'selected'; ?>>Banner</option>
            <option value="rectangle" <?php if ($adPosition['format'] === 'rectangle') echo 'selected'; ?>>Rectangle</option>
            <option value="skyscraper" <?php if ($adPosition['format'] === 'skyscraper') echo 'selected'; ?>>Skyscraper</option>
            <!-- Add more formats as needed -->
        </select>
    </div>
    <div>
        <label for="width">Width:</label>
        <input type="number" id="width" name="width" value="<?php echo htmlspecialchars($adPosition['width']); ?>" required>
    </div>
    <div>
        <label for="height">Height:</label>
        <input type="number" id="height" name="height" value="<?php echo htmlspecialchars($adPosition['height']); ?>" required>
    </div>
    <div>
        <label for="status">Status:</label>
        <select id="status" name="status">
            <option value="active" <?php if ($adPosition['status'] === 'active') echo 'selected'; ?>>Active</option>
            <option value="inactive" <?php if ($adPosition['status'] === 'inactive') echo 'selected'; ?>>Inactive</option>
        </select>
    </div>
    <div>
        <button type="submit">Update Position</button>
    </div>
</form>
