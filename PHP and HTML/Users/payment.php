<?php
session_start();
require 'db.php';

// 1. SECURITY: Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. CAPTURE INPUTS: Get from POST or session memory
$reservation_id = $_POST['reservation_id'] ?? ($_SESSION['pay_reservation_id'] ?? 0);
$payment_amount = (float)($_POST['payment_amount'] ?? ($_SESSION['pay_amount'] ?? 0));
$payment_label  = $_POST['payment_label'] ?? ($_SESSION['pay_label'] ?? 'Vehicle Payment');
$source_type    = $_POST['source'] ?? ($_SESSION['pay_source'] ?? '');

if (!$reservation_id || $payment_amount <= 0) {
    die("Error: Invalid transaction processing parameters.");
}

// 3. DEFINE TRANSACTION TYPE
$payment_type = 'Monthly Installment';
if ($source_type === 'downpayment') { $payment_type = 'Down Payment'; }
elseif ($source_type === 'booking') { $payment_type = 'Booking Fee'; }

try {
    $pdo->beginTransaction();

    // 4. INSERT PAYMENT (Cleaned: No payment_method)
    $pay_stmt = $pdo->prepare("
        INSERT INTO payments (
            reference_id,
            payment_amount,
            payment_type,
            payment_status,
            payment_date,
            remarks
        )
        VALUES (?, ?, ?, 'Paid', NOW(), ?)
    ");

    $pay_stmt->execute([
        $reservation_id,
        $payment_amount,
        $payment_type,
        $payment_label
    ]);

    // 5. UPDATE STATUS IF DOWN PAYMENT
    if ($source_type === 'downpayment') {
        $update_stmt = $pdo->prepare("
            UPDATE reservations 
            SET reservation_status = 'Completed' 
            WHERE reservation_id = ?
        ");
        $update_stmt->execute([$reservation_id]);
    }

    $pdo->commit();

    // 6. SETUP SESSION DATA FOR CONFIRMATION PAGE
    $_SESSION['pay_ref']            = 'TXN-' . strtoupper(uniqid());
    $_SESSION['payment_type']       = $payment_type;
    $_SESSION['pay_reservation_id'] = $reservation_id;
    $_SESSION['pay_label']          = $payment_label;

    // 7. REDIRECT TO RECEIPT PAGE
    header("Location: payment_confirm.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Transaction Gateway Failure: " . $e->getMessage());
}
