<h1>Create Ad Position</h1>

<?php if (isset($_GET['error'])): ?>
    <p style="color:red;">Error: <?php echo htmlspecialchars($_GET['error']); ?></p>
<?php endif; ?>

<form action="/admin/positions/create" method="post">
    <div>
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>
    </div>
    <div>
        <label for="slug">Slug:</label>
        <input type="text" id="slug" name="slug" required>
    </div>
    <div>
        <label for="format">Format:</label>
        <select id="format" name="format" required>
            <option value="banner">Banner</option>
            <option value="rectangle">Rectangle</option>
            <option value="skyscraper">Skyscraper</option>
            <!-- Add more formats as needed -->
        </select>
    </div>
    <div>
        <label for="width">Width:</label>
        <input type="number" id="width" name="width" required>
    </div>
    <div>
        <label for="height">Height:</label>
        <input type="number" id="height" name="height" required>
    </div>
    <div>
        <label for="status">Status:</label>
        <select id="status" name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>
    <div>
        <button type="submit">Create Position</button>
    </div>
</form>
