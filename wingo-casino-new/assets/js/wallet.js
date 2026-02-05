document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        loadWalletData();
        setupEventListeners();
    } else {
        window.location.href = 'login.html';
    }
});

function setupEventListeners() {
    // Deposit button
    const depositBtn = document.getElementById('depositBtn');
    if (depositBtn) {
        depositBtn.addEventListener('click', handleDeposit);
    }
    
    // Withdraw button
    const withdrawBtn = document.getElementById('withdrawBtn');
    if (withdrawBtn) {
        withdrawBtn.addEventListener('click', handleWithdraw);
    }
    
    // Transaction filter
    const filterSelect = document.getElementById('transactionFilter');
    if (filterSelect) {
        filterSelect.addEventListener('change', () => {
            loadTransactions(); // Reload transactions with new filter
        });
    }
}

async function loadWalletData() {
    try {
        // Load balance
        const balanceResponse = await apiRequest('/wallet/balance');
        if (balanceResponse.ok) {
            const balanceData = await balanceResponse.json();
            const balanceDisplay = document.getElementById('balanceDisplay');
            if (balanceDisplay) {
                balanceDisplay.textContent = `$${balanceData.balance.toFixed(2)}`;
            }
        }
        
        // Load transactions
        loadTransactions();
    } catch (error) {
        console.error('Error loading wallet data:', error);
        showMessage('Error loading wallet data', 'danger');
    }
}

async function loadTransactions(page = 1) {
    try {
        const filterSelect = document.getElementById('transactionFilter');
        const type = filterSelect ? filterSelect.value : '';
        
        let url = `/wallet/transactions?page=${page}&limit=10`;
        if (type) {
            url += `&type=${type}`;
        }
        
        const response = await apiRequest(url);
        if (response.ok) {
            const data = await response.json();
            displayTransactions(data.transactions, data.pagination);
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
        showMessage('Error loading transactions', 'danger');
    }
}

function displayTransactions(transactions, pagination) {
    const tbody = document.getElementById('transactionsTableBody');
    if (!tbody) return;
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No transactions found</td></tr>';
        return;
    }
    
    let html = '';
    transactions.forEach(transaction => {
        const date = new Date(transaction.created_at).toLocaleString();
        const amount = parseFloat(transaction.amount);
        const amountFormatted = amount > 0 ? `+${amount.toFixed(2)}` : amount.toFixed(2);
        const amountClass = amount > 0 ? 'text-success' : 'text-danger';
        
        html += `
            <tr>
                <td>${date}</td>
                <td><span class="badge bg-${getTransactionTypeColor(transaction.type)}">${transaction.type}</span></td>
                <td>${transaction.description || '-'}</td>
                <td class="${amountClass}">${amountFormatted}</td>
                <td>$${parseFloat(transaction.balance_after).toFixed(2)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Display pagination
    displayPagination(pagination, (page) => loadTransactions(page));
}

function getTransactionTypeColor(type) {
    switch (type) {
        case 'deposit': return 'success';
        case 'withdrawal': return 'danger';
        case 'bet': return 'warning';
        case 'win': return 'success';
        case 'loss': return 'danger';
        case 'bonus': return 'info';
        default: return 'secondary';
    }
}

function displayPagination(pagination, onPageChange) {
    const paginationContainer = document.getElementById('transactionsPagination');
    if (!paginationContainer) return;
    
    if (pagination.total_pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(${pagination.current_page - 1}); return false;">Previous</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
        if (i === pagination.current_page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(${i}); return false;">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(${pagination.current_page + 1}); return false;">Next</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    paginationContainer.innerHTML = html;
}

async function handleDeposit() {
    const amountInput = document.getElementById('depositAmount');
    if (!amountInput) return;
    
    const amount = parseFloat(amountInput.value);
    if (isNaN(amount) || amount <= 0) {
        showMessage('Please enter a valid amount', 'danger');
        return;
    }
    
    try {
        const response = await apiRequest('/wallet/deposit', {
            method: 'POST',
            body: JSON.stringify({
                amount: amount,
                method: 'demo'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showMessage('Deposit successful!', 'success');
            amountInput.value = '';
            loadWalletData(); // Reload wallet data
        } else {
            showMessage(data.message || 'Deposit failed', 'danger');
        }
    } catch (error) {
        console.error('Deposit error:', error);
        showMessage('An error occurred during deposit', 'danger');
    }
}

async function handleWithdraw() {
    const amountInput = document.getElementById('withdrawAmount');
    if (!amountInput) return;
    
    const amount = parseFloat(amountInput.value);
    if (isNaN(amount) || amount <= 0) {
        showMessage('Please enter a valid amount', 'danger');
        return;
    }
    
    try {
        const response = await apiRequest('/wallet/withdraw', {
            method: 'POST',
            body: JSON.stringify({
                amount: amount,
                method: 'demo'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showMessage('Withdrawal successful!', 'success');
            amountInput.value = '';
            loadWalletData(); // Reload wallet data
        } else {
            showMessage(data.message || 'Withdrawal failed', 'danger');
        }
    } catch (error) {
        console.error('Withdrawal error:', error);
        showMessage('An error occurred during withdrawal', 'danger');
    }
}

// Expose function to global scope for pagination
window.loadTransactions = loadTransactions;