let currentPayId = null;

// Add click event listeners to all pay buttons
document.addEventListener('DOMContentLoaded', function() {
    // Attach click handlers to all pay buttons
    const payButtons = document.querySelectorAll('.pay-btn');
    payButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const patient = this.getAttribute('data-patient');
            const service = this.getAttribute('data-service');
            const amount = this.getAttribute('data-amount');
            openPayModal(id, patient, service, amount);
        });
    });
    
    // Auto-select pre-selected appointment if any
    if (typeof preSelectedId !== 'undefined' && preSelectedId) {
        const preBtn = document.getElementById('paybtn-' + preSelectedId);
        if (preBtn) {
            setTimeout(() => preBtn.click(), 400);
        }
    }
});

function openPayModal(id, patient, service, amount) {
    currentPayId = id;
    document.getElementById('modalPatientInfo').innerHTML =
        '<strong>' + patient + '</strong> &mdash; ' + service;
    document.getElementById('modalAmount').textContent = amount + ' DA';
    document.getElementById('payModal').style.display = 'flex';
}

function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
    currentPayId = null;
}

function confirmPay() {
    if (!currentPayId) return;
    
    const btn = document.getElementById('paybtn-' + currentPayId);
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing…';
    }

    fetch('billing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=pay&appointment_id=' + currentPayId
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === 'success') {
            closePayModal();
            location.reload();
        } else {
            alert('Payment failed: ' + res);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-money-bill"></i> Process Payment';
            }
        }
    })
    .catch(err => {
        alert('Network error: ' + err);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-money-bill"></i> Process Payment';
        }
    });
}

// Close modal when clicking outside
window.onclick = function(e) {
    const modal = document.getElementById('payModal');
    if (e.target === modal) {
        closePayModal();
    }
};

// Search functionality
document.getElementById('searchBilling').addEventListener('input', function() {
    const val = this.value.toLowerCase();
    const rows = document.querySelectorAll('#billingTable tr');
    rows.forEach(row => {
        if (row.cells && row.cells.length > 0) {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(val) ? '' : 'none';
        }
    });
});