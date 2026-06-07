<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

// ======================================================
// SESSION CHECK
// ======================================================
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

// ======================================================
// RESOLVE PAYMENT ID (URL first, then session)
// ======================================================
$payment_id = intval($_GET['id'] ?? $_SESSION['pay_id'] ?? 0);

function show_error_page($title, $message, $btn_text = 'Return Home', $btn_href = 'index.php')
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

if ($payment_id <= 0) {
    show_error_page('Receipt Not Found', 'No valid payment reference was provided. Your receipt link may have expired.');
}

// ======================================================
// 1. FETCH PAYMENT
// ======================================================
$sql_payment = "
SELECT 
    payment_id, payment_type, reference_id, payment_amount, 
    payment_status, receipt_number, payment_reference, 
    remarks, payment_date
FROM payments 
WHERE payment_id = ?
LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql_payment);
mysqli_stmt_bind_param($stmt, "i", $payment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$data) {
    show_error_page('Receipt Not Found', 'No valid payment record was found.');
}

$payment_type = $data['payment_type'] ?? 'Payment';
$reference_id = (int) $data['reference_id'];

$sql_booking = "
SELECT user_id, booking_status, installment_years, interest_rate, snapshot_data 
FROM bookings 
WHERE booking_id = ?
";
$stmt = mysqli_prepare($conn, $sql_booking);
mysqli_stmt_bind_param($stmt, "i", $reference_id);
mysqli_stmt_execute($stmt);
$booking_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking_data || $booking_data['user_id'] != $user_id) {
    show_error_page('Unauthorized Receipt', 'This payment record does not belong to your account or could not be found.');
}

$data['booking_status'] = $booking_data['booking_status'];
$data['installment_years'] = $booking_data['installment_years'];
$data['interest_rate'] = $booking_data['interest_rate'];

if ($payment_type === 'Monthly Installment') {
    $data['booking_status'] = 'Active (Installment)';
}

$snap = json_decode($booking_data['snapshot_data'] ?: '{}', true);
if (!is_array($snap))
    $snap = [];

$car_brand = $snap['car_brand'] ?? 'Unknown Brand';
$car_model = $snap['car_model'] ?? 'Unknown Model';
$car_year = $snap['car_year'] ?? 'N/A';
$car_origin = $snap['car_origin'] ?? 'N/A';
$car_variant = $snap['car_variant'] ?? '-';
$car_color = $snap['car_color'] ?? '-';
$car_price = floatval($snap['car_price'] ?? 0);
$car_image = !empty($snap['car_image']) ? $snap['car_image'] : 'https://via.placeholder.com/600x400.png?text=Vehicle';
$user_name = $snap['user_name'] ?? 'Customer';

$booking_id = $reference_id;
$payment_amount = floatval($data['payment_amount']);
$payment_status = $data['payment_status'] ?? 'Paid';
$receipt_number = $data['receipt_number'] ?: 'N/A';
$payment_ref = $data['payment_reference'] ?: '';
$remarks = $data['remarks'] ?: '';
$booking_status = $data['booking_status'] ?? 'Pending';
$loan_years = (int) ($data['installment_years'] ?: 5);
$loan_rate = floatval($data['interest_rate']);

$payment_date = 'N/A';
if (!empty($data['payment_date'])) {
    $ts = strtotime($data['payment_date']);
    if ($ts)
        $payment_date = date('d M Y, h:i A', $ts);
}

$booking_ref = 'BK' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
$status_slug = strtolower(str_replace(' ', '-', $booking_status));

$approval_message = '';
if ($payment_type === 'Booking Fee') {
    $approval_message = 'Your booking fee has been received successfully. Our financing team will review your submitted documents and contact you within 1-2 business days regarding loan approval and the next steps.';
} elseif ($payment_type === 'Down Payment') {
    $approval_message = 'Your down payment has been received. The financing team will verify your insurance cover note before generating your monthly installment schedule.';
} elseif ($payment_type === 'Monthly Installment') {
    $approval_message = 'Your monthly installment has been recorded. You can view your remaining installments and next due date in your installment schedule.';
}

$type_views = [
    'Booking Fee' => ['title' => 'Booking Fee Received', 'icon' => 'fa-file-signature', 'sub' => 'Your RM 500 booking fee is confirmed.'],
    'Down Payment' => ['title' => 'Down Payment Received', 'icon' => 'fa-hand-holding-usd', 'sub' => 'Your down payment is confirmed.'],
    'Monthly Installment' => ['title' => 'Installment Paid', 'icon' => 'fa-calendar-check', 'sub' => 'Your monthly installment is confirmed.'],
];
$tv = $type_views[$payment_type] ?? ['title' => 'Payment Successful', 'icon' => 'fa-check', 'sub' => 'Your transaction has been processed successfully.'];

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
    .success-page {
        background: #f8fafc;
        min-height: calc(100vh - 80px);
        padding: 40px 20px 60px;
    }

    .success-container {
        max-width: 920px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid #f1f5f9;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.05);
    }

    .success-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        padding: 40px;
        text-align: center;
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: #dcfce7;
        color: #16a34a;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        margin: 0 auto 18px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .success-header h1 {
        font-size: 30px;
        font-weight: 700;
        margin-bottom: 10px;
        letter-spacing: -0.5px;
    }

    .success-header p {
        opacity: 0.85;
        font-size: 14px;
        max-width: 580px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .content-wrapper {
        padding: 32px;
    }

    .amount-paid-box {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 1px solid #bbf7d0;
        padding: 24px;
        border-radius: 14px;
        text-align: center;
        margin-bottom: 26px;
    }

    .amount-paid-box .lbl {
        color: #15803d;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.8px;
        margin-bottom: 6px;
    }

    .amount-paid-box .amt {
        color: #15803d;
        font-size: 42px;
        font-weight: 800;
        letter-spacing: -1px;
    }

    .amount-paid-box .typ {
        color: #16a34a;
        font-size: 13px;
        font-weight: 600;
        margin-top: 4px;
    }

    .reference-box {
        background: #f8fafc;
        border: 1.5px dashed #cbd5e1;
        padding: 18px 22px;
        border-radius: 12px;
        margin-bottom: 26px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .reference-box .col-lbl {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.6px;
        margin-bottom: 4px;
    }

    .reference-box .col-val {
        font-size: 18px;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: 1px;
        font-family: 'Courier New', monospace;
    }

    .status-badge {
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-paid {
        background: #dcfce7;
        color: #166534;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-failed {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-refunded {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Sections */
    .detail-section {
        margin-bottom: 26px;
    }

    .detail-section h3 {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .detail-section h3 i {
        color: #1e293b;
        font-size: 14px;
    }

    .vehicle-section {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 22px;
        align-items: flex-start;
    }

    @media(max-width:700px) {
        .vehicle-section {
            grid-template-columns: 1fr;
        }
    }

    .vehicle-section img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .vehicle-section h2 {
        font-size: 22px;
        color: #0f172a;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .vehicle-section .origin-tag {
        display: inline-block;
        background: #f1f5f9;
        color: #64748b;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .vehicle-section .price {
        font-size: 20px;
        font-weight: 800;
        color: #dc2626;
        margin-top: 6px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .info-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 14px 16px;
        border-radius: 10px;
    }

    .info-card label {
        display: block;
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .info-card p {
        font-size: 14px;
        color: #1e293b;
        font-weight: 600;
        word-break: break-word;
    }

    .info-card.mono p {
        font-family: 'Courier New', monospace;
        font-size: 13px;
    }

    /* Note */
    .note-box {
        background: #eff6ff;
        border-left: 4px solid #2563eb;
        padding: 16px 20px;
        border-radius: 8px;
        margin: 24px 0;
    }

    .note-box p {
        font-size: 13px;
        color: #1e3a8a;
        line-height: 1.7;
        margin: 0;
    }

    .note-box p strong {
        color: #1d4ed8;
    }

    .button-group {
        display: flex;
        gap: 12px;
        margin-top: 18px;
        flex-wrap: wrap;
    }

    .action-btn {
        flex: 1;
        min-width: 200px;
        text-align: center;
        padding: 14px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .primary-btn {
        background: #1e293b;
        color: white;
        box-shadow: 0 4px 6px -1px rgba(30, 41, 59, 0.15);
    }

    .primary-btn:hover {
        background: #0f172a;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(30, 41, 59, 0.2);
    }

    .secondary-btn {
        background: #ffffff;
        color: #1e293b;
        border: 1.5px solid #cbd5e1;
    }

    .secondary-btn:hover {
        background: #f8fafc;
        border-color: #1e293b;
    }

    @media print {
        body {
            background: #fff;
        }

        .button-group,
        .topbar,
        nav,
        header,
        footer,
        .footer,
        .site-footer {
            display: none !important;
        }

        .success-container {
            box-shadow: none;
            border: 1px solid #ddd;
        }
    }
</style>

<div class="success-page">
    <div class="success-container">

        <div class="success-header">
            <div class="success-icon"><i class="fas <?= $tv['icon'] ?>"></i></div>
            <h1><?= htmlspecialchars($tv['title']) ?></h1>
            <p>
                Thank you, <?= htmlspecialchars($user_name) ?>.
                <?= htmlspecialchars($tv['sub']) ?>
                Save this receipt for your records.
            </p>
        </div>

        <div class="content-wrapper">

            <div class="amount-paid-box">
                <div class="lbl">Amount Paid</div>
                <div class="amt">RM <?= number_format($payment_amount, 2) ?></div>
                <div class="typ"><?= htmlspecialchars($payment_type) ?></div>
            </div>

            <!-- Reference numbers -->
            <div class="reference-box">
                <div>
                    <div class="col-lbl">Receipt Number</div>
                    <div class="col-val"><?= htmlspecialchars($receipt_number) ?></div>
                </div>
                <div>
                    <div class="col-lbl">Booking Reference</div>
                    <div class="col-val"><?= htmlspecialchars($booking_ref) ?></div>
                </div>
                <span class="status-badge status-<?= htmlspecialchars(strtolower($payment_status)) ?>">
                    <?= htmlspecialchars($payment_status) ?>
                </span>
            </div>

            <!-- Vehicle -->
            <div class="detail-section">
                <h3><i class="fas fa-car"></i> Vehicle Information</h3>
                <div class="vehicle-section">
                    <img src="<?= htmlspecialchars($car_image) ?>" alt="Vehicle">
                    <div>
                        <h2><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></h2>
                        <span class="origin-tag"><?= htmlspecialchars($car_origin) ?></span>
                        <div class="price">RM <?= number_format($car_price, 2) ?></div>

                        <div class="info-grid" style="margin-top:14px;">
                            <div class="info-card">
                                <label>Year</label>
                                <p><?= htmlspecialchars($car_year) ?></p>
                            </div>
                            <div class="info-card">
                                <label>Variant</label>
                                <p><?= htmlspecialchars($car_variant) ?></p>
                            </div>
                            <div class="info-card">
                                <label>Color</label>
                                <p><?= htmlspecialchars($car_color) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details -->
            <div class="detail-section">
                <h3><i class="fas fa-receipt"></i> Transaction Details</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <label>Payment Type</label>
                        <p><?= htmlspecialchars($payment_type) ?></p>
                    </div>
                    <div class="info-card">
                        <label>Amount Paid</label>
                        <p style="color:#16a34a;">RM <?= number_format($payment_amount, 2) ?></p>
                    </div>
                    <div class="info-card">
                        <label>Payment Date</label>
                        <p><?= htmlspecialchars($payment_date) ?></p>
                    </div>
                    <div class="info-card mono">
                        <label>Transaction Reference</label>
                        <p><?= htmlspecialchars($payment_ref ?: 'N/A') ?></p>
                    </div>
                    <?php if (!empty($remarks)): ?>
                        <div class="info-card" style="grid-column:1 / -1;">
                            <label>Remarks</label>
                            <p style="font-weight:normal;font-size:13px;color:#475569;"><?= htmlspecialchars($remarks) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking Status -->
            <div class="detail-section">
                <h3><i class="fas fa-clipboard-check"></i> Booking Status</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <label>Current Status</label>
                        <p
                            style="color:<?= $booking_status === 'Approved' ? '#16a34a' : ($booking_status === 'Rejected' ? '#dc2626' : '#d97706') ?>;">
                            <?= htmlspecialchars($booking_status) ?>
                        </p>
                    </div>
                    <div class="info-card">
                        <label>Loan Tenure</label>
                        <p><?= htmlspecialchars($loan_years) ?> Years</p>
                    </div>
                    <div class="info-card">
                        <label>Interest Rate</label>
                        <p><?= number_format($loan_rate, 2) ?>% p.a.</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($approval_message)): ?>
                <div class="note-box">
                    <p>
                        <strong><i class="fas fa-info-circle"></i> What happens next?</strong><br>
                        <?= htmlspecialchars($approval_message) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="button-group">
                <a href="view_status.php" class="action-btn primary-btn">
                    <i class="fas fa-clipboard-list"></i> View My Bookings
                </a>
                <a href="cars.php" class="action-btn secondary-btn">
                    <i class="fas fa-car-side"></i> Browse More Vehicles
                </a>
                <a href="javascript:window.print()" class="action-btn secondary-btn"
                    style="flex:0;min-width:auto;padding:14px 18px;">
                    <i class="fas fa-print"></i>
                </a>
            </div>

        </div>
    </div>
</div>

<?php
unset(
    $_SESSION['pay_ref'],
    $_SESSION['pay_booking_id'],
    $_SESSION['payment_type'],
    $_SESSION['pay_label'],
    $_SESSION['pay_id']
);

include 'Includes/footer.php';
?>