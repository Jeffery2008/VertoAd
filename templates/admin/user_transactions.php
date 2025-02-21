<?php require_once __DIR__ . '/header.php'; ?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Transactions for <?= htmlspecialchars($user['username']) ?></h1>
            <p class="text-gray-600">User ID: <?= htmlspecialchars($user['id']) ?> | Current Balance: $<?= number_format($accountService->getBalance($user['id']), 2) ?></p>
        </div>
        <div class="flex gap-4">
            <a href="/admin/balances" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                Back to Balances
            </a>
            <button onclick="exportUserTransactions(<?= $user['id'] ?>)" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                Export Transactions
            </button>
        </div>
    </div>

    <!-- Filter and Date Range -->
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <form id="filterForm" class="flex gap-4 items-end">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                <select name="type" class="border rounded px-3 py-2 w-48">
                    <option value="">All Types</option>
                    <option value="deposit">Deposit</option>
                    <option value="withdrawal">Withdrawal</option>
                    <option value="adjustment">Adjustment</option>
                    <option value="ad_spend">Ad Spend</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <div class="flex gap-2 items-center">
                    <input type="date" id="startDate" name="start_date" class="border rounded px-3 py-2 w-40">
                    <span>to</span>
                    <input type="date" id="endDate" name="end_date" class="border rounded px-3 py-2 w-40">
                </div>
            </div>
            <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                Apply Filters
            </button>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <?php if (empty($transactions)): ?>
            <div class="p-6 text-center text-gray-500">
                No transactions found for this user.
            </div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($transaction['id']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($transaction['created_at']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="<?= getTransactionTypeClass($transaction['type']) ?>">
                                <?= htmlspecialchars(ucfirst($transaction['type'])) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="<?= $transaction['amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $transaction['amount'] >= 0 ? '+' : '' ?><?= number_format($transaction['amount'], 2) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            $<?= number_format($transaction['new_balance'], 2) ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="max-w-xs truncate" title="<?= htmlspecialchars($transaction['description']) ?>">
                                <?= htmlspecialchars($transaction['description']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="<?= getStatusClass($transaction['status']) ?>">
                                <?= htmlspecialchars(ucfirst($transaction['status'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 flex items-center justify-between">
                <div>
                    Showing <?= count($transactions) ?> of <?= $totalTransactions ?? 'many' ?> transactions
                </div>
                <div class="flex gap-2">
                    <?php if ($offset > 0): ?>
                        <a href="?user_id=<?= $user['id'] ?>&offset=<?= max(0, $offset - $limit) ?>&limit=<?= $limit ?>" 
                           class="bg-white border px-4 py-2 rounded hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php if (count($transactions) >= $limit): ?>
                        <a href="?user_id=<?= $user['id'] ?>&offset=<?= $offset + $limit ?>&limit=<?= $limit ?>" 
                           class="bg-white border px-4 py-2 rounded hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportUserTransactions(userId) {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    let url = `/admin/export-transactions?user_id=${userId}`;
    if (startDate && endDate) {
        url += `&start_date=${startDate}&end_date=${endDate}`;
    }
    
    window.location.href = url;
}

// Add form submission handler to apply filters
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    window.location.href = '/admin/transactions?' + params.toString();
});
</script>

<?php
// Helper functions for styling
function getTransactionTypeClass($type) {
    switch ($type) {
        case 'deposit':
            return 'px-2 py-1 text-xs bg-green-100 text-green-800 rounded';
        case 'withdrawal':
            return 'px-2 py-1 text-xs bg-red-100 text-red-800 rounded';
        case 'adjustment':
            return 'px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded';
        case 'ad_spend':
            return 'px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded';
        default:
            return 'px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded';
    }
}

function getStatusClass($status) {
    switch ($status) {
        case 'completed':
            return 'px-2 py-1 text-xs bg-green-100 text-green-800 rounded';
        case 'pending':
            return 'px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded';
        case 'failed':
            return 'px-2 py-1 text-xs bg-red-100 text-red-800 rounded';
        default:
            return 'px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded';
    }
}
?>

<?php require_once __DIR__ . '/footer.php'; ?>
