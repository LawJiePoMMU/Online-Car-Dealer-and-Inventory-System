<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: index.php"); exit(); }

$source         = $_POST['source']         ?? '';
$car_id         = $_POST['car_id']         ?? null;
$payment_amount = $_POST['payment_amount'] ?? '0.00';
$payment_label  = $_POST['payment_label']  ?? 'Payment';
$detail_price   = $_POST['detail_price']   ?? null;
$detail_loan    = $_POST['detail_loan']    ?? null;
$detail_monthly = $_POST['detail_monthly'] ?? null;
$detail_tenure  = $_POST['detail_tenure']  ?? null;

$_SESSION['pay_source']         = $source;
$_SESSION['pay_car_id']         = $car_id;
$_SESSION['pay_amount']         = $payment_amount;
$_SESSION['pay_label']          = $payment_label;
$_SESSION['pay_detail_price']   = $detail_price;
$_SESSION['pay_detail_loan']    = $detail_loan;
$_SESSION['pay_detail_monthly'] = $detail_monthly;
$_SESSION['pay_detail_tenure']  = $detail_tenure;

$errors = [];
if (isset($_POST['pay_now'])) {
    $method = $_POST['pay_method'] ?? '';

    if ($method === 'fpx') {
        $bank = $_POST['fpx_bank'] ?? '';
        if (empty($bank)) $errors[] = "Please select your bank.";
        $_SESSION['pay_method'] = 'Online Banking (FPX)';
        $_SESSION['pay_bank']   = $bank;
    } elseif ($method === 'card') {
        $card_name   = trim($_POST['card_name']   ?? '');
        $card_number = trim($_POST['card_number'] ?? '');
        $card_expiry = trim($_POST['card_expiry'] ?? '');
        $card_cvv    = trim($_POST['card_cvv']    ?? '');
        $card_type   = $_POST['card_type']        ?? 'Visa';
        if (empty($card_name))  $errors[] = "Please enter cardholder name.";
        if (strlen(preg_replace('/\s/','',$card_number)) < 16) $errors[] = "Please enter a valid 16-digit card number.";
        if (empty($card_expiry)) $errors[] = "Please enter card expiry date.";
        if (strlen($card_cvv) < 3) $errors[] = "Please enter a valid CVV.";
        $_SESSION['pay_method']     = 'Credit/Debit Card';
        $_SESSION['pay_card_type']  = $card_type;
        $_SESSION['pay_card_last4'] = substr(preg_replace('/\s/','',$card_number), -4);
    } else {
        $errors[] = "Please select a payment method.";
    }

    if (empty($errors)) {
        if ($source === 'reservation') {
            $res_stmt = $pdo->prepare("INSERT INTO reservations (user_id, car_id, reservation_date, reservation_created_at, reservation_status) VALUES (?, ?, ?, NOW(), 'Pending')");
            $res_stmt->execute([$_SESSION['user_id'], $car_id, $_SESSION['res_date']]);
        } else {
            $res_stmt = $pdo->prepare("INSERT INTO reservations (user_id, car_id, reservation_date, reservation_created_at, reservation_status) VALUES (?, ?, NOW(), NOW(), 'Down Payment')");
            $res_stmt->execute([$_SESSION['user_id'], $car_id]);
        }
        $reservation_id = $pdo->lastInsertId();

        $pay_stmt = $pdo->prepare("INSERT INTO payments (reservation_id, payment_amount, payment_method, payment_status, payment_date) VALUES (?, ?, ?, 'Paid', NOW())");
        $pay_stmt->execute([$reservation_id, $payment_amount, $_SESSION['pay_method']]);
        $payment_id = $pdo->lastInsertId();

        $ref = 'TXN' . strtoupper(substr(md5($payment_id . time()), 0, 8));
        $_SESSION['pay_ref']            = $ref;
        $_SESSION['pay_reservation_id'] = $reservation_id;
        $_SESSION['pay_payment_id']     = $payment_id;
        $_SESSION['pay_date']           = date('d M Y, h:i A');

        header("Location: payment_confirm.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .page-hero { background:linear-gradient(135deg,var(--primary-color),#0056b3); color:white; padding:40px 0 30px; margin-bottom:30px; }
        .page-hero h1 { font-size:32px; font-weight:700; margin-bottom:6px; }
        .page-hero p  { opacity:0.85; font-size:15px; }

        .section-card { background:var(--card-bg); border-radius:8px; box-shadow:var(--shadow); padding:28px; margin-bottom:24px; }
        .section-card h2 { font-size:18px; margin-bottom:18px; color:var(--primary-color); border-bottom:2px solid #f0f0f0; padding-bottom:10px; }
        .section-card h3 { font-size:16px; margin:20px 0 14px; }

        .summary-table { width:100%; border-collapse:collapse; }
        .summary-table td { padding:10px 14px; font-size:14px; border-bottom:1px solid #f0f0f0; }
        .summary-table td:first-child { color:var(--text-light); width:200px; }
        .summary-table td:last-child { font-weight:600; }
        .amount-highlight { font-size:22px; color:var(--primary-color); }

        .method-group { display:flex; gap:16px; margin-bottom:24px; }
        .method-option { flex:1; border:2px solid #e0e0e0; border-radius:8px; padding:16px; cursor:pointer; transition:border-color 0.2s; display:flex; align-items:center; gap:10px; }
        .method-option:has(input:checked) { border-color:var(--primary-color); background:#f0f7ff; }
        .method-option input { accent-color:var(--primary-color); width:18px; height:18px; }
        .method-option span { font-weight:500; font-size:14px; }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

        .alert-error { background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; padding:14px 18px; margin-bottom:20px; color:#721c24; font-size:14px; }

        @media (max-width:600px) { .method-group { flex-direction:column; } .form-row { grid-template-columns:1fr; } }
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

<!-- HERO -->
<div class="page-hero">
    <div class="container">
        <h1>Payment</h1>
        <p>Complete your payment securely below.</p>
    </div>
</div>

<div class="container">

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <?php foreach ($errors as $e): ?><p><?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Order Summary -->
    <div class="section-card">
        <h2>Order Summary</h2>
        <table class="summary-table">
            <tr><td>Description</td><td><?php echo htmlspecialchars($payment_label); ?></td></tr>
            <tr><td>Amount Due</td><td class="amount-highlight">RM <?php echo number_format((float)$payment_amount, 2); ?></td></tr>
        </table>
        <?php if ($source === 'downpayment' && $detail_price): ?>
        <table class="summary-table" style="margin-top:12px;">
            <tr><td>Car Price</td><td><?php echo htmlspecialchars($detail_price); ?></td></tr>
            <tr><td>Loan Amount</td><td><?php echo htmlspecialchars($detail_loan); ?></td></tr>
            <tr><td>Monthly Instalment</td><td><?php echo htmlspecialchars($detail_monthly); ?></td></tr>
            <tr><td>Loan Tenure</td><td><?php echo htmlspecialchars($detail_tenure); ?></td></tr>
        </table>
        <?php endif; ?>
    </div>

    <!-- Payment Form -->
    <form method="POST" action="payment.php">
        <input type="hidden" name="source"         value="<?php echo htmlspecialchars($source); ?>"/>
        <input type="hidden" name="car_id"         value="<?php echo htmlspecialchars($car_id); ?>"/>
        <input type="hidden" name="payment_amount" value="<?php echo htmlspecialchars($payment_amount); ?>"/>
        <input type="hidden" name="payment_label"  value="<?php echo htmlspecialchars($payment_label); ?>"/>
        <input type="hidden" name="detail_price"   value="<?php echo htmlspecialchars($detail_price); ?>"/>
        <input type="hidden" name="detail_loan"    value="<?php echo htmlspecialchars($detail_loan); ?>"/>
        <input type="hidden" name="detail_monthly" value="<?php echo htmlspecialchars($detail_monthly); ?>"/>
        <input type="hidden" name="detail_tenure"  value="<?php echo htmlspecialchars($detail_tenure); ?>"/>

        <div class="section-card">
            <h2>Select Payment Method</h2>

            <div class="method-group">
                <label class="method-option">
                    <input type="radio" name="pay_method" value="fpx" onchange="showMethod('fpx')" checked/>
                    <span>&#127981; Online Banking (FPX)</span>
                </label>
                <label class="method-option">
                    <input type="radio" name="pay_method" value="card" onchange="showMethod('card')"/>
                    <span>&#128179; Credit / Debit Card</span>
                </label>
            </div>

            <!-- FPX -->
            <div id="section-fpx">
                <h3>Select Your Bank</h3>
                <div class="form-group">
                    <select id="fpx_bank" name="fpx_bank" class="form-control">
                        <option value="">-- Select Bank --</option>
                        <option value="Maybank2u">Maybank2u</option>
                        <option value="CIMB Clicks">CIMB Clicks</option>
                        <option value="Public Bank PBe">Public Bank PBe</option>
                        <option value="RHB Bank">RHB Bank</option>
                        <option value="Hong Leong Bank">Hong Leong Bank</option>
                        <option value="AmBank">AmBank</option>
                        <option value="Bank Islam">Bank Islam</option>
                        <option value="Bank Rakyat">Bank Rakyat</option>
                        <option value="BSN">BSN</option>
                        <option value="Affin Bank">Affin Bank</option>
                    </select>
                </div>
                <p style="font-size:13px; color:var(--text-light);">You will be redirected to your bank's secure page to complete payment.</p>
            </div>

            <!-- Card -->
            <div id="section-card" style="display:none;">
                <h3>Card Details</h3>
                <div class="form-group">
                    <label class="auth-label">Cardholder Name</label>
                    <input type="text" name="card_name" class="form-control" placeholder="As printed on card"/>
                </div>
                <div class="form-group">
                    <label class="auth-label">Card Number</label>
                    <input type="text" id="card_number" name="card_number" class="form-control"
                           placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCardNum(this)"/>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="auth-label">Expiry Date</label>
                        <input type="text" id="card_expiry" name="card_expiry" class="form-control"
                               placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)"/>
                    </div>
                    <div class="form-group">
                        <label class="auth-label">CVV</label>
                        <input type="password" name="card_cvv" class="form-control" placeholder="***" maxlength="3"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="auth-label">Card Type</label>
                    <select name="card_type" class="form-control">
                        <option value="Visa">Visa</option>
                        <option value="Mastercard">Mastercard</option>
                        <option value="American Express">American Express</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="pay_now" value="1" class="btn-primary"
                    style="width:100%; padding:15px; font-size:16px; margin-top:10px;">
                Pay Now &rarr;
            </button>
        </div>
    </form>

</div>

<footer class="footer text-center">
    <p>&copy; 2025 AutoDeal. All rights reserved.</p>
</footer>

<script>
    function showMethod(m) {
        document.getElementById('section-fpx').style.display  = m==='fpx'  ? 'block':'none';
        document.getElementById('section-card').style.display = m==='card' ? 'block':'none';
    }
    function formatCardNum(input) {
        let v = input.value.replace(/\D/g,'').substring(0,16);
        input.value = v.replace(/(.{4})/g,'$1 ').trim();
    }
    function formatExpiry(input) {
        let v = input.value.replace(/\D/g,'').substring(0,4);
        if (v.length>=3) v = v.substring(0,2)+'/'+v.substring(2);
        input.value = v;
    }
</script>
</body>
</html>
