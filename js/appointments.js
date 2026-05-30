// Filter functions
function applyFilters() {
    const date = document.getElementById('filterDate').value;
    const status = document.getElementById('filterStatus').value;
    const search = document.getElementById('filterSearch').value;
    
    let url = 'appointments.php?';
    if (date) url += 'date=' + date + '&';
    if (status !== 'all') url += 'status=' + status + '&';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    
    window.location.href = url;
}

function resetFilters() {
    window.location.href = 'appointments.php';
}

// Table search (live filtering)
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const val = this.value.toLowerCase();
        const rows = document.querySelectorAll('#queueTable tr');
        rows.forEach(row => {
            if (row.cells && row.cells.length > 0) {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(val) ? '' : 'none';
            }
        });
    });
}

// Go to payment page
function goToPayment(id) {
    window.location.href = 'billing.php?appt_id=' + id;
}

// Delete appointment
function deleteAppointment(id) {
    if (!confirm('Are you sure you want to delete this appointment?')) return;
    
    fetch('backend/appointments_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&id=' + id
    })
    .then(r => r.text())
    .then(res => {
        if (res === 'success') {
            location.reload();
        } else {
            alert('Error: ' + res);
        }
    })
    .catch(err => alert('Network error: ' + err));
}

// Populate doctor dropdown based on service
function populateEditDoctor(service, selectedId) {
    const sel = document.getElementById('editDoctor');
    if (!sel) return;
    
    sel.innerHTML = '';
    allStaffFromPHP.forEach(s => {
        if (
            (service === 'Consultation' && s.role === 'Doctor') ||
            (service === 'Laboratory' && s.role === 'Laboratory') ||
            (service === 'Radiology' && s.role === 'Radiology')
        ) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.full_name;
            if (s.id == selectedId) opt.selected = true;
            sel.appendChild(opt);
        }
    });
}

// When service changes, update price and doctor list
function onEditServiceChange() {
    const service = document.getElementById('editService').value;
    document.getElementById('editPrice').value = prices[service];
    populateEditDoctor(service, null);
}

// Load appointment data into edit modal
function editAppointment(id) {
    fetch('backend/appointments_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=edit&id=' + id
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('editId').value = data.id;
        document.getElementById('editPatient').value = data.patient_name || '';
        document.getElementById('editService').value = data.service || 'Consultation';
        document.getElementById('editCase').value = data.case_type || 'New';
        document.getElementById('editPrice').value = data.price || '';
        document.getElementById('editDateOnly').value = data.appointment_date || '';
        document.getElementById('editTimeOnly').value = data.appointment_time ? data.appointment_time.substring(0, 5) : '';
        populateEditDoctor(data.service, data.doctor_id);
        document.getElementById('editModal').style.display = 'flex';
    })
    .catch(err => alert('Error loading appointment: ' + err));
}

// Close modal
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Save edited appointment
function saveEdit() {
    const editId = document.getElementById('editId').value;
    const editService = document.getElementById('editService').value;
    const editDoctor = document.getElementById('editDoctor').value;
    const editCase = document.getElementById('editCase').value;
    const editPrice = document.getElementById('editPrice').value;
    const editDateOnly = document.getElementById('editDateOnly').value;
    const editTimeOnly = document.getElementById('editTimeOnly').value;
    
    fetch('backend/appointments_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update'
            + '&id=' + editId
            + '&service=' + encodeURIComponent(editService)
            + '&doctor_id=' + encodeURIComponent(editDoctor)
            + '&case_type=' + encodeURIComponent(editCase)
            + '&price=' + encodeURIComponent(editPrice)
            + '&appointment_date=' + encodeURIComponent(editDateOnly)
            + '&appointment_time=' + encodeURIComponent(editTimeOnly)
    })
    .then(r => r.text())
    .then(res => {
        if (res === 'success') {
            location.reload();
        } else {
            alert('Error saving: ' + res);
        }
    })
    .catch(err => alert('Network error: ' + err));
}

// Click outside modal to close
window.onclick = function(e) {
    const modal = document.getElementById('editModal');
    if (e.target === modal) {
        closeModal();
    }
};

// Quick date filter for today
function filterToday() {
    const today = new Date().toISOString().split('T')[0];
    window.location.href = 'appointments.php?date=' + today;
}