<?php
session_name("AdminSession");
session_start();
include '../Config/database.php';
include '../Config/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$admin_id = $_SESSION['user_id'] ?? 1;
$sys_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_dp_percent','default_loan_rate')");
$sys = [];
while ($r = mysqli_fetch_assoc($sys_query))
    $sys[$r['setting_key']] = $r['setting_value'];
$dp_percent = (float) ($sys['default_dp_percent'] ?? 10);
$loan_rate = (float) ($sys['default_loan_rate'] ?? 3.00);
mysqli_query($conn, "
    UPDATE monthly_installments
       SET payment_status = 'Overdue',
           overdue_days   = DATEDIFF(CURDATE(), due_date)
     WHERE payment_status = 'Pending'
       AND due_date < CURDATE()
");

$expired_dp_query = mysqli_query($conn, "SELECT booking_id FROM down_payments WHERE dp_status = 'Pending' AND DATEDIFF(CURDATE(), dp_created_at) >= 7");
while ($edp = mysqli_fetch_assoc($expired_dp_query)) {
    $bid = $edp['booking_id'];

    $b_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT b.car_id, b.snapshot_data, c.car_origin FROM bookings b LEFT JOIN cars c ON b.car_id=c.car_id WHERE b.booking_id = $bid"));
    if ($b_info) {
        $cid = $b_info['car_id'];
        $is_used = (strcasecmp($b_info['car_origin'] ?? '', 'Used Car') === 0);

        mysqli_query($conn, "UPDATE down_payments SET dp_status = 'Cancelled', dp_reason = 'System Auto-Cancelled: 7 Days Unpaid' WHERE booking_id = $bid");
        mysqli_query($conn, "UPDATE bookings SET booking_status = 'Rejected', rejection_reason = 'System Auto-Rejected: DP 7 Days Unpaid', refunded_at = NOW() WHERE booking_id = $bid");

        if ($is_used) {
            mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Active' WHERE car_id = $cid");
        } else {
            $snap_c = json_decode($b_info['snapshot_data'] ?? '{}', true);
            $rcolor = mysqli_real_escape_string($conn, $snap_c['car_color'] ?? '');
            if ($rcolor !== '') {
                mysqli_query($conn, "UPDATE car_inventory SET quantity = quantity + 1 WHERE car_id = $cid AND color_name = '$rcolor' LIMIT 1");
            }
            mysqli_query($conn, "UPDATE car_status SET car_status_stock_quantity = (SELECT IFNULL(SUM(quantity),0) FROM car_inventory WHERE car_id = $cid) WHERE car_id = $cid");
            mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Active' WHERE car_id = $cid AND car_status_stock_quantity > 0");
        }
    }
}

if (isset($_GET['highlight']) && !isset($_GET['page']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $hl_id = (int) $_GET['highlight'];
    $tab_param = $_GET['tab'] ?? 'booking';

    $find_q = null;
    if ($tab_param === 'booking') {
        $find_q = mysqli_query($conn, "
            SELECT b.booking_id FROM bookings b 
            WHERE b.booking_status='Pending' 
            ORDER BY b.created_at DESC
        ");
    } elseif ($tab_param === 'down_payment') {
        $find_q = mysqli_query($conn, "
            SELECT b.booking_id FROM down_payments dp 
            LEFT JOIN bookings b ON dp.booking_id=b.booking_id 
            WHERE dp.dp_status='Pending' 
            ORDER BY dp.dp_created_at ASC
        ");
    }

    if ($find_q) {
        $pos = 0;
        $found = 0;
        while ($r = mysqli_fetch_assoc($find_q)) {
            $pos++;
            if ($r['booking_id'] == $hl_id) {
                $found = $pos;
                break;
            }
        }
        if ($found > 0) {
            $target_page = (int) ceil($found / 10);
            header("Location: orders.php?tab=$tab_param&page=$target_page&highlight=$hl_id");
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $booking_id = intval($_POST['booking_id'] ?? 0);

    try {
        switch ($action) {
            case 'approve_booking':
                if (!$booking_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
                    break;
                }
                $b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bookings WHERE booking_id=$booking_id"));
                if (!$b || $b['booking_status'] !== 'Pending') {
                    echo json_encode(['success' => false, 'message' => 'Booking is not pending verification.']);
                    break;
                }

                $snap = json_decode($b['snapshot_data'] ?: '{}', true);
                if (empty($snap['user_address'])) {
                    echo json_encode(['success' => false, 'message' => 'Customer billing address is missing. Address verification is mandatory.']);
                    break;
                }

                $docs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM loan_installment_documents WHERE booking_id=$booking_id"));
                if (!$docs || empty($docs['ic_url']) || empty($docs['driving_license_url']) || empty($docs['payslip_url']) || empty($docs['bank_statement_url'])) {
                    echo json_encode(['success' => false, 'message' => 'Customer has not uploaded all 4 mandatory documents (IC, Licence, Payslip, Bank Statement).']);
                    break;
                }

                $price = (float) ($snap['car_price'] ?? 0);
                $locked_dp_pct = (float) ($snap['locked_dp_pct'] ?? $dp_percent);
                $dp_amt = round($price * ($locked_dp_pct / 100), 2);

                mysqli_begin_transaction($conn);
                mysqli_query($conn, "UPDATE bookings SET booking_status='Approved' WHERE booking_id=$booking_id");
                $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM down_payments WHERE booking_id=$booking_id"));
                if (!$exists) {
                    mysqli_query($conn, "INSERT INTO down_payments (booking_id, dp_amount, dp_status, dp_created_at) VALUES ($booking_id, $dp_amt, 'Pending', NOW())");
                }
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Booking approved. Advanced to Down Payment stage.']);
                break;

            case 'reject_booking':
                $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
                if (!$booking_id || $reason === '') {
                    echo json_encode(['success' => false, 'message' => 'Valid booking ID and reason required.']);
                    break;
                }

                $res_query = mysqli_query($conn, "SELECT b.user_id, b.car_id, b.booking_fee, b.booking_status, b.snapshot_data, c.car_origin FROM bookings b LEFT JOIN cars c ON b.car_id=c.car_id WHERE b.booking_id=$booking_id");
                $b = mysqli_fetch_assoc($res_query);
                if (!$b || $b['booking_status'] !== 'Pending') {
                    echo json_encode(['success' => false, 'message' => 'Booking is not pending.']);
                    break;
                }

                $d_query = mysqli_query($conn, "SELECT * FROM loan_installment_documents WHERE booking_id = $booking_id");
                $docs = mysqli_fetch_assoc($d_query);
                $car_id = $b['car_id'];
                $is_used = (strcasecmp($b['car_origin'] ?? '', 'Used Car') === 0);
                if ($docs) {
                    $files_to_delete = [$docs['ic_url'], $docs['driving_license_url'], $docs['payslip_url'], $docs['bank_statement_url']];
                    foreach ($files_to_delete as $file) {
                        if (!empty($file) && file_exists($file))
                            unlink($file);
                    }
                    mysqli_query($conn, "DELETE FROM loan_installment_documents WHERE booking_id = $booking_id");
                }

                mysqli_begin_transaction($conn);
                mysqli_query($conn, "UPDATE bookings SET booking_status='Rejected', rejection_reason='$reason', refunded_at=NOW() WHERE booking_id=$booking_id");
                if ($is_used) {
                    mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Active' WHERE car_id = $car_id");
                } else {
                    $snap_c = json_decode($b['snapshot_data'] ?? '{}', true);
                    $rcolor = mysqli_real_escape_string($conn, $snap_c['car_color'] ?? '');
                    $rvariant = mysqli_real_escape_string($conn, $snap_c['car_variant'] ?? '');
                    if ($rcolor !== '') {
                        mysqli_query($conn, "UPDATE car_inventory SET quantity = quantity + 1 WHERE car_id = $car_id AND IFNULL(variant,'') = '$rvariant' AND color_name = '$rcolor' LIMIT 1");
                    }
                    mysqli_query($conn, "UPDATE car_status SET car_status_stock_quantity = (SELECT IFNULL(SUM(quantity),0) FROM car_inventory WHERE car_id = $car_id) WHERE car_id = $car_id");
                    mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Active' WHERE car_id = $car_id AND car_status_stock_quantity > 0");
                }
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Booking rejected. Stock/availability restored. Booking fee is non-refundable.']);
                break;

            case 'reject_dp':
                $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
                if (!$booking_id || $reason === '') {
                    echo json_encode(['success' => false, 'message' => 'Valid booking ID and reason required.']);
                    break;
                }

                $dp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT dp_status FROM down_payments WHERE booking_id=$booking_id"));
                if (!$dp || $dp['dp_status'] !== 'Pending') {
                    echo json_encode(['success' => false, 'message' => 'DP is not pending verification.']);
                    break;
                }

                $b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT b.car_id, b.booking_fee, b.snapshot_data, c.car_origin FROM bookings b LEFT JOIN cars c ON b.car_id=c.car_id WHERE b.booking_id=$booking_id"));
                $car_id = $b['car_id'];
                $fee = (float) $b['booking_fee'];
                $is_used = (strcasecmp($b['car_origin'] ?? '', 'Used Car') === 0);

                $dp_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT insurance_pdf_url FROM down_payments WHERE booking_id=$booking_id"));
                if ($dp_data && !empty($dp_data['insurance_pdf_url']) && file_exists($dp_data['insurance_pdf_url'])) {
                    unlink($dp_data['insurance_pdf_url']);
                }

                mysqli_begin_transaction($conn);
                mysqli_query($conn, "UPDATE down_payments SET dp_status='Cancelled', dp_reason='$reason', insurance_pdf_url=NULL WHERE booking_id=$booking_id");
                mysqli_query($conn, "UPDATE bookings SET booking_status='Refunded', rejection_reason='[DP Rejected] $reason', refunded_at=NOW() WHERE booking_id=$booking_id");
                mysqli_query($conn, "INSERT INTO payments (payment_type, reference_id, payment_amount, payment_status, remarks, payment_date) VALUES ('Booking Fee Refund', $booking_id, $fee, 'Refunded', '$reason', NOW())");
                if ($is_used) {
                    mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Active' WHERE car_id = $car_id");
                } else {
                    $snap_c = json_decode($b['snapshot_data'] ?? '{}', true);
                    $rcolor = mysqli_real_escape_string($conn, $snap_c['car_color'] ?? '');
                    $rvariant = mysqli_real_escape_string($conn, $snap_c['car_variant'] ?? '');
                    if ($rcolor !== '') {
                        mysqli_query($conn, "UPDATE car_inventory SET quantity = quantity + 1 WHERE car_id = $car_id AND IFNULL(variant,'') = '$rvariant' AND color_name = '$rcolor' LIMIT 1");
                    }
                    mysqli_query($conn, "UPDATE car_status SET car_status_stock_quantity = (SELECT IFNULL(SUM(quantity),0) FROM car_inventory WHERE car_id = $car_id) WHERE car_id = $car_id");
                    mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Active' WHERE car_id = $car_id AND car_status_stock_quantity > 0");
                }
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Down Payment rejected, booking refunded, and stock/availability restored.']);
                break;

            case 'save_dp_details':
                $number = mysqli_real_escape_string($conn, trim($_POST['plate_number'] ?? ''));
                $dpinfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT dp.plate_option, b.car_id FROM down_payments dp LEFT JOIN bookings b ON dp.booking_id=b.booking_id WHERE dp.booking_id=$booking_id"));
                if ($dpinfo && $dpinfo['plate_option'] === 'used') {
                    $cid = (int) $dpinfo['car_id'];
                    $ucd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_plate FROM used_car_details WHERE car_id=$cid LIMIT 1"));
                    if ($ucd && !empty($ucd['car_plate'])) {
                        $number = mysqli_real_escape_string($conn, $ucd['car_plate']);
                    }
                } else {
                    if ($number === '') {
                        echo json_encode(['success' => false, 'message' => 'Please enter a plate number.']);
                        break;
                    }
                    $dupDp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT booking_id FROM down_payments WHERE UPPER(REPLACE(plate_number,' ','')) = UPPER(REPLACE('$number',' ','')) AND booking_id <> $booking_id LIMIT 1"));
                    $dupUsed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_id FROM used_car_details WHERE UPPER(REPLACE(car_plate,' ','')) = UPPER(REPLACE('$number',' ','')) LIMIT 1"));
                    if ($dupDp || $dupUsed) {
                        echo json_encode(['success' => false, 'message' => 'This plate number is already in use. Please enter a different one.']);
                        break;
                    }
                }
                mysqli_query($conn, "UPDATE down_payments SET plate_number='$number' WHERE booking_id=$booking_id");
                echo json_encode(['success' => true, 'message' => 'Plate details saved successfully.']);
                break;

            case 'approve_dp':
                if (!$booking_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
                    break;
                }
                $dp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM down_payments WHERE booking_id=$booking_id"));
                if (!$dp || $dp['dp_status'] !== 'Pending') {
                    echo json_encode(['success' => false, 'message' => 'DP is not pending verification.']);
                    break;
                }
                if (empty($dp['insurance_pdf_url'])) {
                    echo json_encode(['success' => false, 'message' => 'Cannot approve. Customer has not uploaded the Insurance Cover Note yet.']);
                    break;
                }

                $b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bookings WHERE booking_id=$booking_id"));
                $snap = json_decode($b['snapshot_data'] ?: '{}', true);
                $price = (float) ($snap['car_price'] ?? 0);
                $years = (int) ($b['installment_years'] ?? 9);
                if (!in_array($years, [5, 7, 9]))
                    $years = 9;

                $bk_fee = (float) $b['booking_fee'];
                $dp_amt = (float) $dp['dp_amount'];
                $loan_amount = max(0, $price - $bk_fee - $dp_amt);
                $rate = (float) ($b['interest_rate'] ?: $loan_rate);
                $months = $years * 12;
                $total_with_int = $loan_amount * (1 + ($rate / 100) * $years);
                $monthly = $months > 0 ? round($total_with_int / $months, 2) : 0;
                $rcpt = 'DP-' . date('Ymd') . '-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);

                mysqli_begin_transaction($conn);
                mysqli_query($conn, "UPDATE down_payments SET dp_status='Approved', dp_approved_at=NOW() WHERE booking_id=$booking_id");

                mysqli_query($conn, "DELETE FROM monthly_installments WHERE booking_id=$booking_id");
                for ($i = 1; $i <= $months; $i++) {
                    $due = date('Y-m-d', strtotime("+$i month"));
                    mysqli_query($conn, "INSERT INTO monthly_installments (booking_id, installment_number, monthly_amount, due_date, interest_rate, installment_status, payment_status, created_at) VALUES ($booking_id, $i, $monthly, '$due', $rate, 'Active', 'Pending', NOW())");
                }

                $car_id_sold = (int) $b['car_id'];
                $corigin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_origin FROM cars WHERE car_id=$car_id_sold"));
                if ($corigin && strcasecmp($corigin['car_origin'] ?? '', 'Used Car') === 0) {
                    mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Sold' WHERE car_id = $car_id_sold");
                }
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => "DP approved. $months installment(s) generated."]);
                break;

            case 'mark_installment_paid':
                echo json_encode(['success' => false, 'message' => 'Installments are paid by the customer. Admin cannot mark them as paid.']);
                break;

            case 'get_schedule':
                if (!$booking_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
                    break;
                }
                $rows = [];
                $q = mysqli_query($conn, "SELECT * FROM monthly_installments WHERE booking_id=$booking_id ORDER BY installment_number ASC");
                while ($r = mysqli_fetch_assoc($q))
                    $rows[] = $r;
                echo json_encode(['success' => true, 'rows' => $rows]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$tab = $_GET['tab'] ?? 'booking';
if (!in_array($tab, ['booking', 'down_payment', 'monthly_installment', 'history']))
    $tab = 'booking';
$sub_tab = $_GET['sub_tab'] ?? 'booking';
if ($tab === 'history' && !in_array($sub_tab, ['booking', 'down_payment', 'monthly_installment', 'blacklist'])) {
    $sub_tab = 'booking';
}
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$count_booking = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE booking_status='Pending'"))['c'];
$count_dp = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM down_payments WHERE dp_status='Pending'"))['c'];
$count_inst = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT booking_id) AS c FROM monthly_installments WHERE payment_status IN ('Pending','Overdue') AND booking_id NOT IN (SELECT DISTINCT booking_id FROM monthly_installments WHERE overdue_days >= 21)"))['c'];

$count_hist_bk = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE booking_status IN ('Approved','Rejected','Refunded')"))['c'];
$count_hist_dp = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM down_payments WHERE dp_status IN ('Approved','Cancelled','Rejected')"))['c'];
$count_hist_inst = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT booking_id) AS c FROM monthly_installments WHERE booking_id NOT IN (SELECT booking_id FROM monthly_installments WHERE payment_status IN ('Pending','Overdue'))"))['c'];
$count_blacklist = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT booking_id) AS c FROM monthly_installments WHERE overdue_days >= 21"))['c'];
$count_history = $count_hist_bk + $count_hist_dp + $count_hist_inst + $count_blacklist;

$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$search_cond_user = "";
if (!empty($search)) {
    $search_cond_user = " AND (u.user_name LIKE '%$search%' OR c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%') ";
}

$result = null;
$total_rows = 0;

$baseSelect = "
    b.*,
    u.user_name, u.user_email, u.user_ic, u.user_phone, COALESCE(u.user_address,'') AS user_address, COALESCE(u.user_city,'') AS user_city, COALESCE(u.user_state,'') AS user_state, COALESCE(u.user_postcode,'') AS user_postcode,
    c.car_brand, c.car_model, c.car_year, c.car_origin,
    (SELECT variant FROM car_inventory WHERE car_id=c.car_id ORDER BY inventory_id ASC LIMIT 1) AS car_variant,
    cs.car_status_price AS car_price,
    (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) AS car_image,
    (SELECT color_name FROM car_inventory WHERE car_id=c.car_id ORDER BY inventory_id ASC LIMIT 1) AS car_color,
    (SELECT car_plate FROM used_car_details WHERE car_id=c.car_id LIMIT 1) AS used_car_plate,
    dp.id AS dp_id, dp.dp_amount, dp.dp_status, dp.dp_approved_at, dp.dp_created_at, dp.dp_reason, dp.insurance_pdf_url, dp.plate_number, dp.plate_option, dp.insurance_fee, dp.plate_registration_fee, dp.paid_at
";

if ($tab === 'booking') {
    $where = "WHERE b.booking_status='Pending' $search_cond_user";
    $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings b LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
    $result = mysqli_query($conn, "SELECT $baseSelect, doc.ic_url, doc.driving_license_url, doc.payslip_url, doc.bank_statement_url FROM bookings b LEFT JOIN down_payments dp ON b.booking_id=dp.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id LEFT JOIN loan_installment_documents doc ON b.booking_id=doc.booking_id $where ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset");
} elseif ($tab === 'down_payment') {
    $where = "WHERE dp.dp_status='Pending' $search_cond_user";
    $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM down_payments dp LEFT JOIN bookings b ON dp.booking_id=b.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
    $result = mysqli_query($conn, "SELECT $baseSelect, doc.ic_url, doc.driving_license_url, doc.payslip_url, doc.bank_statement_url FROM down_payments dp LEFT JOIN bookings b ON dp.booking_id=b.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id LEFT JOIN loan_installment_documents doc ON b.booking_id=doc.booking_id $where ORDER BY dp.dp_created_at ASC LIMIT $limit OFFSET $offset");
} elseif ($tab === 'monthly_installment') {
    $where = "WHERE EXISTS(SELECT 1 FROM monthly_installments mi WHERE mi.booking_id=b.booking_id AND mi.payment_status IN ('Pending','Overdue')) AND NOT EXISTS(SELECT 1 FROM monthly_installments mi2 WHERE mi2.booking_id=b.booking_id AND mi2.overdue_days >= 21) $search_cond_user";
    $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings b LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
    $result = mysqli_query($conn, "SELECT $baseSelect, (SELECT COUNT(*) FROM monthly_installments WHERE booking_id=b.booking_id) AS total_months, (SELECT COUNT(*) FROM monthly_installments WHERE booking_id=b.booking_id AND payment_status='Paid') AS paid_months, (SELECT COUNT(*) FROM monthly_installments WHERE booking_id=b.booking_id AND payment_status='Overdue') AS overdue_months, (SELECT MIN(due_date) FROM monthly_installments WHERE booking_id=b.booking_id AND payment_status IN ('Pending','Overdue')) AS next_due, (SELECT monthly_amount FROM monthly_installments WHERE booking_id=b.booking_id LIMIT 1) AS monthly_amount FROM bookings b LEFT JOIN down_payments dp ON b.booking_id=dp.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id $where ORDER BY next_due ASC LIMIT $limit OFFSET $offset");
} else {
    if ($sub_tab === 'booking') {
        $status_cond = "b.booking_status IN ('Approved','Rejected','Refunded')";
        if ($filter === 'Approved')
            $status_cond = "b.booking_status = 'Approved'";
        if ($filter === 'Rejected')
            $status_cond = "b.booking_status = 'Rejected'";
        if ($filter === 'Refunded')
            $status_cond = "b.booking_status = 'Refunded'";

        $where = "WHERE $status_cond $search_cond_user";
        $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings b LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
        $result = mysqli_query($conn, "SELECT $baseSelect, doc.ic_url, doc.driving_license_url, doc.payslip_url, doc.bank_statement_url FROM bookings b LEFT JOIN down_payments dp ON b.booking_id=dp.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id LEFT JOIN loan_installment_documents doc ON b.booking_id=doc.booking_id $where ORDER BY COALESCE(b.refunded_at, b.created_at) DESC LIMIT $limit OFFSET $offset");

    } elseif ($sub_tab === 'down_payment') {
        $status_cond = "dp.dp_status IN ('Approved','Cancelled','Rejected')";
        if ($filter === 'Approved')
            $status_cond = "dp.dp_status = 'Approved'";
        if ($filter === 'Cancelled')
            $status_cond = "dp.dp_status = 'Cancelled'";
        if ($filter === 'Rejected')
            $status_cond = "dp.dp_status = 'Rejected'";

        $where = "WHERE $status_cond $search_cond_user";
        $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM down_payments dp LEFT JOIN bookings b ON dp.booking_id=b.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
        $result = mysqli_query($conn, "SELECT $baseSelect FROM down_payments dp LEFT JOIN bookings b ON dp.booking_id=b.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id $where ORDER BY COALESCE(dp.dp_approved_at, dp.dp_created_at) DESC LIMIT $limit OFFSET $offset");
    } elseif ($sub_tab === 'monthly_installment') {
        $where = "WHERE NOT EXISTS(SELECT 1 FROM monthly_installments mi WHERE mi.booking_id=b.booking_id AND mi.payment_status IN ('Pending','Overdue')) AND EXISTS(SELECT 1 FROM monthly_installments mi2 WHERE mi2.booking_id=b.booking_id) $search_cond_user";
        $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings b LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
        $result = mysqli_query($conn, "SELECT $baseSelect, (SELECT COUNT(*) FROM monthly_installments WHERE booking_id=b.booking_id) AS total_months, (SELECT COUNT(*) FROM monthly_installments WHERE booking_id=b.booking_id AND payment_status='Paid') AS paid_months, (SELECT monthly_amount FROM monthly_installments WHERE booking_id=b.booking_id LIMIT 1) AS monthly_amount FROM bookings b LEFT JOIN down_payments dp ON b.booking_id=dp.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id $where ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset");
    } else {
        $where = "WHERE EXISTS(SELECT 1 FROM monthly_installments mi WHERE mi.booking_id=b.booking_id AND mi.overdue_days >= 21) $search_cond_user";
        $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings b LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id $where"))['c'];
        $result = mysqli_query($conn, "SELECT $baseSelect, (SELECT MAX(overdue_days) FROM monthly_installments WHERE booking_id=b.booking_id) AS max_overdue FROM bookings b LEFT JOIN down_payments dp ON b.booking_id=dp.booking_id LEFT JOIN users u ON b.user_id=u.user_id LEFT JOIN cars c ON b.car_id=c.car_id LEFT JOIN car_status cs ON c.car_id=cs.car_id $where ORDER BY max_overdue DESC LIMIT $limit OFFSET $offset");
    }
}
$total_pages = max(1, (int) ceil($total_rows / $limit));

function apply_snapshot($row)
{
    if (!empty($row['snapshot_data'])) {
        $snap = json_decode($row['snapshot_data'], true);
        if (is_array($snap)) {
            foreach ($snap as $k => $v)
                $row[$k] = $v;
        }
    }
    return $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .folder-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)) !important;
            gap: 20px !important;
            padding: 4px 2px 8px !important;
        }

        .folder-card {
            background: #ffffff !important;
            border: 1px solid #eef2f7 !important;
            border-radius: 16px !important;
            padding: 20px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 6px 20px rgba(0, 0, 0, 0.05) !important;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .folder-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.10) !important;
            border-color: #dbe3ec !important;
        }

        .folder-card .progress-bar {
            height: 8px !important;
            background: #eef2f7 !important;
            border-radius: 999px !important;
            overflow: hidden !important;
            margin: 12px 0 4px !important;
        }

        .folder-card .progress-fill {
            height: 100% !important;
            background: linear-gradient(90deg, #1e293b 0%, #16a34a 100%) !important;
            border-radius: 999px !important;
            transition: width .5s ease !important;
        }

        .folder-card .progress-fill.overdue {
            background: linear-gradient(90deg, #f59e0b 0%, #dc2626 100%) !important;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar" style="margin-bottom:24px;">
            <div class="page-title">
                <h1 style="font-size:24px; font-weight:700; color:#111827;">Orders</h1>
            </div>
        </header>

        <div class="page-tabs">
            <a href="?tab=booking" class="tab-item <?= $tab === 'booking' ? 'active' : '' ?>">Booking
                (<?= $count_booking ?>)</a>
            <a href="?tab=down_payment" class="tab-item <?= $tab === 'down_payment' ? 'active' : '' ?>">Down Payment
                (<?= $count_dp ?>)</a>
            <a href="?tab=monthly_installment"
                class="tab-item <?= $tab === 'monthly_installment' ? 'active' : '' ?>">Monthly Installment
                (<?= $count_inst ?>)</a>
            <a href="?tab=history" class="tab-item <?= $tab === 'history' ? 'active' : '' ?>">History
                (<?= $count_history ?>)</a>
        </div>

        <?php if ($tab === 'history'): ?>
            <div class="sub-tabs">
                <a href="?tab=history&sub_tab=booking" class="sub-tab <?= $sub_tab === 'booking' ? 'active' : '' ?>"><i
                        class="fas fa-receipt"></i> Booking (<?= $count_hist_bk ?>)</a>
                <a href="?tab=history&sub_tab=down_payment"
                    class="sub-tab <?= $sub_tab === 'down_payment' ? 'active' : '' ?>"><i
                        class="fas fa-hand-holding-usd"></i> Down Payment (<?= $count_hist_dp ?>)</a>
                <a href="?tab=history&sub_tab=monthly_installment"
                    class="sub-tab <?= $sub_tab === 'monthly_installment' ? 'active' : '' ?>"><i
                        class="fas fa-folder-open"></i> Completed Loans (<?= $count_hist_inst ?>)</a>
                <a href="?tab=history&sub_tab=blacklist" class="sub-tab <?= $sub_tab === 'blacklist' ? 'active' : '' ?>"
                    style="<?= $count_blacklist > 0 ? 'background:#fee2e2;color:#991b1b;border-color:#fecaca;' : '' ?>"><i
                        class="fas fa-user-slash"></i> Blacklist (<?= $count_blacklist ?>)</a>
            </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <form method="GET" style="display:flex;gap:16px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

                <div style="position:relative;">
                    <i class="fas fa-search"
                        style="position:absolute;left:14px;top:11px;color:#9ca3af;font-size:14px;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search customer or car..."
                        style="padding-left:38px;width:280px;font-size:13px;" value="<?= htmlspecialchars($search) ?>">
                </div>
                <?php if ($tab === 'history'): ?>
                    <input type="hidden" name="sub_tab" value="<?= htmlspecialchars($sub_tab) ?>">
                    <?php if ($sub_tab === 'booking' || $sub_tab === 'down_payment'): ?>
                        <select name="filter" class="form-control" onchange="this.form.submit()"
                            style="padding-left:12px; width:200px; font-size:13px;">

                            <option value="All" <?= ($filter === 'All' || empty($filter)) ? 'selected' : '' ?>>All Status</option>

                            <?php if ($sub_tab === 'booking'): ?>
                                <option value="Approved" <?= $filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="Rejected" <?= $filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="Refunded" <?= $filter === 'Refunded' ? 'selected' : '' ?>>Refunded</option>

                            <?php elseif ($sub_tab === 'down_payment'): ?>
                                <option value="Approved" <?= $filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="Cancelled" <?= $filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="Rejected" <?= $filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            <?php endif; ?>

                        </select>
                    <?php endif; ?>
                <?php endif; ?>

            </form>
        </div>
        <?php if ($tab === 'monthly_installment' || ($tab === 'history' && $sub_tab === 'monthly_installment')): ?>
            <div class="table-card" style="padding:0;border:none;">
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <div class="folder-grid">
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $row = apply_snapshot($row);
                            $total = (int) $row['total_months'];
                            $paid = (int) $row['paid_months'];
                            $overdue = (int) ($row['overdue_months'] ?? 0);
                            $pct = $total > 0 ? round($paid / $total * 100) : 0;
                            $next = !empty($row['next_due']) ? date('d M Y', strtotime($row['next_due'])) : '-';
                            $row_json = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            $is_completed = ($tab === 'history');
                            ?>
                            <div class="folder-card" onclick='openScheduleModal(<?= $row_json ?>)'
                                style="<?= $is_completed ? 'border-left:4px solid #10b981;' : '' ?>">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                                    <div>
                                        <div
                                            style="font-size:11px;color:#9ca3af;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;">
                                            BK<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        <div style="font-size:15px;font-weight:700;color:#111827;margin-top:2px;">
                                            <?= htmlspecialchars($row['user_name']) ?>
                                        </div>
                                        <div style="font-size:13px;color:#6b7280;">
                                            <?= htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']) ?>
                                        </div>
                                    </div>
                                    <?php if ($is_completed): ?>
                                        <i class="fas fa-check-circle" style="color:#10b981;font-size:28px;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-folder-open"
                                            style="color:<?= $overdue > 0 ? '#f97316' : '#3b82f6' ?>;font-size:28px;"></i>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_completed): ?>
                                    <div style="font-size:12px;color:#16a34a;font-weight:700;">All <?= $total ?> payments completed
                                    </div>
                                <?php else: ?>
                                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;">
                                        <span><?= $paid ?>/<?= $total ?> paid</span><span><strong
                                                style="color:#111827;"><?= $pct ?>%</strong></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?= $overdue > 0 ? 'overdue' : '' ?>" style="width:<?= $pct ?>%;">
                                        </div>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:12px;">
                                        <span style="color:#6b7280;">Next due:</span><span
                                            style="color:<?= $overdue > 0 ? '#dc2626' : '#111827' ?>;font-weight:600;"><?= $next ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:60px 0;color:#6b7280;font-size:14px;">No records found.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-card" style="padding:0;border:none;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding-left:24px;">Order ID</th>
                            <th style="text-align:left;">Customer</th>
                            <th style="text-align:left;">Car</th>
                            <?php if ($tab === 'booking'): ?>
                                <th style="text-align:left;">Booking Fee</th>
                                <th style="text-align:left;">Docs</th>
                            <?php elseif ($tab === 'down_payment' || ($tab === 'history' && $sub_tab === 'down_payment')): ?>
                                <th style="text-align:left;">DP Amount</th>
                                <th style="text-align:left;">Plate</th>
                            <?php elseif ($tab === 'history' && $sub_tab === 'blacklist'): ?>
                                <th style="text-align:left;">Overdue</th>
                            <?php endif; ?>
                            <th style="text-align:left;">Date</th>
                            <th style="text-align:left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0):
                            while ($row = mysqli_fetch_assoc($result)):
                                $row = apply_snapshot($row);
                                if ($tab === 'booking' || ($tab === 'history' && $sub_tab === 'booking'))
                                    $status_label = $row['booking_status'];
                                elseif ($tab === 'down_payment' || ($tab === 'history' && $sub_tab === 'down_payment'))
                                    $status_label = $row['dp_status'];
                                else
                                    $status_label = 'Blacklisted';

                                $status_slug = strtolower(str_replace(' ', '-', $status_label));
                                if ($status_slug === 'blacklisted')
                                    $status_slug = 'rejected';

                                $doc_count = 0;
                                foreach (['ic_url', 'driving_license_url', 'payslip_url', 'bank_statement_url'] as $col) {
                                    if (!empty($row[$col] ?? null))
                                        $doc_count++;
                                }

                                if ($tab === 'history' && $sub_tab === 'booking')
                                    $date_disp = date('d M Y, g:ia', strtotime($row['refunded_at'] ?: $row['created_at']));
                                elseif ($tab === 'down_payment' || ($tab === 'history' && $sub_tab === 'down_payment'))
                                    $date_disp = !empty($row['dp_approved_at']) ? date('d M Y, g:ia', strtotime($row['dp_approved_at'])) : date('d M Y, g:ia', strtotime($row['dp_created_at'] ?? ''));
                                else
                                    $date_disp = date('d M Y, g:ia', strtotime($row['created_at'] ?? ''));

                                $row_json = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                ?>
                                <tr class="data-row" onclick='openModal(<?= $row_json ?>, "<?= $tab ?>", "<?= $sub_tab ?>")'>
                                    <td style="text-align:left;padding-left:24px;color:#6b7280;font-weight:500;">
                                        BK<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <div style="font-weight:500;color:#111827;font-size:14px;">
                                            <?= htmlspecialchars($row['user_name']) ?>
                                        </div>
                                        <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($row['user_phone']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:500;color:#111827;font-size:14px;">
                                            <?= htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']) ?>
                                        </div>
                                        <div style="font-size:12px;color:#6b7280;">
                                            <?= htmlspecialchars(($row['car_year'] ?? '') . ' | ' . ($row['car_origin'] ?? '')) ?>
                                        </div>
                                    </td>
                                    <?php if ($tab === 'booking'): ?>
                                        <td style="font-weight:600;color:#111827;">RM <?= number_format($row['booking_fee'], 2) ?></td>
                                        <td><span
                                                class="badge-pill <?= $doc_count === 4 ? 'badge-paid' : 'badge-pending' ?>"><?= $doc_count ?>/4
                                                <?= $doc_count === 4 ? 'Complete' : 'Incomplete' ?></span></td>
                                    <?php elseif ($tab === 'down_payment' || ($tab === 'history' && $sub_tab === 'down_payment')): ?>
                                        <td style="font-weight:600;color:#111827;">RM <?= number_format($row['dp_amount'] ?? 0, 2) ?>
                                        </td>
                                        <td><?= !empty($row['plate_number']) ? "<span style='background:#111827;color:#fff;padding:2px 8px;border-radius:4px;font-family:monospace;font-size:11px;'>" . htmlspecialchars($row['plate_number']) . "</span>" : '<span style="color:#9ca3af;">-</span>' ?>
                                        </td>
                                    <?php elseif ($tab === 'history' && $sub_tab === 'blacklist'): ?>
                                        <td><span class="badge-pill badge-overdue"><?= (int) $row['max_overdue'] ?> days</span></td>
                                    <?php endif; ?>
                                    <td style="color:#4b5563;font-weight:500;"><?= $date_disp ?></td>
                                    <td>
                                        <div class="status-cell"><span class="dot dot-<?= $status_slug ?>"></span><span
                                                class="text-<?= $status_slug ?>"><?= htmlspecialchars($status_label) ?></span></div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:48px 0;color:#6b7280;font-size:14px;">No
                                    records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination-container"
                    style="padding:20px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;">
                    <div style="color:#6b7280;font-size:13px;">Showing Page <?= $page ?> of <?= $total_pages ?></div>
                    <?php if ($total_pages > 1):
                        $base_q = "?tab=" . urlencode($tab) . ($tab === 'history' ? "&sub_tab=" . urlencode($sub_tab) : '') . "&search=" . urlencode($search); ?>
                        <div style="display:flex;gap:8px;">
                            <a href="<?= $base_q ?>&page=<?= max(1, $page - 1) ?>" class="page-btn"><i
                                    class="fas fa-angle-left"></i> Prev</a>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?= $base_q ?>&page=<?= $i ?>"
                                    class="page-btn <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="<?= $base_q ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn">Next <i
                                    class="fas fa-angle-right"></i></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <div id="splitModal" class="modal">
        <div class="modal-content">
            <h2 style="font-size:20px;margin-bottom:24px;color:#111827;">
                <i class="fas fa-file-invoice" style="margin-right:8px;color:#3b82f6;"></i>
                <span id="modalTitleText">Order Details</span>
            </h2>

            <div class="modal-layout">

                <div class="modal-column">
                    <div class="info-card">
                        <h3 style="font-size:14px;margin-bottom:15px;color:#111827;"><i class="fas fa-user-circle"></i>
                            Customer Details</h3>
                        <div class="grid-2">
                            <div class="detail-item"><label>Name</label>
                                <p id="detName">-</p>
                            </div>
                            <div class="detail-item"><label>Phone</label>
                                <p id="detContact">-</p>
                            </div>
                            <div class="detail-item"><label>Email</label>
                                <p id="detEmail" style="word-break:break-all;">-</p>
                            </div>
                            <div class="detail-item"><label>IC</label>
                                <p id="detIC">-</p>
                            </div>
                        </div>
                        <div class="detail-item" style="margin-top:15px;"><label>Address</label>
                            <p id="detAddress" style="font-weight:normal;">-</p>
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;"><span id="detCityState">-</span>
                                <span id="detPostcode">-</span>
                            </p>
                        </div>
                    </div>

                    <div id="docsWrap" class="info-card" style="display:none;">
                        <h3 style="font-size:14px;margin-bottom:15px;color:#111827;"><i class="fas fa-file-pdf"></i>
                            Verification Documents</h3>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <?php
                            $doc_rows = [
                                ['ic_url', 'IC Document', 'fa-id-card', '#3b82f6'],
                                ['driving_license_url', 'Driving Licence', 'fa-id-badge', '#ef4444'],
                                ['payslip_url', '3 Months Payslip', 'fa-file-invoice-dollar', '#10b981'],
                                ['bank_statement_url', 'Bank Statement', 'fa-university', '#f59e0b'],
                            ];
                            foreach ($doc_rows as $i => $d):
                                [$key, $label, $icon, $color] = $d;
                                ?>
                                <div
                                    style="display:flex;align-items:center;justify-content:space-between;padding:10px;border:1px solid #f3f4f6;border-radius:8px;background:#f9fafb;">
                                    <span style="font-weight:600;font-size:13px;color:#374151;">
                                        <i class="fas <?= $icon ?>"
                                            style="color:<?= $color ?>;width:20px;text-align:center;margin-right:8px;"></i><?= $label ?>
                                    </span>
                                    <div style="display:flex;gap:12px;align-items:center;">
                                        <span id="statusTxt_<?= $key ?>" style="font-size:12px;font-weight:700;">-</span>
                                        <button type="button" id="btnView_<?= $key ?>"
                                            onclick="viewCustomerDoc('<?= $key ?>')" class="btn-export"
                                            style="padding:4px 10px;font-size:12px;display:none;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <iframe id="frameCustomerDoc" src=""
                                style="width:100%;height:350px;display:none;border:1px solid #d1d5db;border-radius:8px;margin-top:10px;"></iframe>
                        </div>
                    </div>

                    <div id="dpPanel" class="info-card" style="display:none;">
                        <h3 style="font-size:14px;margin-bottom:15px;color:#111827;"><i class="fas fa-car-side"></i>
                            Verification and Car Plate Registration</h3>

                        <div class="detail-item"
                            style="margin-bottom:20px; border-bottom:1px solid #e5e7eb; padding-bottom:20px;">
                            <label>Customer Uploaded Insurance</label>
                            <div style="display:flex;gap:12px;align-items:center;margin-top:8px;">
                                <span id="statusTxt_insurance" style="font-size:12px;font-weight:700;">-</span>
                                <button type="button" id="btnViewInsurance" onclick="viewCustomerDoc('insurance')"
                                    class="btn-export" style="padding:4px 10px;font-size:12px;display:none;">
                                    <i class="fas fa-eye"></i> View Document
                                </button>
                            </div>
                        </div>

                        <div class="detail-item" style="margin-bottom:15px;">
                            <label>Customer Selected Plate Option</label>
                            <p id="detPlateOption"
                                style="font-weight:700;color:#111827;background:#f3f4f6;padding:10px;border-radius:6px; margin-top:6px;">
                                -</p>
                        </div>

                        <div>
                            <label
                                style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:6px;">Register
                                Final Car Plate</label>
                            <input type="text" id="plateNumberInput" class="form-control" placeholder="e.g. ABC 1234" autocomplete="off">
                            <button class="btn-export" id="btnSaveDPDetails"
                                style="margin-top:12px;background:#fff;border-color:#3b82f6;color:#3b82f6;width:100%;"
                                onclick="saveDPDetails()">
                                <i class="fas fa-save"></i> Save Plate Info
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-column">
                    <div class="info-card">
                        <h3 style="font-size:14px;margin-bottom:15px;color:#111827;"><i class="fas fa-car"></i> Car
                            & Finance</h3>
                        <div style="display:flex;gap:15px;margin-bottom:20px;">
                            <img id="detCarImage" src="" alt="Car"
                                style="width:120px;height:85px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">
                            <div style="flex:1;">
                                <p id="detCarBrand"
                                    style="font-weight:800;color:#111827;font-size:16px;margin:0 0 4px 0;">-</p>
                                <p id="detCarModel" style="color:#6b7280;font-size:13px;margin:0;">-</p>
                            </div>
                        </div>
                        <div class="grid-2"
                            style="margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #f3f4f6;">
                            <div class="detail-item"><label>Variant</label>
                                <p id="detCarVariant">-</p>
                            </div>
                            <div class="detail-item"><label>Colour</label>
                                <p id="detCarColor">-</p>
                            </div>
                            <div class="detail-item"><label>Origin</label>
                                <p id="detCarOrigin">-</p>
                            </div>
                            <div class="detail-item"><label>Year</label>
                                <p id="detCarYear">-</p>
                            </div>
                        </div>

                        <div class="finance-box">
                            <div class="finance-row">
                                <span style="color:#6b7280;font-weight:600;">Car Price </span>
                                <span id="detPrice" style="font-weight:700;color:#111827;font-size:15px;">RM 0.00</span>
                            </div>
                            <div class="finance-row">
                                <span style="color:#6b7280;font-weight:600;">Booking Fee <span
                                        class="paid-badge">PAID</span></span>
                                <span id="detBookingFee" style="font-weight:600;color:#10b981;font-size:14px;">- RM
                                    0.00</span>
                            </div>
                            <div class="finance-row" id="rowDP" style="display:none;">
                                <span style="color:#6b7280;font-weight:600;">Down Payment <span
                                        class="paid-badge">PAID</span></span>
                                <span id="detDP" style="font-weight:600;color:#10b981;font-size:14px;">- RM 0.00</span>
                            </div>
                            <div class="finance-row total" id="rowBalance">
                                <span id="lblBalanceText"
                                    style="font-size:13px;font-weight:700;color:#111827;">Remaining Balance</span>
                                <span id="detBalance" style="font-size:18px;font-weight:800;color:#ef4444;">RM
                                    0.00</span>
                            </div>
                            <div class="finance-row total" id="rowMonthly" style="display:none;">
                                <div>
                                    <span style="display:block;font-size:13px;font-weight:700;color:#2563eb;">Monthly
                                        Installment</span>
                                    <span style="font-size:11px;color:#9ca3af;"><span id="detYears">9</span> Yrs @
                                        <?= $loan_rate ?>% P.A.</span>
                                </div>
                                <span id="detMonthly" style="font-size:22px;font-weight:800;color:#2563eb;">RM
                                    0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3 style="font-size:14px;margin-bottom:15px;color:#111827;"><i class="fas fa-info-circle"></i>
                            Order Status</h3>
                        <div class="grid-2">
                            <div class="detail-item"><label>Order ID</label>
                                <p id="detResID" style="color:#d97706;font-family:monospace;font-weight:800;">-</p>
                            </div>
                            <div class="detail-item"><label>Status</label>
                                <p id="detStatus" style="color:#3b82f6;">-</p>
                            </div>
                            <div class="detail-item"><label>Loan Term</label>
                                <p id="detLoanTerm">-</p>
                            </div>
                            <div class="detail-item"><label>Created At</label>
                                <p id="detCreatedAt" style="font-size:12px;">-</p>
                            </div>
                        </div>
                    </div>

                    <div id="reasonWrap" class="info-card" style="display:none;border-left:4px solid #ef4444;">
                        <div class="detail-item"><label id="reasonLabel">Cancellation Reason</label>
                            <p id="detReason" style="color:#dc2626;margin-top:6px;">-</p>
                        </div>
                    </div>
                </div>

            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:20px;border-top:1px solid #d1d5db;">
                <button type="button" class="btn-export" onclick="closeModal()">Close</button>
                <button type="button" id="btnRejectBooking" class="btn-export" onclick="rejectBooking()"
                    style="color:#ef4444;border-color:#ef4444;display:none;">Reject & Cancel</button>
                <button type="button" id="btnApproveBooking" class="btn-add-blue" onclick="approveBooking()"
                    style="display:none;border:none;background:#10b981;color:#fff;">Verify & Approve Booking</button>
                <button type="button" id="btnApproveDP" class="btn-add-blue" onclick="approveDP()"
                    style="display:none;border:none;background:#10b981;color:#fff;">Verify & Generate Loan</button>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content" style="max-width:800px;">
            <h2 style="font-size:20px;margin-bottom:20px;"><i class="fas fa-folder-open"
                    style="color:#3b82f6;margin-right:8px;"></i><span id="schedTitle">Amortization Schedule</span></h2>
            <div id="schedSummary" style="display:flex;gap:12px;margin-bottom:16px;"></div>
            <div style="max-height:500px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Paid At</th>
                        </tr>
                    </thead>
                    <tbody id="schedBody"></tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:20px;"><button onclick="closeScheduleModal()"
                    class="btn-export">Close</button></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.GLOBAL_LOAN_RATE = <?= $loan_rate ?>;
    </script>
    <script src="../../JAVA SCRIPT/orders.js?v=<?= time() ?>"></script>
</body>

</html>