<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['id']) ||
    strcasecmp($_SESSION['role'] ?? '', 'Customer') !== 0
) {
    header("Location: Auth/login.php");
    exit();
}

$user_id = (int) $_SESSION['id'];
$active_tab = $_GET['tab'] ?? 'bookings';
if (!in_array($active_tab, ['bookings', 'reservations', 'history'])) {
    $active_tab = 'bookings';
}

$bookings_sql = "
SELECT
    b.*,
    c.car_brand, c.car_model, c.car_year, c.car_origin,
    cs.car_status_price AS car_price_live,
    (SELECT car_image_url FROM car_image WHERE car_id = b.car_id LIMIT 1) AS car_image_live,

    dp.id AS dp_id,
    dp.dp_amount,
    dp.dp_status,
    dp.dp_created_at,
    dp.dp_approved_at,
    dp.insurance_pdf_url,
    dp.plate_number,
    dp.plate_option,
    dp.dp_reason,

    doc.ic_url, doc.driving_license_url, doc.payslip_url, doc.bank_statement_url,

    
    (SELECT receipt_number FROM payments
     WHERE reference_id = b.booking_id AND payment_type='Booking Fee' AND payment_status='Paid'
     ORDER BY payment_id DESC LIMIT 1) AS bf_receipt,
    (SELECT payment_date FROM payments
     WHERE reference_id = b.booking_id AND payment_type='Booking Fee' AND payment_status='Paid'
     ORDER BY payment_id DESC LIMIT 1) AS bf_paid_date,

   
    (SELECT COUNT(*) FROM monthly_installments WHERE booking_id = b.booking_id) AS total_months,
    (SELECT COUNT(*) FROM monthly_installments WHERE booking_id = b.booking_id AND payment_status='Paid') AS paid_months,
    (SELECT COUNT(*) FROM monthly_installments WHERE booking_id = b.booking_id AND payment_status='Overdue') AS overdue_months,
    (SELECT MIN(due_date) FROM monthly_installments WHERE booking_id = b.booking_id AND payment_status IN ('Pending','Overdue')) AS next_due,
    (SELECT monthly_amount FROM monthly_installments WHERE booking_id = b.booking_id LIMIT 1) AS monthly_amount,
    (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE reference_id = b.booking_id AND payment_status='Paid') AS total_paid

FROM bookings b
LEFT JOIN cars c ON c.car_id = b.car_id
LEFT JOIN car_status cs ON cs.car_id = b.car_id
LEFT JOIN down_payments dp ON dp.booking_id = b.booking_id
LEFT JOIN loan_installment_documents doc ON doc.booking_id = b.booking_id
WHERE b.user_id = ?
ORDER BY b.created_at DESC
";

$bk_stmt = mysqli_prepare($conn, $bookings_sql);
mysqli_stmt_bind_param($bk_stmt, "i", $user_id);
mysqli_stmt_execute($bk_stmt);
$bk_result = mysqli_stmt_get_result($bk_stmt);
$bookings = [];
while ($r = mysqli_fetch_assoc($bk_result))
    $bookings[] = $r;
mysqli_stmt_close($bk_stmt);

$reservations_sql = "
SELECT
    r.*,
    c.car_brand, c.car_model, c.car_year, c.car_origin,
    cs.car_status_status AS car_status_live,
    (SELECT IFNULL(SUM(quantity),0) FROM car_inventory WHERE car_id = r.car_id) AS car_stock_live,
    (SELECT car_image_url FROM car_image WHERE car_id = r.car_id LIMIT 1) AS car_image_live,
    t.test_drive_id, t.test_drive_at, t.test_drive_status, t.test_drive_done_at, t.test_drive_cancel_reason
FROM reservations r
LEFT JOIN cars c ON c.car_id = r.car_id
LEFT JOIN car_status cs ON cs.car_id = r.car_id
LEFT JOIN test_drives t ON t.reservation_id = r.reservation_id
WHERE r.user_id = ?
ORDER BY r.reservation_created_at DESC
";

$res_stmt = mysqli_prepare($conn, $reservations_sql);
mysqli_stmt_bind_param($res_stmt, "i", $user_id);
mysqli_stmt_execute($res_stmt);
$res_result = mysqli_stmt_get_result($res_stmt);
$reservations = [];
while ($r = mysqli_fetch_assoc($res_result))
    $reservations[] = $r;
mysqli_stmt_close($res_stmt);

$receipt_map = [];
$rcpt_sql = "
    SELECT p.payment_id, p.reference_id, p.payment_type, p.payment_amount, p.payment_date
    FROM payments p
    JOIN bookings b ON b.booking_id = p.reference_id
    WHERE b.user_id = ?
      AND p.payment_status = 'Paid'
      AND p.payment_type IN ('Booking Fee','Down Payment')
    ORDER BY p.payment_date ASC
";
$rc_stmt = mysqli_prepare($conn, $rcpt_sql);
mysqli_stmt_bind_param($rc_stmt, "i", $user_id);
mysqli_stmt_execute($rc_stmt);
$rc_res = mysqli_stmt_get_result($rc_stmt);
while ($row = mysqli_fetch_assoc($rc_res))
    $receipt_map[(int) $row['reference_id']][] = $row;
mysqli_stmt_close($rc_stmt);

function get_booking_stage($b)
{
    $st = $b['booking_status'];
    $bf_ok = !empty($b['booking_paid_at']);
    $dp_st = $b['dp_status'] ?? null;
    $ins_ok = !empty($b['insurance_pdf_url']);
    $totM = (int) ($b['total_months'] ?? 0);
    $paidM = (int) ($b['paid_months'] ?? 0);
    $ovrM = (int) ($b['overdue_months'] ?? 0);
    $bid = (int) $b['booking_id'];

    if ($st === 'Rejected') {
        return [
            'key' => 'rejected',
            'label' => 'Booking Rejected',
            'class' => 'rejected',
            'desc' => 'Reason: ' . ($b['rejection_reason'] ?: 'Not specified'),
            'action_label' => null,
            'action_href' => null,
            'progress' => 100
        ];
    }
    if ($st === 'Refunded') {
        return [
            'key' => 'refunded',
            'label' => 'Down Payment Cancelled',
            'class' => 'rejected',
            'desc' => 'Reason: ' . ($b['dp_reason'] ?: $b['rejection_reason'] ?: 'Not specified'),
            'action_label' => null,
            'action_href' => null,
            'progress' => 100
        ];
    }
    if ($st === 'Pending') {
        if (!$bf_ok) {
            return [
                'key' => 'unpaid_bf',
                'label' => 'Booking Fee Unpaid',
                'class' => 'warning',
                'desc' => 'Complete your RM 500 booking fee to begin the review process.',
                'action_label' => 'Pay Booking Fee',
                'action_href' => "payment.php?id=$bid",
                'progress' => 10
            ];
        }
        return [
            'key' => 'awaiting_approval',
            'label' => 'Awaiting Admin Review',
            'class' => 'pending',
            'desc' => 'Our team is reviewing your application and documents. Typically 1-2 business days.',
            'action_label' => null,
            'action_href' => null,
            'progress' => 25
        ];
    }
    if ($st === 'Approved') {
        if (!$dp_st || $dp_st === 'Pending') {
            if (!$ins_ok) {
                return [
                    'key' => 'pay_dp',
                    'label' => 'Action Required: Down Payment',
                    'class' => 'info',
                    'desc' => 'Your booking is approved! Pay the down payment and upload your insurance cover note to proceed.',
                    'action_label' => 'Pay Down Payment',
                    'action_href' => "downpayment.php?id=$bid",
                    'progress' => 50
                ];
            }
            return [
                'key' => 'awaiting_dp',
                'label' => 'Awaiting DP Verification',
                'class' => 'pending',
                'desc' => 'Your payment & insurance cover note are being verified by our finance team.',
                'action_label' => null,
                'action_href' => null,
                'progress' => 65
            ];
        }
        if ($dp_st === 'Approved') {
            if ($totM > 0 && $paidM >= $totM) {
                return [
                    'key' => 'completed',
                    'label' => 'Loan Fully Paid',
                    'class' => 'completed',
                    'desc' => 'Congratulations! All installments have been completed.',
                    'action_label' => 'View Statement',
                    'action_href' => "monthly_installment.php?id=$bid",
                    'progress' => 100
                ];
            }
            $next_due = !empty($b['next_due']) ? date('d M Y', strtotime($b['next_due'])) : null;
            $label = 'Installment Active';
            if ($ovrM > 0)
                $label = "Installment Overdue ($ovrM)";

            $desc = $totM > 0 ? "$paidM of $totM installments paid" : "Installment plan active";
            if ($next_due)
                $desc .= " · Next due: $next_due";

            return [
                'key' => 'pay_installment',
                'label' => $label,
                'class' => $ovrM > 0 ? 'rejected' : 'info',
                'desc' => $desc,
                'action_label' => 'View Installments',
                'action_href' => "monthly_installment.php?id=$bid",
                'progress' => $totM > 0 ? max(75, round(75 + ($paidM / $totM) * 25)) : 75
            ];
        }
        if ($dp_st === 'Cancelled' || $dp_st === 'Rejected') {
            return [
                'key' => 'dp_rejected',
                'label' => 'Down Payment Rejected',
                'class' => 'rejected',
                'desc' => 'Reason: ' . ($b['dp_reason'] ?: 'Not specified'),
                'action_label' => null,
                'action_href' => null,
                'progress' => 100
            ];
        }
    }
    return [
        'key' => 'unknown',
        'label' => $st,
        'class' => 'neutral',
        'desc' => 'Status unknown',
        'action_label' => null,
        'action_href' => null,
        'progress' => 0
    ];
}


function get_reservation_stage($r)
{
    $st = $r['reservation_status'];
    $td_st = $r['test_drive_status'] ?? null;

    if ($st === 'Pending Viewing') {
        return [
            'label' => 'Pending Admin Review',
            'class' => 'pending',
            'desc' => 'Our team will review your request and confirm your test drive shortly.'
        ];
    }
    if ($st === 'Rejected') {
        return [
            'label' => 'Reservation Rejected',
            'class' => 'rejected',
            'desc' => 'Reason: ' . ($r['reservation_cancel_reason'] ?: 'Not specified')
        ];
    }
    if ($st === 'Approved') {
        if ($td_st === 'Scheduled') {
            $td_at = !empty($r['test_drive_at']) ? date('d M Y, h:i A', strtotime($r['test_drive_at'])) : 'TBA';
            return [
                'label' => 'Test Drive Scheduled',
                'class' => 'info',
                'desc' => "Confirmed for $td_at. Please arrive 15 minutes early."
            ];
        }
        if ($td_st === 'Completed') {
            return [
                'label' => 'Test Drive Completed',
                'class' => 'completed',
                'desc' => 'Thank you for your visit. Ready to take the next step?'
            ];
        }
        if ($td_st === 'Cancelled') {
            return [
                'label' => 'Test Drive Cancelled',
                'class' => 'rejected',
                'desc' => 'Reason: ' . ($r['test_drive_cancel_reason'] ?: 'Not specified')
            ];
        }
    }
    return ['label' => $st, 'class' => 'neutral', 'desc' => ''];
}
$history_booking_keys = ['completed', 'rejected', 'refunded', 'dp_rejected'];

$active_bookings = [];
$history_bookings = [];
foreach ($bookings as $b) {
    $stg = get_booking_stage($b);
    if (in_array($stg['key'], $history_booking_keys, true)) {
        $history_bookings[] = $b;
    } else {
        $active_bookings[] = $b;
    }
}

$active_reservations = [];
$history_reservations = [];
foreach ($reservations as $r) {
    $stg = get_reservation_stage($r);
    if (in_array($stg['class'], ['completed', 'rejected'], true)) {
        $history_reservations[] = $r;
    } else {
        $active_reservations[] = $r;
    }
}
$history_count = count($history_bookings) + count($history_reservations);

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
    .activity-page {
        background: #f8fafc;
        min-height: calc(100vh - 80px);
        padding: 40px 20px 60px;
    }

    .activity-wrapper {
        max-width: 1100px;
        margin: 0 auto;
    }

    .page-heading {
        margin-bottom: 24px;
    }

    .page-heading h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }

    .page-heading p {
        color: #64748b;
        font-size: 14px;
    }

    .tabs {
        display: flex;
        gap: 8px;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 24px;
    }

    .tab {
        padding: 12px 22px;
        color: #64748b;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border-bottom: 3px solid transparent;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .tab:hover {
        color: #1e293b;
    }

    .tab.active {
        color: #1e293b;
        border-bottom-color: #1e293b;
        font-weight: 700;
    }

    .tab .count {
        background: #f1f5f9;
        color: #64748b;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .tab.active .count {
        background: #1e293b;
        color: #fff;
    }

    .activity-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 14px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: 0.2s;
    }

    .activity-card:hover {
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.06);
    }

    .card-summary {
        display: grid;
        grid-template-columns: 110px 1fr auto;
        gap: 18px;
        padding: 18px 22px;
        align-items: center;
        cursor: pointer;
    }

    @media(max-width:680px) {
        .card-summary {
            grid-template-columns: 80px 1fr;
            gap: 14px;
        }

        .card-summary .actions {
            grid-column: 1 / -1;
        }
    }

    .car-thumb {
        width: 110px;
        height: 75px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    @media(max-width:680px) {
        .car-thumb {
            width: 80px;
            height: 60px;
        }
    }

    .summary-info .row-top {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 4px;
        flex-wrap: wrap;
    }

    .summary-info .ref {
        color: #94a3b8;
        font-family: monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .summary-info .car-name {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .summary-info .car-meta {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 6px;
    }

    .summary-info .stage-desc {
        font-size: 12px;
        color: #475569;
        line-height: 1.5;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .badge.warning {
        background: #ffedd5;
        color: #9a3412;
    }

    .badge.info {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge.approved {
        background: #dcfce7;
        color: #166534;
    }

    .badge.completed {
        background: #dcfce7;
        color: #15803d;
    }

    .badge.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge.neutral {
        background: #f1f5f9;
        color: #475569;
    }

    .card-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }

    .btn-action {
        background: #1e293b;
        color: #fff;
        text-decoration: none;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
        border: none;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
    }

    .btn-action:hover {
        background: #0f172a;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 41, 59, 0.2);
    }

    .btn-action.primary-green {
        background: #16a34a;
    }

    .btn-action.primary-green:hover {
        background: #15803d;
    }

    .btn-action.outline {
        background: #fff;
        color: #1e293b;
        border: 1.5px solid #e2e8f0;
    }

    .btn-action.outline:hover {
        background: #f8fafc;
        border-color: #1e293b;
    }

    .btn-expand {
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 12px;
        cursor: pointer;
        transition: 0.2s;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-expand:hover {
        background: #f8fafc;
        color: #1e293b;
        border-color: #cbd5e1;
    }

    .card-body {
        display: none;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        padding: 22px;
    }

    .card-body.open {
        display: block;
    }

    .section {
        margin-bottom: 18px;
    }

    .section h4 {
        font-size: 11px;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .progress-track {
        background: #e2e8f0;
        height: 8px;
        border-radius: 999px;
        overflow: hidden;
        margin: 8px 0 4px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #1e293b 0%, #16a34a 100%);
        border-radius: 999px;
        transition: width 0.5s;
    }

    .progress-fill.warn {
        background: linear-gradient(90deg, #f59e0b 0%, #ef4444 100%);
    }

    .stage-timeline {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
        margin-top: 12px;
    }

    .stage-step {
        text-align: center;
        padding: 8px 4px;
        border-radius: 8px;
        font-size: 10px;
        color: #94a3b8;
        border: 1px solid #e2e8f0;
        background: #fff;
    }

    .stage-step .ic {
        font-size: 14px;
        margin-bottom: 3px;
    }

    .stage-step.done {
        background: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }

    .stage-step.current {
        background: #dbeafe;
        color: #1e40af;
        border-color: #bfdbfe;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .stage-step.failed {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    @media(max-width:680px) {
        .stage-timeline {
            grid-template-columns: repeat(5, 1fr);
        }

        .stage-step {
            font-size: 9px;
        }
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }

    .detail-cell {
        background: #fff;
        padding: 11px 14px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .detail-cell label {
        font-size: 10px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-weight: 700;
        display: block;
        margin-bottom: 3px;
    }

    .detail-cell p {
        font-size: 13px;
        color: #1e293b;
        font-weight: 600;
        margin: 0;
        word-break: break-word;
    }

    .doc-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .doc-chip {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        color: #1e293b;
        transition: 0.2s;
    }

    .doc-chip:hover {
        border-color: #1e293b;
        background: #f8fafc;
    }

    .doc-chip i.fa-file-pdf {
        color: #dc2626;
    }

    .doc-chip .missing {
        color: #94a3b8;
        font-style: italic;
    }

    .reason-box {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.6;
    }

    .empty-state {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        padding: 60px 30px;
        text-align: center;
    }

    .empty-state i {
        font-size: 50px;
        color: #cbd5e1;
        margin-bottom: 18px;
    }

    .empty-state h3 {
        color: #1e293b;
        font-size: 18px;
        margin-bottom: 8px;
    }

    .empty-state p {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 24px;
    }

    .empty-state a {
        display: inline-block;
        background: #1e293b;
        color: #fff;
        padding: 12px 28px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: 0.2s;
    }

    .empty-state a:hover {
        background: #0f172a;
        transform: translateY(-1px);
    }
</style>

<div class="activity-page">
    <div class="activity-wrapper">

        <div class="page-heading">
            <h1>My Activity</h1>
            <p>Track your test drive reservations and car bookings here.</p>
        </div>

        <div class="tabs">
            <a href="?tab=bookings" class="tab <?= $active_tab === 'bookings' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i> Car Bookings
                <span class="count"><?= count($active_bookings) ?></span>
            </a>
            <a href="?tab=reservations" class="tab <?= $active_tab === 'reservations' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i> Test Drive Reservations
                <span class="count"><?= count($active_reservations) ?></span>
            </a>
            <a href="?tab=history" class="tab <?= $active_tab === 'history' ? 'active' : '' ?>">
                <i class="fas fa-clock-rotate-left"></i> History
                <span class="count"><?= $history_count ?></span>
            </a>
        </div>

        <?php if ($active_tab === 'history' && $history_count === 0): ?>
            <div class="empty-state">
                <i class="fas fa-clock-rotate-left"></i>
                <h3>No History Yet</h3>
                <p>Completed and cancelled bookings &amp; reservations will appear here.</p>
                <a href="cars.php"><i class="fas fa-car"></i> Browse Vehicles</a>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'bookings' || $active_tab === 'history'):
            $book_list = ($active_tab === 'history') ? $history_bookings : $active_bookings; ?>

            <?php if ($active_tab === 'bookings' && count($book_list) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-car-side"></i>
                    <h3>No Bookings Yet</h3>
                    <p>You haven't applied for any vehicle financing. Browse our inventory to begin.</p>
                    <a href="cars.php"><i class="fas fa-car"></i> Browse Vehicles</a>
                </div>
            <?php else: ?>

                <?php foreach ($book_list as $b):
                    $snap = json_decode($b['snapshot_data'] ?: '{}', true);
                    if (!is_array($snap))
                        $snap = [];

                    $car_brand = $snap['car_brand'] ?? $b['car_brand'] ?? '';
                    $car_model = $snap['car_model'] ?? $b['car_model'] ?? '';
                    $car_year = $snap['car_year'] ?? $b['car_year'] ?? '';
                    $car_origin = $snap['car_origin'] ?? $b['car_origin'] ?? '';
                    $car_image = $snap['car_image'] ?? $b['car_image_live'] ?? 'https://via.placeholder.com/200x140?text=Vehicle';
                    $car_variant = $snap['car_variant'] ?? '-';
                    $car_color = $snap['car_color'] ?? '-';
                    $car_price = floatval($snap['car_price'] ?? $b['car_price_live'] ?? 0);

                    $stage = get_booking_stage($b);
                    $bid = (int) $b['booking_id'];
                    $bref = 'BK' . str_pad($bid, 4, '0', STR_PAD_LEFT);

                    $booking_fee = floatval($b['booking_fee']);
                    $dp_amount = floatval($b['dp_amount'] ?? 0);
                    $total_paid = floatval($b['total_paid'] ?? 0);
                    $monthly_amount = floatval($b['monthly_amount'] ?? 0);
                    $total_months = (int) $b['total_months'];
                    $paid_months = (int) $b['paid_months'];
                    $remaining = max(0, $car_price - $total_paid);

                    $step_states = [
                        'booking_fee' => $b['booking_paid_at'] ? 'done' : 'current',
                        'admin_review' => 'pending',
                        'down_payment' => 'pending',
                        'dp_verify' => 'pending',
                        'installment' => 'pending',
                    ];
                    if (in_array($stage['key'], ['awaiting_approval', 'pay_dp', 'awaiting_dp', 'pay_installment', 'completed'])) {
                        $step_states['booking_fee'] = 'done';
                    }
                    if (in_array($stage['key'], ['pay_dp', 'awaiting_dp', 'pay_installment', 'completed'])) {
                        $step_states['admin_review'] = 'done';
                    }
                    if (in_array($stage['key'], ['awaiting_dp', 'pay_installment', 'completed'])) {
                        $step_states['down_payment'] = 'done';
                    }
                    if (in_array($stage['key'], ['pay_installment', 'completed'])) {
                        $step_states['dp_verify'] = 'done';
                    }
                    if ($stage['key'] === 'completed') {
                        $step_states['installment'] = 'done';
                    }

                    if ($stage['key'] === 'awaiting_approval')
                        $step_states['admin_review'] = 'current';
                    if ($stage['key'] === 'pay_dp')
                        $step_states['down_payment'] = 'current';
                    if ($stage['key'] === 'awaiting_dp')
                        $step_states['dp_verify'] = 'current';
                    if ($stage['key'] === 'pay_installment')
                        $step_states['installment'] = 'current';

                    if (in_array($stage['key'], ['rejected', 'refunded', 'dp_rejected'])) {
                        if ($stage['key'] === 'rejected')
                            $step_states['admin_review'] = 'failed';
                        if ($stage['key'] === 'refunded' || $stage['key'] === 'dp_rejected')
                            $step_states['dp_verify'] = 'failed';
                    }
                    ?>

                    <div class="activity-card">
                        <div class="card-summary" onclick="toggleCard('bk<?= $bid ?>')">
                            <img src="<?= htmlspecialchars($car_image) ?>" class="car-thumb" alt="">
                            <div class="summary-info">
                                <div class="row-top">
                                    <span class="ref"><?= $bref ?></span>
                                    <span class="badge <?= $stage['class'] ?>">
                                        <span class="dot"></span><?= htmlspecialchars($stage['label']) ?>
                                    </span>
                                </div>
                                <div class="car-name"><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></div>
                                <div class="car-meta">
                                    <?= htmlspecialchars($car_year) ?> &middot;
                                    <?= htmlspecialchars($car_variant) ?> &middot;
                                    <?= htmlspecialchars($car_color) ?> &middot;
                                    RM <?= number_format($car_price, 2) ?>
                                </div>
                                <div class="stage-desc"><?= htmlspecialchars($stage['desc']) ?></div>
                            </div>
                            <div class="card-actions">
                                <?php if ($stage['action_label']): ?>
                                    <a href="<?= htmlspecialchars($stage['action_href']) ?>"
                                        class="btn-action <?= $stage['key'] === 'unpaid_bf' ? 'primary-green' : '' ?>"
                                        onclick="event.stopPropagation()">
                                        <?= htmlspecialchars($stage['action_label']) ?> <i class="fas fa-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                                <button class="btn-expand" id="btn_bk<?= $bid ?>"
                                    onclick="event.stopPropagation(); toggleCard('bk<?= $bid ?>')">
                                    <span>Details</span> <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body" id="body_bk<?= $bid ?>">

                            <div class="section">
                                <h4><i class="fas fa-route"></i> Progress</h4>
                                <div class="progress-track">
                                    <div class="progress-fill <?= in_array($stage['key'], ['rejected', 'refunded', 'dp_rejected']) ? 'warn' : '' ?>"
                                        style="width:<?= $stage['progress'] ?>%;"></div>
                                </div>
                                <div style="font-size:11px;color:#64748b;margin-top:4px;"><?= $stage['progress'] ?>% complete</div>

                                <div class="stage-timeline">
                                    <?php
                                    $steps = [
                                        ['booking_fee', 'fa-credit-card', 'Booking Fee'],
                                        ['admin_review', 'fa-clipboard-check', 'Admin Review'],
                                        ['down_payment', 'fa-hand-holding-usd', 'Down Payment'],
                                        ['dp_verify', 'fa-shield-alt', 'DP Verify'],
                                        ['installment', 'fa-calendar-alt', 'Installments'],
                                    ];
                                    foreach ($steps as $s):
                                        [$key, $icon, $label] = $s;
                                        $cls = $step_states[$key];
                                        ?>
                                        <div class="stage-step <?= $cls ?>">
                                            <div class="ic"><i class="fas <?= $icon ?>"></i></div>
                                            <div><?= $label ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="section">
                                <h4><i class="fas fa-receipt"></i> Financial Summary</h4>
                                <div class="detail-grid">
                                    <div class="detail-cell">
                                        <label>Car Price</label>
                                        <p>RM <?= number_format($car_price, 2) ?></p>
                                    </div>
                                    <div class="detail-cell">
                                        <label>Booking Fee</label>
                                        <p style="color:<?= $b['booking_paid_at'] ? '#16a34a' : '#dc2626' ?>;">
                                            RM <?= number_format($booking_fee, 2) ?>             <?= $b['booking_paid_at'] ? '✓' : '' ?>
                                        </p>
                                    </div>
                                    <?php if ($dp_amount > 0): ?>
                                        <div class="detail-cell">
                                            <label>Down Payment</label>
                                            <p style="color:<?= $b['dp_status'] === 'Approved' ? '#16a34a' : '#d97706' ?>;">
                                                RM <?= number_format($dp_amount, 2) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($monthly_amount > 0): ?>
                                        <div class="detail-cell">
                                            <label>Monthly Installment</label>
                                            <p style="color:#2563eb;">RM <?= number_format($monthly_amount, 2) ?></p>
                                        </div>
                                        <div class="detail-cell">
                                            <label>Loan Tenure</label>
                                            <p><?= htmlspecialchars($b['installment_years']) ?> Years @
                                                <?= number_format($b['interest_rate'], 2) ?>%
                                            </p>
                                        </div>
                                        <div class="detail-cell">
                                            <label>Installments Paid</label>
                                            <p><?= $paid_months ?> / <?= $total_months ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-cell">
                                        <label>Total Paid</label>
                                        <p style="color:#16a34a;">RM <?= number_format($total_paid, 2) ?></p>
                                    </div>
                                    <div class="detail-cell">
                                        <label>Loan Principal</label>
                                        <p style="color:#dc2626; margin-bottom: 2px;">RM <?= number_format($remaining, 2) ?></p>
                                        <span style="font-size: 10px; color: #94a3b8; font-weight: normal;">*Excludes
                                            interest</span>
                                    </div>
                                </div>
                            </div>

                            <div class="section">
                                <h4><i class="fas fa-folder-open"></i> Submitted Documents</h4>
                                <div class="doc-row">
                                    <?php
                                    $doc_map = [
                                        ['ic_url', 'IC Document'],
                                        ['driving_license_url', 'Driving Licence'],
                                        ['payslip_url', 'Payslip'],
                                        ['bank_statement_url', 'Bank Statement'],
                                    ];
                                    foreach ($doc_map as $d):
                                        [$col, $label] = $d;
                                        $url = $b[$col] ?? '';
                                        ?>
                                        <?php if (!empty($url)): ?>
                                            <a class="doc-chip" href="<?= htmlspecialchars($url) ?>" target="_blank">
                                                <i class="fas fa-file-pdf"></i> <?= $label ?> <i class="fas fa-external-link-alt"
                                                    style="font-size:10px;color:#94a3b8;"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="doc-chip"><i class="fas fa-times-circle" style="color:#94a3b8;"></i> <span
                                                    class="missing"><?= $label ?> not uploaded</span></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <?php if (!empty($b['insurance_pdf_url'])): ?>
                                        <a class="doc-chip" href="<?= htmlspecialchars($b['insurance_pdf_url']) ?>" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Insurance Cover Note <i class="fas fa-external-link-alt"
                                                style="font-size:10px;color:#94a3b8;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="section">
                                <h4><i class="fas fa-info-circle"></i> Reference Info</h4>
                                <div class="detail-grid">
                                    <div class="detail-cell">
                                        <label>Booking ID</label>
                                        <p style="font-family:monospace;"><?= $bref ?></p>
                                    </div>
                                    <?php if ($b['bf_receipt']): ?>
                                        <div class="detail-cell">
                                            <label>Receipt Number</label>
                                            <p style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($b['bf_receipt']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-cell">
                                        <label>Applied On</label>
                                        <p><?= !empty($b['created_at']) ? date('d M Y', strtotime($b['created_at'])) : '-' ?></p>
                                    </div>
                                    <?php if (!empty($b['plate_number'])): ?>
                                        <div class="detail-cell">
                                            <label>Plate Number</label>
                                            <p style="color:#dc2626;font-family:monospace;"><?= htmlspecialchars($b['plate_number']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($receipt_map[$bid]) || $paid_months > 0): ?>
                                <div class="section">
                                    <h4><i class="fas fa-receipt"></i> Payment Receipts</h4>
                                    <div class="doc-row">
                                        <?php foreach (($receipt_map[$bid] ?? []) as $rc):
                                            $rc_label = $rc['payment_type'] === 'Booking Fee' ? 'Booking Fee' : 'Down Payment';
                                            $rc_date = !empty($rc['payment_date']) ? date('d M Y', strtotime($rc['payment_date'])) : '';
                                            ?>
                                            <a class="doc-chip" href="payment_confirm.php?id=<?= (int) $rc['payment_id'] ?>"
                                                target="_blank">
                                                <i class="fas fa-receipt" style="color:#16a34a;"></i>
                                                <?= $rc_label ?> Receipt — RM <?= number_format($rc['payment_amount'], 2) ?>
                                                <span style="color:#94a3b8;font-size:11px;">(<?= $rc_date ?>)</span>
                                                <i class="fas fa-external-link-alt" style="font-size:10px;color:#94a3b8;"></i>
                                            </a>
                                        <?php endforeach; ?>

                                        <?php if ($paid_months > 0): ?>
                                            <a class="doc-chip" href="monthly_installment.php?id=<?= $bid ?>" target="_blank">
                                                <i class="fas fa-calendar-alt" style="color:#2563eb;"></i>
                                                Installment Receipts (<?= $paid_months ?> paid)
                                                <i class="fas fa-external-link-alt" style="font-size:10px;color:#94a3b8;"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (in_array($stage['key'], ['rejected', 'refunded', 'dp_rejected'])): ?>
                                <div class="section">
                                    <h4><i class="fas fa-exclamation-triangle"></i> Status Reason</h4>
                                    <div class="reason-box"><?= htmlspecialchars($stage['desc']) ?></div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>

        <?php if ($active_tab === 'reservations' || $active_tab === 'history'):
            $res_list = ($active_tab === 'history') ? $history_reservations : $active_reservations; ?>

            <?php if ($active_tab === 'reservations' && count($res_list) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Reservations Yet</h3>
                    <p>You haven't booked any test drives. Schedule one to experience your favourite ride.</p>
                    <a href="cars.php"><i class="fas fa-car"></i> Browse Vehicles</a>
                </div>
            <?php else: ?>

                <?php foreach ($res_list as $r):
                    $snap = json_decode($r['snapshot_data'] ?: '{}', true);
                    if (!is_array($snap))
                        $snap = [];

                    $car_brand = $snap['car_brand'] ?? $r['car_brand'] ?? '';
                    $car_model = $snap['car_model'] ?? $r['car_model'] ?? '';
                    $car_year = $snap['car_year'] ?? $r['car_year'] ?? '';
                    $car_origin = $snap['car_origin'] ?? $r['car_origin'] ?? '';
                    $car_image = $snap['car_image'] ?? $r['car_image_live'] ?? 'https://via.placeholder.com/200x140?text=Vehicle';
                    $car_variant = $snap['car_variant'] ?? '-';
                    $car_color = $snap['car_color'] ?? '-';

                    $rid = (int) $r['reservation_id'];
                    $rref = 'RES' . str_pad($rid, 3, '0', STR_PAD_LEFT);
                    $stage = get_reservation_stage($r);

                    $car_status_live = trim($r['car_status_live'] ?? '');
                    $car_stock_live = (int) ($r['car_stock_live'] ?? 0);
                    $is_used_res = (strcasecmp(trim($car_origin), 'Used Car') === 0);
                    $can_book = (strcasecmp($car_status_live, 'Active') === 0) && ($is_used_res || $car_stock_live > 0);

                    $preferred = !empty($r['preferred_test_drive_at']) ? date('d M Y, h:i A', strtotime($r['preferred_test_drive_at'])) : 'Not specified';
                    $td_at = !empty($r['test_drive_at']) ? date('d M Y, h:i A', strtotime($r['test_drive_at'])) : null;
                    ?>

                    <div class="activity-card">
                        <div class="card-summary" onclick="toggleCard('rs<?= $rid ?>')">
                            <img src="<?= htmlspecialchars($car_image) ?>" class="car-thumb" alt="">
                            <div class="summary-info">
                                <div class="row-top">
                                    <span class="ref"><?= $rref ?></span>
                                    <span class="badge <?= $stage['class'] ?>">
                                        <span class="dot"></span><?= htmlspecialchars($stage['label']) ?>
                                    </span>
                                </div>
                                <div class="car-name"><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></div>
                                <div class="car-meta">
                                    <?= htmlspecialchars($car_year) ?> &middot;
                                    <?= htmlspecialchars($car_variant) ?> &middot;
                                    <?= htmlspecialchars($car_color) ?>
                                </div>
                                <div class="stage-desc"><?= htmlspecialchars($stage['desc']) ?></div>
                            </div>
                            <div class="card-actions">
                                <?php if ($stage['class'] === 'completed' && $can_book): ?>
                                    <a href="start_booking.php?car_id=<?= $r['car_id'] ?>" class="btn-action primary-green"
                                        onclick="event.stopPropagation()">
                                        Book This Car <i class="fas fa-arrow-right"></i>
                                    </a>
                                <?php elseif ($stage['class'] === 'completed' && !$can_book): ?>
                                    <span class="badge neutral" style="white-space:nowrap;">
                                        <span class="dot"></span> No Longer Available
                                    </span>
                                <?php endif; ?>
                                <button class="btn-expand" id="btn_rs<?= $rid ?>"
                                    onclick="event.stopPropagation(); toggleCard('rs<?= $rid ?>')">
                                    <span>Details</span> <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body" id="body_rs<?= $rid ?>">
                            <div class="section">
                                <h4><i class="fas fa-calendar-alt"></i> Reservation Info</h4>
                                <div class="detail-grid">
                                    <div class="detail-cell">
                                        <label>Reservation ID</label>
                                        <p style="font-family:monospace;"><?= $rref ?></p>
                                    </div>
                                    <div class="detail-cell">
                                        <label>Submitted On</label>
                                        <p><?= !empty($r['reservation_created_at']) ? date('d M Y, h:i A', strtotime($r['reservation_created_at'])) : '-' ?>
                                        </p>
                                    </div>
                                    <div class="detail-cell">
                                        <label>Preferred Time</label>
                                        <p><?= htmlspecialchars($preferred) ?></p>
                                    </div>
                                    <?php if ($td_at): ?>
                                        <div class="detail-cell">
                                            <label>Confirmed Time</label>
                                            <p style="color:#16a34a;"><?= htmlspecialchars($td_at) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($r['driving_licence_url'])): ?>
                                <div class="section">
                                    <h4><i class="fas fa-id-card"></i> Driving Licence</h4>
                                    <div class="doc-row">
                                        <a class="doc-chip" href="<?= htmlspecialchars($r['driving_licence_url']) ?>" target="_blank">
                                            <i class="fas fa-file-pdf"></i> View Licence <i class="fas fa-external-link-alt"
                                                style="font-size:10px;color:#94a3b8;"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($stage['class'] === 'rejected'): ?>
                                <div class="section">
                                    <h4><i class="fas fa-exclamation-triangle"></i> Reason</h4>
                                    <div class="reason-box"><?= htmlspecialchars($stage['desc']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
    function toggleCard(id) {
        const body = document.getElementById('body_' + id);
        const btn = document.getElementById('btn_' + id);
        if (!body) return;
        const isOpen = body.classList.contains('open');
        body.classList.toggle('open');
        if (btn) {
            btn.querySelector('i').className = isOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
            btn.querySelector('span').textContent = isOpen ? 'Details' : 'Hide';
        }
    }
</script>

<?php include 'Includes/footer.php'; ?>