<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pay_ref'])) { header("Location: index.php"); exit(); }

$source     = $_SESSION['pay_source']         ?? '';
$amount     = $_SESSION['pay_amount']         ?? '0.00';
$label      = $_SESSION['pay_label']          ?? 'Payment';
$method     = $_SESSION['pay_method']         ?? '-';
$ref        = $_SESSION['pay_ref']            ?? 'N/A';
$date       = $_SESSION['pay_date']           ?? '-';
$bank       = $_SESSION['pay_bank']           ?? null;
$card_type  = $_SESSION['pay_card_type']      ?? null;
$card_last4 = $_SESSION['pay_card_last4']     ?? null;
$expiry     = date('d F Y', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment Confirmation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .success-hero {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white; padding: 50px 0 40px; margin-bottom: 30px; text-align: center;
        }
        .success-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 70px; height: 70px; border-radius: 50%;
            background: rgba(255,255,255,0.25); font-size: 36px;
            margin-bottom: 16px;
        }
        .success-hero h1 { font-size: 30px; font-weight: 700; margin-bottom: 6px; }
        .success-hero p  { opacity: 0.9; font-size: 15px; }

        .section-card { background: var(--card-bg); border-radius: 8px; box-shadow: var(--shadow); padding: 28px; margin-bottom: 24px; }
        .section-card h2 { font-size: 18px; margin-bottom: 18px; color: var(--primary-color); border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
        .info-table td:first-child { color: var(--text-light); font-weight: 500; width: 200px; }

        .ref-box { background: #f0f7ff; border: 1px solid #c8e0ff; border-radius: 6px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        .ref-box span:first-child { font-size: 13px; color: var(--text-light); }
        .ref-box strong { font-size: 18px; color: var(--primary-color); font-family: monospace; letter-spacing: 1px; }

        .status-badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; }
        .status-success { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-danger  { background: #f8d7da; color: #721c24; }

        .steps-list { list-style: none; padding: 0; }
        .steps-list li { display: flex; align-items: flex-start; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: var(--text-dark); }
        .steps-list li:last-child { border-bottom: none; }
        .step-num { min-width: 28px; height: 28px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; }

        .car-thumb-small { height: 60px; border-radius: 6px; object-fit: cover; margin-right: 10px; vertical-align: middle; }

        .btn-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-outline { background: white; color: var(--primary-color); border: 2px solid var(--primary-color); padding: 11px 22px; border-radius: 5px; font-family: 'Poppins', sans-serif; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; display: inline-block; }
        .btn-outline:hover { background: var(--primary-color); color: white; }

        @media (max-width:600px) { .btn-group { flex-direction: column; } .ref-box { flex-direction: column; gap: 4px; } }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">AutoDeal</a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="downpayment.php">Down Payment</a></li>
            <li><a href="reservation.php">Reservation</a></li>
            <li><a href="view_status.php">View Status</a></li>
        </ul>
        <div class="nav-actions"></div>
    </div>
</nav>

<!-- SUCCESS HERO -->
<div class="success-hero">
    <div class="container">
        <div class="success-icon">&#10003;</div>
        <h1>Payment Successful!</h1>
        <?php if ($source === 'downpayment'): ?>
            <p>Your down payment has been received successfully.</p>
        <?php else: ?>
            <p>Your car reservation has been confirmed!</p>
        <?php endif; ?>
    </div>
</div>

<div class="container">

    <!-- Reference Number -->
    <div class="ref-box">
        <span>Transaction Reference</span>
        <strong><?php echo htmlspecialchars($ref); ?></strong>
    </div>

    <!-- Transaction Details -->
    <div class="section-card">
        <h2>Transaction Details</h2>
        <table class="info-table">
            <tr><td>Reference Number</td><td><strong><?php echo htmlspecialchars($ref); ?></strong></td></tr>
            <tr><td>Date &amp; Time</td><td><?php echo htmlspecialchars($date); ?></td></tr>
            <tr><td>Description</td><td><?php echo htmlspecialchars($label); ?></td></tr>
            <tr><td>Amount Paid</td><td><strong style="color:var(--primary-color); font-size:18px;">RM <?php echo number_format((float)$amount, 2); ?></strong></td></tr>
            <tr><td>Payment Method</td><td><?php echo htmlspecialchars($method); ?></td></tr>
            <?php if ($method === 'Online Banking (FPX)' && $bank): ?>
            <tr><td>Bank</td><td><?php echo htmlspecialchars($bank); ?></td></tr>
            <?php elseif ($method === 'Credit/Debit Card' && $card_last4): ?>
            <tr><td>Card</td><td><?php echo htmlspecialchars($card_type); ?> ending in <?php echo htmlspecialchars($card_last4); ?></td></tr>
            <?php endif; ?>
            <tr><td>Status</td><td><span class="status-badge status-success">PAID</span></td></tr>
        </table>
    </div>

    <!-- Down Payment Loan Summary -->
    <?php if ($source === 'downpayment'): ?>
    <div class="section-card">
        <h2>Loan Summary</h2>
        <table class="info-table">
            <tr><td>Car Price</td><td><?php echo htmlspecialchars($_SESSION['pay_detail_price']   ?? '-'); ?></td></tr>
            <tr><td>Loan Amount</td><td><?php echo htmlspecialchars($_SESSION['pay_detail_loan']    ?? '-'); ?></td></tr>
            <tr><td>Monthly Instalment</td><td><?php echo htmlspecialchars($_SESSION['pay_detail_monthly'] ?? '-'); ?></td></tr>
            <tr><td>Loan Tenure</td><td><?php echo htmlspecialchars($_SESSION['pay_detail_tenure']  ?? '-'); ?></td></tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Reservation Summary -->
    <?php if ($source === 'reservation'): ?>
    <div class="section-card">
        <h2>Reservation Details</h2>
        <table class="info-table">
            <?php if (!empty($_SESSION['res_image'])): ?>
            <tr>
                <td>Car</td>
                <td>
                    <img src="<?php echo htmlspecialchars($_SESSION['res_image']); ?>" class="car-thumb-small" alt="Car"/>
                    <?php echo htmlspecialchars(($_SESSION['res_brand']??'').' '.($_SESSION['res_model']??'')); ?>
                </td>
            </tr>
            <?php else: ?>
            <tr><td>Car</td><td><?php echo htmlspecialchars(($_SESSION['res_brand']??'').' '.($_SESSION['res_model']??'')); ?></td></tr>
            <?php endif; ?>
            <tr><td>Registered Name</td><td><?php echo htmlspecialchars($_SESSION['res_name']  ?? '-'); ?></td></tr>
            <tr><td>Visit Date</td><td><?php echo htmlspecialchars($_SESSION['res_date']  ?? '-'); ?></td></tr>
            <tr><td>Reservation Valid Until</td><td><strong><?php echo $expiry; ?></strong></td></tr>
        </table>
        <p style="font-size:13px; color:var(--text-light); margin-top:12px;">
            Our sales team will contact you within 1 working day to finalise your purchase.
        </p>
    </div>
    <?php endif; ?>

    <!-- Next Steps -->
    <div class="section-card">
        <h2>What's Next?</h2>
        <?php if ($source === 'downpayment'): ?>
        <ul class="steps-list">
            <li><div class="step-num">1</div> Our finance team will review your loan application.</li>
            <li><div class="step-num">2</div> A confirmation email will be sent to <strong><?php echo htmlspecialchars($_SESSION['res_email'] ?? ''); ?></strong>.</li>
            <li><div class="step-num">3</div> Visit the showroom within 3 working days with your IC and supporting documents.</li>
            <li><div class="step-num">4</div> Track your application status on the <a href="view_status.php" style="color:var(--primary-color);">View Status</a> page.</li>
        </ul>
        <?php else: ?>
        <ul class="steps-list">
            <li><div class="step-num">1</div> Your reservation is confirmed until <strong><?php echo $expiry; ?></strong>.</li>
            <li><div class="step-num">2</div> A confirmation email will be sent to <strong><?php echo htmlspecialchars($_SESSION['res_email'] ?? ''); ?></strong>.</li>
            <li><div class="step-num">3</div> Visit the showroom on <strong><?php echo htmlspecialchars($_SESSION['res_date'] ?? ''); ?></strong> to complete your purchase.</li>
            <li><div class="step-num">4</div> Track your reservation on the <a href="view_status.php" style="color:var(--primary-color);">View Status</a> page.</li>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div class="btn-group" style="margin-bottom:48px;">
        <button onclick="window.print()" class="btn-outline">&#128438; Print Receipt</button>
        <a href="view_status.php" class="btn-primary" style="padding:12px 24px; text-decoration:none;">View My Status</a>
        <a href="index.php" class="btn-outline">Back to Home</a>
    </div>

</div>

<footer class="footer text-center">
    <p>&copy; 2025 AutoDeal. All rights reserved.</p>
</footer>

<?php unset($_SESSION['pay_ref'], $_SESSION['pay_date'], $_SESSION['pay_method'], $_SESSION['pay_bank'], $_SESSION['pay_card_type'], $_SESSION['pay_card_last4']); ?>
</body>
</html>
