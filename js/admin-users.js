// Staff schedules data from PHP (passed from PHP file)
let currentStaffId = null;
let currentStaffName = '';
let currentStaffRole = '';

// ========== STAFF WORKING HOURS SEARCH ==========
document.getElementById('staffSearchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const resultsDiv = document.getElementById('staffSearchResults');
    
    if (searchTerm === '') {
        resultsDiv.style.display = 'none';
        return;
    }
    
    const filteredStaff = staffListData.filter(staff => 
        staff.name.toLowerCase().includes(searchTerm) || 
        staff.role.toLowerCase().includes(searchTerm)
    );
    
    if (filteredStaff.length > 0) {
        resultsDiv.style.display = 'block';
        resultsDiv.innerHTML = filteredStaff.map(staff => `
            <div class="staff-result-item" onclick="selectStaff(${staff.id}, '${staff.name.replace(/'/g, "\\'")}', '${staff.role}')">
                <div class="staff-result-name">${staff.name}</div>
                <div class="staff-result-role">${staff.role}</div>
            </div>
        `).join('');
    } else {
        resultsDiv.style.display = 'block';
        resultsDiv.innerHTML = '<div class="staff-result-item" style="color:#9ca3af;">No staff found</div>';
    }
});

function selectStaff(staffId, staffName, staffRole) {
    currentStaffId = staffId;
    currentStaffName = staffName;
    currentStaffRole = staffRole;
    
    const badgeDiv = document.getElementById('selectedStaffBadge');
    badgeDiv.style.display = 'block';
    badgeDiv.innerHTML = `
        <div class="selected-staff-badge">
            <i class="fa-solid fa-user-md"></i>
            <strong>${staffName}</strong>
            <span style="color:#6b7280;">(${staffRole})</span>
            <button onclick="clearSelectedStaff()" title="Change Staff">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    `;
    
    document.getElementById('weeklyScheduleContainer').style.display = 'block';
    buildScheduleGrid(currentStaffId);
    document.getElementById('staffSearchInput').value = '';
    document.getElementById('staffSearchResults').style.display = 'none';
}

function clearSelectedStaff() {
    currentStaffId = null;
    document.getElementById('selectedStaffBadge').style.display = 'none';
    document.getElementById('weeklyScheduleContainer').style.display = 'none';
    document.getElementById('staffSearchInput').value = '';
}

function deleteSchedule(staffId, dayOfWeek) {
    if (confirm('Remove schedule for this day? The staff will be marked as not working.')) {
        window.location.href = `admin-users.php?delete_schedule_day=1&staff_id=${staffId}&day=${dayOfWeek}`;
    }
}

function buildScheduleGrid(staffId) {
    const grid = document.getElementById('daysGrid');
    grid.innerHTML = '';
    
    const staffSchedules = schedulesData.filter(s => s.staff_id === staffId);
    
    daysOfWeek.forEach(day => {
        const schedule = staffSchedules.find(s => s.day === day.value);
        const hasSchedule = schedule !== undefined;
        const timeText = hasSchedule ? `${schedule.start} - ${schedule.end}` : 'Not set';
        
        const dayCard = document.createElement('div');
        dayCard.className = 'day-card';
        
        if (hasSchedule) {
            dayCard.innerHTML = `
                <div class="day-name">${day.name}</div>
                <div class="day-time has-schedule">
                    <i class="fa-regular fa-clock"></i> ${timeText}
                </div>
                <div style="display: flex; gap: 0.25rem; justify-content: center;">
                    <button class="edit-schedule-btn" 
                        onclick="openScheduleModal(${staffId}, ${day.value}, '${schedule.start}', '${schedule.end}')">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <button class="delete-schedule-btn" 
                        onclick="deleteSchedule(${staffId}, ${day.value})">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>
            `;
        } else {
            dayCard.innerHTML = `
                <div class="day-name">${day.name}</div>
                <div class="day-time">
                    <i class="fa-regular fa-clock"></i> ${timeText}
                </div>
                <button class="edit-schedule-btn" 
                    onclick="openScheduleModal(${staffId}, ${day.value}, '', '')">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            `;
        }
        grid.appendChild(dayCard);
    });
}

function openScheduleModal(staffId, dayOfWeek, currentStart, currentEnd) {
    document.getElementById('edit_staff_id').value = staffId;
    document.getElementById('edit_day_of_week').value = dayOfWeek;
    document.getElementById('edit_start_time').value = currentStart;
    document.getElementById('edit_end_time').value = currentEnd;
    document.getElementById('scheduleEditModal').style.display = 'flex';
}

function closeScheduleModal() {
    document.getElementById('scheduleEditModal').style.display = 'none';
}

// ========== STAFF TIME OFF SEARCH ==========
let currentTimeoffStaffId = null;
let currentTimeoffStaffName = '';
let currentTimeoffStaffRole = '';

document.getElementById('timeoffStaffSearchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const resultsDiv = document.getElementById('timeoffStaffSearchResults');
    
    if (searchTerm === '') {
        resultsDiv.style.display = 'none';
        return;
    }
    
    const filteredStaff = staffListData.filter(staff => 
        staff.name.toLowerCase().includes(searchTerm) || 
        staff.role.toLowerCase().includes(searchTerm)
    );
    
    if (filteredStaff.length > 0) {
        resultsDiv.style.display = 'block';
        resultsDiv.innerHTML = filteredStaff.map(staff => `
            <div class="timeoff-result-item" onclick="selectTimeoffStaff(${staff.id}, '${staff.name.replace(/'/g, "\\'")}', '${staff.role}')">
                <div class="timeoff-result-name">${staff.name}</div>
                <div class="timeoff-result-role">${staff.role}</div>
            </div>
        `).join('');
    } else {
        resultsDiv.style.display = 'block';
        resultsDiv.innerHTML = '<div class="timeoff-result-item" style="color:#9ca3af;">No staff found</div>';
    }
});

function selectTimeoffStaff(staffId, staffName, staffRole) {
    currentTimeoffStaffId = staffId;
    currentTimeoffStaffName = staffName;
    currentTimeoffStaffRole = staffRole;
    
    const badgeDiv = document.getElementById('selectedTimeoffStaffBadge');
    badgeDiv.style.display = 'block';
    badgeDiv.innerHTML = `
        <div class="selected-timeoff-badge">
            <i class="fa-solid fa-user-md"></i>
            <strong>${staffName}</strong>
            <span style="color:#6b7280;">(${staffRole})</span>
            <button onclick="clearSelectedTimeoffStaff()" title="Change Staff">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    `;
    
    document.getElementById('timeoff_staff_id').value = staffId;
    document.getElementById('timeoffFormContainer').style.display = 'block';
    document.getElementById('timeoffStaffSearchInput').value = '';
    document.getElementById('timeoffStaffSearchResults').style.display = 'none';
}

function clearSelectedTimeoffStaff() {
    currentTimeoffStaffId = null;
    document.getElementById('selectedTimeoffStaffBadge').style.display = 'none';
    document.getElementById('timeoffFormContainer').style.display = 'none';
    document.getElementById('timeoff_staff_id').value = '';
    document.getElementById('timeoffStaffSearchInput').value = '';
    document.getElementById('timeoff_start_date').value = '';
    document.getElementById('timeoff_end_date').value = '';
    document.getElementById('timeoff_reason').value = '';
}

window.onclick = function(event) {
    const modal = document.getElementById('scheduleEditModal');
    if (event.target === modal) {
        closeScheduleModal();
    }
}

function editUser(id, name, username, email, phone, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('username').value = username;
    document.getElementById('email').value = email;
    document.getElementById('phone').value = phone;
    document.getElementById('role').value = role;
    document.getElementById('password').required = false;
    document.getElementById('passwordNote').textContent = 'Leave blank to keep current password.';
    document.getElementById('formTitle').textContent = 'Edit User';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('userForm').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordNote').textContent = '';
    document.getElementById('formTitle').textContent = 'Add New User';
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const icon = document.getElementById('passwordIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function editTimeOff(id, staffName, startDate, endDate, reason) {
    document.getElementById('edit_timeoff_id').value = id;
    document.getElementById('edit_staff_name').value = staffName;
    document.getElementById('edit_start_date').value = startDate;
    document.getElementById('edit_end_date').value = endDate;
    document.getElementById('edit_reason').value = reason;
    document.getElementById('editTimeOffPanel').style.display = 'block';
    window.scrollTo({ top: document.getElementById('editTimeOffPanel').offsetTop - 100, behavior: 'smooth' });
}

function closeEditPanel() {
    document.getElementById('editTimeOffPanel').style.display = 'none';
    document.getElementById('edit_timeoff_id').value = '';
    document.getElementById('edit_staff_name').value = '';
    document.getElementById('edit_start_date').value = '';
    document.getElementById('edit_end_date').value = '';
    document.getElementById('edit_reason').value = '';
}

// ========== SEARCH AND FILTER FUNCTIONS ==========
function filterUsers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const selectedRole = document.getElementById('filterRole').value;
    const selectedStatus = document.getElementById('filterStatus').value;
    
    const rows = document.querySelectorAll('#usersTable tr');
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const role = row.getAttribute('data-role');
        const status = row.getAttribute('data-status');
        
        const matchesSearch = text.includes(searchTerm);
        const matchesRole = selectedRole === 'all' || role === selectedRole;
        const matchesStatus = selectedStatus === 'all' || status === selectedStatus;
        
        if (matchesSearch && matchesRole && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterRole').value = 'all';
    document.getElementById('filterStatus').value = 'all';
    filterUsers();
}

// Event listeners for filters
document.getElementById('searchInput').addEventListener('input', filterUsers);
document.getElementById('filterRole').addEventListener('change', filterUsers);
document.getElementById('filterStatus').addEventListener('change', filterUsers);

document.getElementById('timeoffSearchInput').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#timeoffTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
    });
});