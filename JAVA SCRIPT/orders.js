const locations = {
    "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Kulai", "Muar", "Segamat", "Pontian", "Kota Tinggi", "Mersing", "Tangkak"],
    "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Baling", "Langkawi", "Kubang Pasu", "Yan", "Padang Terap", "Sik"],
    "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Bachok", "Tanah Merah", "Machang", "Kuala Krai", "Gua Musang"],
    "Kuala Lumpur": ["Kuala Lumpur"],
    "Labuan": ["Labuan"],
    "Melaka": ["Melaka Tengah", "Alor Gajah", "Jasin"],
    "Negeri Sembilan": ["Seremban", "Port Dickson", "Jempol", "Tampin", "Kuala Pilah", "Rembau", "Jelebu"],
    "Pahang": ["Kuantan", "Temerloh", "Bentong", "Pekan", "Rompin", "Raub", "Jerantut", "Lipis", "Cameron Highlands"],
    "Penang": ["George Town", "Butterworth", "Bukit Mertajam", "Seberang Perai"],
    "Perak": ["Ipoh", "Taiping", "Teluk Intan", "Manjung", "Kuala Kangsar", "Batu Gajah", "Kampar", "Tapah"],
    "Perlis": ["Kangar", "Arau", "Padang Besar"],
    "Putrajaya": ["Putrajaya"],
    "Sabah": ["Kota Kinabalu", "Sandakan", "Tawau", "Lahad Datu", "Keningau", "Semporna"],
    "Sarawak": ["Kuching", "Miri", "Sibu", "Bintulu", "Limbang", "Samarahan", "Sri Aman"],
    "Selangor": ["Petaling Jaya", "Shah Alam", "Subang Jaya", "Klang", "Kajang", "Sepang", "Selayang", "Ampang", "Gombak"],
    "Terengganu": ["Kuala Terengganu", "Kemaman", "Dungun", "Besut", "Marang", "Setiu"]
};

let currentResId = null;
let currentCarPrice = 0;
let currentRowData = null;

function openModal(row) {
    currentResId = row.reservation_id;
    currentRowData = row;
    const status = row.reservation_status;
    const isHistory = ['Sold', 'Cancelled', 'Refunded'].includes(status);
    document.getElementById('loanYears').disabled = isHistory;

    document.getElementById('detName').textContent = row.user_name || '-';
    document.getElementById('detEmail').textContent = row.user_email || '-';
    document.getElementById('detIC').textContent = row.user_ic || '-';
    document.getElementById('detContact').textContent = row.user_phone || '-';
    document.getElementById('detAddress').textContent = row.user_address || '-';
    document.getElementById('detCity').textContent = row.user_city || '-';
    document.getElementById('detState').textContent = row.user_state || '-';
    document.getElementById('detPostcode').textContent = row.user_postcode || '-';

    document.getElementById('detCarImage').src = row.car_image || 'https://via.placeholder.com/120x88?text=No+Image';
    document.getElementById('detCarBrand').textContent = row.car_brand || '-';
    document.getElementById('detCarModel').textContent = row.car_model || '-';
    document.getElementById('detCarVariant').textContent = row.car_variant || '-';
    document.getElementById('detCarColor').textContent = row.car_color || '-';
    document.getElementById('detCarOrigin').textContent = row.car_origin || '-';
    document.getElementById('detCarPlate').textContent = row.car_plate || 'N/A';
    document.getElementById('detCarStock').textContent = '1 Unit';
    document.getElementById('detCarYear').textContent = row.car_year || '-';

    function setPdfFrame(id, url) {
        const frame = document.getElementById(id);
        if (frame) {
            frame.src = (url && url !== '#') ? url : '';
            frame.style.display = 'none';
        }
    }

    function setupDocUI(docType, url) {
        const viewBtn = document.getElementById('btnView_' + docType);
        const uploadLbl = document.getElementById('lblUpload_' + docType);
        if (!viewBtn || !uploadLbl) return;

        if (url && url !== 'NULL' && url !== '') {
            viewBtn.style.display = 'inline-block';
            uploadLbl.innerHTML = '<i class="fas fa-sync"></i> Replace';
            uploadLbl.style.color = '#f59e0b';
        } else {
            viewBtn.style.display = 'none';
            uploadLbl.innerHTML = '<i class="fas fa-upload"></i> Upload';
            uploadLbl.style.color = '#3b82f6';
        }
    }

    setPdfFrame('frameIcPdf', row.ic_pdf_url);
    setupDocUI('ic_pdf', row.ic_pdf_url);
    setPdfFrame('frameDrivingLicence', row.driving_licence_url);
    setupDocUI('driving_licence', row.driving_licence_url);
    setPdfFrame('frameBankStatement', row.bank_statement_url);
    setupDocUI('bank_statement', row.bank_statement_url);
    setPdfFrame('frameSalarySlip', row.salary_slip_url);
    setupDocUI('salary_slip', row.salary_slip_url);

    currentCarPrice = parseFloat(row.price) || 0;
    recalcMonthly();

    document.getElementById('detBookingFee').textContent = 'RM ' + fmt(parseFloat(row.payment_amount) || 0);
    document.getElementById('detResID').textContent = 'ORD' + String(row.reservation_id).padStart(3, '0');
    document.getElementById('detStatus').textContent = status;
    document.getElementById('detPayMethod').textContent = row.payment_method || '-';
    document.getElementById('detCreatedAt').textContent = row.reservation_created_at ? row.reservation_created_at.substring(0, 10) : '-';

    document.getElementById('btnProcessLoan').style.display = status === 'Pending Viewing' ? '' : 'none';
    document.getElementById('btnMarkSold').style.display = status === 'Loan Processing' ? '' : 'none';
    document.getElementById('btnCancelRes').style.display = ['Pending Viewing', 'Loan Processing'].includes(status) ? '' : 'none';

    const printBtn = document.getElementById('btnPrintDossier');
    if (printBtn) printBtn.style.display = isHistory ? 'inline-block' : 'none';

    const dpPanel = document.getElementById('dpPanel');
    if (['Loan Processing', 'Sold', 'Cancelled', 'Refunded'].includes(status)) {
        dpPanel.style.display = '';
        const dpStatus = row.dp_status || 'Pending';
        let dpAmt = 0;
        if (row.dp_amount !== null && row.dp_amount !== undefined) {
            dpAmt = parseFloat(row.dp_amount);
        } else {
            const dpRate = typeof window.GLOBAL_DP_RATE !== 'undefined' ? window.GLOBAL_DP_RATE : 0.10;
            dpAmt = currentCarPrice * dpRate;
        }

        document.getElementById('detDPAmount').textContent = 'RM ' + fmt(dpAmt);
        document.getElementById('detDPStatus').textContent = dpStatus;
        document.getElementById('detDPApproved').textContent = row.dp_approved_at || '-';
        document.getElementById('detDPReason').textContent = row.dp_reason || '-';
        document.getElementById('dpActionsWrap').style.display = dpStatus === 'Pending' ? 'flex' : 'none';
        document.getElementById('rejectReasonWrap').style.display = 'none';
    } else {
        dpPanel.style.display = 'none';
    }
    toggleEditAddress(false);
    toggleEditPlate(false);

    const pdfSection = document.getElementById('pdfSectionWrap');
    if (pdfSection) pdfSection.style.display = (status === 'Pending Viewing') ? 'none' : 'block';

    const uploadBtns = document.querySelectorAll('.upload-btn-label');
    uploadBtns.forEach(btn => {
        btn.style.display = (status === 'Loan Processing') ? 'inline-block' : 'none';
    });

    document.getElementById('splitModal').classList.remove('hidden');
}
function recalcMonthly() {
    if (!currentRowData) return;
    const years = parseInt(document.getElementById('loanYears').value);
    let dp = 0;
    let loanRate = typeof window.GLOBAL_LOAN_RATE !== 'undefined' ? (window.GLOBAL_LOAN_RATE / 100) : 0.03;
    if (currentRowData.dp_amount !== null && currentRowData.dp_amount !== undefined) {
        dp = parseFloat(currentRowData.dp_amount);
    } else {
        const dpRate = typeof window.GLOBAL_DP_RATE !== 'undefined' ? window.GLOBAL_DP_RATE : 0.10;
        dp = currentCarPrice * dpRate;
    }

    const loan = currentCarPrice - dp;
    const monthly = loan > 0 ? (loan * (1 + loanRate * years)) / (years * 12) : 0;

    document.getElementById('detPrice').textContent = 'RM ' + fmt(currentCarPrice);

    const detDP = document.getElementById('detDP');
    if (detDP) {
        detDP.textContent = '- RM ' + fmt(dp);
        if (detDP.previousElementSibling) {
            const actualPercent = currentCarPrice > 0 ? Math.round((dp / currentCarPrice) * 100) : 0;
            detDP.previousElementSibling.textContent = `Down Payment (${actualPercent}%)`;
        }
    }

    document.getElementById('detMonthly').textContent = 'RM ' + fmt(monthly) + ' / mo';
}

function closeModal() {
    document.getElementById('splitModal').classList.add('hidden');
    currentResId = null;
    currentRowData = null;
}

function togglePdf(id) {
    const frame = document.getElementById(id);
    if (frame.src && frame.src !== window.location.href && !frame.src.endsWith('#')) {
        frame.style.display = frame.style.display === 'none' ? 'block' : 'none';
    } else {
        Swal.fire({ icon: 'info', title: 'Not Found', text: 'Customer has not uploaded this document yet.', timer: 1500, showConfirmButton: false });
    }
}

function toggleEditAddress(show) {
    const isHistory = currentRowData && ['Sold', 'Cancelled', 'Refunded'].includes(currentRowData.reservation_status);
    if (isHistory && show) return;

    document.getElementById('addressViewMode').style.display = show ? 'none' : 'block';
    document.getElementById('addressEditMode').style.display = show ? 'block' : 'none';

    const btn = document.getElementById('editAddressBtn');
    if (btn) {
        btn.style.display = (show || isHistory) ? 'none' : 'inline-block';
    }

    if (show) {
        document.getElementById('inlineAddr').value = document.getElementById('detAddress').textContent !== '-' ? document.getElementById('detAddress').textContent : '';
        document.getElementById('inlinePost').value = document.getElementById('detPostcode').textContent !== '-' ? document.getElementById('detPostcode').textContent : '';

        const stateSel = document.getElementById('inlineState');
        stateSel.innerHTML = '<option value="">-- Select State --</option>';
        Object.keys(locations).forEach(s => {
            stateSel.innerHTML += `<option value="${s}">${s}</option>`;
        });

        const currentState = document.getElementById('detState').textContent;
        if (currentState && currentState !== '-') stateSel.value = currentState;

        inlinePopulateCities();

        const currentCity = document.getElementById('detCity').textContent;
        if (currentCity && currentCity !== '-') document.getElementById('inlineCity').value = currentCity;
    }
}

function inlinePopulateCities() {
    const state = document.getElementById('inlineState').value;
    const citySel = document.getElementById('inlineCity');
    citySel.innerHTML = '<option value="">-- Select City --</option>';
    if (state && locations[state]) {
        locations[state].forEach(c => {
            citySel.innerHTML += `<option value="${c}">${c}</option>`;
        });
    }
}

async function saveInlineAddress() {
    const addr = document.getElementById('inlineAddr').value;
    const state = document.getElementById('inlineState').value;
    const city = document.getElementById('inlineCity').value;
    const post = document.getElementById('inlinePost').value;

    await doAction('update_address', { address: addr, city: city, state: state, postcode: post }, true);

    document.getElementById('detAddress').textContent = addr || '-';
    document.getElementById('detCity').textContent = city || '-';
    document.getElementById('detState').textContent = state || '-';
    document.getElementById('detPostcode').textContent = post || '-';

    toggleEditAddress(false);
}

function editPlate() {
    const current = document.getElementById('detCarPlate').textContent;
    Swal.fire({
        title: 'Edit Number Plate', input: 'text', inputValue: current,
        showCancelButton: true, confirmButtonText: 'Save', confirmButtonColor: '#3b82f6'
    }).then(async r => {
        if (r.isConfirmed && r.value) {
            await doAction('update_plate', { plate: r.value });
            document.getElementById('detCarPlate').textContent = r.value;
        }
    });
}

function toggleEditPlate(show) {
    const isLoanProc = currentRowData && currentRowData.reservation_status === 'Loan Processing';
    if (!isLoanProc && show) return;

    document.getElementById('plateViewMode').style.display = show ? 'none' : 'block';
    document.getElementById('plateEditMode').style.display = show ? 'flex' : 'none';

    const btn = document.getElementById('editPlateBtn');
    if (btn) {
        btn.style.display = (show || !isLoanProc) ? 'none' : 'inline-block';
    }

    if (show) {
        const current = document.getElementById('detCarPlate').textContent;
        document.getElementById('inlinePlate').value = (current !== 'N/A' && current !== '-') ? current : '';
    }
}

async function saveInlinePlate() {
    const plate = document.getElementById('inlinePlate').value.trim();
    await doAction('update_plate', { plate: plate }, true);
    document.getElementById('detCarPlate').textContent = plate || 'N/A';
    toggleEditPlate(false);
}

async function uploadDoc(input, docType) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'upload_document');
    fd.append('reservation_id', currentResId);
    fd.append('doc_type', docType);
    fd.append('doc_file', input.files[0]);
    try {
        const res = await fetch('orders.php', { method: 'POST', body: fd });
        const text = await res.text();
        const data = JSON.parse(text);
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Uploaded!', text: data.message, timer: 1500, showConfirmButton: false });
            const frameMap = { driving_licence: 'frameDrivingLicence', bank_statement: 'frameBankStatement', salary_slip: 'frameSalarySlip' };
            const frame = document.getElementById(frameMap[docType]);
            frame.src = data.file_url;
            frame.style.display = 'block';
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
}

async function processToLoan() {
    const addr = document.getElementById('detAddress').textContent;
    if (addr === '-' || addr.trim() === '') {
        Swal.fire('Action Denied', 'Please fill in the Billing Address before processing to loan.', 'warning');
        return;
    }
    const r = await Swal.fire({
        title: 'Process to Loan?', text: 'Moves to Loan Processing and creates a Down Payment record.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Process', confirmButtonColor: '#10b981'
    });
    if (r.isConfirmed) await doAction('process_to_loan', {});
}

async function approveDP() {
    const r = await Swal.fire({
        title: 'Approve Down Payment?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'Approve', confirmButtonColor: '#3b82f6'
    });
    if (r.isConfirmed) await doAction('approve_dp', {});
}

function toggleRejectReason() {
    const w = document.getElementById('rejectReasonWrap');
    w.style.display = (w.style.display === '' || w.style.display === 'none') ? 'block' : 'none';
}

async function rejectDP() {
    const reason = document.getElementById('rejectReasonText').value.trim();
    if (!reason) { Swal.fire('Required', 'Please enter a rejection reason.', 'warning'); return; }
    await doAction('reject_dp', { reason });
}

async function markSold() {
    const addr = document.getElementById('detAddress').textContent;
    if (addr === '-' || addr.trim() === '') {
        Swal.fire('Incomplete Transaction', 'Please fill in the Billing Address first.', 'warning');
        return;
    }
    const plate = document.getElementById('detCarPlate').textContent;
    if (plate === 'N/A' || plate === '-' || plate.trim() === '') {
        Swal.fire('Incomplete Transaction', 'Please update the Number Plate first.', 'warning');
        return;
    }
    const dl = document.getElementById('frameDrivingLicence').src;
    const bs = document.getElementById('frameBankStatement').src;
    const ss = document.getElementById('frameSalarySlip').src;
    const isInvalid = (url) => !url || url.endsWith('#') || url === window.location.href;

    if (isInvalid(dl) || isInvalid(bs) || isInvalid(ss)) {
        Swal.fire('Incomplete Transaction', 'All 3 documents (Driving Licence, Bank Statement, Salary Slip) must be uploaded before marking as Sold.', 'warning');
        return;
    }

    const r = await Swal.fire({
        title: 'Transaction Complete?',
        html: '✅ All documents verified.<br>📦 Stock will be deducted by 1.',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Yes, Mark as Sold!',
        confirmButtonColor: '#7c3aed'
    });
    if (r.isConfirmed) await doAction('mark_sold', {});
}

async function cancelReservation() {
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'Cancel Reservation?', input: 'textarea',
        inputLabel: 'Reason for cancellation', inputPlaceholder: 'Enter reason…',
        showCancelButton: true, confirmButtonText: 'Cancel Reservation', confirmButtonColor: '#ef4444'
    });
    if (isConfirmed) await doAction('cancel_reservation', { reason: reason || '' });
}

async function doAction(action, extra = {}, skipReload = false) {
    const body = new URLSearchParams({ action, reservation_id: currentResId, ...extra });
    try {
        const res = await fetch('orders.php', { method: 'POST', body });
        const text = await res.text();
        const data = JSON.parse(text);
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Done!', text: data.message, timer: 1500, showConfirmButton: false });
            if (!skipReload) {
                closeModal();
                location.reload();
            }
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
}

function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }

document.getElementById('addReservationForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'add_reservation');
    try {
        const res = await fetch('orders.php', { method: 'POST', body: new URLSearchParams(fd) });
        const text = await res.text();
        const data = JSON.parse(text);
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Created!', text: data.message, timer: 2000, showConfirmButton: false });
            closeAddModal();
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
});

function fmt(n) {
    return parseFloat(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}