<?php
// 1. SESSION CHECK
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. GET RESERVATION ID
if (!isset($_GET['reservation_id'])) {
    die("Error: Missing reservation reference code.");
}
$reservation_id = intval($_GET['reservation_id']);

// 3. DATABASE CONNECTION
require 'db.php'; 

try {
    // 4. GET RESERVATION DETAILS & VEHICLE INFO
    $stmt = $pdo->prepare("
        SELECT * FROM reservations 
        WHERE reservation_id = ? AND user_id = ?
    ");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        die("Error: Reservation record not found or access denied.");
    }

    // 5. DECODE SNAPSHOT
    $snapshot = json_decode($reservation['snapshot_data'] ?? '{}', true) ?? [];

    $car_brand         = $snapshot['car_brand'] ?? '';
    $car_model         = $snapshot['car_model'] ?? '';
    $car_name          = trim($car_brand . ' ' . $car_model);
    if (empty($car_name)) { $car_name = 'Selected Vehicle'; }

    $car_price         = (float)($snapshot['total_compiled_price'] ?? ($snapshot['total_price'] ?? 0));
    $car_image         = $snapshot['car_image'] ?? 'images/default_car.jpg';
    $loan_years        = !empty($snapshot['loan_years']) ? (int)$snapshot['loan_years'] : 0;
    $monthly_payment   = (float)($snapshot['estimated_monthly'] ?? ($snapshot['monthly_payment'] ?? 0));

    // 6. GET TOTAL PRINCIPAL PAID (Excluding Booking Fees for Balance Deduction)
    $paid_stmt = $pdo->prepare("
        SELECT SUM(payment_amount) 
        FROM payments 
        WHERE reference_id = ? 
          AND payment_status = 'paid' 
          AND payment_type != 'Booking Fee'
    ");
    $paid_stmt->execute([$reservation_id]);
    $total_paid = (float)$paid_stmt->fetchColumn();

    // 7. CALCULATE REMAINING BALANCE
    $remaining_balance = max(0, $car_price - $total_paid);

    // 8. INSTALLMENT PROGRESS MILESTONES
    $total_months = $loan_years * 12;
    $completed_installments = 0;

    if ($total_months > 0) {
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM payments 
            WHERE reference_id = ? 
              AND payment_status = 'paid' 
              AND payment_type = 'Monthly Installment'
        ");
        $count_stmt->execute([$reservation_id]);
        $completed_installments = (int)$count_stmt->fetchColumn();
        
        $completed_installments = min($completed_installments, $total_months);
    }

    // 9. PAYMENT HISTORY QUERY
    $history_stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE reference_id = ?
        ORDER BY payment_date DESC
    ");
    $history_stmt->execute([$reservation_id]);
    $payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("System Processing Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financing & Installments - <?php echo htmlspecialchars($car_name); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
body { background: #f4f7fb; color: #333; padding-bottom: 60px; }
.navbar { background: white; padding: 18px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
.logo { font-size: 24px; font-weight: 700; color: #2b6cb0; text-decoration: none; }
.nav-links a { text-decoration: none; color: #444; font-weight: 500; margin-left: 25px; }
.container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
.back-link { display: inline-block; margin-bottom: 20px; color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px; }
.back-link:hover { text-decoration: underline; }
.card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); margin-bottom: 30px; }
.section-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 20px; border-left: 4px solid #2563eb; padding-left: 12px; }
.vehicle-header { display: flex; gap: 25px; align-items: center; }
.vehicle-image { width: 150px; height: 100px; object-fit: cover; border-radius: 14px; }
.vehicle-title { font-size: 24px; font-weight: 700; color: #1e293b; }
.vehicle-meta { color: #64748b; font-size: 14px; }
.metrics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.metric-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }
.metric-label { font-size: 13px; color: #64748b; margin-bottom: 6px; }
.metric-value { font-size: 22px; font-weight: 700; }
.metric-value.blue { color: #2563eb; }
.metric-value.green { color: #16a34a; }
.progress-container { margin-top: 10px; }
.progress-text { display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #475569; }
.progress-bar-bg { background: #e2e8f0; height: 12px; border-radius: 50px; overflow: hidden; }
.progress-bar-fill { background: #16a34a; height: 100%; border-radius: 50px; transition: width 0.4s ease; }
.history-table { width: 100%; border-collapse: collapse; }
.history-table th { background: #f8fafc; color: #64748b; font-size: 13px; text-align: left; padding: 14px; border-bottom: 1px solid #e2e8f0; }
.history-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
.status-badge { display: inline-block; padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.status-paid { background: #dcfce7; color: #166534; }
.status-pending { background: #fff8e1; color: #b7791f; }
.status-failed { background: #ffe5e5; color: #c53030; }
.no-history { text-align: center; color: #64748b; padding: 30px 0; font-style: italic; }
.payment-panel { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 18px; }
.payment-info-title { font-size: 14px; color: #64748b; margin-bottom: 4px; }
.payment-info-price { font-size: 26px; font-weight: 700; color: #1e293b; }
.btn-submit { background: #16a34a; color: white; border: none; padding: 16px 35px; font-size: 15px; font-weight: 600; border-radius: 14px; cursor: pointer; transition: background 0.2s; }
.btn-submit:hover { background: #15803d; }
.fully-paid-banner { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; text-align: center; padding: 20px; font-size: 18px; font-weight: 700; border-radius: 16px; }
@media (max-width: 768px) { .metrics-grid { grid-template-columns: 1fr; } .payment-panel { flex-direction: column; gap: 20px; text-align: center; } .btn-submit { width: 100%; } }
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">AutoDeal</a>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="view_status.php">My Status</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <a href="view_status.php" class="back-link">← Back to Dashboard</a>

    <div class="card vehicle-header">
        <img src="<?php echo htmlspecialchars($car_image); ?>" onerror="this.src='images/default_car.jpg';" class="vehicle-image" alt="Vehicle Image">
        <div>
            <div class="vehicle-title"><?php echo htmlspecialchars($car_name); ?></div>
            <div class="vehicle-meta">Account Reference: RSV-<?php echo str_pad($reservation_id, 6, '0', STR_PAD_LEFT); ?></div>
            <div class="vehicle-meta">Vehicle Price: RM <?php echo number_format($car_price, 2); ?></div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Financing Metrics</div>
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-label">Loan Duration</div>
                <div class="metric-value">
                    <?php if ($loan_years > 0): ?>
                        <?php echo $loan_years; ?> Years <span style="font-size: 14px; color:#64748b; font-weight: normal;">(<?php echo $total_months; ?> Months)</span>
                    <?php else: ?>
                        <span style="color: #94a3b8; font-weight: 500; font-size: 18px;">Not Available</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Total Financing Paid</div>
                <div class="metric-value green">RM <?php echo number_format($total_paid, 2); ?></div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Remaining Balance</div>
                <div class="metric-value blue">RM <?php echo number_format($remaining_balance, 2); ?></div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Installment Progress</div>
                <div class="progress-container">
                    <?php if ($total_months > 0): ?>
                        <div class="progress-text">
                            <span><?php echo $completed_installments; ?> / <?php echo $total_months; ?> Months</span>
                            <span><?php echo round(($completed_installments / $total_months) * 100); ?>%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <?php $bar_width = ($completed_installments / $total_months) * 100; ?>
                            <div class="progress-bar-fill" style="width: <?php echo $bar_width; ?>%;"></div>
                        </div>
                    <?php else: ?>
                        <div style="color: #94a3b8; font-size: 14px; font-style: italic; margin-top: 5px;">Awaiting loan timeline initialization</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Payment History Ledger</div>
        <?php if (count($payment_history) > 0): ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date Processed</th>
                        <th>Transaction Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_history as $payment): ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td>
                            <td style="font-weight: 500; text-transform: capitalize;"><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                            <td style="font-weight: 600;">RM <?php echo number_format($payment['payment_amount'], 2); ?></td>
                            <td>
                                <?php 
                                    $p_status = strtolower($payment['payment_status'] ?? 'pending');
                                    if ($p_status === 'paid') {
                                        echo '<span class="status-badge status-paid">Paid</span>';
                                    } elseif ($p_status === 'pending') {
                                        echo '<span class="status-badge status-pending">Pending</span>';
                                    } else {
                                        echo '<span class="status-badge status-failed">Failed</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-history">No payment records found yet.</div>
        <?php endif; ?>
    </div>

    <div class="card" style="border: none; padding: 0; background: transparent;">
        <?php if ($remaining_balance <= 0): ?>
            <div class="fully-paid-banner">
                🎉 This Vehicle Financing Contract is Fully Settled!
            </div>
        <?php else: ?>
            <div class="payment-panel">
                <div>
                    <div class="payment-info-title">Fixed Scheduled Installment</div>
                    <div class="payment-info-price">RM <?php echo number_format($monthly_payment, 2); ?> <span style="font-size: 14px; color: #64748b; font-weight: normal;">/ month</span></div>
                </div>
                
                <form action="payment.php" method="POST">
                    <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                    <button type="submit" class="btn-submit">
                        Proceed to Payment →
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
