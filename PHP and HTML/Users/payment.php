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
// 2. CAPTURE PAYMENT INPUTS
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
    ?? ($_SESSION['pay_label'] ?? 'Vehicle Payment')
);

$source_type = trim(
    $_POST['source']
    ?? ($_SESSION['pay_source'] ?? '')
);

// =====================================================
// 3. VALIDATION
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
// 4. VERIFY BOOKING OWNERSHIP
// =====================================================

$verify_sql = "
    SELECT *
    FROM bookings
    WHERE booking_id = ?
    AND user_id = ?
    LIMIT 1
";

$verify_stmt = mysqli_prepare(
    $conn,
    $verify_sql
);

if (!$verify_stmt) {

    die(
        "Verification Prepare Failed: "
        . mysqli_error($conn)
    );

}

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

$booking =
mysqli_fetch_assoc(
    $verify_result
);

// =====================================================
// 5. DETERMINE PAYMENT TYPE
// =====================================================

$payment_type =
'Monthly Installment';

if (
    $source_type === 'downpayment'
) {

    $payment_type =
    'Down Payment';

}
elseif (
    $source_type === 'booking'
) {

    $payment_type =
    'Booking Fee';

}

// =====================================================
// 6. PREVENT DUPLICATE BOOKING FEE
// =====================================================

if (
    $payment_type === 'Booking Fee'
) {

    $duplicate_sql = "
        SELECT payment_id
        FROM payments
        WHERE reference_id = ?
        AND payment_type = 'Booking Fee'
        AND payment_status = 'Paid'
        LIMIT 1
    ";

    $duplicate_stmt =
    mysqli_prepare(
        $conn,
        $duplicate_sql
    );

    mysqli_stmt_bind_param(
        $duplicate_stmt,
        "i",
        $booking_id
    );

    mysqli_stmt_execute(
        $duplicate_stmt
    );

    $duplicate_result =
    mysqli_stmt_get_result(
        $duplicate_stmt
    );

    if (
        mysqli_num_rows(
            $duplicate_result
        ) > 0
    ) {

        die(
            "Booking fee has already been paid."
        );

    }

}

// =====================================================
// 7. INSERT PAYMENT RECORD
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

if (!$insert_stmt) {

    die(
        "Payment Prepare Failed: "
        . mysqli_error($conn)
    );

}

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
// 8. UPDATE BOOKING STATUS
// =====================================================

// ==========================================
// BOOKING FEE PAID
// ==========================================

if (
    $source_type === 'booking'
) {

    $update_sql = "
        UPDATE bookings
        SET booking_status = 'Pending Loan Approval'
        WHERE booking_id = ?
    ";

    $update_stmt =
    mysqli_prepare(
        $conn,
        $update_sql
    );

    if ($update_stmt) {

        mysqli_stmt_bind_param(
            $update_stmt,
            "i",
            $booking_id
        );

        mysqli_stmt_execute(
            $update_stmt
        );

    }

}

// ==========================================
// DOWNPAYMENT COMPLETED
// ==========================================

elseif (
    $source_type === 'downpayment'
) {

    $update_sql = "
        UPDATE bookings
        SET booking_status = 'Installment Active'
        WHERE booking_id = ?
    ";

    $update_stmt =
    mysqli_prepare(
        $conn,
        $update_sql
    );

    if ($update_stmt) {

        mysqli_stmt_bind_param(
            $update_stmt,
            "i",
            $booking_id
        );

        mysqli_stmt_execute(
            $update_stmt
        );

    }

}

// ==========================================
// MONTHLY INSTALLMENT
// ==========================================

elseif (
    $source_type === 'installment'
) {

    $update_sql = "
        UPDATE bookings
        SET booking_status = 'Installment Active'
        WHERE booking_id = ?
    ";

    $update_stmt =
    mysqli_prepare(
        $conn,
        $update_sql
    );

    if ($update_stmt) {

        mysqli_stmt_bind_param(
            $update_stmt,
            "i",
            $booking_id
        );

        mysqli_stmt_execute(
            $update_stmt
        );

    }

}

// =====================================================
// 9. CREATE PAYMENT RECEIPT SESSION
// =====================================================

$_SESSION['pay_ref'] =
'TXN-' . strtoupper(uniqid());

$_SESSION['payment_type'] =
$payment_type;

$_SESSION['pay_booking_id'] =
$booking_id;

$_SESSION['pay_label'] =
$payment_label;

// =====================================================
// 10. REDIRECT TO CONFIRMATION PAGE
// =====================================================

header(
    "Location: payment_confirm.php"
);

exit();

?>