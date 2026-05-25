<?php
// 1. 极其重要：必须加上名字，否则它找不到 Admin 的登录状态！
session_name("AdminSession");
session_start();

if (!isset($_SESSION["loggedin"]) 
    || $_SESSION["loggedin"] !== true
    || !isset($_SESSION["user_role"])
    || (strcasecmp($_SESSION["user_role"], "Admin") !== 0 
        && strcasecmp($_SESSION["user_role"], "Super Admin") !== 0)) {
    
    // 2. 修改跳转路径：踢回正确的 Admin/Auth/login.php
    header("Location: /Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Admin/Auth/login.php");
    exit;
}
include '../Config/database.php';
$logged_in_id = $_SESSION['user_id'];   
$logged_in_name = 'Admin';
$avatar_letters = 'A';
$user_avatar = null;
if ($logged_in_id) {
    $user_query = "SELECT user_name, user_avatar FROM users WHERE user_id = '$logged_in_id'";
    $user_result = mysqli_query($conn, $user_query);
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_data = mysqli_fetch_assoc($user_result);
        $logged_in_name = $user_data['user_name'];
        $user_avatar = $user_data['user_avatar'] ?? null;
        $words = explode(" ", $logged_in_name);
        $avatar_letters = "";
        foreach ($words as $w) {
            $avatar_letters .= strtoupper($w[0]);
        }
        $avatar_letters = substr($avatar_letters, 0, 2);
    }
}

$avatar_url = null;
if (!empty($user_avatar)) {
    $avatar_url = $user_avatar;
}


$fleet_query = "SELECT COUNT(*) as total_cars FROM cars";
$fleet_result = mysqli_query($conn, $fleet_query);
$total_cars = mysqli_fetch_assoc($fleet_result)['total_cars'] ?? 0;
$new_query = "SELECT COUNT(*) AS total_new FROM cars WHERE LOWER(TRIM(car_origin)) = 'new car'";
$new_result = mysqli_query($conn, $new_query);
$new_cars = mysqli_fetch_assoc($new_result)['total_new'] ?? 0;
$used_query = "SELECT COUNT(*) AS total_used FROM cars WHERE LOWER(TRIM(car_origin)) = 'used car'";
$used_result = mysqli_query($conn, $used_query);
$used_cars = mysqli_fetch_assoc($used_result)['total_used'] ?? 0;
$users_query = "SELECT COUNT(*) as total_users, SUM(user_role IN ('Admin','Super Admin')) as total_admins, SUM(user_role = 'Customer') as total_customers FROM users WHERE user_status = 'Active'";
$users_result = mysqli_query($conn, $users_query);
$users_data = mysqli_fetch_assoc($users_result);
$total_users = $users_data['total_users'] ?? 0;
$total_admins = $users_data['total_admins'] ?? 0;
$total_customers = $users_data['total_customers'] ?? 0;
$res_count_query = "SELECT COUNT(*) as total_res FROM reservations";
$res_count_result = mysqli_query($conn, $res_count_query);
$total_reservations = mysqli_fetch_assoc($res_count_result)['total_res'] ?? 0;
$stock_query = "SELECT SUM(cs.car_status_stock_quantity) as total_stock,
SUM(CASE WHEN LOWER(TRIM(c.car_origin)) = 'new car' THEN cs.car_status_stock_quantity ELSE 0 END) as new_stock,
SUM(CASE WHEN LOWER(TRIM(c.car_origin)) = 'used car' THEN cs.car_status_stock_quantity ELSE 0 END) as used_stock
FROM cars c JOIN car_status cs ON c.car_id = cs.car_id";
$stock_result = mysqli_query($conn, $stock_query);
$stock_data = mysqli_fetch_assoc($stock_result);
$total_stock = $stock_data['total_stock'] ?? 0;
$new_stock = $stock_data['new_stock'] ?? 0;
$used_stock = $stock_data['used_stock'] ?? 0;
$reservation_status_query = "

SELECT
SUM(LOWER(TRIM(reservation_status)) IN ('pending','pending viewing')) as pending_reservations,
SUM(LOWER(TRIM(reservation_status)) = 'approved') as approved_reservations,
SUM(LOWER(TRIM(reservation_status)) IN ('rejected','cancelled')) as rejected_reservations
FROM reservations
";

$reservation_status_result = mysqli_query($conn, $reservation_status_query);
$reservation_status_data = mysqli_fetch_assoc($reservation_status_result);
$pending_reservations = $reservation_status_data['pending_reservations'] ?? 0;
$approved_reservations = $reservation_status_data['approved_reservations'] ?? 0;
$rejected_reservations = $reservation_status_data['rejected_reservations'] ?? 0;
$recent_res_query = "
SELECT
r.reservation_id, r.reservation_status, r.reservation_created_at, u.user_name AS customer_name, c.car_brand, c.car_model, ci.variant, c.car_origin, ucd.car_plate, t.car_type_name
FROM reservations r
JOIN users u
ON r.user_id = u.user_id
JOIN cars c
ON r.car_id = c.car_id
LEFT JOIN car_inventory ci
ON c.car_id = ci.car_id
LEFT JOIN car_types t
ON c.car_type_id = t.car_type_id
LEFT JOIN used_car_details ucd
ON c.car_id = ucd.car_id
WHERE LOWER(TRIM(r.reservation_status)) = 'pending viewing'
ORDER BY r.reservation_created_at DESC
LIMIT 5";

$recent_res_result = mysqli_query($conn, $recent_res_query);
$calendar_query = "
SELECT
    td.test_drive_id,
    td.test_drive_at,
    td.test_drive_status,
    u.user_name AS customer_name
FROM test_drives td
JOIN reservations r ON td.reservation_id = r.reservation_id
JOIN users u ON r.user_id = u.user_id
WHERE td.test_drive_at IS NOT NULL
AND LOWER(TRIM(td.test_drive_status)) != 'cancelled' 
ORDER BY td.test_drive_at ASC
";
$calendar_result = mysqli_query($conn, $calendar_query);

$calendar_events = [];
if ($calendar_result && mysqli_num_rows($calendar_result) > 0) {
    while ($ev = mysqli_fetch_assoc($calendar_result)) {
        $status = strtolower($ev['test_drive_status']);
        $color = '#6366f1';

        if ($status == 'completed') {
            $color = '#10b981';
        }
        $calendar_events[] = [
            'title' => $ev['customer_name'],
            'start' => date('Y-m-d\TH:i:s', strtotime($ev['test_drive_at'])),
            'color' => $color
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .fc-daygrid-event {
            border: none !important;
            border-radius: 20px !important;
            padding: 4px 12px !important;
            margin: 2px 5px !important;
            display: flex !important;
            align-items: center;
            box-shadow: none !important;
        }

        .fc-daygrid-event .fc-event-main,
        .fc-daygrid-event .fc-event-main * {
            color: inherit !important;
        }

        .fc-event-time {
            font-weight: 800 !important;
            margin-right: 6px !important;
            text-transform: lowercase !important;
        }

        .fc-event-title {
            font-weight: 600 !important;
        }

        .fc-daygrid-event[style*="background-color: rgb(99, 102, 241)"],
        .fc-daygrid-event[style*="background-color: #6366f1"] {
            background-color: #e0e7ff !important;
            color: #3730a3 !important;
        }

        .fc-daygrid-event[style*="background-color: rgb(16, 185, 129)"],
        .fc-daygrid-event[style*="background-color: #10b981"] {
            background-color: #dcfce7 !important;
            color: #166534 !important;
        }

        .fc-daygrid-event[style*="background-color: rgb(239, 68, 68)"],
        .fc-daygrid-event[style*="background-color: #ef4444"] {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
        }

        .fc-daygrid-event-dot {
            display: none !important;
        }


        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            padding: 28px;
            display: flex;
            align-items: center;
            gap: 22px;
            min-height: 170px;
            transition: 0.2s ease;
        }



        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .stat-icon {
            width: 74px;
            height: 74px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            flex-shrink: 0;
        }

        .stat-info p,
        .fleet-title {
            font-size: 16px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .stat-info h3,
        .fleet-chart-info h3 {
            font-size: 42px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 14px;
            line-height: 1;
        }

        .fleet-chart-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 22px;
        }

        .fleet-chart-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 14px;
        }



        .fleet-chart-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .fleet-legend {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
            color: #475569;
            line-height: 1.4;
        }

        .legend-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-dot.new {
            background: #6366f1;
        }

        .legend-dot.used {
            background: #cbd5e1;
        }

        .reservation-bar-wrapper {
            width: 100%;
            height: 220px;
            margin-top: 10px;
        }

        .dashboard-bottom-grid {
            display: block;
            margin-top: 24px;
        }

        .calendar-card,
        {
        min-height: 750px;
        min-width: 0;
        overflow: hidden;
        }

        .reservations-card {
            padding: 0;
            overflow: hidden;
        }

        .reservations-card .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-card .admin-table th {
            padding: 14px 10px !important;
            font-size: 11px !important;
            font-weight: 700;
            white-space: nowrap;
        }



        .reservations-card .admin-table td {
            padding: 22px 14px !important;
            font-size: 14px !important;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .reservations-card .admin-table tbody tr {
            transition: 0.2s ease;
        }


        .reservations-card .admin-table tbody tr:hover {
            background: #f8fafc;
        }

        .reservations-card .admin-table td:nth-child(1) {
            white-space: nowrap;
            font-weight: 600;
        }

        .reservations-card .admin-table td:nth-child(4),
        .reservations-card .admin-table th:nth-child(4) {
            white-space: nowrap;
            font-size: 12px !important;
            color: #6b7280;
        }

        .reservations-card .admin-table td:nth-child(5),
        .reservations-card .admin-table th:nth-child(5) {
            white-space: nowrap;
            text-align: center;
        }

        .reservations-card .card-header {
            padding: 24px 24px 10px 24px;
        }

        .reservations-card table {
            padding: 0 24px 24px 24px;
        }

        .reservations-card .badge {
            padding: 5px 12px !important;
            font-size: 11px !important;
            white-space: nowrap;
            display: inline-block;
        }

        @media (max-width: 1100px) {
            .dashboard-bottom-grid {
                grid-template-columns: 1fr;
            }
        }

        #calendar {
            width: 100%;
            min-height: 520px;
        }

        .fc {
            width: 100% !important;
        }

        .fc-view-harness {
            min-height: 600px !important;
        }

        .fc-toolbar-title {
            font-size: 20px !important;
            font-weight: 700 !important;
        }

        .fc-button {
            background: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            color: #111827 !important;
            border-radius: 10px !important;
            box-shadow: none !important;
        }

        .fc-button-active {
            background: #111827 !important;
            color: white !important;
        }

        .fc-daygrid-event {
            border: none !important;
            border-radius: 8px !important;
            padding: 4px 8px !important;
            font-size: 12px !important;
        }

        .fc-past-day {
            background-color: #f3f4f6 !important;
        }

        .fc-past-day .fc-daygrid-day-number {
            color: #9ca3af !important;
            font-style: italic;
        }

        .avatar-img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e7ff;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar"
            style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 20px; border-bottom: 2px solid #f3f4f6; margin-bottom: 24px;">
            <div class="page-title">
                <h1>Dashboard</h1>
            </div>

            <div class="topbar-right" style="display: flex; align-items: center; gap: 20px;">
                <a href="settings.php" title="Settings" style="color: #6b7280; font-size: 18px; text-decoration: none;">
                    <i class="fas fa-cog"></i>
                </a>

                <a href="notifications.php" class="notification-icon" title="Notifications"
                    style="position: relative; cursor: pointer; color: #6b7280; font-size: 18px; text-decoration: none;">
                    <i class="fas fa-bell"></i>
                </a>

                <div class="user-profile"
                    style="display: flex; align-items: center; gap: 10px; margin-left: 10px; padding-left: 20px; border-left: 1px solid #e5e7eb;">
                    <span style="font-size: 14px; font-weight: 500; color: #1f1f1f;">
                        <?php echo htmlspecialchars($logged_in_name); ?>
                    </span>

                    <?php if ($avatar_url): ?>
                        <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar"
                            style="width: 35px; height: 35px; border-radius: 50%; background-color: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                            <?php echo htmlspecialchars($avatar_letters); ?>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card fleet-chart-card">
                <p class="fleet-title">Fleet Distribution</p>
                <div class="fleet-chart-wrapper">
                    <canvas id="fleetChart"></canvas>
                </div>
                <div class="fleet-chart-info">
                    <h3><?php echo $total_cars; ?></h3>
                    <div class="fleet-legend">
                        <span class="legend-dot new"></span>
                        New Cars (<?php echo $new_cars; ?>)
                    </div>

                    <div class="fleet-legend">
                        <span class="legend-dot used"></span>
                        Used Cars (<?php echo $used_cars; ?>)
                    </div>
                </div>
            </div>

            <div class="stat-card fleet-chart-card">
                <p class="fleet-title">Stock Distribution</p>
                <div class="fleet-chart-wrapper">
                    <canvas id="stockChart"></canvas>
                </div>

                <div class="fleet-chart-info">
                    <h3><?php echo $total_stock; ?></h3>
                    <div class="fleet-legend">
                        <span class="legend-dot new"></span>
                        New Cars (<?php echo $new_stock; ?>)
                    </div>

                    <div class="fleet-legend">
                        <span class="legend-dot used"></span>
                        Used Cars (<?php echo $used_stock; ?>)
                    </div>
                </div>
            </div>

            <div class="stat-card fleet-chart-card">
                <p class="fleet-title">User Distribution</p>
                <div class="fleet-chart-wrapper">
                    <canvas id="usersChart"></canvas>
                </div>
                <div class="fleet-chart-info">
                    <h3><?php echo $total_users; ?></h3>
                    <div class="fleet-legend">
                        <span class="legend-dot new"></span>
                        Admins (<?php echo $total_admins; ?>)
                    </div>

                    <div class="fleet-legend">
                        <span class="legend-dot used"></span>
                        Customers (<?php echo $total_customers; ?>)
                    </div>
                </div>
            </div>

            <div class="stat-card fleet-chart-card">
                <p class="fleet-title">Reservation Status</p>
                <div class="reservation-bar-wrapper">
                    <canvas id="reservationBarChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-bottom-grid">
            <div class="table-card calendar-card">
                <div class="card-header">
                    <h3>Test Drive Calendar</h3>
                </div>
                <div id="calendar"></div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) return;
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar library failed to load');
                calendarEl.innerHTML =
                    '<div style="padding:40px; text-align:center; color:#ef4444;">' +
                    '<i class="fas fa-exclamation-triangle" style="font-size:32px; margin-bottom:12px;"></i>' +
                    '<p>FullCalendar failed to load.</p></div>';
                return;
            }
            const events = <?php echo json_encode($calendar_events); ?>;
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 700,
                expandRows: true,
                stickyHeaderDates: true,
                eventDisplay: 'block',
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'lowercase',
                    hour12: true
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: events,
                dayCellClassNames: function (arg) {
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const cellDate = new Date(arg.date);
                    cellDate.setHours(0, 0, 0, 0);
                    if (cellDate < today) {
                        return ['fc-past-day'];
                    }
                    return [];
                }
            });

            calendar.render();
            const mainContent = document.querySelector('.main-content');
            if (mainContent && typeof ResizeObserver !== 'undefined') {
                let resizeTimer;
                const resizeObserver = new ResizeObserver(() => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => calendar.updateSize(), 50);
                });
                resizeObserver.observe(mainContent);
            }

            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    setTimeout(() => calendar.updateSize(), 300);
                });
            }

            const logoutBtn = document.querySelector('.logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function (e) {
                    if (!confirm("Are you sure you want to log out from the admin panel?")) {
                        e.preventDefault();
                    }
                });
            }
        });

        new Chart(document.getElementById('fleetChart'), {
            type: 'doughnut',
            data: {
                labels: ['New Cars', 'Used Cars'],
                datasets: [{
                    data: [<?php echo $new_cars; ?>, <?php echo $used_cars; ?>],
                    backgroundColor: ['#6366f1', '#cbd5e1'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, cutout: '75%', plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('usersChart'), {
            type: 'doughnut',
            data: {
                labels: ['Admins', 'Customers'],
                datasets: [{
                    data: [<?php echo $total_admins; ?>, <?php echo $total_customers; ?>],
                    backgroundColor: ['#6366f1', '#cbd5e1'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, cutout: '75%', plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('stockChart'), {
            type: 'doughnut',
            data: {
                labels: ['New Cars', 'Used Cars'],
                datasets: [{
                    data: [<?php echo $new_stock; ?>, <?php echo $used_stock; ?>],
                    backgroundColor: ['#6366f1', '#cbd5e1'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, cutout: '75%', plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('reservationBarChart'), {
            type: 'bar',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $pending_reservations; ?>,
                        <?php echo $approved_reservations; ?>,
                        <?php echo $rejected_reservations; ?>
                    ],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444'],
                    borderRadius: 10,
                    borderSkipped: false
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            color: '#64748b',
                            font: { size: 12, weight: '600' },
                            callback: function (value) {
                                if (Number.isInteger(value)) return value;
                                return null;
                            }
                        },
                        grid: { color: '#e5e7eb' }
                    },
                    x: {
                        ticks: { color: '#334155', font: { size: 13, weight: '700' } },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>

</html>