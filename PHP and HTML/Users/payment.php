<?php
session_start();
require 'db.php';

// 1. Ensure the user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

// 2. Prevent direct URL tampering access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header("Location: index.php"); 
    exit(); 
}

// 3. Process incoming variables (fall back to session data if the form is reloading due to errors)
$source         = $_POST['source']         ?? $_SESSION['pay_source']  ?? 'booking';
$car_id         = $_POST['car_id']         ?? $_SESSION['pay_car_id']  ?? ($_SESSION['selected_car_id'] ?? null);
$payment_amount = $_POST['payment_amount'] ?? $_SESSION['pay_amount']  ?? ($_SESSION['total_price'] ?? '0.00');
$payment_label  = $_POST['payment_label']  ?? $_SESSION['pay_label']   ?? (($source === 'booking') ? 'Vehicle Booking Fee & Insurance' : 'Down Payment');

$detail_price   = $_POST['detail_price']   ?? $_SESSION['pay_detail_price']   ?? (isset($_SESSION['total_price']) ? 'RM ' . number_format($_SESSION['total_price'], 2) : null);
$detail_loan    = $_POST['detail_loan']    ?? $_SESSION['pay_detail_loan']    ?? null;
$detail_monthly = $_POST['detail_monthly'] ?? $_SESSION['pay_detail_monthly'] ?? (isset($_SESSION['monthly_payment']) ? 'RM ' . number_format($_SESSION['monthly_payment'], 2) . ' / month' : null);
$detail_tenure  = $_POST['detail_tenure']  ?? $_SESSION['pay_detail_tenure']  ?? (isset($_SESSION['loan_years']) ? $_SESSION['loan_years'] . ' Years' : null);

// Backup states to session variables to survive page reloads
$_SESSION['pay_source']         = $source;
$_SESSION['pay_car_id']         = $car_id;
$_SESSION['pay_amount']         = $payment_amount;
$_SESSION['pay_label']          = $payment_label;
$_SESSION['pay_detail_price']   = $detail_price;
$_SESSION['pay_detail_loan']    = $detail_loan;
$_SESSION['pay_detail_monthly'] = $detail_monthly;
$_SESSION['pay_detail_tenure']  = $detail_tenure;

$errors = [];

// Initialize sticky form variables
$card_name   = '';
$card_number = '';
$card_expiry = '';
$card_type   = 'Visa';

if (isset($_POST['pay_now'])) {
    $card_name   = trim($_POST['card_name']   ?? '');
    $card_number = trim($_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv    = trim($_POST['card_cvv']    ?? '');
    $card_type   = $_POST['card_type']        ?? 'Visa';

    if (empty($card_name)) {
        $errors[] = "Please enter cardholder name.";
    }

    $clean_card_number = preg_replace('/\s/', '', $card_number);
    if (strlen($clean_card_number) < 16) {
        $errors[] = "Please enter a valid 16-digit card number.";
    }

    if (empty($card_expiry)) {
        $errors[] = "Please enter card expiry date.";
    }

    if (strlen($card_cvv) < 3) {
        $errors[] = "Please enter a valid CVV.";
    }

    if (empty($errors)) {
        try {
            // Start Transaction Safety Mechanism
            $pdo->beginTransaction();

            // 1. Compile the JSON Snapshot Data Array out of active registration sessions
            $snapshot_array = [
                "user_name"           => $_SESSION['res_name'] ?? '',
                "user_email"          => $_SESSION['res_email'] ?? '',
                "user_phone"          => $_SESSION['res_phone'] ?? '',
                "user_ic"             => $_SESSION['res_ic'] ?? '',
                "car_brand"           => $_SESSION['res_brand'] ?? '',
                "car_model"           => $_SESSION['res_model'] ?? '',
                "car_year"            => $_SESSION['res_year'] ?? '',
                "car_origin"          => $_SESSION['res_origin'] ?? '',
                "car_color"           => $_SESSION['car_color'] ?? '',
                "car_variant"         => $_SESSION['car_variant'] ?? '',
                "car_plate"           => $_SESSION['car_plate'] ?? '',
                "loan_years"          => $_SESSION['loan_years'] ?? 5,
                "insurance_amount"    => $_SESSION['insurance_amount'] ?? 0,
                "estimated_monthly"   => $_SESSION['monthly_payment'] ?? 0,
                "total_compiled_price"=> $_SESSION['total_price'] ?? 0,
                "ic_doc_path"         => $_SESSION['ic_document'] ?? null,
                "license_doc_path"    => $_SESSION['license_document'] ?? null,
                "payslip_doc_path"    => $_SESSION['payslip_document'] ?? null,
                "bank_stmt_doc_path"  => $_SESSION['bank_statement_document'] ?? null
            ];
            
            $json_snapshot = json_encode($snapshot_array, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            // 2. Map Status explicitly to match your precise ENUM validation bounds
            $reservation_status = ($source === 'booking') ? 'Pending Viewing' : 'Down Payment';

            // Resolve target timing data column safely
            $preferred_date = $_SESSION['booking_date'] ?? date('Y-m-d', strtotime('+1 day'));

            // 3. EXECUTE TARGET SNAPSHOT INSERTION
            $res_stmt = $pdo->prepare("
                INSERT INTO reservations (
                    user_id, 
                    car_id, 
                    reservation_created_at, 
                    reservation_status, 
                    preferred_test_drive_at, 
                    snapshot_data
                ) VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            
            $res_stmt->execute([
                $_SESSION['user_id'], 
                $car_id, 
                $reservation_status, 
                $preferred_date, 
                $json_snapshot
            ]);
            
            $reservation_id = $pdo->lastInsertId();

            // 4. LOG COMPLEMENTARY SYSTEM PAYMENT ENTRY
            $_SESSION['pay_method']     = 'Credit/Debit Card';
            $_SESSION['pay_card_type']  = $card_type;
            $_SESSION['pay_card_last4'] = substr($clean_card_number, -4);

            $pay_stmt = $pdo->prepare("
                INSERT INTO payments (reservation_id, payment_amount, payment_method, payment_status, payment_date) 
                VALUES (?, ?, ?, 'Paid', NOW())
            ");
            $pay_stmt->execute([$reservation_id, $payment_amount, $_SESSION['pay_method']]);
            $payment_id = $pdo->lastInsertId();

            // Generate System Reference Identifiers
            $ref = 'TXN' . strtoupper(substr(md5($payment_id . time()), 0, 8));
            $_SESSION['pay_ref']            = $ref;
            $_SESSION['pay_reservation_id'] = $reservation_id;
            $_SESSION['pay_payment_id']     = $payment_id;
            $_SESSION['pay_date']           = date('d M Y, h:i A');

            // Commit Transaction Safely
            $pdo->commit();

            // Clear configuration data variables so future processes start fresh
            unset($_SESSION['booking_date']);

            header("Location: payment_confirm.php");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database Sync Error: " . $e->getMessage();
        }
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
        .page-hero { background:linear-gradient(135deg,var(--primary-color, #007bff),#0056b3); color:white; padding:40px 0 30px; margin-bottom:30px; }
        .page-hero h1 { font-size:32px; font-weight:700; margin-bottom:6px; }
        .page-hero p  { opacity:0.85; font-size:15px; }

        .section-card { background:var(--card-bg, #ffffff); border-radius:8px; box-shadow:var(--shadow); padding:28px; margin-bottom:24px; }
        .section-card h2 { font-size:18px; margin-bottom:18px; color:var(--primary-color, #007bff); border-bottom:2px solid #f0f0f0; padding-bottom:10px; }
        .section-card h3 { font-size:16px; margin:20px 0 14px; }

        .summary-table { width:100%; border-collapse:collapse; }
        .summary-table td { padding:10px 14px; font-size:14px; border-bottom:1px solid #f0f0f0; }
        .summary-table td:first-child { color:var(--text-light, #6c757d); width:200px; }
        .summary-table td:last-child { font-weight:600; }
        .amount-highlight { font-size:22px; color:var(--primary-color, #007bff); }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; box-sizing: border-box; }

        .alert-error { background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; padding:14px 18px; margin-bottom:20px; color:#721c24; font-size:14px; }
        @media (max-width:600px) { .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">AutoDeal</a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="downpayment.php">Down Payment</a></li>
            <li><a href="booking.php">Reservation</a></li>
            <li><a href="view_status.php">View Status</a></li>
        </ul>
    </div>
</nav>

<div class="page-hero">
    <div class="container">
        <h1>Payment Gateway</h1>
        <p>Complete your payment securely below via credit or debit card.</p>
    </div>
</div>

<div class="container" style="max-width: 750px; margin: 0 auto; padding: 0 20px;">

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <?php foreach ($errors as $e): ?><p style="margin:4px 0;">• <?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section-card">
        <h2>Order Summary</h2>
        <table class="summary-table">
            <tr><td>Description</td><td><?php echo htmlspecialchars($payment_label); ?></td></tr>
            <tr><td>Amount Due</td><td class="amount-highlight">RM <?php echo number_format((float)$payment_amount, 2); ?></td></tr>
        </table>
        <?php if ($detail_price): ?>
        <table class="summary-table" style="margin-top:12px;">
            <tr><td>Total Price</td><td><?php echo htmlspecialchars($detail_price); ?></td></tr>
            <?php if($detail_loan): ?><tr><td>Loan Amount</td><td><?php echo htmlspecialchars($detail_loan); ?></td></tr><?php endif; ?>
            <?php if($detail_monthly): ?><tr><td>Monthly Instalment</td><td><?php echo htmlspecialchars($detail_monthly); ?></td></tr><?php endif; ?>
            <?php if($detail_tenure): ?><tr><td>Loan Tenure</td><td><?php echo htmlspecialchars($detail_tenure); ?></td></tr><?php endif; ?>
        </table>
        <?php endif; ?>
    </div>

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
            <h2>Card Details</h2>
            
            <div class="form-group">
                <label class="auth-label">Cardholder Name</label>
                <input type="text" name="card_name" class="form-control"
                       placeholder="As printed on card" 
                       value="<?php echo htmlspecialchars($card_name); ?>" required/>
            </div>

            <div class="form-group">
                <label class="auth-label">Card Number</label>
                <input type="text" id="card_number" name="card_number"
                       class="form-control"
                       placeholder="1234 5678 9012 3456"
                       maxlength="19"
                       oninput="formatCardNum(this)"
                       value="<?php echo htmlspecialchars($card_number); ?>" required/>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="auth-label">Expiry Date</label>
                    <input type="text" id="card_expiry" name="card_expiry"
                           class="form-control"
                           placeholder="MM/YY"
                           maxlength="5"
                           oninput="formatExpiry(this)"
                           value="<?php echo htmlspecialchars($card_expiry); ?>" required/>
                </div>

                <div class="form-group">
                    <label class="auth-label">CVV</label>
                    <input type="password" name="card_cvv" class="form-control"
                           placeholder="***" maxlength="3" required/>
                </div>
            </div>

            <div class="form-group">
                <label class="auth-label">Card Type</label>
                <select name="card_type" class="form-control">
                    <option value="Visa" <?php echo $card_type === 'Visa' ? 'selected' : ''; ?>>Visa</option>
                    <option value="Mastercard" <?php echo $card_type === 'Mastercard' ? 'selected' : ''; ?>>Mastercard</option>
                    <option value="American Express" <?php echo $card_type === 'American Express' ? 'selected' : ''; ?>>American Express</option>
                </select>
            </div>

            <button type="submit" name="pay_now" value="1" class="btn-primary"
                    style="width:100%; padding:15px; font-size:16px; margin-top:20px; cursor:pointer; background-color: #2b6cb0; color: white; border: none; border-radius: 4px; font-weight: 600;">
                Pay Now &rarr;
            </button>
        </div>
    </form>
</div>

<footer class="footer text-center" style="margin-top: 40px; padding: 20px 0; color: #aaa; text-align: center;">
    <p>&copy; 2026 AutoDeal. All rights reserved.</p>
</footer>

<script>
    function formatCardNum(input) {
        let v = input.value.replace(/\D/g,'').substring(0,16);
        input.value = v.replace(/(.{4})/g,'$1 ').trim();
    }
    function formatExpiry(input) {
        let v = input.value.replace(/\D/g,'').substring(0,4);
        if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2);
        input.value = v;
    }
</script>
</body>
</html>
