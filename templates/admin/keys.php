<?php require_once 'templates/admin/layout.php'; ?>

<div class="container mx-auto px-4 py-8">
    <!-- Batch Key Generation Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold mb-4">Batch Key Generation</h2>
        <form action="/admin/keys/batch" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Batch Name</label>
                    <input type="text" id="batch_name" name="batch_name" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Key Amount Value</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="amount" name="amount" required step="0.01" min="0"
                               class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Number of Keys</label>
                    <input type="number" id="quantity" name="quantity" required min="1" max="1000"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <input type="text" id="notes" name="notes"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Generate Keys
                </button>
            </div>
        </form>
    </div>

    <!-- Single Key Generation Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold mb-4">Single Key Generation</h2>
        <a href="/admin/keys/single" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-block">
            Generate Single Key
        </a>
    </div>

    <!-- Key Batches Table -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold mb-4">Key Batches</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Keys</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Keys</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used Keys</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revoked Keys</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($batches as $batch): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($batch['batch_name']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($batch['notes']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">$<?= number_format($batch['amount'], 2) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?= $batch['total_keys'] ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?= $batch['active_keys'] ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?= $batch['used_keys'] ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?= $batch['revoked_keys'] ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500">
                                by <?= htmlspecialchars($batch['created_by_username']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?= date('Y-m-d H:i', strtotime($batch['created_at'])) ?>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="/admin/keys/batch/<?= $batch['id'] ?>"
                               class="text-blue-600 hover:text-blue-900">View</a>
                            <a href="/admin/keys/batch/<?= $batch['id'] ?>/download"
                               class="ml-3 text-green-600 hover:text-green-900">Download</a>
                            <?php if ($batch['active_keys'] > 0): ?>
                            <a href="#" onclick="revokeBatch(<?= $batch['id'] ?>)"
                               class="ml-3 text-red-600 hover:text-red-900">Revoke All</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>"
                       class="<?= $currentPage === $i ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <!-- Redemption Statistics -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold mb-4">Redemption Statistics</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Today's Stats -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-2">Today</h3>
                <dl class="space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Redemptions:</dt>
                        <dd class="font-medium"><?= $todayStats['total_redemptions'] ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Amount:</dt>
                        <dd class="font-medium">$<?= number_format($todayStats['total_amount'], 2) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Unique Users:</dt>
                        <dd class="font-medium"><?= $todayStats['unique_users'] ?></dd>
                    </div>
                </dl>
            </div>

            <!-- This Month's Stats -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-2">This Month</h3>
                <dl class="space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Redemptions:</dt>
                        <dd class="font-medium"><?= $monthStats['total_redemptions'] ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Amount:</dt>
                        <dd class="font-medium">$<?= number_format($monthStats['total_amount'], 2) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Unique Users:</dt>
                        <dd class="font-medium"><?= $monthStats['unique_users'] ?></dd>
                    </div>
                </dl>
            </div>

            <!-- All Time Stats -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-2">All Time</h3>
                <dl class="space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Redemptions:</dt>
                        <dd class="font-medium"><?= $allTimeStats['total_redemptions'] ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Amount:</dt>
                        <dd class="font-medium">$<?= number_format($allTimeStats['total_amount'], 2) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Unique Users:</dt>
                        <dd class="font-medium"><?= $allTimeStats['unique_users'] ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<script>
function revokeBatch(batchId) {
    if (!confirm('Are you sure you want to revoke all remaining active keys in this batch?')) {
        return;
    }

    fetch(`/admin/keys/batch/${batchId}/revoke`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Failed to revoke batch: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while revoking the batch');
    });
}

function revokeKey(keyId) {
    if (!confirm('Are you sure you want to revoke this key?')) {
        return;
    }

    fetch(`/admin/keys/key/${keyId}/revoke`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Failed to revoke key: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while revoking the key');
    });
}
</script>
