<?php

session_start();

require '../Config/database.php';

// ======================================================
// SESSION SECURITY
// ======================================================

if (!isset($_SESSION['user_id'])) {

    header("Location: Auth/login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

// ======================================================
// VALIDATE BOOKING ID
// ======================================================

$booking_id =
$_GET['booking_id']
?? $_POST['booking_id']
?? 0;

if ($booking_id <= 0) {

    die("Invalid booking reference.");

}

// ======================================================
// FETCH BOOKING
// ======================================================

$sql = "
    SELECT *
    FROM bookings
    WHERE booking_id = ?
    AND user_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {

    die(
        "Database Error: "
        . mysqli_error($conn)
    );

}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $booking_id,
    $user_id
);

mysqli_stmt_execute($stmt);

$result =
mysqli_stmt_get_result($stmt);

if (
    mysqli_num_rows($result)
    <= 0
) {

    die(
        "Booking record not found."
    );

}

$booking =
mysqli_fetch_assoc($result);

// ======================================================
// SNAPSHOT DATA
// ======================================================

$snapshot = json_decode(
    $booking['snapshot_data']
    ?? '{}',
    true
);

if (!is_array($snapshot)) {

    $snapshot = [];

}

// ======================================================
// VEHICLE DETAILS
// ======================================================

$car_brand =
$snapshot['car_brand']
?? '';

$car_model =
$snapshot['car_model']
?? '';

$car_name =
trim(
    $car_brand
    . ' ' .
    $car_model
);

if (empty($car_name)) {

    $car_name =
    'Selected Vehicle';

}

$car_price =
(float)(
    $snapshot['total_compiled_price']
    ?? $snapshot['total_price']
    ?? 0
);

if ($car_price <= 0) {

    $car_price = 50000;

}

$car_image =
$snapshot['car_image']
?? '../Assets/default-car.jpg';

$loan_years =
(int)(
    $snapshot['loan_years']
    ?? 0
);

$monthly_payment =
(float)(
    $snapshot['estimated_monthly']
    ?? $snapshot['monthly_payment']
    ?? 0
);

if ($monthly_payment <= 0) {

    $monthly_payment = 1000;

}

// ======================================================
// BOOKING STATUS CHECK
// ======================================================

$booking_status =
strtolower(
    $booking['booking_status']
    ?? 'pending'
);

if ($booking_status === 'rejected') {

    die(
        "Financing application rejected."
    );

}

// ======================================================
// CHECK DOWNPAYMENT
// ======================================================

$downpayment_exists = false;

$downpayment_sql = "
    SELECT payment_id
    FROM payments
    WHERE reference_id = ?
    AND payment_type = 'Down Payment'
    AND payment_status = 'Paid'
    LIMIT 1
";

$downpayment_stmt =
mysqli_prepare(
    $conn,
    $downpayment_sql
);

mysqli_stmt_bind_param(
    $downpayment_stmt,
    "i",
    $booking_id
);

mysqli_stmt_execute(
    $downpayment_stmt
);

$downpayment_result =
mysqli_stmt_get_result(
    $downpayment_stmt
);

if (
    mysqli_num_rows(
        $downpayment_result
    ) > 0
) {

    $downpayment_exists = true;

}

if (!$downpayment_exists) {

    die(
        "Downpayment required before installment access."
    );

}

// ======================================================
// TOTAL PAID
// EXCLUDING BOOKING FEE
// ======================================================

$total_paid_sql = "
    SELECT
    SUM(payment_amount)
    AS total_paid

    FROM payments

    WHERE reference_id = ?
    AND payment_status = 'Paid'
    AND payment_type != 'Booking Fee'
";

$total_paid_stmt =
mysqli_prepare(
    $conn,
    $total_paid_sql
);

mysqli_stmt_bind_param(
    $total_paid_stmt,
    "i",
    $booking_id
);

mysqli_stmt_execute(
    $total_paid_stmt
);

$total_paid_result =
mysqli_stmt_get_result(
    $total_paid_stmt
);

$total_paid_row =
mysqli_fetch_assoc(
    $total_paid_result
);

$total_paid =
(float)(
    $total_paid_row['total_paid']
    ?? 0
);

// ======================================================
// REMAINING BALANCE
// ======================================================

$remaining_balance =
max(
    0,
    $car_price - $total_paid
);

// ======================================================
// INSTALLMENT PROGRESS
// ======================================================

$total_months =
$loan_years * 12;

$completed_installments = 0;

if ($total_months > 0) {

    $count_sql = "
        SELECT COUNT(*)
        AS total_installments

        FROM payments

        WHERE reference_id = ?
        AND payment_status = 'Paid'
        AND payment_type = 'Monthly Installment'
    ";

    $count_stmt =
    mysqli_prepare(
        $conn,
        $count_sql
    );

    mysqli_stmt_bind_param(
        $count_stmt,
        "i",
        $booking_id
    );

    mysqli_stmt_execute(
        $count_stmt
    );

    $count_result =
    mysqli_stmt_get_result(
        $count_stmt
    );

    $count_row =
    mysqli_fetch_assoc(
        $count_result
    );

    $completed_installments =
    (int)(
        $count_row['total_installments']
        ?? 0
    );

    $completed_installments =
    min(
        $completed_installments,
        $total_months
    );

}

// ======================================================
// PAYMENT HISTORY
// ======================================================

$history_sql = "
    SELECT *
    FROM payments
    WHERE reference_id = ?
    ORDER BY payment_date DESC
";

$history_stmt =
mysqli_prepare(
    $conn,
    $history_sql
);

mysqli_stmt_bind_param(
    $history_stmt,
    "i",
    $booking_id
);

mysqli_stmt_execute(
    $history_stmt
);

$history_result =
mysqli_stmt_get_result(
    $history_stmt
);

$payment_history = [];

while (
    $payment =
    mysqli_fetch_assoc(
        $history_result
    )
) {

    $payment_history[] =
    $payment;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

Financing & Installments -
<?php echo htmlspecialchars($car_name); ?>

</title>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet"
>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:#f4f7fb;
    color:#333;
    padding-bottom:60px;
}

.navbar{
    background:white;
    padding:18px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 12px rgba(0,0,0,0.05);
}

.logo{
    font-size:24px;
    font-weight:700;
    color:#2563eb;
    text-decoration:none;
}

.nav-links a{
    text-decoration:none;
    color:#444;
    margin-left:25px;
    font-weight:500;
}

.container{
    max-width:1100px;
    margin:40px auto;
    padding:0 20px;
}

.back-link{
    display:inline-block;
    margin-bottom:20px;
    color:#2563eb;
    text-decoration:none;
    font-weight:600;
}

.card{
    background:white;
    border-radius:22px;
    padding:30px;
    margin-bottom:30px;
    border:1px solid #e2e8f0;
    box-shadow:0 4px 20px rgba(0,0,0,0.03);
}

.vehicle-header{
    display:flex;
    gap:25px;
    align-items:center;
}

.vehicle-image{
    width:180px;
    height:120px;
    object-fit:cover;
    border-radius:16px;
}

.vehicle-title{
    font-size:28px;
    font-weight:700;
    color:#1e293b;
    margin-bottom:8px;
}

.vehicle-meta{
    color:#64748b;
    font-size:14px;
    margin-top:4px;
}

.section-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:25px;
    color:#1e293b;
    border-left:4px solid #2563eb;
    padding-left:12px;
}

.metrics-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}

.metric-box{
    background:#f8fafc;
    border-radius:18px;
    padding:22px;
    border:1px solid #e2e8f0;
}

.metric-label{
    color:#64748b;
    font-size:14px;
    margin-bottom:8px;
}

.metric-value{
    font-size:26px;
    font-weight:700;
}

.green{
    color:#16a34a;
}

.blue{
    color:#2563eb;
}

.progress-text{
    display:flex;
    justify-content:space-between;
    margin-bottom:10px;
    font-size:14px;
    font-weight:600;
}

.progress-bar-bg{
    height:12px;
    background:#e2e8f0;
    border-radius:50px;
    overflow:hidden;
}

.progress-bar-fill{
    height:100%;
    background:#16a34a;
    border-radius:50px;
}

.history-table{
    width:100%;
    border-collapse:collapse;
}

.history-table th{
    background:#f8fafc;
    padding:15px;
    text-align:left;
    font-size:13px;
    color:#64748b;
}

.history-table td{
    padding:15px;
    border-bottom:1px solid #f1f5f9;
    font-size:14px;
}

.status-badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:50px;
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
}

.status-paid{
    background:#dcfce7;
    color:#166534;
}

.status-pending{
    background:#fef3c7;
    color:#92400e;
}

.status-failed{
    background:#fee2e2;
    color:#991b1b;
}

.payment-panel{
    background:white;
    border-radius:22px;
    padding:30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 20px rgba(0,0,0,0.03);
}

.payment-info-title{
    color:#64748b;
    font-size:14px;
    margin-bottom:6px;
}

.payment-info-price{
    font-size:32px;
    font-weight:700;
    color:#1e293b;
}

.btn-submit{
    background:#16a34a;
    color:white;
    border:none;
    padding:16px 36px;
    border-radius:14px;
    font-size:15px;
    font-weight:600;
    cursor:pointer;
    transition:0.2s;
}

.btn-submit:hover{
    background:#15803d;
}

.fully-paid-banner{
    background:#dcfce7;
    border:1px solid #bbf7d0;
    color:#166534;
    text-align:center;
    padding:20px;
    font-size:18px;
    font-weight:700;
    border-radius:16px;
}

.no-history{
    text-align:center;
    color:#64748b;
    padding:30px 0;
    font-style:italic;
}

@media(max-width:768px){

    .vehicle-header{
        flex-direction:column;
        text-align:center;
    }

    .metrics-grid{
        grid-template-columns:1fr;
    }

    .payment-panel{
        flex-direction:column;
        gap:20px;
        text-align:center;
    }

    .btn-submit{
        width:100%;
    }

}

</style>

</head>

<body>

<nav class="navbar">

<a href="index.php"
class="logo">

AutoDeal

</a>

<div class="nav-links">

<a href="index.php">
Home
</a>

<a href="view_status.php">
My Status
</a>

<a href="logout.php">
Logout
</a>

</div>

</nav>

<div class="container">

<a href="view_status.php"
class="back-link">

← Back to Dashboard

</a>

<!-- VEHICLE CARD -->

<div class="card vehicle-header">

<img
src="<?php echo htmlspecialchars($car_image); ?>"
onerror="this.src='../Assets/default-car.jpg';"
class="vehicle-image"
>

<div>

<div class="vehicle-title">

<?php
echo htmlspecialchars($car_name);
?>

</div>

<div class="vehicle-meta">

Account Reference:
BK-<?php
echo str_pad(
    $booking_id,
    6,
    '0',
    STR_PAD_LEFT
);
?>

</div>

<div class="vehicle-meta">

Vehicle Price:
RM <?php
echo number_format(
    $car_price,
    2
);
?>

</div>

</div>

</div>

<!-- METRICS -->

<div class="card">

<div class="section-title">

Financing Metrics

</div>

<div class="metrics-grid">

<div class="metric-box">

<div class="metric-label">

Loan Duration

</div>

<div class="metric-value">

<?php
echo $loan_years;
?> Years

</div>

</div>

<div class="metric-box">

<div class="metric-label">

Total Financing Paid

</div>

<div class="metric-value green">

RM <?php
echo number_format(
    $total_paid,
    2
);
?>

</div>

</div>

<div class="metric-box">

<div class="metric-label">

Remaining Balance

</div>

<div class="metric-value blue">

RM <?php
echo number_format(
    $remaining_balance,
    2
);
?>

</div>

</div>

<div class="metric-box">

<div class="metric-label">

Installment Progress

</div>

<?php if ($total_months > 0): ?>

<div class="progress-text">

<span>

<?php
echo $completed_installments;
?>

/

<?php
echo $total_months;
?>

Months

</span>

<span>

<?php
echo round(
    (
        $completed_installments
        /
        $total_months
    ) * 100
);
?>%

</span>

</div>

<div class="progress-bar-bg">

<div
class="progress-bar-fill"
style="
width:
<?php
echo (
    $completed_installments
    /
    $total_months
) * 100;
?>%;
"
>

</div>

</div>

<?php else: ?>

<div
style="
margin-top:10px;
font-size:14px;
color:#94a3b8;
"
>

Awaiting financing setup

</div>

<?php endif; ?>

</div>

</div>

</div>

<!-- PAYMENT HISTORY -->

<div class="card">

<div class="section-title">

Payment History Ledger

</div>

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

<?php

$p_status =
strtolower(
    $payment['payment_status']
    ?? 'pending'
);

?>

<tr>

<td>

<?php
echo date(
    'd M Y, h:i A',
    strtotime(
        $payment['payment_date']
    )
);
?>

</td>

<td
style="
font-weight:600;
"
>

<?php
echo htmlspecialchars(
    $payment['payment_type']
);
?>

</td>

<td
style="
font-weight:700;
"
>

RM <?php
echo number_format(
    $payment['payment_amount'],
    2
);
?>

</td>

<td>

<?php if ($p_status === 'paid'): ?>

<span class="status-badge status-paid">

Paid

</span>

<?php elseif ($p_status === 'pending'): ?>

<span class="status-badge status-pending">

Pending

</span>

<?php else: ?>

<span class="status-badge status-failed">

Failed

</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-history">

No payment records available yet.

</div>

<?php endif; ?>

</div>

<!-- MONTHLY PAYMENT -->

<div class="card"
style="
border:none;
padding:0;
background:transparent;
box-shadow:none;
"
>

<?php if ($remaining_balance <= 0): ?>

<div class="fully-paid-banner">

🎉 Vehicle Financing Fully Settled

</div>

<?php else: ?>

<div class="payment-panel">

<div>

<div class="payment-info-title">

Fixed Scheduled Installment

</div>

<div class="payment-info-price">

RM <?php
echo number_format(
    $monthly_payment,
    2
);
?>

<span
style="
font-size:15px;
color:#64748b;
font-weight:500;
"
>

/ month

</span>

</div>

</div>

<form
action="payment.php"
method="POST"
>

<input
type="hidden"
name="booking_id"
value="<?php echo $booking_id; ?>"
>

<input
type="hidden"
name="payment_amount"
value="<?php echo $monthly_payment; ?>"
>

<input
type="hidden"
name="payment_label"
value="Monthly Installment Payment"
>

<input
type="hidden"
name="source"
value="installment"
>

<button
type="submit"
class="btn-submit"
>

Proceed to Payment →

</button>

</form>

</div>

<?php endif; ?>

</div>

</div>

</body>
</html>