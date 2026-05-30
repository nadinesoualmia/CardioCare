// Receptionist Dashboard JavaScript

document.getElementById('receptionForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const data = {
        fullName: document.getElementById('pFullName').value.trim(),
        phone:    document.getElementById('pPhone').value.trim(),
        emergency: document.getElementById('pEmergency').value.trim(),
        gender:   document.getElementById('pGender').value,
        dob:      document.getElementById('pDOB').value,
        email:    document.getElementById('pEmail').value.trim(),
        address:  document.getElementById('pAddress').value.trim(),
        nin:      document.getElementById('pCN').value.trim()
    };

    // Validate required fields (Added DOB as required)
    if (!data.fullName || !data.phone || !data.gender || !data.dob) {
        showFlash('danger', 'Please fill all required fields (Name, Phone, Gender, Date of Birth)');
        return;
    }

    // Validate NIN if provided
    if (data.nin && data.nin.length !== 18) {
        showFlash('danger', 'Algerian NIN must be exactly 18 digits if provided.');
        return;
    }

    // Validate emergency number if provided
    if (data.emergency) {
        const emergencyPattern = /^(05|06|07)\d{8}$|^(\+213)(5|6|7)\d{8}$/;
        if (!emergencyPattern.test(data.emergency)) {
            showFlash('danger', 'Emergency contact must be a valid Algerian number');
            return;
        }
    }

    fetch('backend/register_patient.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            showFlash('success', res.message);
            document.getElementById('receptionForm').reset();
        } else if (res.status === 'duplicate') {
            showFlash('danger', res.message);
        } else {
            showFlash('danger', res.message);
        }
    })
    .catch(err => showFlash('danger', 'Network error: ' + err));
});

function showFlash(type, msg) {
    const el = document.getElementById('flashMsg');
    el.className = 'alert alert-' + type;
    el.innerHTML = (type === 'success'
        ? '<i class="fa-solid fa-circle-check"></i> '
        : '<i class="fa-solid fa-circle-exclamation"></i> ') + msg;
    el.style.display = 'flex';
    setTimeout(() => el.style.display = 'none', 5000);
}