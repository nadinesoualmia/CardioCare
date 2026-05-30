// Generate time slots based on working hours
function generateTimeSlots(startTime, endTime) {
    const slots = [];
    let current = startTime;
    const end = endTime;
    
    while (current < end) {
        slots.push(current);
        let [hours, minutes] = current.split(':').map(Number);
        minutes += 30;
        if (minutes >= 60) {
            hours += 1;
            minutes -= 60;
        }
        current = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }
    return slots;
}

let currentBookedTimes = [];
let currentWorkingHours = { start: '08:00', end: '17:00' };

// Tab switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`tab-${tabName}`).classList.add('active');
    const clickedBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.getAttribute('data-tab') === tabName);
    if (clickedBtn) clickedBtn.classList.add('active');
}

// Load time slots
async function loadTimeSlots(doctorId, date, containerId, timeInputId) {
    if (!doctorId || !date) return;
    const container = document.getElementById(containerId);
    if (!container) return;
    
    try {
        container.innerHTML = '<div class="loading-text"><i class="fa-solid fa-spinner fa-spin"></i> Loading available slots...</div>';
        
        const response = await fetch(`backend/get_booked_times.php?doctor_id=${doctorId}&date=${date}`);
        const data = await response.json();
        
        if (data.off === true) {
            if (data.vacation === true) {
                const startDate = new Date(data.start_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                const endDate = new Date(data.end_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                container.innerHTML = `
                    <div style="color:#ef4444; text-align:center; width:100%; padding:10px;">
                        <i class="fa-solid fa-umbrella-beach" style="font-size:1.2rem; display:block; margin-bottom:8px;"></i>
                        <strong>Doctor on ${data.reason || 'Time Off'}</strong><br>
                        <small>From: ${startDate} to ${endDate}</small>
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div style="color:#ef4444; text-align:center; width:100%; padding:10px;">
                        <i class="fa-solid fa-calendar-xmark" style="font-size:1.2rem; display:block; margin-bottom:8px;"></i>
                        Doctor is not scheduled to work on this day.
                    </div>
                `;
            }
            return;
        }
        
        currentBookedTimes = data.booked || [];
        currentWorkingHours = { start: data.start || '08:00', end: data.end || '17:00' };
        const timeSlots = generateTimeSlots(currentWorkingHours.start, currentWorkingHours.end);
        document.getElementById(timeInputId).value = '';
        const availableSlots = timeSlots.filter(slot => !currentBookedTimes.includes(slot));
        
        container.innerHTML = '';
        
        if (availableSlots.length === 0) {
            container.innerHTML = '<div class="loading-text" style="color:#ef4444;">No available time slots for this date.</div>';
            return;
        }
        
        const infoDiv = document.createElement('div');
        infoDiv.style.cssText = 'color:#10b981; font-size:11px; margin-bottom:8px; width:100%; text-align:center;';
        infoDiv.innerHTML = `⏰ Working hours: ${currentWorkingHours.start} - ${currentWorkingHours.end}`;
        container.appendChild(infoDiv);
        
        availableSlots.forEach(slot => {
            const slotDiv = document.createElement('div');
            slotDiv.className = 'time-slot';
            slotDiv.textContent = slot;
            slotDiv.onclick = () => {
                document.querySelectorAll(`#${containerId} .time-slot`).forEach(s => s.classList.remove('selected'));
                slotDiv.classList.add('selected');
                document.getElementById(timeInputId).value = slot;
            };
            container.appendChild(slotDiv);
        });
        
    } catch (error) {
        console.error('Error loading time slots:', error);
        container.innerHTML = '<div class="loading-text" style="color:#ef4444;">Error loading time slots</div>';
    }
}

// Open schedule form
function openScheduleForm(reqId, patId, patName, examType, dept, drName, urgency) {
    document.getElementById('scheduleWrap').style.display = 'block';
    document.getElementById('scheduleWrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('f_req_id').value = reqId;
    document.getElementById('f_pat_id').value = patId;
    const svc = dept.charAt(0).toUpperCase() + dept.slice(1).toLowerCase();
    document.getElementById('f_service').value = svc;
    document.getElementById('f_pat_name').value = patName;
    document.getElementById('f_svc_display').value = svc + ' — ' + examType;
    document.getElementById('f_price').value = prices[svc] || '';
    const sel = document.getElementById('f_staff_sel');
    sel.innerHTML = '<option value="">Select staff…</option>';
    const need = roleMap[svc];
    allStaff.filter(s => s.role === need).forEach(s => { 
        const o = document.createElement('option'); 
        o.value = s.id; 
        o.textContent = s.full_name; 
        sel.appendChild(o); 
    });
    
    const tomorrow = new Date(); 
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateInput = document.getElementById('f_date');
    dateInput.value = tomorrow.toISOString().split('T')[0];
    
    dateInput.onchange = () => { 
        const doctorId = document.getElementById('f_staff_sel').value; 
        if (doctorId && dateInput.value) {
            loadTimeSlots(doctorId, dateInput.value, 'timeSlotsContainer', 'f_time');
        }
    };
    
    sel.onchange = () => { 
        const doctorId = sel.value; 
        if (doctorId && dateInput.value) {
            loadTimeSlots(doctorId, dateInput.value, 'timeSlotsContainer', 'f_time');
        }
    };
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.getAttribute('data-tab');
            if (tab) switchTab(tab);
        });
    });
    
    // Schedule buttons
    document.querySelectorAll('.sched-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const reqId = this.getAttribute('data-req-id');
            const patId = this.getAttribute('data-pat-id');
            const patName = this.getAttribute('data-pat-name');
            const examType = this.getAttribute('data-exam-type');
            const dept = this.getAttribute('data-dept');
            const drName = this.getAttribute('data-dr-name');
            const urgency = this.getAttribute('data-urgency');
            openScheduleForm(reqId, patId, patName, examType, dept, drName, urgency);
        });
    });
    
    // Cancel button
    document.querySelector('.cancel-btn')?.addEventListener('click', function() {
        document.getElementById('scheduleWrap').style.display = 'none';
    });
    
    // Walk-in tab event listeners
    const wStaff = document.getElementById('w_staff');
    const wDate = document.getElementById('w_date');
    const wService = document.getElementById('w_service');
    
    if (wStaff) {
        wStaff.addEventListener('change', function() { 
            const doctorId = this.value; 
            const date = wDate.value; 
            if (doctorId && date) {
                loadTimeSlots(doctorId, date, 'walkinTimeSlotsContainer', 'w_time');
            }
        });
    }
    
    if (wDate) {
        wDate.addEventListener('change', function() { 
            const doctorId = wStaff.value; 
            const date = this.value; 
            if (doctorId && date) {
                loadTimeSlots(doctorId, date, 'walkinTimeSlotsContainer', 'w_time');
            }
        });
    }
    
    const walkinDateInput = document.getElementById('w_date'); 
    if (walkinDateInput) { 
        const tomorrow = new Date(); 
        tomorrow.setDate(tomorrow.getDate() + 1); 
        walkinDateInput.value = tomorrow.toISOString().split('T')[0]; 
    }
    
    const wPatName = document.getElementById('w_pat_name');
    if (wPatName) {
        wPatName.addEventListener('change', function() { 
            const p = allPatients.find(x => x.full_name === this.value); 
            document.getElementById('w_pat_id').value = p ? p.id : ''; 
        });
    }
    
    if (wService) {
        wService.addEventListener('change', function() { 
            const svc = this.value;
            document.getElementById('w_price').value = prices[svc] || ''; 
            const sel = document.getElementById('w_staff'); 
            sel.innerHTML = '<option value="">Select staff…</option>'; 
            const need = roleMap[svc]; 
            allStaff.filter(s => s.role === need).forEach(s => { 
                const o = document.createElement('option'); 
                o.value = s.id; 
                o.textContent = s.full_name; 
                sel.appendChild(o); 
            }); 
        });
    }
    
    // Form submit validation
    document.getElementById('scheduleForm')?.addEventListener('submit', function(e) {
        const selectedTime = document.getElementById('f_time').value;
        if (selectedTime && currentBookedTimes.includes(selectedTime)) {
            e.preventDefault();
            alert('❌ This time slot is already booked! Please select another time.');
            return false;
        }
        if (!selectedTime) {
            e.preventDefault();
            alert('Please select a time slot first.');
            return false;
        }
    });
    
    document.getElementById('walkinForm')?.addEventListener('submit', function(e) {
        const selectedTime = document.getElementById('w_time').value;
        if (selectedTime && currentBookedTimes.includes(selectedTime)) {
            e.preventDefault();
            alert('❌ This time slot is already booked! Please select another time.');
            return false;
        }
        if (!selectedTime) {
            e.preventDefault();
            alert('Please select a time slot first.');
            return false;
        }
    });
});