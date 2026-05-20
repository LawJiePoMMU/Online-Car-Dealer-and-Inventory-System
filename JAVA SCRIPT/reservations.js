const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

const style = document.createElement('style');
style.innerHTML = '.swal2-container { z-index: 100000 !important; }';
document.head.appendChild(style);

let currentRow = null;
let currentContext = { tab: '', sub_tab: '' };

function getCurrentDateTimeLocal() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
}

function openModal(row, tab, sub_tab) {
    currentRow = row;
    currentContext = { tab: tab, sub_tab: sub_tab || '' };

    document.getElementById('detName').textContent = row.user_name || 'Unknown';
    document.getElementById('detEmail').textContent = row.user_email || '-';
    document.getElementById('detIC').textContent = row.user_ic || '-';
    document.getElementById('detContact').textContent = row.user_phone || '-';

    document.getElementById('detCarImage').src = row.car_image || 'https://via.placeholder.com/120x88?text=No+Image';
    document.getElementById('detCarBrand').textContent = row.car_brand || 'Unknown';
    document.getElementById('detCarModel').textContent = row.car_model || '-';
    document.getElementById('detCarVariant').textContent = row.car_variant || '-';
    document.getElementById('detCarColor').textContent = row.car_color || '-';
    document.getElementById('detCarYear').textContent = row.car_year || '-';

    const plateWrap = document.getElementById('detCarPlateWrap');
    if (row.car_origin === 'Used Car' && row.car_plate) {
        document.getElementById('detCarPlate').textContent = row.car_plate;
        plateWrap.style.display = 'block';
    } else {
        plateWrap.style.display = 'none';
    }

    document.getElementById('detResStatus').textContent = row.reservation_status || '-';
    document.getElementById('detTDStatus').textContent = row.test_drive_status || '-';

    ['btnRejectRes', 'btnApproveRes', 'btnCancelTD', 'btnMarkCompleted'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    document.getElementById('scheduleWrap').style.display = 'none';
    document.getElementById('reasonWrap').style.display = 'none';
    document.getElementById('detTDStatus').closest('.detail-item').style.display = 'block';
    document.getElementById('detTDDone').closest('.detail-item').style.display = 'block';
    document.getElementById('detTDAt').previousElementSibling.textContent = 'Test Drive At';
    let tdAtStr = '-';
    if (row.test_drive_at && row.test_drive_at !== '0000-00-00 00:00') tdAtStr = new Date(row.test_drive_at).toLocaleString();
    let tdDoneStr = '-';
    if (row.test_drive_done_at && row.test_drive_done_at !== '0000-00-00 00:00') tdDoneStr = new Date(row.test_drive_done_at).toLocaleString();

    document.getElementById('detTDAt').innerHTML = tdAtStr;
    document.getElementById('detTDDone').textContent = tdDoneStr;

    const titleEl = document.getElementById('modalTitleText');

   if (tab === 'reservations') {
        titleEl.textContent = 'Reservation Details';
        document.getElementById('detTDStatus').closest('.detail-item').style.display = 'none';
        document.getElementById('detTDDone').closest('.detail-item').style.display = 'none';
        document.getElementById('detTDAt').previousElementSibling.textContent = 'Requested Time';
        let timeHtml = (row.preferred_test_drive_at && row.preferred_test_drive_at !== '0000-00-00 00:00:00') ? new Date(row.preferred_test_drive_at).toLocaleString() : 'Not Specified';
        if (row.clash_users) {
            timeHtml += `<br><span style="color:#ef4444;font-size:12px;display:inline-block;margin-top:6px;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Clash Detected: ${row.clash_users}</span>`;
            timeHtml += `<br><span style="color:#10b981;font-size:12px;display:inline-block;margin-top:2px;font-weight:600;"><i class="fas fa-lightbulb"></i> Suggestion: ${new Date(row.suggested_time).toLocaleString()}</span>`;
        }
        document.getElementById('detTDAt').innerHTML = timeHtml;
        document.getElementById('btnApproveRes').style.display = 'inline-block';
        document.getElementById('btnRejectRes').style.display = 'inline-block';
    } else if (tab === 'test_drives') {
        titleEl.textContent = 'Test Drive Details';
        document.getElementById('detTDStatus').closest('.detail-item').style.display = 'block'; 
        document.getElementById('detTDDone').closest('.detail-item').style.display = 'none'; 
        document.getElementById('btnCancelTD').style.display = 'inline-block';
        const testDriveTime = new Date(row.test_drive_at);
        const currentTime = new Date();
        if (currentTime >= testDriveTime) {
            document.getElementById('btnMarkCompleted').style.display = 'inline-block';
        } else {
            document.getElementById('btnMarkCompleted').style.display = 'none';
        }
    } else if (tab === 'history') {
        titleEl.textContent = 'History Details';
        if (sub_tab === 'test_drives') {
            document.getElementById('detTDStatus').closest('.detail-item').style.display = 'block';
            document.getElementById('detTDDone').closest('.detail-item').style.display = 'block';
            
            if (row.test_drive_status === 'Cancelled' && row.test_drive_cancel_reason) {
                document.getElementById('reasonLabel').textContent = 'Cancellation Reason';
                document.getElementById('detReason').textContent = row.test_drive_cancel_reason;
                document.getElementById('reasonWrap').style.display = 'block';
            }
        } else if (sub_tab === 'reservations') {
            document.getElementById('detTDStatus').closest('.detail-item').style.display = 'none';
            document.getElementById('detTDDone').closest('.detail-item').style.display = 'none';
            if (row.reservation_cancel_reason) {
                document.getElementById('reasonLabel').textContent = 'Rejection Reason';
                document.getElementById('detReason').textContent = row.reservation_cancel_reason;
                document.getElementById('reasonWrap').style.display = 'block';
            }
        }
    }

    const url = row.driving_licence_url;
    const frame = document.getElementById('frameLicence');
    const vbtn = document.getElementById('btnViewLicence');
    const missingLabel = document.getElementById('lblLicenceMissing');
    if (frame) {
        frame.src = (url && url !== '#' && url !== 'NULL') ? url : '';
        frame.style.display = 'none';
    }

    if (url && url !== 'NULL' && url !== '') {
        vbtn.style.display = 'inline-block';
        if (missingLabel) missingLabel.style.display = 'none';
    } else {
        vbtn.style.display = 'none';
        if (missingLabel) missingLabel.style.display = 'inline-block';
    }
    document.getElementById('splitModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('splitModal').style.display = 'none';
    currentRow = null;
    currentContext = { tab: '', sub_tab: '' };
}

async function doAction(payload, skipReload = false) {
    const body = new URLSearchParams(payload);
    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            Toast.fire({ icon: 'success', title: data.message || 'Done!' });
            if (!skipReload) setTimeout(() => location.reload(), 800);
            return true;
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            return false;
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
        return false;
    }
}

async function approveReservation() {
    if (!currentRow) return;

    if (!currentRow.preferred_test_drive_at || currentRow.preferred_test_drive_at === '0000-00-00 00:00') {
        Swal.fire({ icon: 'warning', title: 'Action Denied', text: 'Customer has not defined a valid test drive datetime.' });
        return;
    }

    const r = await Swal.fire({
        title: 'Approve reservation?',
        text: 'This will schedule the test drive at the customer\'s requested time.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Approve'
    });
    if (r.isConfirmed) {
        await doAction({
            action: 'approve_reservation',
            reservation_id: currentRow.reservation_id
        });
    }
}

async function rejectReservation() {
    if (!currentRow) return;
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'Reject reservation?',
        input: 'textarea',
        inputLabel: 'Reason (required)',
        inputPlaceholder: 'Enter reason for rejection…',
        inputValidator: (v) => !v && 'Reason is required',
        showCancelButton: true,
        confirmButtonText: 'Reject',
        confirmButtonColor: '#ef4444'
    });
    if (isConfirmed) {
        await doAction({
            action: 'reject_reservation',
            reservation_id: currentRow.reservation_id,
            reason: reason
        });
    }
}

async function markCompleted() {
    if (!currentRow) return;
    const url = currentRow.driving_licence_url;
    if (!url || url === 'NULL' || url === '' || url === '#') {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Document',
            text: 'Cannot complete test drive. Customer did not provide a driving licence.'
        });
        return;
    }
    const r = await Swal.fire({
        title: 'Mark test drive as completed?',
        text: 'Confirm the customer attended the test drive.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Mark Completed'
    });
    if (r.isConfirmed) {
        await doAction({
            action: 'mark_test_drive_completed',
            test_drive_id: currentRow.test_drive_id
        });
    }
}

async function cancelTestDrive() {
    if (!currentRow) return;
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'Cancel test drive?',
        input: 'textarea',
        inputLabel: 'Cancellation reason (required)',
        inputPlaceholder: 'Enter reason…',
        inputValidator: (v) => !v && 'Reason is required',
        showCancelButton: true,
        confirmButtonText: 'Cancel',
        confirmButtonColor: '#ef4444'
    });
    if (isConfirmed) {
        await doAction({
            action: 'cancel_test_drive',
            test_drive_id: currentRow.test_drive_id,
            reason: reason
        });
    }
}

function togglePdf(id) {
    const frame = document.getElementById(id);
    if (frame.src && frame.src !== window.location.href && !frame.src.endsWith('#')) {
        frame.style.display = frame.style.display === 'none' ? 'block' : 'none';
    } else {
        Toast.fire({ icon: 'info', title: 'Document not uploaded yet.' });
    }
}


document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const highlightId = params.get('highlight');
    if (highlightId) {
        const cleanUrl = new URL(window.location);
        cleanUrl.searchParams.delete('highlight');
        window.history.replaceState(null, '', cleanUrl);

        setTimeout(() => {
            let targetRow = null;
            document.querySelectorAll('tbody .data-row').forEach(row => {
                const idCell = row.querySelector('td:first-child');
                if (idCell) {
                    const rowId = idCell.textContent.trim().replace('RES', '').replace('TD', '').replace(/^0+/, '');
                    if (rowId === highlightId) targetRow = row;
                }
            });
            if (targetRow) {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                let count = 0;
                const originalBg = targetRow.style.backgroundColor;
                const flash = setInterval(() => {
                    targetRow.style.transition = 'background-color 0.3s ease';
                    targetRow.style.backgroundColor = (count % 2 === 0) ? '#fef3c7' : originalBg;
                    count++;
                    if (count >= 6) {
                        clearInterval(flash);
                        targetRow.style.backgroundColor = originalBg;
                    }
                }, 400);
            }
        }, 500);
    }
    // ===== End Highlight =====

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    });
});