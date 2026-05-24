<?php

session_start();

require '../Config/database.php';

// ======================================================
// SECURITY CHECK
// ======================================================

if (!isset($_SESSION['user_id'])) {

    header("Location: Auth/login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

// ======================================================
// VALIDATE BOOKING
// ======================================================

$booking_id =
$_GET['booking_id']
?? $_POST['booking_id']
?? 0;

if ($booking_id <= 0) {

    die("Invalid booking request.");

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

    die("Booking not found.");

}

$booking =
mysqli_fetch_assoc($result);

// ======================================================
// BOOKING STATUS CHECK
// ======================================================

$booking_status =
strtolower(
    $booking['booking_status']
    ?? 'pending'
);

if ($booking_status !== 'approved') {

    die(
        "Downpayment only available after admin approval."
    );

}

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

$vehicle_price =
(float)(
    $snapshot['total_compiled_price']
    ?? $snapshot['total_price']
    ?? 0
);

if ($vehicle_price <= 0) {

    $vehicle_price = 50000;

}

$downpayment_amount =
round(
    $vehicle_price * 0.10,
    2
);

// ======================================================
// CHECK BOOKING FEE
// ======================================================

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

if (!$booking_fee_exists) {

    die(
        "Booking fee payment required before downpayment."
    );

}

// ======================================================
// CHECK EXISTING DOWNPAYMENT
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

if ($downpayment_exists) {

    die(
        "Downpayment already completed."
    );

}

// ======================================================
// HANDLE INSURANCE UPLOAD
// ======================================================

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_FILES['insurance_file'])
        ||
        $_FILES['insurance_file']['error']
        !== 0
    ) {

        $error_message =
        "Please upload signed insurance document.";

    }
    else {

        $allowed_extensions = [
            'pdf',
            'jpg',
            'jpeg',
            'png'
        ];

        $file_name =
        $_FILES['insurance_file']['name'];

        $file_tmp =
        $_FILES['insurance_file']['tmp_name'];

        $file_size =
        $_FILES['insurance_file']['size'];

        $file_extension =
        strtolower(
            pathinfo(
                $file_name,
                PATHINFO_EXTENSION
            )
        );

        // ======================================================
        // MIME TYPE VALIDATION
        // ======================================================

        $mime_type =
        mime_content_type(
            $file_tmp
        );

        $allowed_mime_types = [
            'application/pdf',
            'image/jpeg',
            'image/png'
        ];

        if (
            !in_array(
                $mime_type,
                $allowed_mime_types
            )
        ) {

            $error_message =
            "Invalid file content.";

        }
        elseif (
            !in_array(
                $file_extension,
                $allowed_extensions
            )
        ) {

            $error_message =
            "Invalid file format.";

        }
        elseif (
            $file_size >
            5 * 1024 * 1024
        ) {

            $error_message =
            "File size exceeds 5MB.";

        }
        else {

            $upload_folder =
            "../uploads/insurance/";

            if (
                !file_exists(
                    $upload_folder
                )
            ) {

                mkdir(
                    $upload_folder,
                    0777,
                    true
                );

            }

            $new_file_name =
            'insurance_' .
            time() .
            '_' .
            rand(1000,9999)
            . '.'
            . $file_extension;

            $upload_path =
            $upload_folder
            . $new_file_name;

            if (
                move_uploaded_file(
                    $file_tmp,
                    $upload_path
                )
            ) {

                // ======================================================
                // SAVE RELATIVE PATH ONLY
                // ======================================================

                $snapshot['signed_insurance_path']
                =
                "uploads/insurance/"
                . $new_file_name;

                $updated_snapshot =
                json_encode(
                    $snapshot
                );

                $update_sql = "
                    UPDATE bookings
                    SET snapshot_data = ?
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
                    $updated_snapshot,
                    $booking_id
                );

                mysqli_stmt_execute(
                    $update_stmt
                );

                // ======================================================
                // REDIRECT TO PAYMENT
                // ======================================================

                echo "
                <form
                    id='payment_redirect'
                    method='POST'
                    action='payment.php'
                >

                    <input
                        type='hidden'
                        name='booking_id'
                        value='{$booking_id}'
                    >

                    <input
                        type='hidden'
                        name='payment_amount'
                        value='{$downpayment_amount}'
                    >

                    <input
                        type='hidden'
                        name='payment_label'
                        value='Vehicle Down Payment'
                    >

                    <input
                        type='hidden'
                        name='source'
                        value='downpayment'
                    >

                </form>

                <script>
                    document
                    .getElementById(
                        'payment_redirect'
                    )
                    .submit();
                </script>
                ";

                exit();

            }
            else {

                $error_message =
                "Insurance upload failed.";

            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
Vehicle Downpayment
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
    color:#1e293b;
    padding:40px 20px;
}

.container{
    max-width:900px;
    margin:auto;
}

.card{
    background:white;
    border-radius:24px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 10px 30px rgba(0,0,0,0.04);
}

.header{
    padding:35px;
    border-bottom:1px solid #e2e8f0;
}

.header h1{
    font-size:32px;
    margin-bottom:10px;
}

.header p{
    color:#64748b;
}

.content{
    padding:35px;
}

.car-section{
    display:grid;
    grid-template-columns:300px 1fr;
    gap:30px;
    margin-bottom:35px;
}

.car-image{
    width:100%;
    height:220px;
    object-fit:cover;
    border-radius:18px;
}

.car-title{
    font-size:30px;
    font-weight:700;
    margin-bottom:15px;
}

.price-box{
    background:#f8fafc;
    border-radius:18px;
    padding:22px;
    margin-bottom:20px;
}

.price-row{
    display:flex;
    justify-content:space-between;
    margin-bottom:12px;
}

.price-row:last-child{
    margin-bottom:0;
}

.label{
    color:#64748b;
}

.value{
    font-weight:700;
}

.highlight{
    color:#2563eb;
    font-size:24px;
}

.notice{
    background:#eff6ff;
    border-left:5px solid #2563eb;
    padding:18px;
    border-radius:12px;
    margin-bottom:25px;
}

.notice strong{
    display:block;
    margin-bottom:8px;
}

.upload-box{
    border:2px dashed #cbd5e1;
    border-radius:18px;
    padding:30px;
    text-align:center;
    margin-bottom:25px;
}

.upload-box input{
    margin-top:15px;
}

.btn{
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    font-size:15px;
    font-weight:700;
    cursor:pointer;
    transition:0.2s;
}

.btn-pay{
    background:#16a34a;
    color:white;
}

.btn-pay:hover{
    background:#15803d;
}

.btn-download{
    background:#2563eb;
    color:white;
    text-decoration:none;
    display:inline-block;
    padding:14px 22px;
    border-radius:14px;
    margin-top:15px;
    font-weight:600;
}

.error{
    background:#fee2e2;
    color:#991b1b;
    padding:16px;
    border-radius:12px;
    margin-bottom:20px;
}

@media(max-width:768px){

    .car-section{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="header">

<h1>
Vehicle Downpayment
</h1>

<p>
Complete your downpayment process
to proceed to monthly installment financing.
</p>

</div>

<div class="content">

<?php if (!empty($error_message)): ?>

<div class="error">

<?php
echo htmlspecialchars(
    $error_message
);
?>

</div>

<?php endif; ?>

<div class="car-section">

<div>

<img
src="<?php echo htmlspecialchars($car_image); ?>"
class="car-image"
>

</div>

<div>

<div class="car-title">

<?php
echo htmlspecialchars($car_name);
?>

</div>

<div class="price-box">

<div class="price-row">

<div class="label">
Vehicle Price
</div>

<div class="value">

RM <?php
echo number_format(
    $vehicle_price,
    2
);
?>

</div>

</div>

<div class="price-row">

<div class="label">
Required Downpayment (10%)
</div>

<div class="value highlight">

RM <?php
echo number_format(
    $downpayment_amount,
    2
);
?>

</div>

</div>

</div>

<p
style="
font-size:13px;
color:#64748b;
line-height:1.7;
"
>

Vehicle price includes standard
registration, road tax,
and car plate number fees.

</p>

</div>

</div>

<div class="notice">

<strong>
Insurance Form Required
</strong>

Please download the insurance form,
sign it,
and upload the completed copy
before proceeding to payment.

<br>

<a
href="../documents/insurance_form.pdf"
class="btn-download"
download
>

Download Insurance Form PDF

</a>

</div>

<form
method="POST"
enctype="multipart/form-data"
>

<input
type="hidden"
name="booking_id"
value="<?php echo $booking_id; ?>"
>

<div class="upload-box">

<h3>
Upload Signed Insurance Form
</h3>

<p
style="
margin-top:10px;
color:#64748b;
"
>

Accepted formats:
PDF, JPG, PNG (Max 5MB)

</p>

<input
type="file"
name="insurance_file"
required
>

</div>

<button
type="submit"
class="btn btn-pay"
>

Proceed To Secure Payment →

</button>

</form>

</div>

</div>

</div>

</body>
</html>