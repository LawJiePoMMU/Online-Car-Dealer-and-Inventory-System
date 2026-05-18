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
    // This prevents the card from getting stuck on an old 'Booking Fee' row once a 'Down Payment' is made!
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
              AND p2.payment_type IN ('Booking Fee', 'Down Payment')
        )

        WHERE r.user_id = ?

        ORDER BY r.reservation_created_at DESC"
    );

    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll();

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

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
}

.status-card {
    background: white;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    border-top: 6px solid #2b6cb0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.vehicle-title {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 8px;
    color: #1e293b;
}

.vehicle-sub {
    color: #64748b;
    margin-bottom: 25px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid #edf2f7;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #64748b;
    font-size: 14px;
}

.info-value {
    font-weight: 600;
    text-align: right;
}

.status-badge {
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
}

.pending,
.pending-viewing {
    background: #fff8e1;
    color: #b7791f;
}

.approved {
    background: #e6ffed;
    color: #2f855a;
}

.rejected {
    background: #ffe5e5;
    color: #c53030;
}

.paid {
    background: #e6ffed;
    color: #2f855a;
}

.unpaid {
    background: #f1f5f9;
    color: #475569;
}

.action-box {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 2px dashed #edf2f7;
}

.btn-proceed {
    display: block;
    width: 100%;
    text-align: center;
    background: #28a745;
    color: white;
    padding: 14px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(40,167,69,0.2);
    transition: 0.2s ease;
}

.btn-proceed:hover {
    background: #218838;
}

.completed-payment {
    background: #ecfdf3;
    color: #166534;
    padding: 14px;
    border-radius: 10px;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
}

.empty-box {
    background: white;
    border-radius: 18px;
    padding: 60px 30px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}

.empty-box h2 {
    margin-bottom: 12px;
}

.empty-box p {
    color: #64748b;
    margin-bottom: 25px;
}

.btn {
    display: inline-block;
    background: #2b6cb0;
    color: white;
    padding: 14px 26px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
}

.btn:hover {
    background: #1e4f86;
}

@media(max-width: 768px) {
    .status-grid {
        grid-template-columns: 1fr;
    }
    .navbar {
        flex-direction: column;
        gap: 15px;
    }
    .page-header h1 {
        font-size: 30px;
    }
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
<div class="status-grid">

<?php foreach ($reservations as $reservation): ?>
<?php
    // Safe snapshot array metadata isolation
    $snapshot = json_decode($reservation['snapshot_data'], true);

    // Build the car title string safely
    $car_name = trim(($snapshot['car_brand'] ?? '') . ' ' . ($snapshot['car_model'] ?? ''));
    if (empty($car_name)) {
        $car_name = 'Selected Vehicle';
    }

    // Reservation string mapping 
    $raw_res_status = $reservation['reservation_status'] ?? 'Pending';
    $css_status_class = strtolower(str_replace(' ', '-', $raw_res_status));

    // Evaluate basic payment presence flags
    $payment_status = strtolower($reservation['payment_status'] ?? 'unpaid');

    // Calculate down payment amounts safely (10% of total asset compiled value)
    $raw_price = (float)($snapshot['total_compiled_price'] ?? 0);
    $downpayment_amount = $raw_price * 0.10;
    if ($downpayment_amount <= 0) {
        $downpayment_amount = 5000.00; // Development presentation layer fallback
    }

    // Check if the current user has already executed their down payment checkout step
    $already_paid_downpayment = false;
    if (
        strtolower($reservation['payment_type'] ?? '') === 'down payment'
        && strtolower($reservation['payment_status'] ?? '') === 'paid'
    ) {
        $already_paid_downpayment = true;
    }
?>

<div class="status-card">
    <div>
        <div class="vehicle-title">
            <?php echo htmlspecialchars($car_name); ?>
        </div>

        <div class="vehicle-sub">
            Reference Code:
            <strong>RSV-<?php echo str_pad($reservation['reservation_id'], 6, '0', STR_PAD_LEFT); ?></strong>
        </div>

        <div class="info-row">
            <div class="info-label">Application Status</div>
            <div class="info-value">
                <span class="status-badge <?php echo $css_status_class; ?>">
                    <?php echo htmlspecialchars($raw_res_status); ?>
                </span>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Payment Status</div>
            <div class="info-value">
                <span class="status-badge <?php echo ($payment_status === 'paid') ? 'paid' : 'unpaid'; ?>">
                    <?php echo htmlspecialchars($reservation['payment_status'] ?? 'Unpaid'); ?>
                </span>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Payment Type</div>
            <div class="info-value">
                <?php echo htmlspecialchars($reservation['payment_type'] ?? '-'); ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Amount Processed</div>
            <div class="info-value">
                RM <?php echo number_format((float)($reservation['payment_amount'] ?? 0), 2); ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Reservation Date</div>
            <div class="info-value">
                <?php echo date('d M Y', strtotime($reservation['reservation_created_at'])); ?>
            </div>
        </div>

        <?php if (!empty($snapshot['loan_years'])): ?>
        <div class="info-row">
            <div class="info-label">Loan Duration</div>
            <div class="info-value">
                <?php echo htmlspecialchars($snapshot['loan_years']); ?> Years
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($snapshot['estimated_monthly'])): ?>
        <div class="info-row">
            <div class="info-label">Estimated Monthly</div>
            <div class="info-value">
                RM <?php echo number_format((float)$snapshot['estimated_monthly'], 2); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (strtolower($raw_res_status) === 'approved'): ?>
    <div class="action-box">
        <?php if (!$already_paid_downpayment): ?>
        <form method="POST" action="payment.php">

            <input type="hidden" name="source" value="downpayment">
            
            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">

            <input type="hidden" name="car_id" value="<?php echo htmlspecialchars($reservation['car_id']); ?>">
            <input type="hidden" name="payment_amount" value="<?php echo $downpayment_amount; ?>">
            <input type="hidden" name="payment_label" value="Vehicle Down Payment Secured Order">

            <button type="submit" class="btn-proceed">
                Proceed To Down Payment (RM <?php echo number_format($downpayment_amount, 2); ?>)
            </button>

        </form>
        <?php else: ?>
        <div class="completed-payment">
            ✅ Down Payment Completed
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php endforeach; ?>
</div>

<?php else: ?>
<div class="empty-box">
    <h2>No Reservation Records Found</h2>
    <p>You have not submitted any vehicle reservations yet.</p>
    <a href="cars.php" class="btn">Browse Vehicles</a>
</div>
<?php endif; ?>

</div>

</body>
</html>
