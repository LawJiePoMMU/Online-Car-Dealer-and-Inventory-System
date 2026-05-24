<?php

session_start();

require '../Config/database.php';

// =====================================================
// 1. SECURITY CHECK
// =====================================================

if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['id'])
) {

    header("Location: Auth/login.php");
    exit();

}

if (
    !isset($_SESSION['pay_ref'])
    ||
    !isset($_SESSION['pay_booking_id'])
) {

    header("Location: index.php");
    exit();

}

// =====================================================
// 2. FETCH PAYMENT + BOOKING DATA
// =====================================================

$booking_id =
intval($_SESSION['pay_booking_id']);

$user_id = intval(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

$sql = "
    SELECT
        b.*,
        p.payment_amount,
        p.payment_status,
        p.payment_date,
        p.payment_type

    FROM bookings b

    INNER JOIN payments p
        ON b.booking_id = p.reference_id

    WHERE b.booking_id = ?
    AND b.user_id = ?

    ORDER BY p.payment_id DESC

    LIMIT 1
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
    "ii",
    $booking_id,
    $user_id
);

mysqli_stmt_execute($stmt);

$result =
mysqli_stmt_get_result($stmt);

$db_data =
mysqli_fetch_assoc($result);

if (!$db_data) {

    die(
        "Payment transaction record not found."
    );

}

// =====================================================
// 3. SAFE SNAPSHOT DATA HANDLING
// =====================================================

$snapshot = json_decode(
    $db_data['snapshot_data'] ?? '{}',
    true
);

if (!is_array($snapshot)) {

    $snapshot = [];

}

$car_brand =
$snapshot['car_brand'] ?? '';

$car_model =
$snapshot['car_model'] ?? 'Selected Vehicle';

$car_image =
!empty($snapshot['car_image'])
? $snapshot['car_image']
: '../Assets/default-car.jpg';

// =====================================================
// 4. DISPLAY VARIABLES
// =====================================================

$txn_ref =
$_SESSION['pay_ref'];

$payment_amount =
(float)$db_data['payment_amount'];

$payment_type =
$db_data['payment_type']
?? 'Payment';

$payment_label =
$_SESSION['pay_label']
?? 'Vehicle Payment';

$payment_status =
$db_data['payment_status']
?? 'Paid';

$payment_date = date(
    'd M Y, h:i A',
    strtotime($db_data['payment_date'])
);

$booking_status =
$db_data['booking_status']
?? 'Pending';

// =====================================================
// 5. LOAN APPROVAL MESSAGE
// =====================================================

$approval_message = '';

if (
    $payment_type === 'Booking Fee'
    ||
    $payment_type === 'Down Payment'
) {

    $approval_message =
    'Your financing documents are currently under loan approval review by our financing department.';

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Payment Confirmation
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

/* NAVBAR */

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
    gap:20px;
    list-style:none;
}

.nav-links a{
    text-decoration:none;
    color:#475569;
    font-weight:500;
}

/* SUCCESS HERO */

.success-section{
    text-align:center;
    padding:60px 20px 30px;
}

.success-icon{
    width:100px;
    height:100px;
    margin:0 auto 25px;
    background:#dcfce7;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:50px;
    color:#16a34a;
}

.success-section h1{
    font-size:34px;
    color:#16a34a;
    margin-bottom:10px;
}

.success-section p{
    color:#64748b;
    font-size:15px;
}

/* CONTAINER */

.container{
    max-width:900px;
    margin:0 auto 60px;
    padding:0 20px;
}

/* RECEIPT CARD */

.receipt-card{
    background:white;
    border-radius:18px;
    padding:35px;
    box-shadow:0 4px 14px rgba(0,0,0,0.04);
    border:1px solid #e2e8f0;
}

/* VEHICLE */

.vehicle-preview{
    display:flex;
    gap:20px;
    align-items:center;
    margin-bottom:30px;
    padding-bottom:25px;
    border-bottom:2px solid #f1f5f9;
}

.vehicle-image{
    width:220px;
    height:140px;
    object-fit:cover;
    border-radius:14px;
    border:1px solid #e2e8f0;
}

.vehicle-info h2{
    font-size:28px;
    margin-bottom:10px;
}

.vehicle-info p{
    font-size:14px;
    color:#64748b;
    margin-bottom:5px;
}

/* TITLE */

.receipt-title{
    font-size:22px;
    margin-bottom:24px;
    color:#2563eb;
    border-bottom:2px solid #f1f5f9;
    padding-bottom:14px;
}

/* TABLE */

.receipt-table{
    width:100%;
    border-collapse:collapse;
}

.receipt-table td{
    padding:16px 10px;
    border-bottom:1px solid #f1f5f9;
    font-size:14px;
}

.receipt-table td:first-child{
    color:#64748b;
    width:40%;
}

.receipt-table td:last-child{
    text-align:right;
    font-weight:600;
}

/* BADGES */

.badge-paid{
    background:#dcfce7;
    color:#15803d;
    padding:8px 14px;
    border-radius:999px;
    font-size:13px;
    font-weight:600;
}

.badge-pending{
    background:#fef3c7;
    color:#92400e;
    padding:8px 14px;
    border-radius:999px;
    font-size:13px;
    font-weight:600;
}

/* AMOUNT */

.amount{
    color:#16a34a;
    font-size:22px;
    font-weight:700;
}

/* APPROVAL BOX */

.approval-box{
    margin-top:28px;
    background:#eff6ff;
    border-left:5px solid #2563eb;
    padding:18px;
    border-radius:12px;
}

.approval-box p{
    font-size:14px;
    line-height:1.7;
    color:#475569;
}

/* BUTTONS */

.action-buttons{
    display:flex;
    gap:16px;
    margin-top:30px;
    flex-wrap:wrap;
}

.btn{
    flex:1;
    min-width:220px;
    text-align:center;
    padding:15px;
    border-radius:10px;
    text-decoration:none;
    font-weight:600;
    transition:0.2s ease;
}

.btn-primary{
    background:#2563eb;
    color:white;
}

.btn-primary:hover{
    background:#1d4ed8;
}

.btn-secondary{
    background:#e2e8f0;
    color:#1e293b;
}

.btn-secondary:hover{
    background:#cbd5e1;
}

/* FOOTER */

.footer{
    text-align:center;
    color:#94a3b8;
    font-size:13px;
    padding:30px 0;
}

@media(max-width:700px){

    .vehicle-preview{
        flex-direction:column;
        align-items:flex-start;
    }

    .vehicle-image{
        width:100%;
        height:220px;
    }

    .receipt-card{
        padding:24px;
    }

}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <a href="index.php"
       class="logo">
        AutoDeal
    </a>

    <ul class="nav-links">

        <li>
            <a href="index.php">
                Home
            </a>
        </li>

        <li>
            <a href="view_status.php">
                My Purchases
            </a>
        </li>

    </ul>

</nav>

<!-- SUCCESS -->

<section class="success-section">

    <div class="success-icon">
        ✓
    </div>

    <h1>
        Payment Successful
    </h1>

    <p>
        Your transaction has been processed successfully.
    </p>

</section>

<!-- CONTENT -->

<div class="container">

    <div class="receipt-card">

        <!-- VEHICLE -->

        <div class="vehicle-preview">

            <img
                src="<?php echo htmlspecialchars($car_image); ?>"
                class="vehicle-image"
                alt="Vehicle Image"
            >

            <div class="vehicle-info">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $car_brand . ' ' . $car_model
                    );
                    ?>
                </h2>

                <p>
                    Booking ID:
                    #<?php echo $booking_id; ?>
                </p>

                <p>
                    Current Status:
                    <?php echo htmlspecialchars($booking_status); ?>
                </p>

            </div>

        </div>

        <!-- RECEIPT -->

        <h2 class="receipt-title">
            Transaction Receipt
        </h2>

        <table class="receipt-table">

            <tr>

                <td>
                    Transaction Reference
                </td>

                <td>
                    <?php echo htmlspecialchars($txn_ref); ?>
                </td>

            </tr>

            <tr>

                <td>
                    Payment Type
                </td>

                <td>
                    <?php echo htmlspecialchars($payment_type); ?>
                </td>

            </tr>

            <tr>

                <td>
                    Description
                </td>

                <td>
                    <?php echo htmlspecialchars($payment_label); ?>
                </td>

            </tr>

            <tr>

                <td>
                    Vehicle
                </td>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $car_brand . ' ' . $car_model
                    );
                    ?>
                </td>

            </tr>

            <tr>

                <td>
                    Amount Paid
                </td>

                <td class="amount">
                    RM <?php
                    echo number_format(
                        $payment_amount,
                        2
                    );
                    ?>
                </td>

            </tr>

            <tr>

                <td>
                    Transaction Date
                </td>

                <td>
                    <?php echo $payment_date; ?>
                </td>

            </tr>

            <tr>

                <td>
                    Payment Status
                </td>

                <td>

                    <span class="badge-paid">
                        <?php
                        echo htmlspecialchars(
                            $payment_status
                        );
                        ?>
                    </span>

                </td>

            </tr>

        </table>

        <!-- LOAN APPROVAL MESSAGE -->

        <?php if (!empty($approval_message)): ?>

            <div class="approval-box">

                <p>
                    <?php
                    echo htmlspecialchars(
                        $approval_message
                    );
                    ?>
                </p>

            </div>

        <?php endif; ?>

        <!-- ACTION BUTTONS -->

        <div class="action-buttons">

            <a href="view_status.php"
               class="btn btn-primary">

                View Purchase Status

            </a>

            <a href="index.php"
               class="btn btn-secondary">

                Return Home

            </a>

        </div>

    </div>

</div>

<!-- FOOTER -->

<div class="footer">
    © 2026 AutoDeal. All rights reserved.
</div>

</body>
</html>

<?php

// =====================================================
// 6. CLEAN PAYMENT SESSIONS
// =====================================================

unset($_SESSION['pay_ref']);
unset($_SESSION['pay_booking_id']);
unset($_SESSION['payment_type']);
unset($_SESSION['pay_label']);

?>