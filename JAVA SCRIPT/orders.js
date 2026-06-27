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

    let rawPrice = String(row.car_price || '0').replace(/[^0-9.]/g, '');
    const carPrice = parseFloat(rawPrice) || 0;
    const bookFee = parseFloat(row.booking_fee || 0);
    const dpAmt = parseFloat(row.dp_amount || 0);
    const insFee = parseFloat(row.insurance_fee || 0);
    const plateFee = parseFloat(row.plate_registration_fee || 0);
    const extraFees = insFee + plateFee;
    const years = parseInt(row.installment_years || 9);
    const loanRate = parseFloat(row.interest_rate) || window.GLOBAL_LOAN_RATE || 3;
    const financeBox = document.querySelector('.finance-box');
    let financeHTML = '';


    const bookingPaid = row.booking_paid_at;

    const bookingBadge = bookingPaid
        ? '<span class="paid-badge" style="margin-left:8px;">PAID</span>'
        : '<span style="background:#fef3c7;color:#d97706;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:700;margin-left:8px;">UNPAID</span>';
    if (isBooking || (isHistory && sub_tab === 'booking')) {
        let balance = carPrice - bookFee;
        financeHTML = `
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Car Price</span>
                <span style="font-weight:700;color:#111827;font-size:15px;">RM ${fmt(carPrice)}</span>
            </div>
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Booking Fee ${bookingBadge}</span>
                <span style="font-weight:600;color:#10b981;font-size:14px;">- RM ${fmt(bookFee)}</span>
            </div>
            <div class="finance-row total">
                <span style="font-size:13px;font-weight:700;color:#111827;">Remaining Balance</span>
                <span style="font-size:18px;font-weight:800;color:#ef4444;">RM ${fmt(balance)}</span>
            </div>
        `;
    } else if (isDP || (isHistory && sub_tab === 'down_payment')) {
        let totalPaid = dpAmt + extraFees;
        let afterBooking = carPrice - bookFee;
        let dpPct = row.locked_dp_pct ? parseFloat(row.locked_dp_pct) : (carPrice > 0 ? Math.round(dpAmt / carPrice * 100) : 0);
        let paidBadge = row.paid_at ? '<span class="paid-badge" style="margin-left:8px;">PAID</span>' : '<span style="background:#fef3c7;color:#d97706;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:700;margin-left:8px;">UNPAID</span>';

        financeHTML = `
            <div class="finance-row" style="border-bottom:1px dashed #e5e7eb;padding-bottom:10px;margin-bottom:6px;">
                <span style="color:#111827;font-weight:700;">Car Price (after Booking Fee)</span>
                <span style="font-weight:800;color:#111827;font-size:15px;">RM ${fmt(afterBooking)}</span>
            </div>
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Down Payment <span style="color:#9ca3af;font-weight:700;font-size:12px;">(${dpPct}%)</span></span>
                <span style="font-weight:600;color:#10b981;font-size:14px;">- RM ${fmt(dpAmt)}</span>
            </div>
        `;

        if (insFee > 0) {
            financeHTML += `
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Insurance Fee</span>
                <span style="font-weight:600;color:#6b7280;font-size:14px;">+ RM ${fmt(insFee)}</span>
            </div>`;
        }
        if (plateFee > 0) {
            financeHTML += `
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Plate Registration</span>
                <span style="font-weight:600;color:#6b7280;font-size:14px;">+ RM ${fmt(plateFee)}</span>
            </div>`;
        }

        financeHTML += `
            <div class="finance-row total">
                <span style="font-size:13px;font-weight:700;color:#111827;display:flex;align-items:center;">Total Paid ${paidBadge}</span>
                <span style="font-size:18px;font-weight:800;color:#10b981;">RM ${fmt(totalPaid)}</span>
            </div>
        `;
        let remainingBalance = afterBooking - dpAmt;
        financeHTML += `
            <div class="finance-row total" style="margin-top:8px;border-top:1px dashed #e5e7eb;padding-top:10px;">
                <span style="font-size:13px;font-weight:700;color:#111827;">Remaining Balance</span>
                <span style="font-size:18px;font-weight:800;color:#ef4444;">RM ${fmt(remainingBalance)}</span>
            </div>
        `;
    } else {
        let balance = carPrice - bookFee - dpAmt;
        const loan = Math.max(0, balance);
        const rateDec = loanRate / 100;
        const months = years * 12;
        const totalWithInt = loan * (1 + rateDec * years);
        const monthly = months > 0 ? (totalWithInt / months) : 0;

        financeHTML = `
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Car Price</span>
                <span style="font-weight:700;color:#111827;font-size:15px;">RM ${fmt(carPrice)}</span>
            </div>
            <div class="finance-row">
                <span style="color:#6b7280;font-weight:600;">Booking + DP Paid</span>
                <span style="font-weight:600;color:#10b981;font-size:14px;">- RM ${fmt(bookFee + dpAmt)}</span>
            </div>
            <div class="finance-row total">
                <span style="font-size:13px;font-weight:700;color:#111827;">Total Loan Amount</span>
                <span style="font-size:18px;font-weight:800;color:#ef4444;">RM ${fmt(loan)}</span>
            </div>
            <div class="finance-row total" style="margin-top:10px; border-top:1px dashed #e5e7eb; padding-top:10px;">
                <div>
                    <span style="display:block;font-size:13px;font-weight:700;color:#2563eb;">Monthly Installment</span>
                    <span style="font-size:11px;color:#9ca3af;">${years} Yrs @ ${loanRate}% P.A.</span>
                </div>
                <span style="font-size:22px;font-weight:800;color:#2563eb;">RM ${fmt(monthly)}</span>
            </div>
        `;
    }
    financeBox.innerHTML = financeHTML;

    const docsWrap = document.getElementById('docsWrap');
    if (isBooking || (isHistory && sub_tab === 'booking')) {
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
        if (docFrame) { docFrame.src = ''; docFrame.style.display = 'none'; }
    } else {
        docsWrap.style.display = 'none';
    }

    const dpPanel = document.getElementById('dpPanel');
    const btnApproveDP = document.getElementById('btnApproveDP');

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
        if (row.plate_option === 'new') optText = 'New Plate (Registered by Dealer)';
        else if (row.plate_option === 'used') optText = 'Keep Used Car Plate';
        document.getElementById('detPlateOption').textContent = optText;

        const plateNumInput = document.getElementById('plateNumberInput');
        const btnSaveDP = document.getElementById('btnSaveDPDetails');

        if (row.plate_option === 'used') {
            const usedPlate = row.used_car_plate || row.plate_number || '';
            plateNumInput.value = usedPlate;
            plateNumInput.readOnly = true;
            plateNumInput.style.background = '#f3f4f6';
            btnSaveDP.style.display = isReadonly ? 'none' : 'block';
        } else {
            plateNumInput.value = row.plate_number || '';
            plateNumInput.readOnly = isReadonly;
            plateNumInput.style.background = isReadonly ? '#f3f4f6' : '#fff';
            btnSaveDP.style.display = isReadonly ? 'none' : 'block';
        }

        if (isDP) {
            btnApproveDP.style.display = 'inline-block';
            if (!row.paid_at) {
                btnApproveDP.disabled = true;
                btnApproveDP.style.background = '#9ca3af';
                btnApproveDP.style.cursor = 'not-allowed';
                btnApproveDP.textContent = 'Awaiting Payment...';
            } else {
                btnApproveDP.disabled = false;
                btnApproveDP.style.background = '#10b981';
                btnApproveDP.style.cursor = 'pointer';
                btnApproveDP.textContent = 'Verify & Generate Loan';
            }
        } else {
            btnApproveDP.style.display = 'none';
        }

    } else {
        dpPanel.style.display = 'none';
        btnApproveDP.style.display = 'none';
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
    document.getElementById('detLoanTerm').textContent = years + ' Yrs @ ' + loanRate + '%';
    document.getElementById('detCreatedAt').textContent = row.created_at ? new Date(row.created_at).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

    const btnApproveBooking =
        document.getElementById('btnApproveBooking');

    const btnRejectBooking =
        document.getElementById('btnRejectBooking');

    if (isBooking) {

        if (!row.booking_paid_at) {
            btnApproveBooking.style.display = 'inline-block';
            btnApproveBooking.disabled = true;
            btnApproveBooking.style.background = '#9ca3af';
            btnApproveBooking.style.cursor = 'not-allowed';
            btnApproveBooking.textContent = 'Awaiting Payment...';
            btnRejectBooking.style.display = 'inline-block';
        } else {
            btnApproveBooking.style.display = 'inline-block';
            btnApproveBooking.disabled = false;
            btnApproveBooking.style.background = '#10b981';
            btnApproveBooking.textContent =
                'Verify & Approve Booking';
            btnRejectBooking.style.display = 'inline-block';
        }

    } else {
        btnApproveBooking.style.display = 'none';
        btnRejectBooking.style.display = 'inline-block';
    }

    document.getElementById('splitModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('splitModal').style.display = 'none';
    currentRow = null;
}

window.viewCustomerDoc = function (key) {
    const url = window.currentCustomerDocs[key];

    if (!url || url === 'NULL' || url === '#') {
        Toast.fire({ icon: 'info', title: 'Document not found.' });
        return;
    }

    let overlay = document.getElementById('docViewerOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'docViewerOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.75);z-index:200000;display:none;align-items:center;justify-content:center;padding:30px;';
        overlay.innerHTML =
            '<div style="background:#fff;border-radius:14px;width:100%;max-width:900px;height:85vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,0.35);">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #e5e7eb;">' +
            '<span id="docViewerTitle" style="font-weight:700;color:#111827;font-size:15px;"><i class="fas fa-file-pdf" style="color:#ef4444;margin-right:8px;"></i>Document Preview</span>' +
            '<div style="display:flex;gap:10px;align-items:center;">' +
            '<a id="docViewerOpen" href="#" target="_blank" class="btn-export" style="padding:5px 12px;font-size:12px;text-decoration:none;"><i class="fas fa-external-link-alt"></i> Open in new tab</a>' +
            '<button id="docViewerClose" type="button" style="background:#f3f4f6;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;color:#374151;">&times;</button>' +
            '</div>' +
            '</div>' +
            '<iframe id="docViewerFrame" src="" style="flex:1;width:100%;border:none;"></iframe>' +
            '</div>';
        document.body.appendChild(overlay);

        const closeIt = () => {
            overlay.style.display = 'none';
            document.getElementById('docViewerFrame').src = '';
        };
        overlay.addEventListener('click', e => { if (e.target === overlay) closeIt(); });
        overlay.querySelector('#docViewerClose').addEventListener('click', closeIt);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && overlay.style.display === 'flex') closeIt();
        });
    }

    const labels = {
        ic_url: 'IC Document',
        driving_license_url: 'Driving Licence',
        payslip_url: '3 Months Payslip',
        bank_statement_url: 'Bank Statement',
        insurance: 'Insurance Cover Note'
    };
    document.getElementById('docViewerTitle').innerHTML =
        '<i class="fas fa-file-pdf" style="color:#ef4444;margin-right:8px;"></i>' + (labels[key] || 'Document Preview');
    document.getElementById('docViewerFrame').src = url;
    document.getElementById('docViewerOpen').href = url;
    overlay.style.display = 'flex';
};

async function openScheduleModal(row) {
    currentRow = row;
    document.getElementById('schedTitle').textContent = 'Amortization Schedule — ' + (row.user_name || '?');
    document.getElementById('schedBody').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:#9ca3af;">Loading...</td></tr>';
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
        document.getElementById('schedBody').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:#9ca3af;">No installments.</td></tr>';
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
        return `
            <tr>
                <td style="font-weight:700;color:#6b7280;">${r.installment_number}</td>
                <td>${due}</td>
                <td style="font-weight:700;color:#111827;">RM ${fmt(r.monthly_amount)}</td>
                <td><span class="badge-pill ${statusClass}">${r.payment_status}</span>${overdueLabel}${blackflag}</td>
                <td style="color:#6b7280;">${paid_at}</td>
            </tr>
        `;
    }).join('');
    document.getElementById('schedBody').innerHTML = html;
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

async function markInstallmentPaid(installmentId) {
    Swal.fire({ icon: 'info', title: 'Not Allowed', text: 'Monthly installments are paid by the customer, not the admin.' });
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
    const { value: reason, isConfirmed } = await Swal.fire({ title: 'Reject DP?', text: 'Transaction will be cancelled and sent to History.', input: 'textarea', inputPlaceholder: 'Reason...', inputValidator: v => !v && 'Reason is required', showCancelButton: true, confirmButtonText: 'Reject DP', confirmButtonColor: '#ef4444' });
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