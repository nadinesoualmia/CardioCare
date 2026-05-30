let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let selectedAppointment = null;

function renderCalendar() {
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    let startingDay = firstDay.getDay();
    const totalDays = lastDay.getDate();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('currentMonthDisplay').innerText = monthNames[currentMonth] + ' ' + currentYear;
    
    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';
    
    for (let i = 0; i < startingDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarDays.appendChild(emptyDay);
    }
    
    const today = new Date();
    const todayDate = today.getDate();
    const todayMonth = today.getMonth();
    const todayYear = today.getFullYear();
    
    for (let day = 1; day <= totalDays; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        
        const dateStr = currentYear + '-' + String(currentMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.innerText = day;
        if (todayYear === currentYear && todayMonth === currentMonth && todayDate === day) {
            dayNumber.classList.add('today');
        }
        dayCell.appendChild(dayNumber);
        
        if (appointmentsByDate[dateStr] && appointmentsByDate[dateStr].length > 0) {
            appointmentsByDate[dateStr].forEach(appt => {
                const apptDiv = document.createElement('div');
                apptDiv.className = 'day-appointment';
                
                let statusClass = 'waiting';
                let statusText = appt.status || 'Waiting';
                if (appt.status === 'Completed') {
                    statusClass = 'completed';
                } else if (appt.status === 'Scheduled') {
                    statusClass = 'scheduled';
                }
                
                let timeDisplay = appt.appointment_time;
                if (timeDisplay && timeDisplay.length > 5) {
                    timeDisplay = timeDisplay.substring(0, 5);
                }
                
                apptDiv.innerHTML = `
                    <span class="appointment-time">${timeDisplay}</span>
                    <span class="appointment-patient">${escapeHtml(appt.patient_name)}</span>
                    <span class="appointment-badge badge-${statusClass}">${statusText}</span>
                `;
                
                apptDiv.onclick = (function(a) {
                    return function() { showAppointmentDetails(a); };
                })(appt);
                
                dayCell.appendChild(apptDiv);
            });
        }
        
        calendarDays.appendChild(dayCell);
    }
    
    const totalCells = calendarDays.children.length;
    const remainingCells = 42 - totalCells;
    for (let i = 0; i < remainingCells; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarDays.appendChild(emptyDay);
    }
}

function showAppointmentDetails(appt) {
    selectedAppointment = appt;
    const modal = document.getElementById('appointmentModal');
    const modalDetails = document.getElementById('modalDetails');
    const completeBtn = document.getElementById('modalCompleteBtn');
    
    const status = appt.status || 'Waiting';
    const isCompleted = status === 'Completed';
    
    let timeDisplay = appt.appointment_time;
    if (timeDisplay && timeDisplay.length > 5) {
        timeDisplay = timeDisplay.substring(0, 5);
    }
    
    modalDetails.innerHTML = `
        <div class="modal-detail"><strong>Patient:</strong> ${escapeHtml(appt.patient_name)}</div>
        <div class="modal-detail"><strong>Date:</strong> ${appt.appointment_date}</div>
        <div class="modal-detail"><strong>Time:</strong> ${timeDisplay}</div>
        <div class="modal-detail"><strong>Queue:</strong> ${appt.queue_number || 'N/A'}</div>
        <div class="modal-detail"><strong>Service:</strong> ${appt.service}</div>
        <div class="modal-detail"><strong>Status:</strong> <span style="color:${isCompleted ? '#10b981' : '#f59e0b'}">${status}</span></div>
    `;
    
    if (isCompleted) {
        completeBtn.disabled = true;
        completeBtn.style.background = '#9ca3af';
        completeBtn.style.cursor = 'not-allowed';
        completeBtn.innerText = 'Already Completed';
    } else {
        completeBtn.disabled = false;
        completeBtn.style.background = '#10b981';
        completeBtn.style.cursor = 'pointer';
        completeBtn.innerText = 'Mark as Completed';
    }
    
    modal.style.display = 'block';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function markCompleteFromModal() {
    if (selectedAppointment && selectedAppointment.status !== 'Completed') {
        markComplete(selectedAppointment.queue_number);
    }
}

function closeModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    selectedAppointment = null;
}

function markComplete(queueNumber) {
    fetch('backend/appointments_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=markComplete&queue_number=' + encodeURIComponent(queueNumber)
    })
    .then(r => r.text())
    .then(r => {
        if (r.trim() === 'success') {
            location.reload();
        } else {
            alert(r);
        }
    });
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    } else if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    renderCalendar();
}

function goToToday() {
    currentDate = new Date();
    currentMonth = currentDate.getMonth();
    currentYear = currentDate.getFullYear();
    renderCalendar();
}

window.onclick = function(event) {
    const modal = document.getElementById('appointmentModal');
    if (event.target === modal) {
        closeModal();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
});