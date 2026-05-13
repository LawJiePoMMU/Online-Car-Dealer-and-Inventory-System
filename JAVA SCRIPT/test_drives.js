let currentResId = null;
let currentRowData = null;

function fmt(n) { return parseFloat(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function getCurrentDateTimeLocal() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
}

function openModal(row) {
    currentResId = row.reservation_id;
    currentRowData = row;
    const status = row.reservation_status;

    document.getElementById('detName').textContent = row.user_name || '-';
    document.getElementById('detEmail').textContent = row.user_email || '-';
    document.getElementById('detIC').textContent = row.user_ic || '-';
    document.getElementById('detContact').textContent = row.user_phone || '-';

    document.getElementById('detCarImage').src = row.car_image || 'https://via.placeholder.com/120x88?text=No+Image';
    document.getElementById('detCarBrand').textContent = row.car_brand || '-';
    document.getElementById('detCarModel').textContent = row.car_model || '-';
    document.getElementById('detCarVariant').textContent = row.car_variant || '-';
    document.getElementById('detCarColor').textContent = row.car_color || '-';
    document.getElementById('detCarYear').textContent = row.car_year || '-';

    document.getElementById('detTDAt').textContent = row.test_drive_at ? new Date(row.test_drive_at).toLocaleString() : 'Not Scheduled';
    document.getElementById('detTDDone').textContent = row.test_drive_done_at ? new Date(row.test_drive_done_at).toLocaleString() : '-';

    const tdScheduleWrap = document.getElementById('tdScheduleWrap');
    if (status === 'Test Drive Pending') {
        tdScheduleWrap.style.display = 'block';
        const tdDateTimeInput = document.getElementById('tdDateTime');
        if (tdDateTimeInput) tdDateTimeInput.min = getCurrentDateTimeLocal();
    } else {
        tdScheduleWrap.style.display = 'none';
    }

    const url = row.driving_licence_url;
    const frame = document.getElementById('frameLicence');
    const vbtn = document.getElementById('btnViewLicence');
    const lblText = document.getElementById('lblLicenceText'); // 修復上傳 Bug

    if (frame) { frame.src = (url && url !== '#') ? url : ''; frame.style.display = 'none'; }
    if (url && url !== 'NULL' && url !== '') {
        vbtn.style.display = 'inline-block';
        if (lblText) lblText.innerHTML = '<i class="fas fa-sync"></i> Replace';
    } else {
        vbtn.style.display = 'none';
        if (lblText) lblText.innerHTML = '<i class="fas fa-upload"></i> Upload';
    }

    document.getElementById('btnMarkTDDone').style.display = (status === 'Test Drive Pending') ? 'inline-block' : 'none';
    document.getElementById('btnConvertBook').style.display = (status === 'Test Drive Done') ? 'inline-block' : 'none';
    document.getElementById('btnCancelRes').style.display = (status === 'Test Drive Pending' || status === 'Test Drive Done') ? 'inline-block' : 'none';

    // 打開 Modal (符合 users.php 樣式)
    document.getElementById('splitModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('splitModal').style.display = 'none';
    currentResId = null; currentRowData = null;
}

async function doAction(action, extra = {}, skipReload = false) {
    const body = new URLSearchParams({ action, reservation_id: currentResId, ...extra });
    try {
        const res = await fetch('test_drives.php', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Done!', text: data.message, timer: 1500, showConfirmButton: false });
            if (!skipReload) { closeModal(); location.reload(); }
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
}

function togglePdf(id) {
    const frame = document.getElementById(id);
    if (frame.src && frame.src !== window.location.href && !frame.src.endsWith('#')) {
        frame.style.display = frame.style.display === 'none' ? 'block' : 'none';
    } else {
        Swal.fire({ icon: 'info', title: 'Not Found', text: 'Document not uploaded yet.', timer: 1500, showConfirmButton: false });
    }
}

async function uploadLicence(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'upload_licence');
    fd.append('reservation_id', currentResId);
    fd.append('doc_file', input.files[0]);
    try {
        const res = await fetch('test_drives.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Uploaded!', text: data.message, timer: 1500, showConfirmButton: false });
            location.reload();
        } else { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); }
    } catch (e) { Swal.fire({ icon: 'error', title: 'Network Error', text: e.message }); }
}

async function scheduleTestDrive() {
    const tdTime = document.getElementById('tdDateTime').value;
    if (!tdTime) { Swal.fire('Required', 'Please select a date and time.', 'warning'); return; }
    if (new Date(tdTime) < new Date()) { Swal.fire('Invalid Time', 'Test drive cannot be scheduled in the past.', 'error'); return; }
    await doAction('schedule_test_drive', { test_drive_at: tdTime });
}

async function markTestDriveDone() {
    const frame = document.getElementById('frameLicence');
    if (!frame.src || frame.src === window.location.href || frame.src.endsWith('#')) {
        Swal.fire('Incomplete', 'Please upload the Driving Licence (PDF) first.', 'warning'); return;
    }
    const r = await Swal.fire({ title: 'Mark as Done?', text: 'Did the customer complete the test drive?', icon: 'question', showCancelButton: true, confirmButtonColor: '#10b981' });
    if (r.isConfirmed) await doAction('mark_test_drive_done', {});
}

function openConvertModal() { document.getElementById('convertModal').style.display = 'block'; }

async function confirmConvert() {
    const amt = document.getElementById('convertAmount').value;
    const method = document.getElementById('convertMethod').value;
    if (!amt || amt <= 0) { Swal.fire('Required', 'Please enter a valid amount.', 'warning'); return; }
    await doAction('convert_to_booking', { payment_amount: amt, payment_method: method });
}

async function cancelReservation() {
    const { value: reason, isConfirmed } = await Swal.fire({ title: 'Cancel Reservation?', input: 'textarea', inputLabel: 'Reason', inputPlaceholder: 'Enter reason…', showCancelButton: true, confirmButtonText: 'Cancel', confirmButtonColor: '#ef4444' });
    if (isConfirmed) await doAction('cancel_reservation', { reason: reason || '' });
}

function openAddTDModal() {
    const addInput = document.querySelector('#addTDForm input[name="test_drive_at"]');
    if (addInput) addInput.min = getCurrentDateTimeLocal();
    document.getElementById('addTDModal').style.display = 'block';
}

const addTDForm = document.getElementById('addTDForm');
if (addTDForm) {
    addTDForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this); fd.append('action', 'add_test_drive');
        const tdTime = fd.get('test_drive_at');
        if (new Date(tdTime) < new Date()) { Swal.fire('Invalid Time', 'Test drive cannot be scheduled in the past.', 'error'); return; }

        try {
            const res = await fetch('test_drives.php', { method: 'POST', body: new URLSearchParams(fd) });
            const data = await res.json();
            if (data.success) { await Swal.fire({ icon: 'success', title: 'Created!', text: data.message, timer: 2000, showConfirmButton: false }); location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); }
        } catch (e) { Swal.fire({ icon: 'error', title: 'Network Error', text: e.message }); }
    });
}