const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

const _swalZ = document.createElement('style');
_swalZ.innerHTML = '.swal2-container { z-index: 100000 !important; }';
document.head.appendChild(_swalZ);

let currentRow = null;
let currentContext = { tab: '', sub_tab: '' };

function fmt(n) { return parseFloat(n || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function openModal(row, tab, sub_tab) {
    currentRow = row;
    currentContext = { tab: tab || '', sub_tab: sub_tab || '' };

    const isBooking = tab === 'booking';
    const isDP = tab === 'down_payment';
    const isMonthly = tab === 'monthly_installment';
    const isHistory = tab === 'history';
    const isReadonly = isHistory;

    let title = isBooking ? 'Booking Document Verification' : (isDP ? 'Down Payment & Plate Verification' : 'History Details');
    document.getElementById('modalTitleText').textContent = title;
    document.getElementById('detName').textContent = row.user_name || '-';
    document.getElementById('detEmail').textContent = row.user_email || '-';
    document.getElementById('detIC').textContent = row.user_ic || '-';
    document.getElementById('detContact').textContent = row.user_phone || '-';
    document.getElementById('detAddress').textContent = row.user_address || '-';
    document.getElementById('detCityState').textContent = [row.user_city, row.user_state].filter(Boolean).join(', ') || '-';
    document.getElementById('detPostcode').textContent = row.user_postcode || '';
    document.getElementById('detCarImage').src = row.car_image || 'https://via.placeholder.com/120x88?text=No+Image';
    document.getElementById('detCarBrand').textContent = row.car_brand || '-';
    document.getElementById('detCarModel').textContent = row.car_model || '-';
    document.getElementById('detCarVariant').textContent = row.car_variant || '-';
    document.getElementById('detCarColor').textContent = row.car_color || '-';
    document.getElementById('detCarOrigin').textContent = row.car_origin || '-';
    document.getElementById('detCarYear').textContent = row.car_year || '-';
    const carPrice = parseFloat(row.car_price || 0);
    const bookFee = parseFloat(row.booking_fee || 0);
    const dpAmt = parseFloat(row.dp_amount || 0);
    const years = parseInt(row.installment_years || 9);
    const loanRate = window.GLOBAL_LOAN_RATE || 3;

    document.getElementById('detPrice').textContent = 'RM ' + fmt(carPrice);
    document.getElementById('detBookingFee').textContent = '- RM ' + fmt(bookFee);
    document.getElementById('detYears').textContent = years;

    let balance = carPrice - bookFee;

    if (isBooking || (isHistory && sub_tab === 'booking')) {
        document.getElementById('rowDP').style.display = 'none';
        document.getElementById('rowMonthly').style.display = 'none';
        document.getElementById('lblBalanceText').textContent = 'Remaining Balance (尾款)';
        document.getElementById('detBalance').textContent = 'RM ' + fmt(balance);
        document.getElementById('detBalance').style.color = '#ef4444'; 
    } 
    else if (isDP || (isHistory && sub_tab === 'down_payment')) {
        document.getElementById('rowDP').style.display = 'flex';
        document.getElementById('rowMonthly').style.display = 'none';
        document.getElementById('detDP').textContent = '- RM ' + fmt(dpAmt);
        balance -= dpAmt;
        document.getElementById('lblBalanceText').textContent = 'Remaining Balance (尾款)';
        document.getElementById('detBalance').textContent = 'RM ' + fmt(balance);
        document.getElementById('detBalance').style.color = '#ef4444'; 
    } 
    else {
        document.getElementById('rowDP').style.display = 'flex';
        document.getElementById('rowMonthly').style.display = 'flex';
        document.getElementById('detDP').textContent = '- RM ' + fmt(dpAmt);
        balance -= dpAmt;
        document.getElementById('lblBalanceText').textContent = 'Total Loan Amount (總貸款額)';
        document.getElementById('detBalance').textContent = 'RM ' + fmt(balance);
        document.getElementById('detBalance').style.color = '#2563eb'; 

        const loan = Math.max(0, balance);
        const rateDec = loanRate / 100;
        const months = years * 12;
        const totalWithInt = loan * (1 + rateDec * years);
        const monthly = months > 0 ? (totalWithInt / months) : 0;
        document.getElementById('detMonthly').textContent = 'RM ' + fmt(monthly);
    }

    const docsWrap = document.getElementById('docsWrap');
    if (isBooking || isDP || (isHistory && (sub_tab === 'booking' || sub_tab === 'down_payment'))) {
        docsWrap.style.display = 'block';
        window.currentCustomerDocs = {
            ic_url: row.ic_url || '',
            driving_license_url: row.driving_license_url || '',
            payslip_url: row.payslip_url || '',
            bank_statement_url: row.bank_statement_url || ''
        };

        const keys = ['ic_url', 'driving_license_url', 'payslip_url', 'bank_statement_url'];
        keys.forEach(key => {
            const btn = document.getElementById('btnView_' + key);
            const txt = document.getElementById('statusTxt_' + key);
            if (window.currentCustomerDocs[key]) {
                btn.style.display = 'inline-block';
                txt.textContent = 'Uploaded';
                txt.style.color = '#10b981';
            } else {
                btn.style.display = 'none';
                txt.textContent = 'Not uploaded';
                txt.style.color = '#ef4444';
            }
        });
        
        const docFrame = document.getElementById('frameCustomerDoc');
        if(docFrame) { docFrame.src = ''; docFrame.style.display = 'none'; }
    } else {
        docsWrap.style.display = 'none';
    }

    const dpPanel = document.getElementById('dpPanel');
    if (isDP || (isHistory && sub_tab === 'down_payment') || isMonthly || (isHistory && sub_tab === 'monthly_installment')) {
        dpPanel.style.display = 'block';

        window.currentCustomerDocs = window.currentCustomerDocs || {};
        window.currentCustomerDocs['insurance'] = row.insurance_pdf_url || '';
        
        const btnIns = document.getElementById('btnViewInsurance');
        const txtIns = document.getElementById('statusTxt_insurance');

        if (window.currentCustomerDocs['insurance'] && window.currentCustomerDocs['insurance'] !== 'NULL') {
            btnIns.style.display = 'inline-block';
            txtIns.textContent = 'Uploaded';
            txtIns.style.color = '#10b981';
        } else {
            btnIns.style.display = 'none';
            txtIns.textContent = 'Not uploaded';
            txtIns.style.color = '#ef4444';
        }

        let optText = '-';
        if (row.plate_option === 'new') optText = 'New Plate Registration';
        else if (row.plate_option === 'used') optText = 'Keep Used Car Plate';
        else if (row.plate_option === 'custom') optText = 'Custom Plate Number';
        document.getElementById('detPlateOption').textContent = optText;

        const plateNumInput = document.getElementById('plateNumberInput');
        plateNumInput.value = row.plate_number || '';
        plateNumInput.readOnly = isReadonly;
        document.getElementById('btnSaveDPDetails').style.display = isReadonly ? 'none' : 'block';
    } else {
        dpPanel.style.display = 'none';
    }

    const reasonWrap = document.getElementById('reasonWrap');
    let reasonText = '';
    if (isHistory) {
        if (sub_tab === 'booking') reasonText = row.rejection_reason || '';
        else if (sub_tab === 'down_payment' && row.dp_status === 'Cancelled') reasonText = row.dp_reason || '';
    }
    if (reasonText) {
        document.getElementById('detReason').textContent = reasonText;
        reasonWrap.style.display = 'block';
    } else {
        reasonWrap.style.display = 'none';
    }

    document.getElementById('detResID').textContent = 'BK' + String(row.booking_id).padStart(4, '0');
    let statusLabel = row.booking_status || '-';
    if (isDP || (isHistory && sub_tab === 'down_payment')) statusLabel = row.dp_status || '-';
    if (isHistory && sub_tab === 'blacklist') statusLabel = 'Blacklisted';
    document.getElementById('detStatus').textContent = statusLabel;
    document.getElementById('detLoanTerm').textContent = years + ' Yrs @ ' + (window.GLOBAL_LOAN_RATE || 3) + '%';
    document.getElementById('detCreatedAt').textContent = row.created_at ? new Date(row.created_at).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
    document.getElementById('btnApproveBooking').style.display = isBooking ? 'inline-block' : 'none';
    document.getElementById('btnRejectBooking').style.display = isBooking ? 'inline-block' : 'none';
    document.getElementById('btnApproveDP').style.display = isDP ? 'inline-block' : 'none';
    document.getElementById('btnRejectDP').style.display = isDP ? 'inline-block' : 'none';

    document.getElementById('splitModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('splitModal').style.display = 'none';
    currentRow = null;
}

window.viewCustomerDoc = function(key) {
    const url = window.currentCustomerDocs[key];
    const frame = document.getElementById('frameCustomerDoc');
    
    if (!url || url === 'NULL' || url === '#') {
        Toast.fire({ icon: 'info', title: 'Document not found.' });
        return;
    }
    
    if (frame.src.includes(url) && frame.style.display === 'block') {
        frame.style.display = 'none';
    } else {
        frame.src = url;
        frame.style.display = 'block';
    }
};

async function openScheduleModal(row) {
    currentRow = row;
    document.getElementById('schedTitle').textContent = 'Amortization Schedule — ' + (row.user_name || '?');
    document.getElementById('schedBody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#9ca3af;">Loading...</td></tr>';
    document.getElementById('schedSummary').innerHTML = '';
    document.getElementById('scheduleModal').style.display = 'flex';

    const body = new URLSearchParams({ action: 'get_schedule', booking_id: row.booking_id });
    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); return; }
        renderSchedule(data.rows);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
}

function renderSchedule(rows) {
    if (!rows || rows.length === 0) {
        document.getElementById('schedBody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#9ca3af;">No installments.</td></tr>';
        return;
    }

    const total = rows.length;
    const paid = rows.filter(r => r.payment_status === 'Paid').length;
    const overdue = rows.filter(r => r.payment_status === 'Overdue').length;
    const totalAmt = rows.reduce((s, r) => s + parseFloat(r.monthly_amount || 0), 0);
    const paidAmt = rows.filter(r => r.payment_status === 'Paid').reduce((s, r) => s + parseFloat(r.monthly_amount || 0), 0);
    const pct = total > 0 ? Math.round((paid / total) * 100) : 0;

    document.getElementById('schedSummary').innerHTML = `
        <div style="flex:1;background:#fff;padding:12px;border:1px solid #e5e7eb;border-radius:8px;">
            <div style="font-size:11px;color:#6b7280;font-weight:700;">PROGRESS</div>
            <div style="font-size:16px;font-weight:700;color:#111827;">${paid}/${total} <small>(${pct}%)</small></div>
        </div>
        <div style="flex:1;background:#dcfce7;padding:12px;border-radius:8px;">
            <div style="font-size:11px;color:#166534;font-weight:700;">PAID</div>
            <div style="font-size:16px;font-weight:700;color:#166534;">RM ${fmt(paidAmt)}</div>
        </div>
        <div style="flex:1;background:#fee2e2;padding:12px;border-radius:8px;">
            <div style="font-size:11px;color:#991b1b;font-weight:700;">OVERDUE</div>
            <div style="font-size:16px;font-weight:700;color:#991b1b;">${overdue} Months</div>
        </div>
        <div style="flex:1;background:#fff;padding:12px;border:1px solid #e5e7eb;border-radius:8px;">
            <div style="font-size:11px;color:#6b7280;font-weight:700;">TOTAL LOAN</div>
            <div style="font-size:16px;font-weight:700;color:#2563eb;">RM ${fmt(totalAmt)}</div>
        </div>
    `;

    const html = rows.map(r => {
        const statusClass = r.payment_status === 'Paid' ? 'badge-paid' : (r.payment_status === 'Overdue' ? 'badge-overdue' : 'badge-pending');
        const due = r.due_date ? new Date(r.due_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
        const paid_at = r.paid_at ? new Date(r.paid_at).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
        const overdueLabel = (r.payment_status === 'Overdue' && r.overdue_days > 0) ? ` <span style="color:#dc2626;font-size:11px;">(${r.overdue_days}d late)</span>` : '';
        const blackflag = (r.overdue_days >= 21) ? ' <span style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;margin-left:4px;">BLACKLIST</span>' : '';
        const actionBtn = (r.payment_status === 'Paid') ? '<span style="color:#9ca3af;font-size:12px;">—</span>' : `<button class="btn-add-blue" style="padding:4px 10px;font-size:12px;border:none;background:#10b981;color:#fff;" onclick="markInstallmentPaid(${r.installment_id})"><i class="fas fa-check"></i> Mark Paid</button>`;
        return `
            <tr>
                <td style="font-weight:700;color:#6b7280;">${r.installment_number}</td>
                <td>${due}</td>
                <td style="font-weight:700;color:#111827;">RM ${fmt(r.monthly_amount)}</td>
                <td><span class="badge-pill ${statusClass}">${r.payment_status}</span>${overdueLabel}${blackflag}</td>
                <td style="color:#6b7280;">${paid_at}</td>
                <td>${actionBtn}</td>
            </tr>
        `;
    }).join('');
    document.getElementById('schedBody').innerHTML = html;
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

async function markInstallmentPaid(installmentId) {
    const r = await Swal.fire({ title: 'Mark as Paid?', text: 'Payment receipt will be generated.', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes', confirmButtonColor: '#10b981' });
    if (!r.isConfirmed) return;
    const ok = await doAction({ action: 'mark_installment_paid', installment_id: installmentId }, true);
    if (ok && currentRow) await openScheduleModal(currentRow);
}

async function doAction(payload, skipReload = false) {
    const body = new URLSearchParams(payload);
    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            Toast.fire({ icon: 'success', title: data.message || 'Done!' });
            if (!skipReload) setTimeout(() => location.reload(), 700);
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

async function approveBooking() {
    if (!currentRow) return;
    const r = await Swal.fire({ title: 'Approve & Verify?', text: 'Documents are verified. Advance customer to Down Payment stage.', icon: 'question', showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: 'Approve' });
    if (r.isConfirmed) await doAction({ action: 'approve_booking', booking_id: currentRow.booking_id });
}

async function rejectBooking() {
    if (!currentRow) return;
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'Reject & Refund',
        html: '<p style="text-align:left;font-size:13px;color:#ef4444;">Record will be sent to History.</p>',
        input: 'textarea',
        inputPlaceholder: 'Reason for rejection...',
        inputValidator: v => !v && 'Reason required',
        showCancelButton: true, confirmButtonText: 'Reject Booking', confirmButtonColor: '#ef4444'
    });
    if (isConfirmed) await doAction({ action: 'reject_booking', booking_id: currentRow.booking_id, reason });
}

async function saveDPDetails() {
    if (!currentRow) return;
    const plateNum = document.getElementById('plateNumberInput').value.trim();
    
    const ok = await doAction({ 
        action: 'save_dp_details', 
        booking_id: currentRow.booking_id, 
        plate_number: plateNum, 
        plate_fee: 0
    }, true);

    if (ok) {
        currentRow.plate_number = plateNum; 
    }
}

async function approveDP() {
    if (!currentRow) return;
    if (!window.currentCustomerDocs['insurance'] || window.currentCustomerDocs['insurance'] === 'NULL') { 
        Swal.fire({ icon: 'warning', title: 'Missing Cover Note', text: 'Cannot approve. Customer must upload the Insurance Cover Note.' }); 
        return; 
    }
    const r = await Swal.fire({ title: 'Approve & Finalize?', text: 'Payment is verified. Generates monthly loan schedule.', icon: 'success', showCancelButton: true, confirmButtonText: 'Verify & Approve', confirmButtonColor: '#10b981' });
    if (r.isConfirmed) await doAction({ action: 'approve_dp', booking_id: currentRow.booking_id });
}

async function rejectDP() {
    if (!currentRow) return;
    const { value: reason, isConfirmed } = await Swal.fire({ title: 'Reject DP?', text:'Transaction will be cancelled and sent to History.', input: 'textarea', inputPlaceholder: 'Reason...', inputValidator: v => !v && 'Reason is required', showCancelButton: true, confirmButtonText: 'Reject DP', confirmButtonColor: '#ef4444' });
    if (isConfirmed) await doAction({ action: 'reject_dp', booking_id: currentRow.booking_id, reason });
}

document.addEventListener('DOMContentLoaded', () => {
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
                    const rowId = idCell.textContent.trim().replace('BK', '').replace(/^0+/, '');
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
    ['splitModal', 'scheduleModal'].forEach(id => {
        const m = document.getElementById(id);
        if (m) m.addEventListener('click', function (e) { if (e.target === m) m.style.display = 'none'; });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ['splitModal', 'scheduleModal'].forEach(id => {
                const m = document.getElementById(id);
                if (m && m.style.display === 'flex') m.style.display = 'none';
            });
        }
    });
});