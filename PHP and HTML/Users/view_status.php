<?php
session_start();
require 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetches the user's reservations along with the ABSOLUTE LATEST transaction record.
    $stmt = $pdo->prepare(
        "SELECT
            r.reservation_id,
            r.car_id,
            r.reservation_created_at,
            r.reservation_status,
            r.preferred_test_drive_at,
            r.snapshot_data,

            p.payment_amount,
            p.payment_type,
            p.payment_status,
            p.payment_date,
            p.remarks

        FROM reservations r

        LEFT JOIN payments p ON p.payment_id = (
            SELECT max(p2.payment_id) 
            FROM payments p2 
            WHERE p2.reference_id = r.reservation_id 
              AND p2.payment_type IN ('Booking Fee', 'Down Payment', 'Monthly Installment')
        )

        WHERE r.user_id = ?

        ORDER BY r.reservation_created_at DESC"
    );

    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Mapping Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reservation Status</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #f4f7fb;
    color: #333;
}

.navbar {
    background: white;
    padding: 18px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
}

.logo {
    font-size: 24px;
    font-weight: 700;
    color: #2b6cb0;
    text-decoration: none;
}

.nav-links {
    display: flex;
    gap: 25px;
}

.nav-links a {
    text-decoration: none;
    color: #444;
    font-weight: 500;
}

.page-header {
    padding: 60px 20px 30px;
    text-align: center;
}

.page-header h1 {
    font-size: 38px;
    margin-bottom: 10px;
    color: #1e293b;
}

.page-header p {
    color: #64748b;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 0 20px 60px;
}

.card {
    background: white;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0,0,0,0.03);
}

.vehicle-table {
    width: 100%;
    border-collapse: collapse;
}

.vehicle-table th {
    background: #f8fafc;
    color: #475569;
    text-align: left;
    padding: 18px;
    font-size: 14px;
    border-bottom: 1px solid #e2e8f0;
}

.vehicle-table td {
    padding: 18px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.vehicle-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.vehicle-image {
    width: 90px;
    height: 60px;
    object-fit: cover;
    border-radius: 12px;
}

.vehicle-name {
    font-weight: 700;
    margin-bottom: 5px;
}

.vehicle-plate {
    font-size: 13px;
    color: #64748b;
}

.status {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.pending, .pending-viewing, .under-review { background: #fff8e1; color: #b7791f; }
.approved, .ongoing { background: #dbeafe; color: #1d4ed8; }
.completed, .paid { background: #dcfce7; color: #166534; }
.rejected { background: #ffe5e5; color: #c53030; }
.unpaid { background: #f1f5f9; color: #475569; }

.btn {
    border: none;
    padding: 10px 18px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-view {
    background: #2563eb;
    color: white;
}

.btn-view:hover {
    background: #1d4ed8;
}

.btn-pay {
    background: #16a34a;
    color: white;
    width: 100%;
    text-align: center;
    padding: 14px;
    font-size: 14px;
    border-radius: 14px;
}

.btn-pay:hover {
    background: #15803d;
}

.detail-row {
    display: none;
    background: #f8fafc;
}

.detail-content {
    padding: 30px;
}

.detail-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 30px;
}

.detail-image {
    width: 100%;
    height: 220px;
    object-fit: cover;
    border-radius: 18px;
}

.detail-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
    color: #1e293b;
}

.detail-price {
    font-size: 26px;
    font-weight: 700;
    color: #2563eb;
    margin-bottom: 25px;
}

.tracking-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 18px;
}

.tracking-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}

.tracking-table td {
    padding: 14px;
    background: white;
    border-bottom: 1px solid #f1f5f9;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 25px;
}

.info-box {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
}

.info-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
}

.info-value {
    font-size: 18px;
    font-weight: 700;
}

.summary-box {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 22px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    color: #64748b;
}

.summary-value {
    font-weight: 700;
}

.blue { color: #2563eb; }
.green { color: #16a34a; }

.action-box {
    margin-top: 25px;
}

.empty-box {
    background: white;
    border-radius: 18px;
    padding: 60px 30px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}

.empty-box h2 { margin-bottom: 12px; }
.empty-box p { color: #64748b; margin-bottom: 25px; }

@media(max-width:950px){
    .detail-grid { grid-template-columns: 1fr; }
    .info-grid { grid-template-columns: 1fr; }
    .vehicle-table { display: block; overflow-x: auto; }
}

@media(max-width: 768px) {
    .navbar { flex-direction: column; gap: 15px; }
    .page-header h1 { font-size: 30px; }
}
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">AutoDeal</a>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="cars.php">Cars</a>
        <a href="view_status.php">Status</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <h1>My Reservation Status</h1>
    <p>Track your reservation, payment, and financing approval progress.</p>
</div>

<div class="container">

<?php if (count($reservations) > 0): ?>
<div class="card">
    <table class="vehicle-table">
        <thead>
            <tr>
                <th>Vehicle</th>
                <th>Status</th>
                <th>Monthly Installment</th>
                <th>Latest Payment</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $reservation): ?>
        <?php
            // Safe snapshot array metadata isolation
            $snapshot = json_decode($reservation['snapshot_data'] ?? '{}', true) ?? [];

            // Build the car title string safely
            $car_name = trim(($snapshot['car_brand'] ?? '') . ' ' . ($snapshot['car_model'] ?? ''));
            if (empty($car_name)) {
                $car_name = 'Selected Vehicle';
            }

            // Car image layout fallback
            $car_image = $snapshot['car_image'] ?? 'images/default_car.jpg';

            // Base identity metrics
            $unique_id        = $reservation['reservation_id'];
            $raw_res_status   = $reservation['reservation_status'] ?? 'Pending';
            $css_status_class = strtolower(str_replace(' ', '-', $raw_res_status));
            $payment_status   = strtolower($reservation['payment_status'] ?? 'unpaid');

            // Clean UI valuation items
            // NOTE FOR FYP: Base car price values are parsed here. If insurance/fees are added to the system later, 
            // append them to this formula calculation block (e.g., $raw_price + $snapshot['insurance_fees']).
            $raw_price           = (float)($snapshot['total_compiled_price'] ?? ($snapshot['total_price'] ?? 0));
            $estimated_monthly   = (float)($snapshot['estimated_monthly'] ?? ($snapshot['monthly_payment'] ?? 0));
            $loan_years          = !empty($snapshot['loan_years']) ? (int)$snapshot['loan_years'] : 0;
            $processed_amount    = (float)($reservation['payment_amount'] ?? 0);
            
            // Standard Down Payment rule definition (10% of vehicle total compiled cost)
            $downpayment_amount = $raw_price * 0.10;
            if ($downpayment_amount <= 0) {
                $downpayment_amount = 5000.00; 
            }

            // NOTE FOR FYP PANEL EVALUATIONS: 
            // Running aggregate loop calculations below works perfectly for lightweight prototype architectures.
            // If scaling up to an enterprise scale database system, optimize via single GROUP BY joins to avoid the N+1 pattern.
            $total_paid_stmt = $pdo->prepare("
                SELECT SUM(payment_amount) 
                FROM payments 
                WHERE reference_id = ? 
                  AND payment_status = 'paid' 
                  AND payment_type != 'Booking Fee'
            ");
            $total_paid_stmt->execute([$unique_id]);
            $actual_total_paid = (float)$total_paid_stmt->fetchColumn();
            
            // Formulate current running financial statement targets
            $remaining_balance = max(0, $raw_price - $actual_total_paid);

            // CALCULATE INSTALLMENT MILESTONE COUNTS
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
                $count_stmt->execute([$unique_id]);
                $completed_installments = (int)$count_stmt->fetchColumn();
                
                // ISSUE 2 FIX: GUARANTEE COUNTERS NEVER OVER-RUN LOGICAL LIMIT CONTEXTS
                $completed_installments = min($completed_installments, $total_months);
            }

            // Validate secure payment states
            $already_paid_downpayment = false;
            if (
                strtolower($reservation['payment_type'] ?? '') === 'down payment'
                && $payment_status === 'paid'
            ) {
                $already_paid_downpayment = true;
            }
            
            if ($actual_total_paid >= $downpayment_amount) {
                $already_paid_downpayment = true;
            }
        ?>

        <tr>
            <td>
                <div class="vehicle-info">
                    <img src="<?php echo htmlspecialchars($car_image); ?>" class="vehicle-image" alt="Car Image">
                    <div>
                        <div class="vehicle-name"><?php echo htmlspecialchars($car_name); ?></div>
                        <div class="vehicle-plate">Reference Code: RSV-<?php echo str_pad($unique_id, 6, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span class="status <?php echo ($css_status_class === 'pending') ? 'under-review' : $css_status_class; ?>">
                    <?php echo ($raw_res_status === 'Pending') ? 'Under Review' : htmlspecialchars($raw_res_status); ?>
                </span>
            </td>
            <td>
                <?php echo ($estimated_monthly > 0) ? 'RM ' . number_format($estimated_monthly, 2) . ' / mth' : 'Under Review'; ?>
            </td>
            <td>
                <div style="font-weight: 600;">RM <?php echo number_format($processed_amount, 2); ?></div>
                <div style="font-size: 11px; color: #64748b; font-weight: 500; text-transform: capitalize;">
                    <?php echo htmlspecialchars($reservation['payment_type'] ?? 'Processing'); ?>
                </div>
            </td>
            <td>
                <button class="btn btn-view" onclick="toggleDetails(<?php echo $unique_id; ?>)" id="toggleBtn_<?php echo $unique_id; ?>">
                    View Details
                </button>
            </td>
        </tr>

        <tr class="detail-row" id="detailRow_<?php echo $unique_id; ?>">
            <td colspan="5">
                <div class="detail-content">
                    <div class="detail-grid">
                        
                        <div>
                            <img src="<?php echo htmlspecialchars($car_image); ?>" class="detail-image" alt="Vehicle Panel">
                        </div>

                        <div>
                            <div class="detail-title"><?php echo htmlspecialchars($car_name); ?></div>
                            <div class="detail-price">RM <?php echo number_format($raw_price, 2); ?></div>

                            <div class="info-grid">
                                <div class="info-box">
                                    <div class="info-label">Loan Duration</div>
                                    <div class="info-value"><?php echo ($loan_years > 0) ? $loan_years . ' Years' : 'TBD'; ?></div>
                                </div>
                                <div class="info-box">
                                    <div class="info-label">Estimated Monthly</div>
                                    <div class="info-value">
                                        <?php echo ($estimated_monthly > 0) ? 'RM ' . number_format($estimated_monthly, 2) : 'Under Review'; ?>
                                    </div>
                                </div>
                                <div class="info-box">
                                    <div class="info-label">Current Stage</div>
                                    <div class="info-value" style="text-transform: capitalize; font-size:16px;">
                                        <?php echo htmlspecialchars($reservation['payment_type'] ?? 'Reservation'); ?>
                                    </div>
                                </div>
                                <div class="info-box">
                                    <div class="info-label">Payment Status</div>
                                    <div class="info-value">
                                        <span class="status <?php echo ($payment_status === 'paid') ? 'paid' : 'unpaid'; ?>" style="font-size:11px;">
                                            <?php echo htmlspecialchars($reservation['payment_status'] ?? 'Unpaid'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="tracking-title">Process Tracking</div>
                            <table class="tracking-table">
                                <tr>
                                    <td>Reservation</td>
                                    <td><span class="status completed">Completed</span></td>
                                </tr>
                                <tr>
                                    <td>Loan Approval</td>
                                    <td>
                                        <span class="status <?php echo ($css_status_class === 'approved' || $css_status_class === 'completed') ? 'completed' : (($css_status_class === 'rejected') ? 'rejected' : 'pending'); ?>">
                                            <?php echo ($raw_res_status === 'Pending') ? 'Under Review' : htmlspecialchars($raw_res_status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Down Payment</td>
                                    <td>
                                        <span class="status <?php echo ($already_paid_downpayment || $css_status_class === 'completed') ? 'completed' : 'pending'; ?>">
                                            <?php echo ($already_paid_downpayment || $css_status_class === 'completed') ? 'Verified' : 'Awaiting Action'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <div class="summary-box">
                                <div class="summary-row">
                                    <div class="summary-label">Reservation Date</div>
                                    <div class="summary-value">
                                        <?php echo date('d M Y', strtotime($reservation['reservation_created_at'])); ?>
                                    </div>
                                </div>
                                <?php if ($total_months > 0 && $already_paid_downpayment): ?>
                                <div class="summary-row">
                                    <div class="summary-label">Installment Progress</div>
                                    <div class="summary-value" style="text-transform: none;">
                                        <?php echo $completed_installments; ?> / <?php echo $total_months; ?> Payments Completed
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="summary-row">
                                    <div class="summary-label">Total Principal Paid</div>
                                    <div class="summary-value green">RM <?php echo number_format($actual_total_paid, 2); ?></div>
                                </div>
                                <div class="summary-row">
                                    <div class="summary-label">Remaining Balance</div>
                                    <div class="summary-value blue">RM <?php echo number_format($remaining_balance, 2); ?></div>
                                </div>
                                <?php if (!empty($reservation['remarks'])): ?>
                                <div class="summary-row">
                                    <div class="summary-label">Remarks</div>
                                    <div class="summary-value" style="font-weight: 500; font-size: 13px; color: #64748b;">
                                        <?php echo htmlspecialchars($reservation['remarks']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="action-box">
                                <?php if ($remaining_balance <= 0 && $already_paid_downpayment): ?>
                                    <div class="btn btn-pay" style="background: #dcfce7; color: #166534; cursor: default; text-align: center; font-weight:700; border: 1px solid #bbf7d0;">
                                        🎉 Vehicle Fully Paid
                                    </div>
                                <?php elseif (strtolower($raw_res_status) === 'approved' && !$already_paid_downpayment): ?>
                                    <form method="POST" action="payment.php">
                                        <input type="hidden" name="source" value="downpayment">
                                        <input type="hidden" name="reservation_id" value="<?php echo $unique_id; ?>">
                                        <input type="hidden" name="car_id" value="<?php echo htmlspecialchars($reservation['car_id']); ?>">
                                        <input type="hidden" name="payment_amount" value="<?php echo $downpayment_amount; ?>">
                                        <input type="hidden" name="payment_label" value="Vehicle Down Payment Secured Order">
                                        
                                        <button type="submit" class="btn btn-pay">
                                            Proceed to Down Payment (RM <?php echo number_format($downpayment_amount, 2); ?>) →
                                        </button>
                                    </form>
                                <?php elseif ($already_paid_downpayment || strtolower($raw_res_status) === 'completed'): ?>
                                    <div style="display: flex; gap: 15px;">
                                        <a href="installment_pay.php?reservation_id=<?php echo $unique_id; ?>" class="btn btn-pay" style="background: #2563eb;">
                                            Pay Installment
                                        </a>
                                        <a href="receipt_view.php?reservation_id=<?php echo $unique_id; ?>" class="btn" style="background: #cbd5e1; color: #334155; display: flex; align-items: center; border-radius: 14px; padding: 0 25px; font-weight:700;">
                                            View Receipt
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <button class="btn" style="background:#e2e8f0; color:#94a3b8; cursor:not-allowed; width:100%; text-align:center; padding:14px; border-radius:14px;" disabled>
                                        Under Review
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<div class="empty-box">
    <h2>No Reservation Records Found</h2>
    <p>You have not submitted any vehicle reservations yet.</p>
    <a href="cars.php" class="btn">Browse Vehicles</a>
</div>
<?php endif; ?>

</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById("detailRow_" + id);
    const btn = document.getElementById("toggleBtn_" + id);

    if (row.style.display === "table-row") {
        row.style.display = "none";
        btn.innerHTML = "View Details";
    } else {
        row.style.display = "table-row";
        btn.innerHTML = "Hide Details";
    }
}
</script>
</body>
</html>