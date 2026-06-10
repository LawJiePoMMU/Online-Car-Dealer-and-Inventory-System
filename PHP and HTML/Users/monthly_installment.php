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
$booking_id = intval($_GET['id'] ?? $_POST['booking_id'] ?? 0);
$pay_inst_id = intval($_GET['pay'] ?? $_POST['installment_id'] ?? 0);

function show_error_page($title, $message, $btn_text = 'View My Activity', $btn_href = 'view_status.php')
{
    include 'Includes/header.php';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">';
    echo '<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:#f8fafc;">
        <div style="max-width:480px;background:#fff;border:1px solid #f1f5f9;border-radius:18px;padding:40px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,0.05);">
            <div style="width:70px;height:70px;background:#fef3c7;color:#d97706;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 20px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="color:#1e293b;font-size:22px;margin-bottom:10px;">' . htmlspecialchars($title) . '</h2>
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:24px;">' . htmlspecialchars($message) . '</p>
            <a href="' . htmlspecialchars($btn_href) . '" style="display:inline-block;background:#1e293b;color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;">' . htmlspecialchars($btn_text) . '</a>
        </div>
    </div>';
    include 'Includes/footer.php';
    exit;
}

if ($booking_id <= 0) {
    show_error_page('Invalid Booking', 'No valid booking was specified.');
}

mysqli_query(
    $conn,
    "UPDATE monthly_installments
     SET payment_status = 'Overdue',
         overdue_days   = DATEDIFF(CURDATE(), due_date)
     WHERE booking_id = $booking_id
       AND payment_status = 'Pending'
       AND due_date < CURDATE()"
);


$sql = "
SELECT
    b.*,
    c.car_brand, c.car_model, c.car_year, c.car_origin,
    cs.car_status_price AS car_price_live,
    (SELECT car_image_url FROM car_image WHERE car_id = b.car_id LIMIT 1) AS car_image_live,
    dp.dp_amount, dp.dp_status, dp.insurance_fee, dp.plate_registration_fee
FROM bookings b
LEFT JOIN cars c ON c.car_id = b.car_id
LEFT JOIN car_status cs ON cs.car_id = b.car_id
LEFT JOIN down_payments dp ON dp.booking_id = b.booking_id
WHERE b.booking_id = ? AND b.user_id = ?
LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) {
    show_error_page(
        'Unauthorized Access',
        'This booking does not belong to your account, or it no longer exists.'
    );
}

if (($booking['dp_status'] ?? '') !== 'Approved') {
    show_error_page(
        'Installments Not Available',
        'Your down payment must be approved by admin before installments are generated.'
    );
}


$inst_q = mysqli_query(
    $conn,
    "SELECT * FROM monthly_installments
     WHERE booking_id = $booking_id
     ORDER BY installment_number ASC"
);
$installments = [];
while ($r = mysqli_fetch_assoc($inst_q))
    $installments[] = $r;

if (count($installments) === 0) {
    show_error_page(
        'No Installments Generated',
        'Your monthly installment schedule has not been generated yet. Please contact support.'
    );
}

$inst_payment_map = [];
$pay_q = mysqli_query(
    $conn,
    "SELECT payment_id, receipt_number FROM payments
     WHERE reference_id = $booking_id
       AND payment_type = 'Monthly Installment'
       AND payment_status = 'Paid'"
);
if ($pay_q) {
    while ($p = mysqli_fetch_assoc($pay_q)) {
        $rn = $p['receipt_number'] ?? '';
        $iid = (int) substr($rn, strrpos($rn, '-') + 1);
        if ($iid > 0)
            $inst_payment_map[$iid] = (int) $p['payment_id'];
    }
}

$snap = json_decode($booking['snapshot_data'] ?: '{}', true);
if (!is_array($snap))
    $snap = [];

$car_brand = $snap['car_brand'] ?? $booking['car_brand'] ?? '';
$car_model = $snap['car_model'] ?? $booking['car_model'] ?? '';
$car_year = $snap['car_year'] ?? $booking['car_year'] ?? '';
$car_origin = $snap['car_origin'] ?? $booking['car_origin'] ?? '';
$car_image = $snap['car_image'] ?? $booking['car_image_live'] ?? 'https://via.placeholder.com/600x400.png?text=Vehicle';
$car_variant = $snap['car_variant'] ?? '-';
$car_color = $snap['car_color'] ?? '-';
$car_price = floatval($snap['car_price'] ?? $booking['car_price_live'] ?? 0);

$total_months = count($installments);
$paid_months = 0;
$overdue_months = 0;
$paid_amount = 0.0;
$total_amount = 0.0;
$next_due_inst = null;

foreach ($installments as $i) {
    $amt = floatval($i['monthly_amount']);
    $total_amount += $amt;
    if ($i['payment_status'] === 'Paid') {
        $paid_months++;
        $paid_amount += $amt;
    }
    if ($i['payment_status'] === 'Overdue')
        $overdue_months++;
    if (
        $next_due_inst === null
        && in_array($i['payment_status'], ['Pending', 'Overdue'])
    ) {
        $next_due_inst = $i;
    }
}
$remaining_amount = $total_amount - $paid_amount;
$progress_pct = $total_months > 0 ? round(($paid_months / $total_months) * 100) : 0;

$is_blacklisted = false;
foreach ($installments as $i) {
    if ((int) $i['overdue_days'] >= 21) {
        $is_blacklisted = true;
        break;
    }
}

$errors = [];
$pay_mode = false;
$pay_inst = null;

if ($pay_inst_id > 0) {
    foreach ($installments as $i) {
        if ((int) $i['installment_id'] === $pay_inst_id) {
            $pay_inst = $i;
            break;
        }
    }
    if ($pay_inst && $pay_inst['payment_status'] !== 'Paid' && !$is_blacklisted) {
        $pay_mode = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment']) && $pay_inst) {

    // Card validation
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_name = trim($_POST['card_name'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = preg_replace('/\D/', '', $_POST['card_cvv'] ?? '');

    if (strlen($card_number) !== 16)
        $errors[] = "Card number must be 16 digits.";
    if (empty($card_name) || !preg_match('/^[A-Za-z\s\.\-]{2,}$/', $card_name)) {
        $errors[] = "Valid name on card is required.";
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_expiry, $m)) {
        $errors[] = "Card expiry must be MM/YY.";
    } else {
        $em = (int) $m[1];
        $ey = 2000 + (int) $m[2];
        $ny = (int) date('Y');
        $nm = (int) date('n');
        if ($ey < $ny || ($ey === $ny && $em < $nm))
            $errors[] = "Card has expired.";
    }
    if (strlen($card_cvv) !== 3)
        $errors[] = "CVV must be 3 digits.";

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {
            $inst_amt = floatval($pay_inst['monthly_amount']);
            $inst_num = (int) $pay_inst['installment_number'];
            $inst_id_v = (int) $pay_inst['installment_id'];
            $receipt_num = 'INS-' . date('Ymd') . '-' . str_pad($inst_id_v, 6, '0', STR_PAD_LEFT);
            $pay_ref = 'TXN-' . strtoupper(bin2hex(random_bytes(5)));
            $last4 = substr($card_number, -4);
            $remarks = sprintf(
                'Monthly Installment #%d/%d (Card ending **** %s)',
                $inst_num,
                $total_months,
                $last4
            );

            $upd = mysqli_prepare(
                $conn,
                "UPDATE monthly_installments
                 SET payment_status = 'Paid',
                     paid_at        = NOW(),
                     overdue_days   = 0
                 WHERE installment_id = ? AND booking_id = ?"
            );
            mysqli_stmt_bind_param($upd, "ii", $inst_id_v, $booking_id);
            if (!mysqli_stmt_execute($upd)) {
                throw new Exception('Installment update failed: ' . mysqli_stmt_error($upd));
            }
            mysqli_stmt_close($upd);

            $ins = mysqli_prepare(
                $conn,
                "INSERT INTO payments
                 (payment_type, reference_id, payment_amount, payment_status,
                  receipt_number, payment_reference, remarks, payment_date, created_at)
                 VALUES ('Monthly Installment', ?, ?, 'Paid', ?, ?, ?, NOW(), NOW())"
            );
            mysqli_stmt_bind_param(
                $ins,
                "idsss",
                $booking_id,
                $inst_amt,
                $receipt_num,
                $pay_ref,
                $remarks
            );
            if (!mysqli_stmt_execute($ins)) {
                throw new Exception('Payment insert failed: ' . mysqli_stmt_error($ins));
            }
            $payment_id = mysqli_insert_id($conn);
            mysqli_stmt_close($ins);

            mysqli_commit($conn);

            header("Location: payment_confirm.php?id=" . $payment_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Payment processing failed. Please try again.";
        }
    }
}

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
    .mi-page {
        background: #f8fafc;
        min-height: calc(100vh - 80px);
        padding: 40px 20px 60px;
    }

    .mi-wrapper {
        max-width: 1100px;
        margin: 0 auto;
    }

    .page-heading {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
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

    .back-btn {
        background: #fff;
        border: 1.5px solid #e2e8f0;
        color: #1e293b;
        padding: 10px 18px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
    }

    .back-btn:hover {
        background: #f8fafc;
        border-color: #1e293b;
    }

    .mi-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
        padding: 24px;
        margin-bottom: 20px;
    }

    .vehicle-banner {
        display: grid;
        grid-template-columns: 160px 1fr auto;
        gap: 20px;
        align-items: center;
    }

    @media(max-width:700px) {
        .vehicle-banner {
            grid-template-columns: 1fr;
            text-align: center;
        }
    }

    .vehicle-banner img {
        width: 160px;
        height: 110px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .vehicle-banner .name {
        font-size: 18px;
        color: #0f172a;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .vehicle-banner .meta {
        font-size: 12px;
        color: #64748b;
    }

    .vehicle-banner .book-id {
        background: #1e293b;
        color: #fff;
        padding: 6px 14px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 13px;
        font-weight: 700;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    @media(max-width:780px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .stat-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .stat-card .lbl {
        font-size: 10px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .stat-card .val {
        font-size: 20px;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: -0.5px;
    }

    .stat-card.success .val {
        color: #16a34a;
    }

    .stat-card.danger .val {
        color: #dc2626;
    }

    .stat-card.info .val {
        color: #2563eb;
    }

    .stat-card .sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 4px;
    }

    /* Progress bar */
    .progress-track {
        background: #e2e8f0;
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
        margin: 10px 0 4px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #1e293b 0%, #16a34a 100%);
        border-radius: 999px;
        transition: width 0.5s;
    }

    .progress-fill.warn {
        background: linear-gradient(90deg, #f59e0b 0%, #dc2626 100%);
    }

    .blacklist-warning {
        background: #fef2f2;
        border: 2px solid #fecaca;
        border-left: 5px solid #dc2626;
        padding: 18px 22px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .blacklist-warning i {
        font-size: 28px;
        color: #dc2626;
    }

    .blacklist-warning .ti {
        font-size: 15px;
        font-weight: 700;
        color: #991b1b;
        margin-bottom: 4px;
    }

    .blacklist-warning .ds {
        font-size: 13px;
        color: #7f1d1d;
        line-height: 1.5;
    }

    .installments-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
    }

    .installments-card .head {
        padding: 18px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .installments-card .head h3 {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .inst-table {
        width: 100%;
        border-collapse: collapse;
    }

    .inst-table th {
        background: #f8fafc;
        padding: 12px 18px;
        text-align: left;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
    }

    .inst-table td {
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        color: #1e293b;
    }

    .inst-table tr.next-due {
        background: #fffbeb;
    }

    .inst-table tr.next-due td {
        border-bottom-color: #fde68a;
    }

    .inst-table tr.is-paid td {
        color: #94a3b8;
    }

    .inst-table tr:hover:not(.is-paid) {
        background: #f8fafc;
    }

    .inst-num {
        background: #1e293b;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
    }

    .inst-amount {
        font-family: monospace;
        font-weight: 700;
        color: #1e293b;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge.paid {
        background: #dcfce7;
        color: #166534;
    }

    .badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .badge.overdue {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-pay-row {
        background: #16a34a;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: 'Poppins', sans-serif;
        transition: 0.2s;
    }

    .btn-pay-row:hover {
        background: #15803d;
        transform: translateY(-1px);
    }

    .btn-pay-row.danger {
        background: #dc2626;
    }

    .btn-pay-row.danger:hover {
        background: #991b1b;
    }

    .payment-modal-bg {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        display: flex;
        align-items: flex-start;
        justify-content: center;
        z-index: 1000;
        padding: 30px 20px;
        overflow-y: auto;
    }

    .payment-modal {
        background: #fff;
        border-radius: 18px;
        max-width: 600px;
        width: 100%;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        margin: auto;
    }

    .payment-modal .pm-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff;
        padding: 24px;
        position: relative;
    }

    .payment-modal .pm-header h2 {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .payment-modal .pm-header p {
        font-size: 12px;
        opacity: 0.75;
    }

    .payment-modal .pm-close {
        position: absolute;
        top: 18px;
        right: 18px;
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .payment-modal .pm-close:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .payment-modal .pm-body {
        padding: 24px;
    }

    .pay-amount-display {
        background: #fef3c7;
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pay-amount-display .lbl {
        font-size: 11px;
        color: #92400e;
        font-weight: 600;
        text-transform: uppercase;
    }

    .pay-amount-display .num-disp {
        font-size: 11px;
        color: #78350f;
    }

    .pay-amount-display .amt {
        font-size: 22px;
        font-weight: 800;
        color: #dc2626;
    }

    .card-visual {
        background: linear-gradient(135deg, #334155 0%, #1e293b 50%, #0f172a 100%);
        color: #fff;
        border-radius: 14px;
        padding: 20px;
        height: 170px;
        position: relative;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
        margin-bottom: 18px;
        overflow: hidden;
    }

    .card-visual::before {
        content: "";
        position: absolute;
        top: -50%;
        right: -30%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.06) 0%, transparent 70%);
    }

    .card-visual .brand {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 18px;
        font-weight: 800;
        font-style: italic;
    }

    .card-visual .chip {
        width: 40px;
        height: 30px;
        background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%);
        border-radius: 5px;
        margin-bottom: 22px;
    }

    .card-visual .num {
        font-family: 'Courier New', monospace;
        font-size: 18px;
        letter-spacing: 2px;
        font-weight: 600;
        margin-bottom: 16px;
    }

    .card-visual .row-bottom {
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.85;
    }

    .card-visual .row-bottom .v {
        font-size: 12px;
        letter-spacing: 1px;
        margin-top: 2px;
        opacity: 1;
        text-transform: none;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .form-row.single {
        grid-template-columns: 1fr;
    }

    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .form-input {
        width: 100%;
        padding: 11px 13px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        background: #fff;
        color: #1e293b;
        outline: none;
    }

    .form-input:focus {
        border-color: #1e293b;
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.08);
    }

    .form-input.mono {
        font-family: 'Courier New', monospace;
        letter-spacing: 1.5px;
    }

    .btn-pay {
        width: 100%;
        background: #1e293b;
        color: #fff;
        border: none;
        padding: 14px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        transition: 0.2s;
        margin-top: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-pay:hover:not(:disabled) {
        background: #0f172a;
    }

    .btn-pay:disabled {
        opacity: 0.7;
        cursor: wait;
    }

    .error-box {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 14px;
    }

    .error-box p {
        margin: 2px 0;
        font-size: 12px;
        font-weight: 500;
    }

    .error-box p::before {
        content: "\f071";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        margin-right: 6px;
    }
</style>

<div class="mi-page">
    <div class="mi-wrapper">

        <div class="page-heading">
            <div>
                <h1>Monthly Installments</h1>
                <p>Track your loan repayment schedule and pay your next installment here.</p>
            </div>
            <a href="view_status.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to My Activity
            </a>
        </div>

        <div class="mi-card">
            <div class="vehicle-banner">
                <img src="<?= htmlspecialchars($car_image) ?>" alt="Vehicle">
                <div>
                    <div class="name"><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></div>
                    <div class="meta">
                        <?= htmlspecialchars($car_year) ?> &middot;
                        <?= htmlspecialchars($car_variant) ?> &middot;
                        <?= htmlspecialchars($car_color) ?> &middot;
                        <?= htmlspecialchars($car_origin) ?>
                    </div>
                    <div class="meta" style="margin-top:6px;">
                        <strong>Loan:</strong> <?= htmlspecialchars($booking['installment_years']) ?> Years @
                        <?= number_format($booking['interest_rate'], 2) ?>% p.a.
                    </div>
                </div>
                <div class="book-id">BK<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <?php if ($is_blacklisted): ?>
            <div class="blacklist-warning">
                <i class="fas fa-user-slash"></i>
                <div>
                    <div class="ti">Account Blacklisted</div>
                    <div class="ds">One or more of your installments is overdue by 21 days or more. Please contact our
                        finance department immediately to resolve your account.</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="lbl"><i class="fas fa-percentage"></i> Progress</div>
                <div class="val"><?= $progress_pct ?>%</div>
                <div class="progress-track" style="margin-top:8px;">
                    <div class="progress-fill <?= $overdue_months > 0 ? 'warn' : '' ?>"
                        style="width:<?= $progress_pct ?>%"></div>
                </div>
                <div class="sub" style="margin-top:6px;"><?= $paid_months ?> of <?= $total_months ?> paid</div>
            </div>

            <div class="stat-card success">
                <div class="lbl"><i class="fas fa-check-circle"></i> Total Paid</div>
                <div class="val">RM <?= number_format($paid_amount, 2) ?></div>
                <div class="sub"><?= $paid_months ?> month<?= $paid_months > 1 ? 's' : '' ?></div>
            </div>

            <div class="stat-card info">
                <div class="lbl"><i class="fas fa-coins"></i> Total Outstanding</div>
                <div class="val">RM <?= number_format($remaining_amount, 2) ?></div>
                <div class="sub"><?= $total_months - $paid_months ?>
                    month<?= ($total_months - $paid_months) > 1 ? 's' : '' ?> left</div>
                <span style="font-size: 10px; color: #94a3b8; font-weight: normal;">*Includes
                    interest</span>
            </div>

            <div class="stat-card <?= $overdue_months > 0 ? 'danger' : '' ?>">
                <div class="lbl"><i class="fas fa-exclamation-circle"></i> Overdue</div>
                <div class="val"><?= $overdue_months ?></div>
                <div class="sub"><?= $overdue_months > 0 ? 'months past due' : 'all up to date' ?></div>
            </div>
        </div>

        <div class="installments-card">
            <div class="head">
                <h3><i class="fas fa-calendar-alt"></i> Repayment Schedule</h3>
                <?php if ($next_due_inst && !$is_blacklisted): ?>
                    <div style="font-size:12px;color:#64748b;">
                        Next due: <strong
                            style="color:#1e293b;"><?= date('d M Y', strtotime($next_due_inst['due_date'])) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
            <table class="inst-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Paid On</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $next_pending_seen = false;
                    foreach ($installments as $i):
                        $status = $i['payment_status'];
                        $is_paid = ($status === 'Paid');
                        $is_overdue = ($status === 'Overdue');
                        $overdue_days = (int) $i['overdue_days'];
                        $is_next = (!$is_paid && !$next_pending_seen);
                        if ($is_next)
                            $next_pending_seen = true;

                        $row_class = '';
                        if ($is_paid)
                            $row_class = 'is-paid';
                        elseif ($is_next)
                            $row_class = 'next-due';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><span class="inst-num"><?= $i['installment_number'] ?></span></td>
                            <td><?= date('d M Y', strtotime($i['due_date'])) ?></td>
                            <td class="inst-amount">RM <?= number_format($i['monthly_amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= strtolower($status) ?>">
                                    <?= $status ?>
                                    <?php if ($is_overdue && $overdue_days > 0): ?>
                                        · <?= $overdue_days ?>d
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><?= !empty($i['paid_at']) ? date('d M Y', strtotime($i['paid_at'])) : '—' ?></td>
                            <td style="text-align:right;">
                                <?php if ($is_paid): ?>
                                    <?php $pid = $inst_payment_map[(int) $i['installment_id']] ?? 0; ?>
                                    <?php if ($pid): ?>
                                        <a href="payment_confirm.php?id=<?= $pid ?>" target="_blank" class="btn-pay-row"
                                            style="background:#1e293b;">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </a>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle" style="color:#16a34a;"></i>
                                    <?php endif; ?>
                                <?php elseif ($is_blacklisted): ?>
                                    <span style="color:#94a3b8;font-size:11px;">Contact support</span>
                                <?php elseif ($is_next): ?>
                                    <a href="?id=<?= $booking_id ?>&pay=<?= $i['installment_id'] ?>"
                                        class="btn-pay-row <?= $is_overdue ? 'danger' : '' ?>">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </a>
                                <?php else: ?>
                                    <span style="color:#cbd5e1;font-size:11px;">Awaiting prior</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php if ($pay_mode): ?>
    <div class="payment-modal-bg" id="paymentModal">
        <div class="payment-modal">
            <div class="pm-header">
                <h2><i class="fas fa-credit-card"></i> Pay Installment #<?= $pay_inst['installment_number'] ?> of
                    <?= $total_months ?>
                </h2>
                <p>Due: <?= date('d M Y', strtotime($pay_inst['due_date'])) ?></p>
                <a href="?id=<?= $booking_id ?>" class="pm-close" title="Close">
                    <i class="fas fa-times"></i>
                </a>
            </div>

            <div class="pm-body">

                <div class="pay-amount-display">
                    <div>
                        <div class="lbl">Amount Due</div>
                        <div class="num-disp">Installment #<?= $pay_inst['installment_number'] ?></div>
                    </div>
                    <div class="amt">RM <?= number_format($pay_inst['monthly_amount'], 2) ?></div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-box">
                        <?php foreach ($errors as $err): ?>
                            <p><?= htmlspecialchars($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card-visual">
                    <div class="brand">VISA</div>
                    <div class="chip"></div>
                    <div class="num" id="cardVisualNum">•••• •••• •••• ••••</div>
                    <div class="row-bottom">
                        <div>
                            Card Holder
                            <div class="v" id="cardVisualName">YOUR NAME</div>
                        </div>
                        <div>
                            Expires
                            <div class="v" id="cardVisualExp">MM/YY</div>
                        </div>
                    </div>
                </div>

                <form method="POST" autocomplete="off" id="payForm">
                    <input type="hidden" name="confirm_payment" value="1">
                    <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                    <input type="hidden" name="installment_id" value="<?= $pay_inst['installment_id'] ?>">

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Card Number</label>
                            <input type="text" name="card_number" id="card_number" class="form-input mono"
                                inputmode="numeric" placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label>Name on Card</label>
                            <input type="text" name="card_name" id="card_name" class="form-input" placeholder="JOHN DOE"
                                style="text-transform:uppercase;" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry (MM/YY)</label>
                            <input type="text" name="card_expiry" id="card_expiry" class="form-input mono"
                                inputmode="numeric" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" name="card_cvv" id="card_cvv" class="form-input mono" inputmode="numeric"
                                placeholder="•••" maxlength="3" required>
                        </div>
                    </div>

                    <button type="submit" name="confirm_payment" value="1" class="btn-pay" id="btnPay">
                        <i class="fas fa-shield-alt"></i>
                        Pay RM <?= number_format($pay_inst['monthly_amount'], 2) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const AMOUNT_TEXT = 'RM <?= number_format($pay_inst['monthly_amount'], 2) ?>';
        const INST_NUM = <?= $pay_inst['installment_number'] ?>;

        const num = document.getElementById('card_number');
        const name = document.getElementById('card_name');
        const exp = document.getElementById('card_expiry');
        const cvv = document.getElementById('card_cvv');
        const vNum = document.getElementById('cardVisualNum');
        const vName = document.getElementById('cardVisualName');
        const vExp = document.getElementById('cardVisualExp');

        num.addEventListener('input', e => {
            let v = e.target.value.replace(/\D/g, '').substring(0, 16);
            e.target.value = v.replace(/(.{4})/g, '$1 ').trim();
            let disp = (v + '••••••••••••••••').substring(0, 16);
            vNum.textContent = disp.match(/.{1,4}/g).join(' ');
        });
        name.addEventListener('input', e => {
            let v = e.target.value.replace(/[^A-Za-z\s\.\-]/g, '').toUpperCase();
            e.target.value = v;
            vName.textContent = v.trim() || 'YOUR NAME';
        });
        exp.addEventListener('input', e => {
            let v = e.target.value.replace(/\D/g, '').substring(0, 4);
            if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
            e.target.value = v;
            vExp.textContent = v.length === 0 ? 'MM/YY' : v;
        });
        cvv.addEventListener('input', e => {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });

        const form = document.getElementById('payForm');
        const btn = document.getElementById('btnPay');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const rawNum = num.value.replace(/\s/g, '');
            if (rawNum.length !== 16) {
                Swal.fire({ icon: 'error', title: 'Invalid Card', text: 'Card number must be 16 digits.' });
                return;
            }
            if (!/^[A-Za-z\s\.\-]{2,}$/.test(name.value.trim())) {
                Swal.fire({ icon: 'error', title: 'Invalid Name', text: 'Please enter the name on card.' });
                return;
            }
            if (!/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(exp.value)) {
                Swal.fire({ icon: 'error', title: 'Invalid Expiry', text: 'Expiry must be MM/YY.' });
                return;
            }
            const [mm, yy] = exp.value.split('/').map(s => parseInt(s, 10));
            const ny = new Date().getFullYear(), nm = new Date().getMonth() + 1;
            if ((2000 + yy) < ny || ((2000 + yy) === ny && mm < nm)) {
                Swal.fire({ icon: 'error', title: 'Card Expired', text: 'Please use a valid card.' });
                return;
            }
            if (cvv.value.length !== 3) {
                Swal.fire({ icon: 'error', title: 'Invalid CVV', text: 'CVV must be 3 digits.' });
                return;
            }

            const last4 = rawNum.slice(-4);
            const r = await Swal.fire({
                icon: 'question',
                title: `Confirm Installment #${INST_NUM} Payment`,
                html: `
            <div style="text-align:left;font-size:13px;">
              <div style="background:#f8fafc;padding:14px;border-radius:8px;margin:12px 0;border:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                  <span style="color:#64748b;">Amount</span>
                  <strong style="color:#dc2626;font-size:16px;">${AMOUNT_TEXT}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;">
                  <span>Card</span>
                  <span style="font-family:monospace;">**** **** **** ${last4}</span>
                </div>
              </div>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: 'Yes, Pay Now',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#1e293b',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true
            });

            if (!r.isConfirmed) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            Swal.fire({
                title: 'Processing Payment',
                html: `
            <div style="margin:18px 0;">
              <i class="fas fa-credit-card" style="font-size:36px;color:#1e293b;margin-bottom:10px;"></i>
              <p style="color:#64748b;font-size:13px;">Connecting to bank...<br>Please do not close this window.</p>
            </div>
        `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            setTimeout(() => { form.submit(); }, 2000);
        });

        // Close modal on background click
        document.getElementById('paymentModal').addEventListener('click', function (e) {
            if (e.target === this) {
                window.location.href = '?id=<?= $booking_id ?>';
            }
        });
    </script>
<?php endif; ?>

<?php include 'Includes/footer.php'; ?>