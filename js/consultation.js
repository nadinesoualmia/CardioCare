// Auto-select patient ID from datalist
document.addEventListener('DOMContentLoaded', function() {
    const patientSearch = document.getElementById('patientSearch');
    const patientIdInput = document.getElementById('patient_id');
    const patientOptions = document.querySelectorAll('#patientDataList option');
    
    if (patientSearch) {
        patientSearch.addEventListener('input', function() {
            const val = this.value;
            let found = false;
            
            patientOptions.forEach(opt => {
                if (opt.value === val) {
                    patientIdInput.value = opt.dataset.id;
                    found = true;
                }
            });
            
            if (!found) {
                patientIdInput.value = '';
            }
        });
    }
    
    // Form validation before submit
    const consultationForm = document.getElementById('consultationForm');
    if (consultationForm) {
        consultationForm.addEventListener('submit', function(e) {
            const patientId = patientIdInput.value;
            const patientName = patientSearch ? patientSearch.value : '';
            
            if (!patientId) {
                e.preventDefault();
                alert('Please select a valid patient from the list');
                return false;
            }
            
            if (!patientName) {
                e.preventDefault();
                alert('Please select a patient');
                return false;
            }
        });
    }
});