<?php
/**
 * @var \App\Controllers\AdminController $adminController
 */
?>
<h1>Single Key Generation</h1>

<form method="POST" action="/admin/keys/single">
    <div class="form-group">
        <label for="amount">Key Value Amount</label>
        <input type="number" id="amount" name="amount" class="form-control" required step="0.01">
    </div>
    <button type="submit" class="btn btn-primary">Generate Single Key</button>
</form>
