<?php
session_name("AdminSession");
session_start();
include '../Config/database.php';
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$month_condition = ($month === 'all') ? "" : "AND MONTH(b.created_at) = '" . (int) $month . "'";
$kpi_query = mysqli_query($conn, "
SELECT
    COUNT(b.booking_id) as total_bookings,
    COALESCE(SUM(dp_totals.total_dp), 0) as total_downpayments,
    COALESCE(SUM(mi_totals.total_mi), 0) as total_installments,
    (
        COALESCE(SUM(b.booking_fee), 0) +
        COALESCE(SUM(dp_totals.total_dp), 0) +
        COALESCE(SUM(mi_totals.total_mi), 0)
    ) as total_revenue
FROM bookings b
LEFT JOIN (
    SELECT booking_id, SUM(dp_amount) as total_dp 
    FROM down_payments 
    WHERE paid_at IS NOT NULL
    GROUP BY booking_id
) dp_totals ON b.booking_id = dp_totals.booking_id
LEFT JOIN (
    SELECT booking_id, SUM(monthly_amount) as total_mi 
    FROM monthly_installments 
    WHERE payment_status = 'Paid'
    GROUP BY booking_id
) mi_totals ON b.booking_id = mi_totals.booking_id
WHERE YEAR(b.created_at) = '$year'
$month_condition
");
$kpi = mysqli_fetch_assoc($kpi_query);
if ($month === 'all') {
    $trend_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $trend_values = array_fill(0, 12, 0);
    $trend_query = mysqli_query($conn, "
        SELECT 
            MONTH(b.created_at) as label_num, 
            (
                COALESCE(SUM(b.booking_fee), 0) +
                COALESCE(SUM(dp_totals.total_dp), 0) +
                COALESCE(SUM(mi_totals.total_mi), 0)
            ) as revenue
        FROM bookings b
        LEFT JOIN (SELECT booking_id, SUM(dp_amount) as total_dp FROM down_payments WHERE paid_at IS NOT NULL GROUP BY booking_id) dp_totals ON b.booking_id = dp_totals.booking_id
        LEFT JOIN (SELECT booking_id, SUM(monthly_amount) as total_mi FROM monthly_installments WHERE payment_status = 'Paid' GROUP BY booking_id) mi_totals ON b.booking_id = mi_totals.booking_id
        WHERE YEAR(b.created_at) = '$year'
        GROUP BY MONTH(b.created_at)
    ");
    while ($r = mysqli_fetch_assoc($trend_query)) {
        $month_index = $r['label_num'] - 1;
        $trend_values[$month_index] = $r['revenue'];
    }

} else {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);
    $trend_labels = [];
    $trend_values = array_fill(0, $days_in_month, 0);
    for ($i = 1; $i <= $days_in_month; $i++) {
        $trend_labels[] = 'Day ' . $i;
    }

    $trend_query = mysqli_query($conn, "
        SELECT 
            DAY(b.created_at) as label_num, 
            (
                COALESCE(SUM(b.booking_fee), 0) +
                COALESCE(SUM(dp_totals.total_dp), 0) +
                COALESCE(SUM(mi_totals.total_mi), 0)
            ) as revenue
        FROM bookings b
        LEFT JOIN (SELECT booking_id, SUM(dp_amount) as total_dp FROM down_payments WHERE paid_at IS NOT NULL GROUP BY booking_id) dp_totals ON b.booking_id = dp_totals.booking_id
        LEFT JOIN (SELECT booking_id, SUM(monthly_amount) as total_mi FROM monthly_installments WHERE payment_status = 'Paid' GROUP BY booking_id) mi_totals ON b.booking_id = mi_totals.booking_id
        WHERE YEAR(b.created_at) = '$year' AND MONTH(b.created_at) = '$month'
        GROUP BY DAY(b.created_at)
    ");
    while ($r = mysqli_fetch_assoc($trend_query)) {
        $day_index = $r['label_num'] - 1;
        $trend_values[$day_index] = $r['revenue'];
    }
}

$top_models_query = mysqli_query($conn, "
SELECT 
    c.car_brand, 
    c.car_model, 
    COUNT(*) as total_sales
FROM bookings b
JOIN cars c ON b.car_id = c.car_id
WHERE LOWER(TRIM(c.car_origin)) = 'new car'
AND YEAR(b.created_at) = '$year'
$month_condition
GROUP BY b.car_id
ORDER BY total_sales DESC
LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .print-info {
            display: none;
        }

        @media print {

            .print-info {
                display: block;
            }

            .sidebar,
            .filter-group {
                display: none !important;
            }
        }

        body {
            background: #f5f7fb;
        }

        .main-content {
            padding: 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e9eef5;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .page-title p {
            font-size: 13px;
            color: #64748b;
        }

        .filter-group {
            display: flex;
            gap: 10px;
        }

        .filter-group select {
            height: 42px;
            min-width: 120px;
            padding: 0 14px;
            border: 1px solid #dbe4ee;
            border-radius: 12px;
            background: #fff;
            font-size: 13px;
            font-weight: 600;
        }

        .filter-group button {
            height: 42px;
            padding: 0 18px;
            border: none;
            border-radius: 12px;
            background: #6366f1;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .stat-card,
        .chart-card {
            background: #fff;
            border-radius: 20px;
            padding: 22px;
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03), 0 6px 20px rgba(15, 23, 42, 0.04);
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 135px;
            transition: 0.2s ease;
            box-sizing: border-box;
            width: 100%;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 20px;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #64748b;
            line-height: 1.4;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-size: 18px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 12px;
        }

        .stat-sub {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #10b981;
            font-size: 11px;
            font-weight: 700;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .chart-header {
            margin-bottom: 18px;
        }

        .chart-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-size: 15px;
        }

        .chart-title h2 {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .chart-sub {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .chart-wrapper {
            width: 100%;
            height: 260px;
        }

        .top-models-card {
            margin-top: 18px;
        }

        .model-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .model-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
        }

        .model-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .model-rank {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #6366f1;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
        }

        .model-name {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
        }

        .model-sales {
            font-size: 12px;
            font-weight: 700;
            color: #6366f1;
        }

        canvas {
            width: 100% !important;
            height: 100% !important;
        }

        @media(max-width:1200px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .filter-group {
                width: 100%;
                flex-direction: column;
            }

            .filter-group select,
            .filter-group button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>Sales Report</h1>

                <div class="print-info">
                    <p>
                        Report Period:
                        <?= ($month === 'all')
                            ? "All Months $year"
                            : date('F', mktime(0, 0, 0, $month, 1)) . " $year"; ?>
                    </p>

                    <p>
                        Generated Date:
                        <?= date('d/m/Y H:i:s'); ?>
                    </p>
                </div>
            </div>
            <form method="GET" class="filter-group">
                <button type="button" onclick="window.print()">
                    <i class="fas fa-print"></i> 
                </button>
                <select name="month" class="form-control">
                    <option value="all" <?= ($month === 'all') ? 'selected' : '' ?>>All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($month !== 'all' && (int) $month == $m) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <select name="year">
                    <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= ($year == $y) ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <button type="submit">Filter</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">TOTAL REVENUE</div>
                    <div class="stat-icon"><i class="fas fa-sack-dollar"></i></div>
                </div>
                <div class="stat-value">RM <?= number_format($kpi['total_revenue'], 2) ?></div>
                <div class="stat-sub"><i class="fas fa-arrow-trend-up"></i> Revenue Overview</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">TOTAL BOOKINGS</div>
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                </div>
                <div class="stat-value"><?= $kpi['total_bookings'] ?></div>
                <div class="stat-sub"><i class="fas fa-users"></i> Booking Conversion</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">DOWN PAYMENTS</div>
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-value">RM <?= number_format($kpi['total_downpayments'], 2) ?></div>
                <div class="stat-sub"><i class="fas fa-wallet"></i> Initial Payments</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">INSTALLMENTS</div>
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-value">RM <?= number_format($kpi['total_installments'], 2) ?></div>
                <div class="stat-sub"><i class="fas fa-clock"></i> Monthly Collections</div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <div class="chart-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <h2>Revenue Trend</h2>
                            <div class="chart-sub">Monthly booking revenue analytics</div>
                        </div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="revenueTrend"></canvas>
                </div>
            </div>

            <div class="chart-card top-models-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <div class="chart-icon"><i class="fas fa-car-side"></i></div>
                        <div>
                            <h2>Top Selling Models</h2>
                            <div class="chart-sub">Best performing vehicles</div>
                        </div>
                    </div>
                </div>
                <div class="model-list">
                    <?php
                    $rank = 1;
                    while ($model = mysqli_fetch_assoc($top_models_query)):
                        ?>
                        <div class="model-item">
                            <div class="model-left">
                                <div class="model-rank">#<?= $rank ?></div>
                                <div class="model-name">
                                    <?= $model['car_brand'] ?>     <?= $model['car_model'] ?>
                                </div>
                            </div>
                            <div class="model-sales"><?= $model['total_sales'] ?> Sales</div>
                        </div>
                        <?php
                        $rank++;
                    endwhile;
                    ?>
                </div>
            </div>
    </main>

    <script>
        new Chart(document.getElementById('revenueTrend'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($trend_values) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.12)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>

</html>