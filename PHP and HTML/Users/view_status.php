<?php

session_start();

require '../Config/database.php';

// =====================================================
// 1. SECURITY CHECK
// =====================================================

if (!isset($_SESSION['user_id'])) {

    header("Location: Auth/login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

// =====================================================
// 2. FETCH BOOKINGS + LATEST PAYMENT
// =====================================================

$sql = "
    SELECT
        b.*,

        p.payment_amount,
        p.payment_type,
        p.payment_status,
        p.payment_date,
        p.remarks

    FROM bookings b

    LEFT JOIN payments p
        ON p.payment_id = (

            SELECT MAX(p2.payment_id)

            FROM payments p2

            WHERE p2.reference_id = b.booking_id

        )

    WHERE b.user_id = ?

    ORDER BY b.booking_id DESC
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {

    die(
        "Prepare Failed: "
        . mysqli_error($conn)
    );

}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result =
mysqli_stmt_get_result($stmt);

$bookings = [];

while ($row = mysqli_fetch_assoc($result)) {

    $bookings[] = $row;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    My Purchase Status
</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:#f4f7fb;
    color:#1e293b;
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

.nav-links{
    display:flex;
    gap:25px;
}

.nav-links a{
    text-decoration:none;
    color:#475569;
    font-weight:500;
}

.page-header{
    padding:60px 20px 30px;
    text-align:center;
}

.page-header h1{
    font-size:40px;
    margin-bottom:10px;
}

.page-header p{
    color:#64748b;
}

.container{
    max-width:1200px;
    margin:auto;
    padding:0 20px 60px;
}

.card{
    background:white;
    border-radius:22px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 8px 24px rgba(0,0,0,0.04);
}

.vehicle-table{
    width:100%;
    border-collapse:collapse;
}

.vehicle-table th{
    background:#f8fafc;
    padding:18px;
    text-align:left;
    font-size:14px;
    color:#64748b;
    border-bottom:1px solid #e2e8f0;
}

.vehicle-table td{
    padding:18px;
    border-bottom:1px solid #f1f5f9;
}

.vehicle-info{
    display:flex;
    align-items:center;
    gap:15px;
}

.vehicle-image{
    width:90px;
    height:60px;
    object-fit:cover;
    border-radius:12px;
}

.vehicle-name{
    font-weight:700;
    margin-bottom:4px;
}

.vehicle-ref{
    font-size:13px;
    color:#64748b;
}

.status{
    display:inline-block;
    padding:7px 14px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}

.pending{
    background:#fff8e1;
    color:#b7791f;
}

.review{
    background:#dbeafe;
    color:#1d4ed8;
}

.completed{
    background:#dcfce7;
    color:#166534;
}

.rejected{
    background:#fee2e2;
    color:#b91c1c;
}

.unpaid{
    background:#f1f5f9;
    color:#475569;
}

.btn{
    border:none;
    padding:10px 18px;
    border-radius:12px;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    transition:0.2s;
}

.btn-view{
    background:#2563eb;
    color:white;
}

.btn-view:hover{
    background:#1d4ed8;
}

.btn-pay{
    background:#16a34a;
    color:white;
    width:100%;
    padding:14px;
    border-radius:14px;
    text-align:center;
    font-size:14px;
    font-weight:700;
    text-decoration:none;
    display:inline-block;
    border:none;
    cursor:pointer;
}

.btn-pay:hover{
    background:#15803d;
}

.detail-row{
    display:none;
    background:#f8fafc;
}

.detail-content{
    padding:35px;
}

.detail-grid{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:30px;
}

.detail-image{
    width:100%;
    height:240px;
    object-fit:cover;
    border-radius:18px;
}

.detail-title{
    font-size:30px;
    font-weight:700;
    margin-bottom:10px;
}

.detail-price{
    font-size:28px;
    font-weight:700;
    color:#2563eb;
    margin-bottom:25px;
}

.info-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
    margin-bottom:25px;
}

.info-box{
    background:white;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:18px;
}

.info-label{
    font-size:13px;
    color:#64748b;
    margin-bottom:8px;
}

.info-value{
    font-size:18px;
    font-weight:700;
}

.tracking-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:18px;
}

.tracking-table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:25px;
}

.tracking-table td{
    background:white;
    padding:14px;
    border-bottom:1px solid #f1f5f9;
}

.summary-box{
    background:white;
    border:1px solid #e2e8f0;
    border-radius:18px;
    padding:22px;
}

.summary-row{
    display:flex;
    justify-content:space-between;
    padding:12px 0;
    border-bottom:1px solid #f1f5f9;
}

.summary-row:last-child{
    border-bottom:none;
}

.summary-label{
    color:#64748b;
}

.summary-value{
    font-weight:700;
}

@media(max-width:950px){

    .detail-grid{
        grid-template-columns:1fr;
    }

    .info-grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:768px){

    .navbar{
        flex-direction:column;
        gap:15px;
    }

    .vehicle-table{
        display:block;
        overflow-x:auto;
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

        <a href="cars.php">
            Cars
        </a>

        <a href="view_status.php">
            Status
        </a>

        <a href="logout.php">
            Logout
        </a>

    </div>

</nav>

<div class="page-header">

    <h1>
        My Purchase Status
    </h1>

    <p>
        Track your financing progress,
        loan approval,
        and installment payments.
    </p>

</div>

<div class="container">

<?php if (count($bookings) > 0): ?>

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

<?php foreach ($bookings as $booking): ?>

<?php

$snapshot = json_decode(
    $booking['snapshot_data'] ?? '{}',
    true
);

if (!is_array($snapshot)) {

    $snapshot = [];

}

$car_name =
trim(
    ($snapshot['car_brand'] ?? '')
    . ' ' .
    ($snapshot['car_model'] ?? '')
);

if (empty($car_name)) {

    $car_name = 'Selected Vehicle';

}

$car_image =
!empty($snapshot['car_image'])
? $snapshot['car_image']
: '../Assets/default-car.jpg';

$booking_id =
$booking['booking_id'];

$booking_status =
$booking['booking_status']
?? 'Pending';

$payment_type =
$booking['payment_type']
?? 'No Payment';

$payment_amount =
(float)($booking['payment_amount'] ?? 0);

$payment_status =
strtolower(
    $booking['payment_status']
    ?? 'unpaid'
);

$raw_price =
(float)(
    $snapshot['total_compiled_price']
    ?? $snapshot['total_price']
    ?? 0
);

if ($raw_price <= 0) {

    $raw_price = 50000;

}

$monthly_payment =
(float)(
    $snapshot['estimated_monthly']
    ?? $snapshot['monthly_payment']
    ?? 0
);

if ($monthly_payment <= 0) {

    $monthly_payment = 1000;

}

$loan_years =
(int)(
    $booking['installment_years']
    ?? 0
);

// =====================================================
// TOTAL PAID
// =====================================================

$total_paid_sql = "
    SELECT SUM(payment_amount) AS total_paid
    FROM payments
    WHERE reference_id = ?
    AND payment_status = 'Paid'
";

$total_paid_stmt = mysqli_prepare(
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

$remaining_balance =
max(
    0,
    $raw_price - $total_paid
);

// =====================================================
// BOOKING FEE CHECK
// =====================================================

$booking_fee_exists = false;

$booking_fee_sql = "
    SELECT payment_id
    FROM payments
    WHERE reference_id = ?
    AND payment_type = 'Booking Fee'
    AND payment_status = 'Paid'
    LIMIT 1
";

$booking_fee_stmt = mysqli_prepare(
    $conn,
    $booking_fee_sql
);

mysqli_stmt_bind_param(
    $booking_fee_stmt,
    "i",
    $booking_id
);

mysqli_stmt_execute(
    $booking_fee_stmt
);

$booking_fee_result =
mysqli_stmt_get_result(
    $booking_fee_stmt
);

if (
    mysqli_num_rows(
        $booking_fee_result
    ) > 0
) {

    $booking_fee_exists = true;

}

// =====================================================
// DOWNPAYMENT CHECK
// =====================================================

$downpayment_exists = false;

$downpayment_sql = "
    SELECT payment_id
    FROM payments
    WHERE reference_id = ?
    AND payment_type = 'Down Payment'
    AND payment_status = 'Paid'
    LIMIT 1
";

$downpayment_stmt = mysqli_prepare(
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

// =====================================================
// STATUS CLASS
// =====================================================

$status_class = 'pending';

if (
    strtolower($booking_status)
    === 'approved'
) {

    $status_class = 'completed';

}
elseif (
    strtolower($booking_status)
    === 'completed'
) {

    $status_class = 'completed';

}
elseif (
    strtolower($booking_status)
    === 'rejected'
) {

    $status_class = 'rejected';

}

?>

<tr>

<td>

<div class="vehicle-info">

<img
src="<?php echo htmlspecialchars($car_image); ?>"
class="vehicle-image"
onerror="this.src='../Assets/default-car.jpg';"
>

<div>

<div class="vehicle-name">

<?php
echo htmlspecialchars($car_name);
?>

</div>

<div class="vehicle-ref">

Booking ID:
#<?php echo $booking_id; ?>

</div>

</div>

</div>

</td>

<td>

<span class="status <?php echo $status_class; ?>">

<?php
echo htmlspecialchars($booking_status);
?>

</span>

</td>

<td>

RM <?php
echo number_format(
    $monthly_payment,
    2
);
?> / mth

</td>

<td>

<div style="font-weight:700;">

RM <?php
echo number_format(
    $payment_amount,
    2
);
?>

</div>

<div
style="
font-size:12px;
color:#64748b;
">

<?php
echo htmlspecialchars(
    $payment_type
);
?>

</div>

</td>

<td>

<button
class="btn btn-view"
onclick="toggleDetails(<?php echo $booking_id; ?>)"
id="toggleBtn_<?php echo $booking_id; ?>"
>

View Details

</button>

</td>

</tr>

<tr
class="detail-row"
id="detailRow_<?php echo $booking_id; ?>"
>

<td colspan="5">

<div class="detail-content">

<div class="detail-grid">

<div>

<img
src="<?php echo htmlspecialchars($car_image); ?>"
class="detail-image"
onerror="this.src='../Assets/default-car.jpg';"
>

</div>

<div>

<div class="detail-title">

<?php
echo htmlspecialchars($car_name);
?>

</div>

<div class="detail-price">

RM <?php
echo number_format(
    $raw_price,
    2
);
?>

</div>

<div class="info-grid">

<div class="info-box">

<div class="info-label">
Loan Duration
</div>

<div class="info-value">

<?php
echo $loan_years;
?> Years

</div>

</div>

<div class="info-box">

<div class="info-label">
Monthly Installment
</div>

<div class="info-value">

RM <?php
echo number_format(
    $monthly_payment,
    2
);
?>

</div>

</div>

<div class="info-box">

<div class="info-label">
Current Stage
</div>

<div class="info-value">

<?php
echo htmlspecialchars(
    $payment_type
);
?>

</div>

</div>

<div class="info-box">

<div class="info-label">
Payment Status
</div>

<div class="info-value">

<span class="status <?php echo ($payment_status === 'paid') ? 'completed' : 'unpaid'; ?>">

<?php
echo ucfirst(
    $payment_status
);
?>

</span>

</div>

</div>

</div>

<div class="tracking-title">
Process Tracking
</div>

<table class="tracking-table">

<tr>

<td>
Booking Fee
</td>

<td>

<span class="status <?php echo $booking_fee_exists ? 'completed' : 'pending'; ?>">

<?php
echo $booking_fee_exists
? 'Paid'
: 'Pending';
?>

</span>

</td>

</tr>

<tr>

<td>
Loan Approval
</td>

<td>

<span class="status <?php echo $status_class; ?>">

<?php
echo htmlspecialchars(
    $booking_status
);
?>

</span>

</td>

</tr>

<tr>

<td>
Down Payment
</td>

<td>

<span class="status <?php echo $downpayment_exists ? 'completed' : 'pending'; ?>">

<?php
echo $downpayment_exists
? 'Paid'
: 'Awaiting Action';
?>

</span>

</td>

</tr>

</table>

<div class="summary-box">

<div class="summary-row">

<div class="summary-label">
Vehicle Price
</div>

<div class="summary-value">

RM <?php
echo number_format(
    $raw_price,
    2
);
?>

</div>

</div>

<div class="summary-row">

<div class="summary-label">
Total Paid
</div>

<div class="summary-value">

RM <?php
echo number_format(
    $total_paid,
    2
);
?>

</div>

</div>

<div class="summary-row">

<div class="summary-label">
Remaining Balance
</div>

<div class="summary-value">

RM <?php
echo number_format(
    $remaining_balance,
    2
);
?>

</div>

</div>

</div>

<div style="margin-top:25px;">

<?php if (
    strtolower($booking_status)
    === 'approved'
    &&
    !$downpayment_exists
): ?>

<form
method="GET"
action="downpayment.php"
>

<input
type="hidden"
name="booking_id"
value="<?php echo $booking_id; ?>"
>

<button
type="submit"
class="btn-pay"
>

Proceed To Down Payment →

</button>

</form>

<?php elseif (
    $downpayment_exists
    &&
    strtolower($booking_status)
    !== 'rejected'
): ?>

<form
method="GET"
action="monthly_installment.php"
>

<input
type="hidden"
name="booking_id"
value="<?php echo $booking_id; ?>"
>

<button
type="submit"
class="btn-pay"
style="background:#2563eb;"
>

Continue Installment Payment →

</button>

</form>

<?php else: ?>

<button
class="btn-pay"
style="
background:#94a3b8;
cursor:not-allowed;
"
disabled
>

Awaiting Approval

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

<div
style="
background:white;
padding:60px;
border-radius:22px;
text-align:center;
"
>

<h2
style="
margin-bottom:15px;
"
>

No Purchase Records Found

</h2>

<p
style="
color:#64748b;
margin-bottom:25px;
"
>

You have not submitted any vehicle bookings yet.

</p>

<a
href="cars.php"
class="btn btn-view"
style="
text-decoration:none;
"
>

Browse Vehicles

</a>

</div>

<?php endif; ?>

</div>

<script>

function toggleDetails(id){

    const row =
    document.getElementById(
        "detailRow_" + id
    );

    const btn =
    document.getElementById(
        "toggleBtn_" + id
    );

    if (
        row.style.display
        === "table-row"
    ){

        row.style.display =
        "none";

        btn.innerHTML =
        "View Details";

    }
    else{

        row.style.display =
        "table-row";

        btn.innerHTML =
        "Hide Details";

    }

}

</script>

</body>
</html>