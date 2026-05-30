// Exam Types data
const examTypes = {
    radiology: ['Chest X-Ray', 'Echocardiogram', 'CT Scan', 'MRI', 'Abdominal Ultrasound'],
    laboratory: ['Blood Test', 'Lipid Profile', 'Glucose Test', 'Troponin Test', 'CBC', 'HbA1c']
};

// Department change handler
function onDeptChange(dept) {
    const examSel = document.getElementById('examSel');
    examSel.innerHTML = '';
    examSel.disabled = false;
    
    const types = examTypes[dept] || [];
    types.forEach(t => {
        const option = document.createElement('option');
        option.value = t;
        option.textContent = t;
        examSel.appendChild(option);
    });
}

// Add event listener when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const deptSel = document.getElementById('deptSel');
    if (deptSel) {
        deptSel.addEventListener('change', function() {
            onDeptChange(this.value);
        });
    }
    
    // Patient search validation
    const patientSearch = document.getElementById('patientSearch');
    if (patientSearch) {
        patientSearch.addEventListener('input', function() {
            const options = document.querySelectorAll('#patientList option');
            let found = false;
            options.forEach(opt => {
                if (opt.value === this.value) {
                    found = true;
                }
            });
            if (!found && this.value !== '') {
                this.setCustomValidity('Please select a patient from the list');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});