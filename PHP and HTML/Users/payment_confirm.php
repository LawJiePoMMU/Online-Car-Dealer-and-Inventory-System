
<?php
session_start();
require 'db.php';

// 1. Ensure the user is logged in and actually just completed a payment
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['pay_ref']) || !isset($_SESSION['pay_reservation_id'])) {
    // Prevent direct access to confirmation page if no payment transaction exists
    header("Location: index.php");
    exit();
}

// 2. Fetch the newly created reservation and payment details from the database 
// to ensure accuracy (even though we have session backups)
try {
    $stmt = $pdo->prepare("
        SELECT r.*, p.payment_amount, p.payment_method, p.payment_status, p.payment_date 
        FROM reservations r
        JOIN payments p ON r.reservation_id = p.reservation_id
        WHERE r.reservation_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$_SESSION['pay_reservation_id'], $_SESSION['user_id']]);
    $db_data = $stmt->fetch();

    if (!$db_data) {
        throw new Exception("Transaction record not found in system storage.");
    }

    // Decode our compiled snapshot payload to extract structural display variables
    $snapshot = json_decode($db_data['snapshot_data'], true);

} catch (Exception $e) {
    die("Error retrieving confirmation details: " . $e->getMessage());
}

// 3. Keep variables handy for display
$txn_ref        = $_SESSION['pay_ref'];
$payment_amount = $db_data['payment_amount'];
$car_model      = $snapshot['car_model'] ?? 'Selected Vehicle';
$car_brand      = $snapshot['car_brand'] ?? '';
$payment_label  = $_SESSION['pay_label'] ?? 'Payment Complete';

// 4. CLEANUP WORKFLOW: Clear payment wizard sessions so they can't form-resubmit,
// but keep user details intact for application navigation
unset($_SESSION['pay_source']);
unset($_SESSION['pay_car_id']);
unset($_SESSION['pay_amount']);
unset($_SESSION['pay_label']);
unset($_SESSION['pay_detail_price']);
unset($_SESSION['pay_detail_loan']);
unset($_SESSION['pay_detail_monthly']);
unset($_SESSION['pay_detail_tenure']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment Successful</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .success-wrapper { max-width: 600px; margin: 60px auto; padding: 0 20px; text-align: center; }
        .success-card { background: #ffffff; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 40px; border-top: 6px solid #28a745; }
        
        .success-icon { width: 80px; height: 80px; background: #d4edda; color: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 24px; }
        
        h1 { font-size: 26px; color: #333; margin-bottom: 8px; font-weight: 700; }
        .subtitle { color: #6c757d; font-size: 15px; margin-bottom: 30px; }
        
        .receipt-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; text-align: left; background: #f8f9fa; border-radius: 8px; overflow: hidden; }
        .receipt-table td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid #eef0f2; }
        .receipt-table tr:last-child td { border-bottom: none; }
        .receipt-table td:first-child { color: #6c757d; width: 40%; }
        .receipt-table td:last-child { font-weight: 600; color: #333; text-align: right; }
        
        .badge-paid { background: #28a745; color: white; padding: 4px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        
        .btn-status { display: block; background: #2b6cb0; color: white; padding: 15px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 16px; transition: background 0.2s ease; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(43, 108, 176, 0.2); }
        .btn-status:hover { background: #1a446c; }
        .btn-home { display: inline-block; color: #2b6cb0; text-decoration: none; font-size: 14px; font-weight: 500; }
        .btn-home:hover { text-decoration: underline; }
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

<div class="success-wrapper">
    <div class="success-card">
        <div class="success-icon">✓</div>
        <h1>Payment Confirmed!</h1>
        <p class="subtitle">Your transaction completed successfully and your request has been logged.</p>
        
        <table class="receipt-table">
            <tr>
                <td>Transaction Ref</td>
                <td><?php echo htmlspecialchars($txn_ref); ?></td>
            </tr>
            <tr>
                <td>Description</td>
                <td><?php echo htmlspecialchars($payment_label); ?></td>
            </tr>
            <tr>
                <td>Vehicle</td>
                <td><?php echo htmlspecialchars($car_brand . ' ' . $car_model); ?></td>
            </tr>
            <tr>
                <td>Amount Charged</td>
                <td style="color: #2b6cb0; font-size: 16px;">RM <?php echo number_format((float)$payment_amount, 2); ?></td>
            </tr>
            <tr>
                <td>Payment Method</td>
                <td><?php echo htmlspecialchars($db_data['payment_method']); ?></td>
            </tr>
            <tr>
                <td>Transaction Date</td>
                <td><?php echo date('d M Y, h:i A', strtotime($db_data['payment_date'])); ?></td>
            </tr>
            <tr>
                <td>Payment Status</td>
                <td><span class="badge-paid"><?php echo htmlspecialchars($db_data['payment_status']); ?></span></td>
            </tr>
        </table>
        
        <a href="view_status.php" class="btn-status">
            Track Loan Approval Status &rarr;
        </a>
        
        <a href="index.php" class="btn-home">Return to Homepage</a>
    </div>
</div>

</body>
</html>
