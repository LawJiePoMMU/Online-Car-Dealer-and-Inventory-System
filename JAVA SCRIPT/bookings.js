// 大馬州屬與城市資料 (用於修改地址)
const locations = {
    "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Kulai", "Muar", "Segamat", "Pontian", "Kota Tinggi"],
    "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Langkawi"],
    "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Gua Musang"],
    "Kuala Lumpur": ["Kuala Lumpur"],
    "Labuan": ["Labuan"],
    "Melaka": ["Melaka Tengah", "Alor Gajah", "Jasin"],
    "Negeri Sembilan": ["Seremban", "Port Dickson", "Nilai"],
    "Pahang": ["Kuantan", "Temerloh", "Bentong", "Cameron Highlands"],
    "Penang": ["George Town", "Butterworth", "Bukit Mertajam", "Bayan Lepas"],
    "Perak": ["Ipoh", "Taiping", "Teluk Intan", "Manjung"],
    "Perlis": ["Kangar", "Arau", "Padang Besar"],
    "Putrajaya": ["Putrajaya"],
    "Sabah": ["Kota Kinabalu", "Sandakan", "Tawau", "Lahad Datu"],
    "Sarawak": ["Kuching", "Miri", "Sibu", "Bintulu"],
    "Selangor": ["Petaling Jaya", "Shah Alam", "Subang Jaya", "Klang", "Kajang", "Sepang", "Ampang"],
    "Terengganu": ["Kuala Terengganu", "Kemaman", "Dungun"]
};

let currentResId = null;
let currentRowData = null;

function fmt(n) { return parseFloat(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function openModal(row) {
    currentResId = row.reservation_id;
    currentRowData = row;
    const status = row.reservation_status;

    // 基本資料
    document.getElementById('detName').textContent = row.user_name || '-';
    document.getElementById('detEmail').textContent = row.user_email || '-';
    document.getElementById('detIC').textContent = row.user_ic || '-';
    document.getElementById('detContact').textContent = row.user_phone || '-';
    
    // 地址資料
    document.getElementById('detAddress').textContent = row.user_address || '-';
    document.getElementById('detCity').textContent = row.user_city || '-';
    document.getElementById('detState').textContent = row.user_state || '-';
    document.getElementById('detPostcode').textContent = row.user_postcode || '-';

    // 車輛資料
    document.getElementById('detCarImage').src = row.car_image || 'https://via.placeholder.com/120x88?text=No+Image';
    document.getElementById('detCarBrand').textContent = row.car_brand || '-';
    document.getElementById('detCarModel').textContent = row.car_model || '-';
    document.getElementById('detCarVariant').textContent = row.car_variant || '-';
    document.getElementById('detCarYear').textContent = row.car_year || '-';
    document.getElementById('detCarPlate').textContent = row.car_plate || 'N/A';
    document.getElementById('detPrice').textContent = 'RM ' + fmt(row.price || 0);

    // 訂單摘要
    document.getElementById('detResID').textContent = 'ORD' + String(row.reservation_id).padStart(3, '0');
    document.getElementById('detStatus').textContent = status;
    document.getElementById('detCreatedAt').textContent = row.reservation_created_at ? row.reservation_created_at.substring(0, 10) : '-';

    // Booking 專屬面板
    const bookingPanel = document.getElementById('bookingPanel');
    if (bookingPanel) {
        bookingPanel.style.display = 'block';
        document.getElementById('detBookingFee').textContent = 'RM ' + fmt(row.payment_amount || 0);
        document.getElementById('detPayMethod').textContent = row.payment_method || '-';
        document.getElementById('detBookedAt').textContent = row.booked_at ? row.booked_at.substring(0, 10) : '-';
        document.getElementById('detSoldAt').textContent = row.reservation_sold_at ? row.reservation_sold_at.substring(0, 10) : '-';
    }

    // 隱藏試駕專屬面板
    const tdPanel = document.getElementById('tdPanel');
    if (tdPanel) tdPanel.style.display = 'none';

    // 隱藏駕照上傳 (Booking 階段通常不需要再次上傳駕照，或者保留給 Sales 查閱)
    const lblLicence = document.getElementById('lblLicenceUpload');
    if (lblLicence) lblLicence.style.display = 'none'; 

    // 控制按鈕顯示
    document.getElementById('btnMarkSold').style.display = (status === 'Booked') ? 'inline-block' : 'none';
    document.getElementById('btnPrintReceipt').style.display = (status === 'Sold' || status === 'Booked') ? 'inline-block' : 'none';
    document.getElementById('btnCancelRes').style.display = (status === 'Booked') ? 'inline-block' : 'none';

    // 確保不會顯示 Test Drive 的按鈕
    const btnMarkTDDone = document.getElementById('btnMarkTDDone');
    const btnConvertBook = document.getElementById('btnConvertBook');
    if(btnMarkTDDone) btnMarkTDDone.style.display = 'none';
    if(btnConvertBook) btnConvertBook.style.display = 'none';

    toggleEditAddress(false);
    toggleEditPlate(false);
    document.getElementById('splitModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('splitModal').classList.add('hidden');
    currentResId = null; currentRowData = null;
}

// 基礎 Fetch 請求
async function doAction(action, extra = {}, skipReload = false) {
    const body = new URLSearchParams({ action, reservation_id: currentResId, ...extra });
    try {
        const res = await fetch('bookings.php', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Done!', text: data.message, timer: 1500, showConfirmButton: false });
            if (!skipReload) { closeModal(); location.reload(); }
        } else { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); }
    } catch (e) { Swal.fire({ icon: 'error', title: 'Network Error', text: e.message }); }
}

// 標記售出
async function markSold() {
    const addr = document.getElementById('detAddress').textContent;
    if (!addr || addr === '-' || !addr.trim()) { Swal.fire('Incomplete', 'Please fill in the Billing Address first.', 'warning'); return; }
    
    const plate = document.getElementById('detCarPlate').textContent;
    if (!plate || plate === 'N/A' || plate === '-' || !plate.trim()) { Swal.fire('Incomplete', 'Please update the Number Plate first.', 'warning'); return; }
    
    const r = await Swal.fire({ title: 'Mark as Sold?', text: 'This will deduct 1 unit from inventory.', icon: 'success', showCancelButton: true, confirmButtonText: 'Yes, Mark Sold', confirmButtonColor: '#7c3aed' });
    if (r.isConfirmed) await doAction('mark_sold', {});
}

// 取消訂單
async function cancelReservation() {
    const { value: reason, isConfirmed } = await Swal.fire({ title: 'Cancel Booking?', input: 'textarea', inputLabel: 'Reason', inputPlaceholder: 'Enter reason…', showCancelButton: true, confirmButtonText: 'Cancel Booking', confirmButtonColor: '#ef4444' });
    if (isConfirmed) await doAction('cancel_reservation', { reason: reason || '' });
}

// ── 編輯地址邏輯 ──
function toggleEditAddress(show) {
    const isHistory = currentRowData && ['Sold', 'Cancelled'].includes(currentRowData.reservation_status);
    if (isHistory && show) return; // 歷史紀錄不允許編輯
    
    document.getElementById('addressViewMode').style.display = show ? 'none' : 'block';
    document.getElementById('addressEditMode').style.display = show ? 'block' : 'none';
    const btn = document.getElementById('editAddressBtn');
    if (btn) btn.style.display = (show || isHistory) ? 'none' : 'inline-block';
    
    if (show) {
        document.getElementById('inlineAddr').value = document.getElementById('detAddress').textContent !== '-' ? document.getElementById('detAddress').textContent : '';
        document.getElementById('inlinePost').value = document.getElementById('detPostcode').textContent !== '-' ? document.getElementById('detPostcode').textContent : '';
        
        const stateSel = document.getElementById('inlineState');
        stateSel.innerHTML = '<option value="">-- Select State --</option>';
        Object.keys(locations).forEach(s => stateSel.innerHTML += `<option value="${s}">${s}</option>`);
        
        const curState = document.getElementById('detState').textContent;
        if (curState && curState !== '-') stateSel.value = curState;
        inlinePopulateCities();
        
        const curCity = document.getElementById('detCity').textContent;
        if (curCity && curCity !== '-') document.getElementById('inlineCity').value = curCity;
    }
}

function inlinePopulateCities() {
    const state = document.getElementById('inlineState').value;
    const citySel = document.getElementById('inlineCity');
    citySel.innerHTML = '<option value="">-- Select City --</option>';
    if (state && locations[state]) {
        locations[state].forEach(c => citySel.innerHTML += `<option value="${c}">${c}</option>`);
    }
}

async function saveInlineAddress() {
    const addr = document.getElementById('inlineAddr').value;
    const state = document.getElementById('inlineState').value;
    const city = document.getElementById('inlineCity').value;
    const post = document.getElementById('inlinePost').value;
    await doAction('update_address', { address: addr, city, state, postcode: post }, true);
    
    document.getElementById('detAddress').textContent = addr || '-';
    document.getElementById('detCity').textContent = city || '-';
    document.getElementById('detState').textContent = state || '-';
    document.getElementById('detPostcode').textContent = post || '-';
    toggleEditAddress(false);
}

// ── 編輯車牌邏輯 ──
function toggleEditPlate(show) {
    const isHistory = currentRowData && ['Sold', 'Cancelled'].includes(currentRowData.reservation_status);
    if (isHistory && show) return;
    
    document.getElementById('plateViewMode').style.display = show ? 'none' : 'block';
    document.getElementById('plateEditMode').style.display = show ? 'flex' : 'none';
    const btn = document.getElementById('editPlateBtn');
    if (btn) btn.style.display = (show || isHistory) ? 'none' : 'inline-block';
    
    if (show) {
        const cur = document.getElementById('detCarPlate').textContent;
        document.getElementById('inlinePlate').value = (cur !== 'N/A' && cur !== '-') ? cur : '';
    }
}

async function saveInlinePlate() {
    const plate = document.getElementById('inlinePlate').value.trim();
    await doAction('update_plate', { plate }, true);
    document.getElementById('detCarPlate').textContent = plate || 'N/A';
    toggleEditPlate(false);
}

// ── 列印發票/收據 ──
function printReceipt() {
    if (!currentRowData) return;
    const row = currentRowData;
    const carPrice = parseFloat(row.price) || 0;
    const bookingFee = parseFloat(row.payment_amount) || 0;
    const addr = [row.user_address, row.user_city, row.user_state, row.user_postcode].filter(Boolean).join(', ');

    document.getElementById('rec_order_id').textContent = 'ORD' + String(row.reservation_id).padStart(3, '0');
    document.getElementById('rec_date').textContent = new Date().toISOString().substring(0, 10);
    document.getElementById('rec_customer_name').textContent = row.user_name || '-';
    document.getElementById('rec_customer_ic').textContent = row.user_ic || '-';
    
    document.getElementById('rec_cust_name2').textContent = row.user_name || '-';
    document.getElementById('rec_cust_ic2').textContent = row.user_ic || '-';
    document.getElementById('rec_cust_phone').textContent = row.user_phone || '-';
    document.getElementById('rec_cust_email').textContent = row.user_email || '-';
    
    document.getElementById('rec_car_brand').textContent = row.car_brand || '-';
    document.getElementById('rec_car_model').textContent = row.car_model || '-';
    document.getElementById('rec_car_variant').textContent = row.car_variant || '-';
    document.getElementById('rec_car_year').textContent = row.car_year || '-';
    document.getElementById('rec_car_colour').textContent = row.car_color || '-';
    
    document.getElementById('rec_amount').textContent = 'RM ' + fmt(bookingFee);
    document.getElementById('rec_pay_method').textContent = row.payment_method || '-';

    document.getElementById('receiptPrintArea').style.display = 'block';
    window.print();
    setTimeout(() => { document.getElementById('receiptPrintArea').style.display = 'none'; }, 800);
}

// 新增 Walk-in Booking Modal 邏輯
function openAddBookingModal() { document.getElementById('addBookingModal').classList.remove('hidden'); }
const addBookingForm = document.getElementById('addBookingForm');
if (addBookingForm) {
    addBookingForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this); fd.append('action', 'add_booking');
        try {
            const res = await fetch('bookings.php', { method: 'POST', body: new URLSearchParams(fd) });
            const data = await res.json();
            if (data.success) { await Swal.fire({ icon: 'success', title: 'Created!', text: data.message, timer: 2000, showConfirmButton: false }); location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); }
        } catch (e) { Swal.fire({ icon: 'error', title: 'Network Error', text: e.message }); }
    });
}