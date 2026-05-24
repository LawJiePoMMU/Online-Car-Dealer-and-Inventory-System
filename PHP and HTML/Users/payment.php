<?php

session_start();

require '../Config/database.php';

// =====================================================
// SECURITY CHECK
// =====================================================

if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['id'])
) {

    header("Location: ../Auth/login.php");
    exit();

}

$user_id = intval(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

// =====================================================
// PAYMENT VARIABLES
// =====================================================

$booking_id = intval(
    $_POST['booking_id']
    ?? ($_SESSION['booking_id'] ?? 0)
);

$payment_amount = (float)(
    $_POST['payment_amount']
    ?? ($_SESSION['pay_amount'] ?? 0)
);

$payment_label = trim(
    $_POST['payment_label']
    ?? ($_SESSION['pay_label'] ?? '')
);

$source_type = trim(
    $_POST['source']
    ?? ($_SESSION['pay_source'] ?? 'booking')
);

// =====================================================
// PAGE TITLES
// =====================================================

$page_title = 'Vehicle Payment';

$button_text = 'Pay Now';

if ($source_type === 'booking') {

    $page_title =
    'Vehicle Booking Fee';

    $button_text =
    'Confirm Booking';

}
elseif ($source_type === 'downpayment') {

    $page_title =
    'Vehicle Downpayment';

    $button_text =
    'Pay Downpayment';

}
elseif ($source_type === 'installment') {

    $page_title =
    'Monthly Installment Payment';

    $button_text =
    'Pay Installment';

}

// =====================================================
// PAYMENT LABEL FALLBACK
// =====================================================

if (empty($payment_label)) {

    if ($source_type === 'booking') {

        $payment_label =
        'Booking Fee';

    }
    elseif ($source_type === 'downpayment') {

        $payment_label =
        'Down Payment';

    }
    else {

        $payment_label =
        'Monthly Installment';

    }

}

// =====================================================
// VEHICLE IMAGE FALLBACK
// =====================================================

$vehicle_image =
$_SESSION['res_image']
?? 'https://via.placeholder.com/500x300';

// =====================================================
// SHOW PAYMENT FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?php echo $page_title; ?>
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
    background:#f1f5f9;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:40px;
}

.payment-wrapper{
    width:100%;
    max-width:1150px;
    display:grid;
    grid-template-columns:1fr 420px;
    background:white;
    border-radius:26px;
    overflow:hidden;
    box-shadow:0 12px 40px rgba(0,0,0,0.08);
}

/* LEFT */

.left-side{
    padding:45px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.payment-title{
    font-size:32px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:8px;
}

.payment-subtitle{
    color:#64748b;
    margin-bottom:50px;
}

/* FORM */

form{
    width:100%;
    max-width:500px;
}

.form-group{
    margin-bottom:20px;
}

label{
    display:block;
    margin-bottom:8px;
    font-size:14px;
    font-weight:600;
    color:#334155;
}

input{
    width:100%;
    padding:15px;
    border:1px solid #dbe3ee;
    border-radius:14px;
    font-size:15px;
    transition:0.2s;
}

input:focus{
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 4px rgba(37,99,235,0.10);
}

.row{
    display:flex;
    gap:15px;
}

.pay-btn{
    width:100%;
    padding:16px;
    background:#2563eb;
    color:white;
    border:none;
    border-radius:16px;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    margin-top:10px;
    transition:0.2s;
}

.pay-btn:hover{
    background:#1d4ed8;
}

/* RIGHT */

.right-side{
    background:#2563eb;
    color:white;
    padding:40px;
}

.summary-title{
    font-size:28px;
    font-weight:700;
    margin-bottom:30px;
}

/* VEHICLE */

.vehicle-box{
    background:rgba(255,255,255,0.12);
    padding:18px;
    border-radius:20px;
    margin-bottom:30px;
}

.vehicle-image{
    width:100%;
    height:190px;
    object-fit:cover;
    border-radius:14px;
    margin-bottom:15px;
}

.vehicle-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:10px;
}

.vehicle-detail{
    margin-bottom:8px;
    font-size:14px;
}

/* SUMMARY */

.summary-item{
    display:flex;
    justify-content:space-between;
    margin-bottom:18px;
    font-size:15px;
}

.total-box{
    margin-top:30px;
    padding-top:20px;
    border-top:1px solid rgba(255,255,255,0.25);
}

.total-title{
    font-size:15px;
    opacity:0.9;
}

.total-amount{
    font-size:36px;
    font-weight:700;
    margin-top:10px;
}

.secure-box{
    margin-top:35px;
    background:rgba(255,255,255,0.12);
    padding:18px;
    border-radius:14px;
    font-size:14px;
    line-height:1.6;
}

@media(max-width:950px){

    .payment-wrapper{
        grid-template-columns:1fr;
    }

    .right-side{
        order:-1;
    }

}

</style>

</head>

<body>

<div class="payment-wrapper">

    <!-- LEFT -->

    <div class="left-side">

        <h1 class="payment-title">
            <?php echo $page_title; ?>
        </h1>

        <p class="payment-subtitle">
            Complete your vehicle payment securely.
        </p>

        <!-- FORM -->

        <form method="POST" action="">

            <input
                type="hidden"
                name="booking_id"
                value="<?php echo $booking_id; ?>"
            >

            <input
                type="hidden"
                name="payment_amount"
                value="<?php echo $payment_amount; ?>"
            >

            <input
                type="hidden"
                name="source"
                value="<?php echo htmlspecialchars($source_type); ?>"
            >

            <div class="form-group">

                <label>
                    Card Holder Name
                </label>

                <input
                    type="text"
                    name="card_name"
                    placeholder="John Doe"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Card Number
                </label>

                <input
                    type="text"
                    name="card_number"
                    maxlength="19"
                    placeholder="1234 5678 9012 3456"
                    required
                >

            </div>

            <div class="row">

                <div class="form-group" style="flex:1;">

                    <label>
                        Expiry Date
                    </label>

                    <input
                        type="text"
                        name="expiry"
                        maxlength="5"
                        placeholder="MM/YY"
                        required
                    >

                </div>

                <div class="form-group" style="flex:1;">

                    <label>
                        CVV
                    </label>

                    <input
                        type="password"
                        name="cvv"
                        maxlength="4"
                        placeholder="123"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                class="pay-btn"
            >
                <?php echo $button_text; ?>
            </button>

        </form>

    </div>

    <!-- RIGHT -->

    <div class="right-side">

        <h2 class="summary-title">
            Payment Summary
        </h2>

        <!-- VEHICLE -->

        <div class="vehicle-box">

            <img
                src="<?php echo htmlspecialchars($vehicle_image); ?>"
                class="vehicle-image"
            >

            <div class="vehicle-title">

                <?php
                echo htmlspecialchars(
                    ($_SESSION['res_brand'] ?? '')
                    . ' ' .
                    ($_SESSION['res_model'] ?? '')
                );
                ?>

            </div>

            <div class="vehicle-detail">

                Vehicle Year:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['res_year'] ?? ''
                    );
                    ?>
                </strong>

            </div>

            <div class="vehicle-detail">

                Vehicle Type:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['res_origin'] ?? ''
                    );
                    ?>
                </strong>

            </div>

            <div class="vehicle-detail">

                Loan Tenure:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['loan_years'] ?? ''
                    );
                    ?>
                    Years
                </strong>

            </div>

            <div class="vehicle-detail">

                Selected Color:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['car_color'] ?? ''
                    );
                    ?>
                </strong>

            </div>

        </div>

        <!-- SUMMARY -->

        <div class="summary-item">

            <span>
                Payment Type
            </span>

            <strong>
                <?php echo htmlspecialchars($payment_label); ?>
            </strong>

        </div>

        <div class="summary-item">

            <span>
                Booking Reference
            </span>

            <strong>
                #<?php echo $booking_id; ?>
            </strong>

        </div>

        <div class="summary-item">

            <span>
                Insurance
            </span>

            <strong>

                RM <?php
                echo number_format(
                    $_SESSION['insurance_amount']
                    ?? 0,
                    2
                );
                ?>

            </strong>

        </div>

        <div class="total-box">

            <div class="total-title">
                Total Payment
            </div>

            <div class="total-amount">

                RM <?php
                echo number_format(
                    $payment_amount,
                    2
                );
                ?>

            </div>

        </div>

        <div class="secure-box">

            🔒 Your payment is protected using
            simulated SSL encryption for
            educational project purposes only.

        </div>

    </div>

</div>

</body>
</html>

<?php
exit();
}

// =====================================================
// PAYMENT VALIDATION
// =====================================================

if (
    $booking_id <= 0
    ||
    $payment_amount <= 0
) {

    die(
        "Error: Invalid payment transaction."
    );

}

// =====================================================
// VERIFY BOOKING OWNERSHIP
// =====================================================

$verify_sql = "
SELECT *
FROM bookings
WHERE booking_id = ?
AND user_id = ?
LIMIT 1
";

$verify_stmt =
mysqli_prepare(
    $conn,
    $verify_sql
);

mysqli_stmt_bind_param(
    $verify_stmt,
    "ii",
    $booking_id,
    $user_id
);

mysqli_stmt_execute(
    $verify_stmt
);

$verify_result =
mysqli_stmt_get_result(
    $verify_stmt
);

if (
    mysqli_num_rows(
        $verify_result
    ) <= 0
) {

    die(
        "Unauthorized booking access."
    );

}

// =====================================================
// PAYMENT TYPE
// =====================================================

$payment_type = 'Booking Fee';

if ($source_type === 'downpayment') {

    $payment_type =
    'Down Payment';

}
elseif ($source_type === 'installment') {

    $payment_type =
    'Monthly Installment';

}

// =====================================================
// PREVENT DUPLICATE BOOKING PAYMENT
// =====================================================

if ($payment_type === 'Booking Fee') {

    $check_sql = "
    SELECT payment_id
    FROM payments
    WHERE reference_id = ?
    AND payment_type = ?
    LIMIT 1
    ";

    $check_stmt =
    mysqli_prepare(
        $conn,
        $check_sql
    );

    mysqli_stmt_bind_param(
        $check_stmt,
        "is",
        $booking_id,
        $payment_type
    );

    mysqli_stmt_execute(
        $check_stmt
    );

    $check_result =
    mysqli_stmt_get_result(
        $check_stmt
    );

    if (
        mysqli_num_rows(
            $check_result
        ) > 0
    ) {

        die(
            "Booking payment already completed."
        );

    }

}

// =====================================================
// INSERT PAYMENT
// =====================================================

$insert_sql = "
INSERT INTO payments (

    reference_id,
    payment_amount,
    payment_type,
    payment_status,
    payment_date,
    remarks

)
VALUES (

    ?,
    ?,
    ?,
    'Paid',
    NOW(),
    ?

)
";

$insert_stmt =
mysqli_prepare(
    $conn,
    $insert_sql
);

mysqli_stmt_bind_param(
    $insert_stmt,
    "idss",
    $booking_id,
    $payment_amount,
    $payment_type,
    $payment_label
);

$insert_execute =
mysqli_stmt_execute(
    $insert_stmt
);

if (!$insert_execute) {

    die(
        "Payment Insert Failed: "
        . mysqli_error($conn)
    );

}

// =====================================================
// UPDATE BOOKING STATUS
// =====================================================

$booking_status =
'Pending Loan Approval';

if (
    $source_type === 'downpayment'
    ||
    $source_type === 'installment'
) {

    $booking_status =
    'Installment Active';

}

$update_sql = "
UPDATE bookings
SET booking_status = ?
WHERE booking_id = ?
";

$update_stmt =
mysqli_prepare(
    $conn,
    $update_sql
);

mysqli_stmt_bind_param(
    $update_stmt,
    "si",
    $booking_status,
    $booking_id
);

$update_execute =
mysqli_stmt_execute(
    $update_stmt
);

if (!$update_execute) {

    die(
        "Booking Update Failed: "
        . mysqli_error($conn)
    );

}

// =====================================================
// PAYMENT CONFIRM SESSION
// =====================================================

$_SESSION['pay_ref'] =
'TXN-' . strtoupper(uniqid());

$_SESSION['payment_type'] =
$payment_type;

$_SESSION['pay_booking_id'] =
$booking_id;

// =====================================================
// REDIRECT
// =====================================================

header(
    "Location: payment_confirm.php"
);

exit();