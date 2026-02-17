// LOADING OVERLAY
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

// OPEN ADD MODAL
function openModal() {
    document.getElementById('escalationForm').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('type').value = 'Normal';
    document.getElementById('modalTitle').innerText = 'Add Escalation';
    document.getElementById('submitBtn').innerText = 'Save';
    const approvalWrapper = document.getElementById('approvalWrapper');
    if (approvalWrapper) approvalWrapper.classList.add('hidden');
    document.getElementById('escalationModal').classList.remove('hidden');
    document.getElementById('escalationModal').classList.add('flex');
}

// CLOSE MODAL
function closeModal() {
    document.getElementById('escalationModal').classList.add('hidden');
    document.getElementById('escalationModal').classList.remove('flex');
}

// EDIT MODAL
function openEditModal(id) {
    let row = document.querySelector('tr[data-id="' + id + '"]');
    if (!row) return;
    document.getElementById('edit_id').value = row.dataset.id;
    document.getElementById('ar_number').value = row.dataset.ar;
    document.getElementById('engineer_number').value = row.dataset.engineer;
    document.getElementById('dispatch_id').value = row.dataset.dispatch;
    document.getElementById('serial_number').value = row.dataset.serial;
    document.getElementById('unit_description').value = row.dataset.unit;
    document.getElementById('css_response').value = row.dataset.css;
    document.getElementById('remarks').value = row.dataset.remarks;
    document.getElementById('status').value = row.dataset.status;
    document.getElementById('type').value = row.dataset.type;
    document.getElementById('modalTitle').innerText = 'Edit Escalation';
    document.getElementById('submitBtn').innerText = 'Update';

    // Approval dropdown logic
    let approvalWrapper = document.getElementById('approvalWrapper');
    let approvalSelect = document.getElementById('approval_status');
    if (approvalWrapper && approvalSelect) {
        approvalSelect.innerHTML = '';
        approvalWrapper.classList.add('hidden');
        let creatorRole = row.dataset.creatorRole;
        let currentApproval = row.dataset.approval;
        let loggedRole = document.body.dataset.role;
        if (loggedRole === 'admin' && creatorRole === 'user') {
            approvalWrapper.classList.remove('hidden');
            ['Pending', 'Approved', 'Rejected'].forEach(status => {
                let option = new Option(status, status);
                if (status === currentApproval) option.selected = true;
                approvalSelect.add(option);
            });
        }
        if (loggedRole === 'super_admin' && creatorRole === 'admin') {
            approvalWrapper.classList.remove('hidden');
            ['Pending', 'Approved', 'Rejected', 'Under Investigation'].forEach(status => {
                let option = new Option(status, status);
                if (status === currentApproval) option.selected = true;
                approvalSelect.add(option);
            });
        }
    }
    document.getElementById('escalationModal').classList.remove('hidden');
    document.getElementById('escalationModal').classList.add('flex');
}

// MAKE RESO
function makeReso(id) {
    let row = document.querySelector('tr[data-id="' + id + '"]');
    if (!row) return;
    document.getElementById('escalationForm').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('ar_number').value = row.dataset.ar;
    document.getElementById('engineer_number').value = row.dataset.engineer;
    document.getElementById('dispatch_id').value = row.dataset.dispatch;
    document.getElementById('serial_number').value = row.dataset.serial;
    document.getElementById('unit_description').value = row.dataset.unit;
    document.getElementById('css_response').value = row.dataset.css;
    document.getElementById('remarks').value = row.dataset.remarks;
    document.getElementById('status').value = 'Open';
    document.getElementById('type').value = 'Reso';
    document.getElementById('modalTitle').innerText = 'Create Reso Escalation';
    document.getElementById('submitBtn').innerText = 'Save Reso';
    const approvalWrapper = document.getElementById('approvalWrapper');
    if (approvalWrapper) approvalWrapper.classList.add('hidden');
    document.getElementById('escalationModal').classList.remove('hidden');
    document.getElementById('escalationModal').classList.add('flex');
}

// VIEW FULL TEXT (generic — used for remarks, css, unit desc, etc.)
function viewRemarks(text, title) {
    document.getElementById('remarksModalTitle').innerText = title || 'Full Remarks';
    document.getElementById('remarksContent').innerText = text;
    const modal = document.getElementById('remarksModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRemarks() {
    const modal = document.getElementById('remarksModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Close modals on backdrop click
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('remarksModal').addEventListener('click', function (e) {
        if (e.target === this) closeRemarks();
    });
    document.getElementById('escalationModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
});