<?php
/**
 * @var \App\Controllers\AdminController $adminController
 */
?>
<h1>Batch Key Generation</h1>

<form method="POST" action="/admin/keys/batch">
    <div class="form-group">
        <label for="batch_name">Batch Name</label>
        <input type="text" id="batch_name" name="batch_name" class="form-control" required>
    </div>
    <div class="form-group">
        <label for="amount">Key Value Amount</label>
        <input type="number" id="amount" name="amount" class="form-control" required step="0.01">
    </div>
    <div class="form-group">
        <label for="quantity">Quantity</label>
        <input type="number" id="quantity" name="quantity" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Generate Batch</button>
</form>
