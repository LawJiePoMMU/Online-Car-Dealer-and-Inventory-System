<?php

session_start();
include '../database.php';

$fleet_query = "SELECT COUNT(*) as total_cars FROM cars";
$fleet_result = mysqli_query($conn, $fleet_query);
$total_cars = mysqli_fetch_assoc($fleet_result)['total_cars'] ?? 0;

$res_count_query = "SELECT COUNT(*) as total_res FROM reservations";
$res_count_result = mysqli_query($conn, $res_count_query);
$total_reservations = mysqli_fetch_assoc($res_count_result)['total_res'] ?? 0;

$users_query = "SELECT COUNT(*) as total_users FROM users";
$users_result = mysqli_query($conn, $users_query);
$total_users = mysqli_fetch_assoc($users_result)['total_users'] ?? 0;

$stock_query = "SELECT SUM(car_status_stock_quantity) as in_stock FROM car_status";
$stock_result = mysqli_query($conn, $stock_query);
$in_stock = mysqli_fetch_assoc($stock_result)['in_stock'] ?? 0;

$recent_res_query = "
    SELECT 
        r.reservation_id, 
        u.user_name AS customer_name, 
        c.car_brand, 
        c.car_model, 
        t.car_type_name,
        r.reservation_created_at, 
        r.reservation_status 
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    JOIN cars c ON r.car_id = c.car_id
    LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
    ORDER BY r.reservation_created_at DESC
    LIMIT 5
";
$recent_res_result = mysqli_query($conn, $recent_res_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Welcome back, Admin</p>
            </div>
            <div class="user-profile">
                <span style="font-size: 14px; font-weight: 500; color: #1f1f1f;">Law Jie Po</span>
                <div class="avatar">L</div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #6366f1; background: #e0e7ff;"><i class="fas fa-car"></i></div>
                <div class="stat-info" style="width: 100%;">
                    <p>Total Fleet</p>
                    <h3 style="margin-bottom: 4px;"><?php echo $total_cars; ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: #10b981; background: #d1fae5;"><i
                        class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <p>Reservations</p>
                    <h3><?php echo $total_reservations; ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: #f59e0b; background: #fef3c7;"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <p>Total Users</p>
                    <h3><?php echo $total_users; ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: #ef4444; background: #fee2e2;"><i class="fas fa-warehouse"></i>
                </div>
                <div class="stat-info">
                    <p>In Stock (Units)</p>
                    <h3><?php echo $in_stock ? $in_stock : 0; ?></h3>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">
                <h3>Recent Reservations</h3>
                <a href="reservations.php" class="view-all">View All</a>
            </div>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Reservation ID</th>
                        <th>Customer</th>
                        <th>Car Model</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($recent_res_result && mysqli_num_rows($recent_res_result) > 0) {
                        while ($row = mysqli_fetch_assoc($recent_res_result)) {
                            $status = strtolower($row['reservation_status']);
                            $status_class = 'badge-pending';

                            if ($status == 'approved' || $status == 'completed') {
                                $status_class = 'badge-success';
                            } elseif ($status == 'cancelled' || $status == 'rejected') {
                                $status_class = 'badge-unpaid';
                            }

                            $full_car_name = htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']);

                            $type_badge = '';
                            if (!empty($row['car_type_name'])) {
                                $type_class = (strtolower($row['car_type_name']) == 'new') ? 'badge-new' : 'badge-used';
                                $type_badge = '<span class="badge ' . $type_class . '" style="margin-left: 8px; font-size: 10px; padding: 4px 8px;">' . htmlspecialchars($row['car_type_name']) . '</span>';
                            }

                            echo "<tr>";
                            echo "<td>#RES-" . str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                            echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                            echo "<td class='car-name'><strong>" . $full_car_name . "</strong>" . $type_badge . "</td>";
                            echo "<td>" . date('M d, Y', strtotime($row['reservation_created_at'])) . "</td>";
                            echo "<td><span class='badge " . $status_class . "'>" . ucfirst($row['reservation_status']) . "</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; color: #6b7280; padding: 24px;'>No recent reservations found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </main>

    <script src="../../JAVA SCRIPT/dashboard.js"></script>
</body>

</html>