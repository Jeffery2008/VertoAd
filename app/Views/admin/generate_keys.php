<!DOCTYPE html>
<html>
<head>
    <title>Generate Activation Keys</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <h1>Generate Activation Keys</h1>

    <form method="POST" action="/admin/generate-keys">
        <div>
            <label for="amount">Amount:</label>
            <input type="number" name="amount" id="amount" step="0.01" required>
        </div>
        <div>
            <label for="quantity">Quantity:</label>
            <input type="number" name="quantity" id="quantity" required>
        </div>
        <div>
            <label for="export_csv">
              <input type="checkbox" name="export" value="csv" id="export_csv">
              Export to CSV
            </label>
        </div>
        <div>
            <button type="submit">Generate</button>
        </div>
    </form>

    <?php if (isset($keys)): ?>
        <h2>Generated Keys:</h2>
        <ul>
            <?php foreach ($keys as $key): ?>
                <li><?php echo $key; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html> 