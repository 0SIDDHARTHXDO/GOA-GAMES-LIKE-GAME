document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        loadVipData();
    } else {
        window.location.href = 'login.html';
    }
});

async function loadVipData() {
    try {
        // Load user VIP status
        const statusResponse = await apiRequest('/vip/my-status');
        if (statusResponse.ok) {
            const statusData = await statusResponse.json();
            displayVipStatus(statusData.user_vip_info);
        }
        
        // Load VIP levels
        const levelsResponse = await apiRequest('/vip/levels');
        if (levelsResponse.ok) {
            const levelsData = await levelsResponse.json();
            displayVipLevels(levelsData.vip_levels);
        }
    } catch (error) {
        console.error('Error loading VIP data:', error);
        showMessage('Error loading VIP data', 'danger');
    }
}

function displayVipStatus(vipInfo) {
    // Update current VIP level display
    const currentLevelEl = document.getElementById('currentVipLevel');
    if (currentLevelEl && vipInfo.vip_name) {
        currentLevelEl.textContent = vipInfo.vip_name;
        if (vipInfo.color) {
            currentLevelEl.style.color = vipInfo.color;
        }
    }
    
    // Update VIP info
    const vipInfoEl = document.getElementById('currentVipInfo');
    if (vipInfoEl && vipInfo.bonus_percentage) {
        vipInfoEl.textContent = `${vipInfo.bonus_percentage}% Bonus`;
    }
    
    // Calculate progress to next level
    if (vipInfo.upgrade_available && vipInfo.eligible_vip) {
        const progressTextEl = document.getElementById('vipProgressText');
        if (progressTextEl) {
            progressTextEl.textContent = `Balance: $${vipInfo.balance.toFixed(2)} / $${vipInfo.eligible_vip.min_balance.toFixed(2)} to ${vipInfo.eligible_vip.name}`;
        }
        
        const progressPercent = Math.min(100, (vipInfo.balance / vipInfo.eligible_vip.min_balance) * 100);
        const progressBarEl = document.getElementById('vipProgress');
        if (progressBarEl) {
            progressBarEl.style.width = `${progressPercent}%`;
            progressBarEl.setAttribute('aria-valuenow', progressPercent.toString());
        }
    } else if (vipInfo.min_balance) {
        // If user is at max level or needs more balance
        const progressTextEl = document.getElementById('vipProgressText');
        if (progressTextEl) {
            progressTextEl.textContent = `Balance: $${vipInfo.balance.toFixed(2)}`;
        }
        
        // Calculate progress based on current level's min balance
        let nextLevelMin = vipInfo.min_balance * 2; // Assume next level is double for demo
        if (vipInfo.balance >= nextLevelMin) {
            nextLevelMin = vipInfo.balance * 1.1; // Just add 10% for demo
        }
        
        const progressPercent = Math.min(100, (vipInfo.balance / nextLevelMin) * 100);
        const progressBarEl = document.getElementById('vipProgress');
        if (progressBarEl) {
            progressBarEl.style.width = `${progressPercent}%`;
            progressBarEl.setAttribute('aria-valuenow', progressPercent.toString());
        }
    }
}

function displayVipLevels(levels) {
    const tbody = document.getElementById('vipLevelsTable');
    if (!tbody) return;
    
    if (levels.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No VIP levels available</td></tr>';
        return;
    }
    
    let html = '';
    levels.forEach(level => {
        html += `
            <tr>
                <td><span class="badge" style="background-color: ${level.color || '#6c757d'}; color: white;">${level.name}</span></td>
                <td>$${parseFloat(level.min_balance).toFixed(2)}</td>
                <td>${parseFloat(level.bonus_percentage).toFixed(2)}%</td>
                <td>
                    <ul class="list-unstyled mb-0">
                        <li>• Exclusive bonuses</li>
                        <li>• Priority support</li>
                        <li>• Higher withdrawal limits</li>
                    </ul>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Refresh data periodically
setInterval(loadVipData, 60000); // Refresh every minute