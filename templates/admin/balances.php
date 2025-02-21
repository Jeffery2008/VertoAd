<?php require_once __DIR__ . '/header.php'; ?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">User Balances</h1>
        <div class="flex gap-4">
            <button onclick="exportTransactions()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                Export Transactions
            </button>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <div class="flex gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="searchInput" placeholder="Search by username..." 
                       class="border rounded px-3 py-2 w-64">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Balance Range</label>
                <div class="flex gap-2 items-center">
                    <input type="number" id="minBalance" placeholder="Min" class="border rounded px-3 py-2 w-32">
                    <span>to</span>
                    <input type="number" id="maxBalance" placeholder="Max" class="border rounded px-3 py-2 w-32">
                </div>
            </div>
            <button onclick="applyFilters()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                Apply Filters
            </button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Transaction</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr id="user-row-<?= htmlspecialchars($user['id']) ?>">
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['id']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['username']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        $<span class="balance-amount"><?= number_format($user['balance'], 2) ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= $user['last_transaction_date'] ?? 'No transactions' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <button onclick="showAdjustModal(<?= htmlspecialchars($user['id']) ?>)" 
                                class="text-blue-600 hover:text-blue-900">Adjust Balance</button>
                        <a href="/admin/transactions?user_id=<?= htmlspecialchars($user['id']) ?>" 
                           class="ml-4 text-gray-600 hover:text-gray-900">View History</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Adjust Balance Modal -->
<div id="adjustBalanceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Adjust Balance</h3>
            <form id="adjustBalanceForm" onsubmit="submitAdjustment(event)">
                <input type="hidden" id="adjustUserId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" id="adjustAmount" step="0.01" required
                           class="border rounded px-3 py-2 w-full">
                    <p class="text-sm text-gray-500 mt-1">Use positive for credit, negative for debit</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="adjustDescription" required
                            class="border rounded px-3 py-2 w-full h-24"></textarea>
                </div>
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="hideAdjustModal()"
                            class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAdjustModal(userId) {
    document.getElementById('adjustUserId').value = userId;
    document.getElementById('adjustBalanceModal').classList.remove('hidden');
}

function hideAdjustModal() {
    document.getElementById('adjustBalanceModal').classList.add('hidden');
    document.getElementById('adjustBalanceForm').reset();
}

async function submitAdjustment(event) {
    event.preventDefault();
    
    const userId = document.getElementById('adjustUserId').value;
    const amount = document.getElementById('adjustAmount').value;
    const description = document.getElementById('adjustDescription').value;

    try {
        const response = await fetch('/admin/adjust-balance', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ user_id: userId, amount, description })
        });

        const data = await response.json();
        
        if (data.success) {
            // Update the balance display
            const balanceElement = document.querySelector(`#user-row-${userId} .balance-amount`);
            balanceElement.textContent = parseFloat(data.new_balance).toFixed(2);
            
            hideAdjustModal();
            alert('Balance adjusted successfully');
        } else {
            throw new Error(data.error || 'Failed to adjust balance');
        }
    } catch (error) {
        alert(error.message);
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const minBalance = parseFloat(document.getElementById('minBalance').value) || 0;
    const maxBalance = parseFloat(document.getElementById('maxBalance').value) || Infinity;

    document.querySelectorAll('tbody tr').forEach(row => {
        const username = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const balance = parseFloat(row.querySelector('.balance-amount').textContent);
        
        const matchesSearch = username.includes(search);
        const matchesBalance = balance >= minBalance && balance <= maxBalance;

        row.style.display = matchesSearch && matchesBalance ? '' : 'none';
    });
}

function exportTransactions() {
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    
    let url = '/admin/export-transactions';
    if (startDate && endDate) {
        url += `?start_date=${startDate}&end_date=${endDate}`;
    }
    
    window.location.href = url;
}

// Initialize filters on input change
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('minBalance').addEventListener('input', applyFilters);
document.getElementById('maxBalance').addEventListener('input', applyFilters);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
