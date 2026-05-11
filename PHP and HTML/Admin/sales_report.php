<?php
session_start();
include '../Config/database.php';

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;

// Current month stats
$cur = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as total_sold,
            COALESCE(SUM(cs.car_status_price), 0) as total_revenue,
            COALESCE(AVG(cs.car_status_price), 0) as avg_price
     FROM reservations r
     LEFT JOIN cars c ON r.car_id = c.car_id
     LEFT JOIN car_status cs ON c.car_id = cs.car_id
     WHERE r.reservation_status = 'Sold'
     AND MONTH(r.reservation_sold_at) = $month
     AND YEAR(r.reservation_sold_at) = $year"
));

// Previous month stats
$prev = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as total_sold,
            COALESCE(SUM(cs.car_status_price), 0) as total_revenue
     FROM reservations r
     LEFT JOIN cars c ON r.car_id = c.car_id
     LEFT JOIN car_status cs ON c.car_id = cs.car_id
     WHERE r.reservation_status = 'Sold'
     AND MONTH(r.reservation_sold_at) = $prev_month
     AND YEAR(r.reservation_sold_at) = $prev_year"
));

// Status distribution
$status_q = mysqli_query(
    $conn,
    "SELECT reservation_status, COUNT(*) as cnt
     FROM reservations
     WHERE MONTH(reservation_created_at) = $month
     AND YEAR(reservation_created_at) = $year
     GROUP BY reservation_status"
);
$status_data = [];
while ($row = mysqli_fetch_assoc($status_q)) {
    $status_data[$row['reservation_status']] = $row['cnt'];
}

// Top 5 car models
$top_q = mysqli_query(
    $conn,
    "SELECT c.car_brand, c.car_model, COUNT(*) as sold_count,
            COALESCE(SUM(cs.car_status_price), 0) as total_value
     FROM reservations r
     LEFT JOIN cars c ON r.car_id = c.car_id
     LEFT JOIN car_status cs ON c.car_id = cs.car_id
     WHERE r.reservation_status = 'Sold'
     AND MONTH(r.reservation_sold_at) = $month
     AND YEAR(r.reservation_sold_at) = $year
     GROUP BY r.car_id
     ORDER BY sold_count DESC
     LIMIT 5"
);
$top_cars = [];
while ($row = mysqli_fetch_assoc($top_q)) {
    $top_cars[] = $row;
}

// Revenue change
$revenue_change = 0;
if ($prev['total_revenue'] > 0) {
    $revenue_change = (($cur['total_revenue'] - $prev['total_revenue']) / $prev['total_revenue']) * 100;
}
$sold_change = 0;
if ($prev['total_sold'] > 0) {
    $sold_change = (($cur['total_sold'] - $prev['total_sold']) / $prev['total_sold']) * 100;
}

function fmt($n)
{
    return number_format($n, 2);
}

function change_badge($val)
{
    $icon = $val >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    $color = $val >= 0 ? '#10b981' : '#ef4444';
    $bg = $val >= 0 ? '#d1fae5' : '#fee2e2';
    return "<span style='background:{$bg};color:{$color};font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;'>
                <i class='fas {$icon}'></i> " . abs(round($val, 1)) . "%
            </span>";
}

$month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .report-filter {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .report-filter select {
            padding: 9px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            color: #374151;
            background: #fff;
            cursor: pointer;
        }

        .report-filter select:focus {
            outline: none;
            border-color: #1e3a8a;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .stat-card .stat-label {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
        }

        .stat-card .stat-sub {
            font-size: 12px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
        }

        .section-card h3 {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .status-row:last-child {
            border-bottom: none;
        }

        .status-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-bar-wrap {
            flex: 1;
            margin: 0 16px;
            background: #f3f4f6;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }

        .status-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .status-count {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            min-width: 30px;
            text-align: right;
        }

        .top-car-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .top-car-row:last-child {
            border-bottom: none;
        }

        .top-car-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .top-car-name {
            flex: 1;
            margin: 0 14px;
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .top-car-sold {
            font-size: 13px;
            font-weight: 700;
            color: #6b7280;
            min-width: 60px;
            text-align: right;
        }

        .top-car-value {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            min-width: 110px;
            text-align: right;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 32px;
            margin-bottom: 12px;
            display: block;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .two-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 800; color: #111827;">Sales Report</h1>
        </header>

        <!-- Filter -->
        <form method="GET" class="report-filter">
            <select name="month" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <span style="font-size:13px; color:#6b7280;">Showing:
                <strong><?= $month_name . ' ' . $year ?></strong></span>
        </form>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-dollar-sign" style="color:#10b981;"></i> Total Revenue</div>
                <div class="stat-value">RM <?= fmt($cur['total_revenue']) ?></div>
                <div class="stat-sub">
                    vs last month <?= change_badge($revenue_change) ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-check-double" style="color:#6366f1;"></i> Cars Sold</div>
                <div class="stat-value"><?= $cur['total_sold'] ?> <span
                        style="font-size:16px;color:#6b7280;">Units</span></div>
                <div class="stat-sub">
                    vs last month <?= change_badge($sold_change) ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-calculator" style="color:#f59e0b;"></i> Average Price</div>
                <div class="stat-value">RM <?= fmt($cur['avg_price']) ?></div>
                <div class="stat-sub" style="color:#9ca3af;">Per unit sold this month</div>
            </div>
        </div>

        <div class="two-col">
            <!-- Status Distribution -->
            <div class="section-card">
                <h3><i class="fas fa-chart-pie" style="color:#1e3a8a;"></i> Order Status This Month</h3>
                <?php
                $statuses = [
                    'Pending Viewing' => ['#f59e0b', '#fef3c7'],
                    'Loan Processing' => ['#3b82f6', '#dbeafe'],
                    'Sold' => ['#10b981', '#d1fae5'],
                    'Cancelled' => ['#ef4444', '#fee2e2'],
                ];
                $total_orders = array_sum($status_data);
                if ($total_orders > 0):
                    foreach ($statuses as $label => [$color, $bg]):
                        $cnt = $status_data[$label] ?? 0;
                        $pct = $total_orders > 0 ? round(($cnt / $total_orders) * 100) : 0;
                        ?>
                        <div class="status-row">
                            <div class="status-label">
                                <span class="status-dot" style="background:<?= $color ?>;"></span>
                                <?= $label ?>
                            </div>
                            <div class="status-bar-wrap">
                                <div class="status-bar" style="width:<?= $pct ?>%; background:<?= $color ?>;"></div>
                            </div>
                            <span class="status-count"><?= $cnt ?></span>
                        </div>
                    <?php endforeach;
                else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        No orders this month.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Cars -->
            <div class="section-card">
                <h3><i class="fas fa-trophy" style="color:#f59e0b;"></i> Top 5 Models Sold</h3>
                <?php if (!empty($top_cars)): ?>
                    <?php foreach ($top_cars as $i => $car): ?>
                        <div class="top-car-row">
                            <div class="top-car-rank"><?= $i + 1 ?></div>
                            <div class="top-car-name">
                                <?= htmlspecialchars($car['car_brand'] . ' ' . $car['car_model']) ?>
                            </div>
                            <div class="top-car-sold"><?= $car['sold_count'] ?> unit<?= $car['sold_count'] > 1 ? 's' : '' ?>
                            </div>
                            <div class="top-car-value">RM <?= fmt($car['total_value']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-car"></i>
                        No sales recorded this month.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</body>

</html>