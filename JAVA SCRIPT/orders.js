    const locations = {
        "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Kulai", "Muar", "Segamat", "Pontian", "Kota Tinggi", "Mersing", "Tangkak"],
        "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Baling", "Langkawi", "Kubang Pasu", "Yan", "Padang Terap", "Sik"],
        "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Bachok", "Tanah Merah", "Machang", "Kuala Krai", "Gua Musang"],
        "Kuala Lumpur": ["Kuala Lumpur"],
        "Labuan": ["Labuan"],
        "Melaka": ["Melaka Tengah","Alor Gajah","Jasin"],
        "Negeri Sembilan": ["Seremban", "Port Dickson", "Jempol", "Tampin", "Kuala Pilah", "Rembau", "Jelebu"],
        "Pahang": ["Kuantan", "Temerloh", "Bentong", "Pekan", "Rompin", "Raub", "Jerantut", "Lipis", "Cameron Highlands"],
        "Penang": ["George Town", "Butterworth", "Bukit Mertajam", "Nibong Tebal", "Kepala Batas"],
        "Perak": ["Ipoh", "Taiping", "Teluk Intan", "Manjung", "Kuala Kangsar", "Batu Gajah", "Kampar", "Tapah"],
        "Perlis": ["Kangar", "Arau", "Padang Besar"],
        "Putrajaya": ["Putrajaya"],
        "Sabah": ["Kota Kinabalu", "Sandakan", "Tawau", "Lahad Datu", "Keningau", "Kinabatangan", "Semporna"],
        "Sarawak": ["Kuching", "Miri", "Sibu", "Bintulu", "Limbang", "Samarahan", "Sri Aman"],
        "Selangor": ["Petaling Jaya", "Shah Alam", "Subang Jaya", "Klang", "Kajang", "Sepang", "Selayang", "Ampang", "Gombak", "Hulu Langat"],
        "Terengganu": ["Kuala Terengganu", "Kemaman", "Dungun", "Besut", "Marang", "Setiu"]
    };

let currentResId    = null;
let currentCarPrice = 0;
let currentRowData  = null;
function openModal(row) {
    currentResId   = row.reservation_id;
    currentRowData = row;
    const status   = row.reservation_status;
    const isHistory = ['Sold','Cancelled','Refunded'].includes(status);

    document.getElementById('loanYears').disabled = isHistory;

    document.getElementById('detName').textContent     = row.user_name    || '-';
    document.getElementById('detEmail').textContent    = row.user_email   || '-';
    document.getElementById('detIC').textContent       = row.user_ic      || '-';
    document.getElementById('detContact').textContent  = row.user_phone   || '-';
    document.getElementById('detAddress').textContent  = row.user_address || '-';
    document.getElementById('detCity').textContent     = row.user_city    || '-';
    document.getElementById('detState').textContent    = row.user_state   || '-';
    document.getElementById('detPostcode').textContent = row.user_postcode|| '-';
    document.getElementById('detCarImage').src           = row.car_image  || 'https://via.placeholder.com/120x88?text=No+Image';
    document.getElementById('detCarBrand').textContent   = row.car_brand  || '-';
    document.getElementById('detCarModel').textContent   = row.car_model  || '-';
    document.getElementById('detCarVariant').textContent = row.car_variant|| '-';
    document.getElementById('detCarColor').textContent   = row.car_color  || '-';
    document.getElementById('detCarOrigin').textContent  = row.car_origin || '-';
    document.getElementById('detCarPlate').textContent   = row.car_plate  || 'N/A';
    document.getElementById('detCarStock').textContent   = '1 Unit';
    document.getElementById('detCarYear').textContent    = row.car_year   || '-';

    [['ic_pdf','frameIcPdf'],['driving_licence','frameDrivingLicence'],
     ['bank_statement','frameBankStatement'],['salary_slip','frameSalarySlip']
    ].forEach(([dt,fid]) => {
        const url    = row[dt+'_url'];
        const frame  = document.getElementById(fid);
        const vbtn   = document.getElementById('btnView_'+dt);
        const lbl    = document.getElementById('lblUpload_'+dt);
        if (frame) { frame.src=(url&&url!=='#')?url:''; frame.style.display='none'; }
        if (!vbtn||!lbl) return;
        if (url&&url!=='NULL'&&url!=='') {
            vbtn.style.display='inline-block';
            lbl.innerHTML='<i class="fas fa-sync"></i> Replace'; lbl.style.color='#f59e0b';
        } else {
            vbtn.style.display='none';
            lbl.innerHTML='<i class="fas fa-upload"></i> Upload'; lbl.style.color='#3b82f6';
        }
    });

    currentCarPrice = parseFloat(row.price) || 0;
    recalcMonthly();

    document.getElementById('detBookingFee').textContent = 'RM '+fmt(parseFloat(row.payment_amount)||0);
    document.getElementById('detResID').textContent      = 'ORD'+String(row.reservation_id).padStart(3,'0');
    document.getElementById('detStatus').textContent     = status;
    document.getElementById('detPayMethod').textContent  = row.payment_method||'-';
    document.getElementById('detCreatedAt').textContent  = row.reservation_created_at?row.reservation_created_at.substring(0,10):'-';
    document.getElementById('btnProcessLoan').style.display = status==='Pending Viewing'?'':'none';
    document.getElementById('btnMarkSold').style.display    = status==='Loan Processing'?'':'none';
    document.getElementById('btnCancelRes').style.display   = ['Pending Viewing','Loan Processing'].includes(status)?'':'none';
    document.getElementById('btnPrintDossier').style.display = status==='Sold'?'inline-block':'none';

    const pdfSection = document.getElementById('pdfSectionWrap');
    if (pdfSection) pdfSection.style.display = status==='Pending Viewing'?'none':'block';

    document.querySelectorAll('.upload-btn-label').forEach(btn => {
        btn.style.display = status==='Loan Processing'?'inline-block':'none';
    });

    const dpPanel = document.getElementById('dpPanel');
    if (['Loan Processing','Sold','Cancelled','Refunded'].includes(status)) {
        dpPanel.style.display = '';
        const dpStatus = row.dp_status||'Pending';
        let dpAmt = row.dp_amount!=null&&row.dp_amount!==undefined
            ? parseFloat(row.dp_amount)
            : currentCarPrice*(window.GLOBAL_DP_RATE||0.10);

        document.getElementById('detDPAmount').textContent   = 'RM '+fmt(dpAmt);
        document.getElementById('detDPStatus').textContent   = dpStatus;
        document.getElementById('detDPApproved').textContent = row.dp_approved_at||'-';
        document.getElementById('detDPReason').textContent   = row.dp_reason||'-';
        document.getElementById('dpActionsWrap').style.display   = (dpStatus==='Pending'&&!isHistory)?'flex':'none';
        document.getElementById('rejectReasonWrap').style.display = 'none';

        const btBox        = document.getElementById('bankTransferBox');
        const btnApprove   = document.getElementById('btnBankApprove');
        const btnReject    = document.getElementById('btnBankReject');
        const btDone       = document.getElementById('btDoneBadge');
        const btStatus     = document.getElementById('btStatus');
        const btTitle      = document.getElementById('btTitle');

        if (dpStatus==='Approved') {
            btBox.style.display = 'block';
            const btVal = parseInt(row.bank_transfer_received??0);

            if (btVal===1) {
                
                btBox.classList.remove('rejected');
                btTitle.classList.remove('rejected');
                btTitle.innerHTML = '<i class="fas fa-university" style="margin-right:6px;"></i>Bank Transfer Status';
                btStatus.textContent = 'Approved — confirmed on '+(row.bank_transfer_at?row.bank_transfer_at.substring(0,10):'-');
                btnApprove.style.display = 'none';
                btnReject.style.display  = 'none';
                btDone.style.display     = 'inline-block';
                btDone.textContent       = '✓ Transfer Approved';
                btDone.style.background  = '#dcfce7';
                btDone.style.color       = '#166534';
            } else if (btVal===2) {
               
                btBox.classList.add('rejected');
                btTitle.classList.add('rejected');
                btStatus.textContent = 'Rejected — on '+(row.bank_transfer_at?row.bank_transfer_at.substring(0,10):'-');
                btnApprove.style.display = 'none';
                btnReject.style.display  = 'none';
                btDone.style.display     = 'inline-block';
                btDone.textContent       = '✗ Transfer Rejected';
                btDone.style.background  = '#fee2e2';
                btDone.style.color       = '#dc2626';
            } else {
                
                btBox.classList.remove('rejected');
                btTitle.classList.remove('rejected');
                btStatus.textContent = 'Awaiting confirmation of bank transfer from customer';
                btnApprove.style.display = isHistory?'none':'inline-block';
                btnReject.style.display  = isHistory?'none':'inline-block';
                btDone.style.display     = 'none';
            }
        } else {
            btBox.style.display = 'none';
        }
    } else {
        dpPanel.style.display = 'none';
    }

    toggleEditAddress(false);
    toggleEditPlate(false);
    document.getElementById('splitModal').classList.remove('hidden');
}

function recalcMonthly() {
    if (!currentRowData) return;
    const years    = parseInt(document.getElementById('loanYears').value);
    const loanRate = (window.GLOBAL_LOAN_RATE||3)/100;
    const dp = currentRowData.dp_amount!=null&&currentRowData.dp_amount!==undefined
        ? parseFloat(currentRowData.dp_amount)
        : currentCarPrice*(window.GLOBAL_DP_RATE||0.10);

    const loan    = currentCarPrice-dp;
    const monthly = loan>0?(loan*(1+loanRate*years))/(years*12):0;

    document.getElementById('detPrice').textContent   = 'RM '+fmt(currentCarPrice);
    document.getElementById('detMonthly').textContent = 'RM '+fmt(monthly)+' / mo';

    const detDP = document.getElementById('detDP');
    if (detDP) {
        detDP.textContent = '- RM '+fmt(dp);
        const pct = currentCarPrice>0?Math.round((dp/currentCarPrice)*100):0;
        const lbl = document.getElementById('dpLabel');
        if (lbl) lbl.textContent = `Down Payment (${pct}%)`;
    }
}

function closeModal() {
    document.getElementById('splitModal').classList.add('hidden');
    currentResId=null; currentRowData=null;
}

function printInvoice() {
    if (!currentRowData) return;
    const row      = currentRowData;
    const carPrice = parseFloat(row.price)||0;
    const dpAmt    = row.dp_amount!=null&&row.dp_amount!==undefined
        ? parseFloat(row.dp_amount)
        : carPrice*(window.GLOBAL_DP_RATE||0.10);
    const bookingFee = parseFloat(row.payment_amount)||0;
    // Balance = car price minus down payment (booking fee is part of DP, not extra)
    const balance    = carPrice - dpAmt;
    const pct        = carPrice>0?Math.round((dpAmt/carPrice)*100):0;
    const addr       = [row.user_address,row.user_city,row.user_state,row.user_postcode].filter(Boolean).join(', ');

    document.getElementById('inv_order_id').textContent      = 'ORD'+String(row.reservation_id).padStart(3,'0');
    document.getElementById('inv_date').textContent          = row.reservation_sold_at?row.reservation_sold_at.substring(0,10):new Date().toISOString().substring(0,10);

    document.getElementById('inv_customer_name').textContent = row.user_name||'-';
    document.getElementById('inv_customer_ic').textContent   = row.user_ic||'-';
    document.getElementById('inv_cust_name2').textContent    = row.user_name||'-';
    document.getElementById('inv_cust_ic2').textContent      = row.user_ic||'-';
    document.getElementById('inv_cust_phone').textContent    = row.user_phone||'-';
    document.getElementById('inv_cust_email').textContent    = row.user_email||'-';
    document.getElementById('inv_cust_addr').textContent     = addr||'-';

    document.getElementById('inv_car_brand').textContent    = row.car_brand||'-';
    document.getElementById('inv_car_model').textContent    = row.car_model||'-';
    document.getElementById('inv_car_variant').textContent  = row.car_variant||'-';
    document.getElementById('inv_car_year').textContent     = row.car_year||'-';
    document.getElementById('inv_car_colour').textContent   = row.car_color||'-';
    document.getElementById('inv_car_plate').textContent    = row.car_plate||'-';
    document.getElementById('inv_car_origin').textContent   = row.car_origin||'-';
    document.getElementById('inv_car_name').textContent     = (row.car_brand||'')+' '+(row.car_model||'');
    document.getElementById('inv_car_detail').textContent   = [row.car_variant,row.car_color,row.car_year].filter(Boolean).join(' | ');
    document.getElementById('inv_car_price').textContent    = fmt(carPrice);

    document.getElementById('inv_subtotal').textContent     = 'RM '+fmt(carPrice);
    document.getElementById('inv_booking_fee').textContent  = '- RM '+fmt(bookingFee);
    document.getElementById('inv_dp_label').textContent     = 'Down Payment ('+pct+'%)';
    document.getElementById('inv_dp_amount').textContent    = '- RM '+fmt(dpAmt);
    document.getElementById('inv_balance').textContent      = 'RM '+fmt(balance);
    document.getElementById('inv_pay_method').textContent   = row.payment_method||'-';

    document.getElementById('invoicePrintArea').style.display = 'block';
    window.print();
    setTimeout(()=>{ document.getElementById('invoicePrintArea').style.display='none'; },800);
}

function togglePdf(id) {
    const frame = document.getElementById(id);
    if (frame.src&&frame.src!==window.location.href&&!frame.src.endsWith('#')) {
        frame.style.display = frame.style.display==='none'?'block':'none';
    } else {
        Swal.fire({icon:'info',title:'Not Found',text:'Document not uploaded yet.',timer:1500,showConfirmButton:false});
    }
}

function toggleEditAddress(show) {
    const isHistory = currentRowData&&['Sold','Cancelled','Refunded'].includes(currentRowData.reservation_status);
    if (isHistory&&show) return;
    document.getElementById('addressViewMode').style.display = show?'none':'block';
    document.getElementById('addressEditMode').style.display = show?'block':'none';
    const btn = document.getElementById('editAddressBtn');
    if (btn) btn.style.display = (show||isHistory)?'none':'inline-block';
    if (show) {
        document.getElementById('inlineAddr').value = document.getElementById('detAddress').textContent!=='-'?document.getElementById('detAddress').textContent:'';
        document.getElementById('inlinePost').value = document.getElementById('detPostcode').textContent!=='-'?document.getElementById('detPostcode').textContent:'';
        const stateSel = document.getElementById('inlineState');
        stateSel.innerHTML = '<option value="">-- Select State --</option>';
        Object.keys(locations).forEach(s=>stateSel.innerHTML+=`<option value="${s}">${s}</option>`);
        const curState = document.getElementById('detState').textContent;
        if (curState&&curState!=='-') stateSel.value=curState;
        inlinePopulateCities();
        const curCity = document.getElementById('detCity').textContent;
        if (curCity&&curCity!=='-') document.getElementById('inlineCity').value=curCity;
    }
}
function inlinePopulateCities() {
    const state=document.getElementById('inlineState').value;
    const citySel=document.getElementById('inlineCity');
    citySel.innerHTML='<option value="">-- Select City --</option>';
    if (state&&locations[state]) locations[state].forEach(c=>citySel.innerHTML+=`<option value="${c}">${c}</option>`);
}
async function saveInlineAddress() {
    const addr=document.getElementById('inlineAddr').value, state=document.getElementById('inlineState').value,
          city=document.getElementById('inlineCity').value, post=document.getElementById('inlinePost').value;
    await doAction('update_address',{address:addr,city,state,postcode:post},true);
    document.getElementById('detAddress').textContent=addr||'-';
    document.getElementById('detCity').textContent=city||'-';
    document.getElementById('detState').textContent=state||'-';
    document.getElementById('detPostcode').textContent=post||'-';
    toggleEditAddress(false);
}

function toggleEditPlate(show) {
    const isLoan = currentRowData&&currentRowData.reservation_status==='Loan Processing';
    if (!isLoan&&show) return;
    document.getElementById('plateViewMode').style.display = show?'none':'block';
    document.getElementById('plateEditMode').style.display = show?'flex':'none';
    const btn=document.getElementById('editPlateBtn');
    if (btn) btn.style.display=(show||!isLoan)?'none':'inline-block';
    if (show) {
        const cur=document.getElementById('detCarPlate').textContent;
        document.getElementById('inlinePlate').value=(cur!=='N/A'&&cur!=='-')?cur:'';
    }
}
async function saveInlinePlate() {
    const plate=document.getElementById('inlinePlate').value.trim();
    await doAction('update_plate',{plate},true);
    document.getElementById('detCarPlate').textContent=plate||'N/A';
    toggleEditPlate(false);
}

async function uploadDoc(input,docType) {
    if (!input.files[0]) return;
    const fd=new FormData();
    fd.append('action','upload_document'); fd.append('reservation_id',currentResId);
    fd.append('doc_type',docType); fd.append('doc_file',input.files[0]);
    try {
        const res=await fetch('orders.php',{method:'POST',body:fd});
        const data=await res.json();
        if (data.success) {
            Swal.fire({icon:'success',title:'Uploaded!',text:data.message,timer:1500,showConfirmButton:false});
            const frameMap={driving_licence:'frameDrivingLicence',bank_statement:'frameBankStatement',salary_slip:'frameSalarySlip',ic_pdf:'frameIcPdf'};
            const frame=document.getElementById(frameMap[docType]);
            if (frame){frame.src=data.file_url;frame.style.display='block';}
        } else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    } catch(e) { Swal.fire({icon:'error',title:'Network Error',text:e.message}); }
}

async function processToLoan() {
    const addr=document.getElementById('detAddress').textContent;
    if (!addr||addr==='-'||!addr.trim()) { Swal.fire('Action Denied','Please fill in the Billing Address first.','warning'); return; }
    const r=await Swal.fire({title:'Process to Loan?',text:'Moves to Loan Processing and creates a Down Payment record.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, Process',confirmButtonColor:'#10b981'});
    if (r.isConfirmed) await doAction('process_to_loan',{});
}
async function approveDP() {
    const r=await Swal.fire({title:'Approve Down Payment?',icon:'question',showCancelButton:true,confirmButtonText:'Approve',confirmButtonColor:'#3b82f6'});
    if (r.isConfirmed) await doAction('approve_dp',{});
}
function toggleRejectReason() {
    const w=document.getElementById('rejectReasonWrap');
    w.style.display=(w.style.display===''||w.style.display==='none')?'block':'none';
}
async function rejectDP() {
    const reason=document.getElementById('rejectReasonText').value.trim();
    if (!reason){Swal.fire('Required','Please enter a rejection reason.','warning');return;}
    await doAction('reject_dp',{reason});
}

async function confirmBankTransfer(btStatus) {
    const isApprove = btStatus==='Approved';
    const r=await Swal.fire({
        title: isApprove?'Confirm Transfer Received?':'Reject Bank Transfer?',
        text:  isApprove?'This confirms the bank has transferred the payment.':'This marks the transfer as failed or rejected.',
        icon:  isApprove?'question':'warning',
        showCancelButton: true,
        confirmButtonText: isApprove?'Yes, Approve':'Yes, Reject',
        confirmButtonColor: isApprove?'#166534':'#dc2626'
    });
    if (!r.isConfirmed) return;

    const body=new URLSearchParams({action:'confirm_bank_transfer',reservation_id:currentResId,bt_status:btStatus});
    const res=await fetch('orders.php',{method:'POST',body});
    const data=await res.json();
    if (data.success) {
        // Update local state
        currentRowData.bank_transfer_received = isApprove?1:2;
        currentRowData.bank_transfer_at = new Date().toISOString().substring(0,10);

        // Update UI without reload
        const btBox    = document.getElementById('bankTransferBox');
        const btnA     = document.getElementById('btnBankApprove');
        const btnR     = document.getElementById('btnBankReject');
        const btDone   = document.getElementById('btDoneBadge');
        const btStat   = document.getElementById('btStatus');
        const btTitle  = document.getElementById('btTitle');

        btnA.style.display='none'; btnR.style.display='none';
        btDone.style.display='inline-block';
        if (isApprove) {
            btBox.classList.remove('rejected'); btTitle.classList.remove('rejected');
            btStat.textContent='Approved — confirmed today';
            btDone.textContent='✓ Transfer Approved'; btDone.style.background='#dcfce7'; btDone.style.color='#166534';
        } else {
            btBox.classList.add('rejected'); btTitle.classList.add('rejected');
            btStat.textContent='Rejected — marked today';
            btDone.textContent='✗ Transfer Rejected'; btDone.style.background='#fee2e2'; btDone.style.color='#dc2626';
        }
        Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message,showConfirmButton:false,timer:2000});
    } else {
        Swal.fire({icon:'error',title:'Error',text:data.message});
    }
}

async function markSold() {
    const addr=document.getElementById('detAddress').textContent;
    if (!addr||addr==='-'||!addr.trim()) { Swal.fire('Incomplete','Please fill in the Billing Address first.','warning'); return; }
    const plate=document.getElementById('detCarPlate').textContent;
    if (!plate||plate==='N/A'||plate==='-'||!plate.trim()) { Swal.fire('Incomplete','Please update the Number Plate first.','warning'); return; }
    if ((currentRowData.bank_transfer_received??0)!=1) {
        Swal.fire('Incomplete','Bank transfer must be Approved before marking as Sold.','warning'); return;
    }
    const isInvalid=url=>!url||url.endsWith('#')||url===window.location.href;
    if (isInvalid(document.getElementById('frameDrivingLicence').src)||
        isInvalid(document.getElementById('frameBankStatement').src)||
        isInvalid(document.getElementById('frameSalarySlip').src)) {
        Swal.fire('Incomplete','All 3 documents must be uploaded.','warning'); return;
    }
    const r=await Swal.fire({title:'Transaction Complete?',html:'✅ Documents verified<br>🏦 Bank transfer approved<br>📦 Stock will be deducted by 1',icon:'success',showCancelButton:true,confirmButtonText:'Yes, Mark as Sold!',confirmButtonColor:'#7c3aed'});
    if (r.isConfirmed) await doAction('mark_sold',{});
}

async function cancelReservation() {
    const {value:reason,isConfirmed}=await Swal.fire({title:'Cancel Reservation?',input:'textarea',inputLabel:'Reason',inputPlaceholder:'Enter reason…',showCancelButton:true,confirmButtonText:'Cancel Reservation',confirmButtonColor:'#ef4444'});
    if (isConfirmed) await doAction('cancel_reservation',{reason:reason||''});
}

async function doAction(action,extra={},skipReload=false) {
    const body=new URLSearchParams({action,reservation_id:currentResId,...extra});
    try {
        const res=await fetch('orders.php',{method:'POST',body});
        const data=JSON.parse(await res.text());
        if (data.success) {
            await Swal.fire({icon:'success',title:'Done!',text:data.message,timer:1500,showConfirmButton:false});
            if (!skipReload){closeModal();location.reload();}
        } else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    } catch(e) { Swal.fire({icon:'error',title:'Network Error',text:e.message}); }
}

function openAddModal()  { document.getElementById('addModal').classList.remove('hidden'); }
function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }

document.getElementById('addReservationForm').addEventListener('submit',async function(e){
    e.preventDefault();
    const fd=new FormData(this); fd.append('action','add_reservation');
    try {
        const res=await fetch('orders.php',{method:'POST',body:new URLSearchParams(fd)});
        const data=await res.json();
        if (data.success) { await Swal.fire({icon:'success',title:'Created!',text:data.message,timer:2000,showConfirmButton:false}); closeAddModal(); location.reload(); }
        else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    } catch(e) { Swal.fire({icon:'error',title:'Network Error',text:e.message}); }
});

function fmt(n) { return parseFloat(n).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2}); }