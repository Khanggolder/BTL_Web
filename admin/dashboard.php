<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';


require_admin($pdo);

$success_msg = '';
$error_msg = '';
$revenue_chart = [];
$order_status_chart = [];
$payment_method_labels = [
    'BANK_TRANSFER' => 'Chuyển khoản MB',
    'MOMO' => 'MoMo Sandbox',
    'PAYPAL' => 'PayPal Sandbox',
    'VNPAY' => 'VNPay Sandbox',
    'CREDIT_CARD' => 'Thẻ tín dụng',
];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_order'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($order_id > 0 && ($action === 'APPROVE' || $action === 'CANCEL')) {
        try {
            $pdo->beginTransaction();

            if ($action === 'APPROVE') {
                
                $stmt = $pdo->prepare("UPDATE orders SET status = 'COMPLETED', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'PENDING'");
                $stmt->execute([$order_id]);

                
                if ($stmt->rowCount() > 0) {
                    
                    $stmt_ord = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
                    $stmt_ord->execute([$order_id]);
                    $user_id = $stmt_ord->fetchColumn();

                    $stmt_items = $pdo->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
                    $stmt_items->execute([$order_id]);
                    $course_ids = $stmt_items->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    foreach ($course_ids as $course_id) {
                        
                        $stmt_check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
                        $stmt_check->execute([$user_id, $course_id]);
                        
                        if (!$stmt_check->fetch()) {
                            $stmt_enroll = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, active) VALUES (?, ?, 1)");
                            $stmt_enroll->execute([$user_id, $course_id]);

                            
                            $stmt_inc = $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = ?");
                            $stmt_inc->execute([$course_id]);
                        }
                    }
                    $success_msg = "Phê duyệt đơn hàng thành công!";
                }
            } else {
                
                $stmt = $pdo->prepare("UPDATE orders SET status = 'CANCELLED', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'PENDING'");
                $stmt->execute([$order_id]);
                if ($stmt->rowCount() > 0) {
                    $success_msg = "Đã hủy đơn hàng thành công!";
                }
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Lỗi xử lý đơn hàng: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action_orders'])) {
    $selected_order_ids = array_values(array_filter(array_map('intval', $_POST['order_ids'] ?? []), function ($id) {
        return $id > 0;
    }));
    $bulk_action = $_POST['bulk_action'] ?? '';

    if (empty($selected_order_ids)) {
        $error_msg = "Vui lòng chọn ít nhất một đơn hàng.";
    } elseif (!in_array($bulk_action, ['APPROVE', 'CANCEL'], true)) {
        $error_msg = "Thao tác hàng loạt không hợp lệ.";
    } else {
        try {
            $pdo->beginTransaction();
            $processed_count = 0;

            foreach ($selected_order_ids as $bulk_order_id) {
                if ($bulk_action === 'APPROVE') {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'COMPLETED', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'PENDING'");
                    $stmt->execute([$bulk_order_id]);

                    if ($stmt->rowCount() > 0) {
                        $stmt_ord = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
                        $stmt_ord->execute([$bulk_order_id]);
                        $target_user_id = $stmt_ord->fetchColumn();

                        $stmt_items = $pdo->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
                        $stmt_items->execute([$bulk_order_id]);
                        $course_ids = $stmt_items->fetchAll(PDO::FETCH_COLUMN) ?: [];

                        foreach ($course_ids as $course_id) {
                            $stmt_check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
                            $stmt_check->execute([$target_user_id, $course_id]);

                            if (!$stmt_check->fetch()) {
                                $stmt_enroll = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, active) VALUES (?, ?, 1)");
                                $stmt_enroll->execute([$target_user_id, $course_id]);

                                $stmt_inc = $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = ?");
                                $stmt_inc->execute([$course_id]);
                            }
                        }

                        $processed_count++;
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'CANCELLED', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'PENDING'");
                    $stmt->execute([$bulk_order_id]);
                    if ($stmt->rowCount() > 0) {
                        $processed_count++;
                    }
                }
            }

            $pdo->commit();
            $success_msg = $bulk_action === 'APPROVE'
                ? "Đã duyệt " . $processed_count . " đơn hàng."
                : "Đã hủy " . $processed_count . " đơn hàng.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Lỗi xử lý hàng loạt: " . $e->getMessage();
        }
    }
}


try {
    
    $total_courses = $pdo->query("SELECT COUNT(id) FROM courses")->fetchColumn() ?: 0;

    
    $total_students = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM enrollments WHERE active = 1")->fetchColumn() ?: 0;

    
    $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'COMPLETED'")->fetchColumn() ?: 0;

    
    $total_pending_orders = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'PENDING'")->fetchColumn() ?: 0;

    
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.status = 'PENDING'
        ORDER BY o.id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_orders = $stmt->fetchAll() ?: [];

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, SUM(total_amount) AS revenue
        FROM orders
        WHERE status = 'COMPLETED'
          AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
        GROUP BY month_key
        ORDER BY month_key ASC
    ");
    $monthly_revenue_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    for ($i = 5; $i >= 0; $i--) {
        $month_key = date('Y-m', strtotime("-$i months"));
        $revenue_chart[] = [
            'label' => date('m/Y', strtotime($month_key . '-01')),
            'value' => (float) ($monthly_revenue_rows[$month_key] ?? 0),
        ];
    }

    $stmt = $pdo->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");
    $status_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $order_status_chart = [
        'PENDING' => (int) ($status_rows['PENDING'] ?? 0),
        'COMPLETED' => (int) ($status_rows['COMPLETED'] ?? 0),
        'CANCELLED' => (int) ($status_rows['CANCELLED'] ?? 0),
    ];

} catch (PDOException $e) {
    die("Lỗi cơ sở dữ liệu: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LearnHub</title>
    
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        
        .admin-wrapper {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            background-color: #0f172a;
            color: #94a3b8;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            border-right: 1px solid #1e293b;
        }
        
        .admin-sidebar-logo {
            font-size: 22px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-menu-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .admin-menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            color: #94a3b8;
            transition: var(--transition);
        }
        
        .admin-menu-item a:hover, .admin-menu-item.active a {
            color: white;
            background-color: var(--primary);
        }
        
        .admin-container {
            background-color: #f8fafc;
            padding: 40px;
        }
        
        .admin-card-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .admin-stat-card {
            background-color: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .admin-stat-info h3 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        
        .admin-stat-info p {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
        }
        
        .admin-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bg-blue { background-color: #eff6ff; color: var(--primary); }
        .bg-green { background-color: #d1fae5; color: var(--success); }
        .bg-purple { background-color: #f5f3ff; color: var(--secondary); }
        .bg-orange { background-color: #fffbeb; color: var(--accent); }

        .dashboard-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.6fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .dashboard-chart-card {
            background-color: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }

        .dashboard-chart-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 20px;
        }

        .revenue-bars {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 14px;
            align-items: end;
            height: 220px;
            border-bottom: 1px solid var(--border);
            padding-top: 8px;
        }

        .revenue-bar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            height: 100%;
            min-width: 0;
        }

        .revenue-bar {
            width: 100%;
            max-width: 54px;
            min-height: 8px;
            border-radius: 6px 6px 0 0;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
        }

        .revenue-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }

        .status-row {
            display: grid;
            grid-template-columns: 100px minmax(0, 1fr) 40px;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
        }

        .status-track {
            height: 10px;
            background-color: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
        }

        .status-fill {
            height: 100%;
            border-radius: 99px;
        }

        .status-pie {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 22px;
            display: grid;
            place-items: center;
            background: #e2e8f0;
        }

        .status-pie::after {
            content: '';
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: white;
            box-shadow: inset 0 0 0 1px var(--border);
        }

        .dashboard-pending-table-scroll {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .dashboard-pending-table {
            min-width: 900px;
        }

        .dashboard-bulk-toolbar {
            width: 100%;
            clear: both;
            align-items: center;
            margin-top: 0;
        }

        .dashboard-bulk-toolbar .btn {
            flex: 0 0 auto;
        }

        @media (max-width: 760px) {
            .dashboard-pending-card {
                padding: 14px !important;
                border-radius: 12px !important;
            }

            .dashboard-pending-table {
                min-width: 760px;
            }

            .dashboard-bulk-toolbar {
                display: none;
                grid-template-columns: 1fr;
                width: 100%;
            }

            .dashboard-bulk-toolbar .btn {
                width: 100%;
                min-height: 40px;
                height: auto !important;
            }

            .dashboard-pending-table tbody tr,
            .dashboard-pending-table td {
                vertical-align: middle !important;
            }

            .dashboard-pending-table td:last-child {
                display: table-cell !important;
                vertical-align: middle !important;
                text-align: center !important;
                white-space: nowrap !important;
            }

            .dashboard-pending-table td:last-child form {
                display: inline-flex !important;
                align-items: center !important;
                margin: 0 !important;
                vertical-align: middle !important;
            }

            .dashboard-pending-table td:last-child .btn {
                height: 32px !important;
                min-height: 32px !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                align-items: center !important;
            }
        }
    </style>
</head>
<body>

    <div class="admin-wrapper">
        
        
        <aside class="admin-sidebar">
            <div class="admin-sidebar-logo">
                <i data-lucide="shield-check" style="stroke: var(--primary); width: 28px; height: 28px;"></i>
                <span>Admin Hub</span>
            </div>
            
            <ul class="admin-menu-list">
                <li class="admin-menu-item active">
                    <a href="dashboard.php">
                        <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i>
                        Bảng điều khiển
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="courses.php">
                        <i data-lucide="book-open" style="width: 18px; height: 18px;"></i>
                        Quản lý khóa học
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="lessons.php">
                        <i data-lucide="play-circle" style="width: 18px; height: 18px;"></i>
                        Quản lý bài học
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="orders.php">
                        <i data-lucide="credit-card" style="width: 18px; height: 18px;"></i>
                        Quản lý đơn hàng
                    </a>
                </li>
                <li class="admin-menu-item" style="margin-top: 20px; border-top: 1px solid #1e293b; padding-top: 16px;">
                    <a href="users.php">
                        <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                        Quản lý người dùng
                    </a>
                </li>
                <li class="admin-menu-item" style="margin-top: 20px; border-top: 1px solid #1e293b; padding-top: 16px;">
                    <a href="../index.php" style="color: #ef4444;">
                        <i data-lucide="arrow-left-right" style="width: 18px; height: 18px;"></i>
                        Quay lại Client
                    </a>
                </li>
            </ul>
        </aside>

        
        <main class="admin-container">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                <div>
                    <h1 style="font-size: 30px; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Hệ thống Quản trị</h1>
                    <p style="color: var(--text-muted); font-size: 14px;">Chào mừng trở lại, người điều hành LearnHub.</p>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    <i data-lucide="check-circle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            
            <div class="admin-card-grid">
                
                <div class="admin-stat-card">
                    <div class="admin-stat-info">
                        <h3><?php echo $total_courses; ?></h3>
                        <p>KHÓA HỌC</p>
                    </div>
                    <div class="admin-stat-icon bg-blue">
                        <i data-lucide="book-open"></i>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-info">
                        <h3><?php echo $total_students; ?></h3>
                        <p>HỌC VIÊN ĐÃ HỌC</p>
                    </div>
                    <div class="admin-stat-icon bg-purple">
                        <i data-lucide="graduation-cap"></i>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-info">
                        <h3><?php echo number_format($total_revenue, 0, ',', '.'); ?>đ</h3>
                        <p>TỔNG DOANH THU</p>
                    </div>
                    <div class="admin-stat-icon bg-green">
                        <i data-lucide="dollar-sign"></i>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-info">
                        <h3><?php echo $total_pending_orders; ?></h3>
                        <p>ĐƠN HÀNG ĐANG CHỜ</p>
                    </div>
                    <div class="admin-stat-icon bg-orange">
                        <i data-lucide="clock"></i>
                    </div>
                </div>

            </div>

            <?php
                $max_revenue = max(array_column($revenue_chart, 'value') ?: [0]);
                $total_status_orders = array_sum($order_status_chart);
                $status_meta = [
                    'PENDING' => ['label' => 'Chờ duyệt', 'color' => '#f59e0b'],
                    'COMPLETED' => ['label' => 'Hoàn tất', 'color' => '#10b981'],
                    'CANCELLED' => ['label' => 'Đã hủy', 'color' => '#ef4444'],
                ];
                $pending_pct = $total_status_orders > 0 ? ($order_status_chart['PENDING'] / $total_status_orders) * 100 : 0;
                $completed_pct = $total_status_orders > 0 ? ($order_status_chart['COMPLETED'] / $total_status_orders) * 100 : 0;
                $pending_stop = round($pending_pct, 2);
                $completed_stop = round($pending_pct + $completed_pct, 2);
            ?>
            <div class="dashboard-chart-grid">
                <div class="dashboard-chart-card">
                    <h3 class="dashboard-chart-title">Doanh thu 6 tháng gần nhất</h3>
                    <div class="revenue-bars">
                        <?php foreach ($revenue_chart as $point): ?>
                            <?php $height = $max_revenue > 0 ? max(8, round(($point['value'] / $max_revenue) * 180)) : 8; ?>
                            <div class="revenue-bar-item" title="<?php echo number_format($point['value'], 0, ',', '.'); ?>đ">
                                <div style="font-size:11px;color:var(--text-muted);font-weight:700;"><?php echo number_format($point['value'], 0, ',', '.'); ?>đ</div>
                                <div class="revenue-bar" style="height:<?php echo $height; ?>px;"></div>
                                <div class="revenue-label"><?php echo htmlspecialchars($point['label']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-chart-card">
                    <h3 class="dashboard-chart-title">Trạng thái đơn hàng</h3>
                    <div class="status-pie" style="background: conic-gradient(#f59e0b 0 <?php echo $pending_stop; ?>%, #10b981 <?php echo $pending_stop; ?>% <?php echo $completed_stop; ?>%, #ef4444 <?php echo $completed_stop; ?>% 100%);"></div>
                    <?php foreach ($status_meta as $status_key => $meta): ?>
                        <?php
                            $count = $order_status_chart[$status_key] ?? 0;
                            $width = $total_status_orders > 0 ? round(($count / $total_status_orders) * 100) : 0;
                        ?>
                        <div class="status-row">
                            <span><?php echo htmlspecialchars($meta['label']); ?></span>
                            <div class="status-track">
                                <div class="status-fill" style="width:<?php echo $width; ?>%;background-color:<?php echo $meta['color']; ?>;"></div>
                            </div>
                            <span style="text-align:right;"><?php echo $count; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($total_status_orders === 0): ?>
                        <p style="color:var(--text-muted);font-style:italic;">Chưa có dữ liệu đơn hàng để hiển thị.</p>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="dashboard-pending-card" style="background-color: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="bell" style="color: var(--accent);"></i>
                    Đơn hàng cần xử lý phê duyệt gấp (<?php echo count($pending_orders); ?>)
                </h3>

                <?php if (empty($pending_orders)): ?>
                    <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 40px 0;">Tuyệt vời! Không có hóa đơn chuyển khoản nào đang chờ xử lý.</p>
                <?php else: ?>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;color:var(--text-main);">
                            <input type="checkbox" id="dashboard-check-all-orders">
                            Chọn tất cả
                        </label>
                    </div>
                    <div class="dashboard-bulk-toolbar" id="dashboard-bulk-toolbar" style="display:none; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
                        <button type="submit" form="dashboard-bulk-orders-form" name="bulk_action" value="APPROVE" class="btn btn-primary" style="height:38px;font-size:13px;border-radius:var(--radius-sm);background:linear-gradient(135deg,#10b981,#059669);" data-confirm="Duyệt tất cả đơn hàng đã chọn?">Duyệt mục đã chọn</button>
                        <button type="submit" form="dashboard-bulk-orders-form" name="bulk_action" value="CANCEL" class="btn btn-danger" style="height:38px;font-size:13px;border-radius:var(--radius-sm);" data-confirm="Hủy tất cả đơn hàng đã chọn?">Hủy mục đã chọn</button>
                    </div>
                    <form id="dashboard-bulk-orders-form" method="POST" action="dashboard.php">
                        <input type="hidden" name="bulk_action_orders" value="1">
                    </form>
                    <div class="dashboard-pending-table-scroll">
                    <table class="dashboard-pending-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); font-weight: 700; color: var(--text-main); background-color: var(--bg-main);">
                                <th style="padding: 12px 16px;">Mã hóa đơn</th>
                                <th style="padding: 12px 16px;">Học viên mua</th>
                                <th style="padding: 12px 16px;">Số tiền</th>
                                <th style="padding: 12px 16px;">Phương thức</th>
                                <th style="padding: 12px 16px;">Ngày đặt</th>
                                <th style="padding: 12px 16px; text-align: center;">Hành động duyệt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_orders as $ord): ?>
                                <tr style="border-bottom: 1px solid var(--border); font-weight: 500;">
                                    <td style="padding: 12px 16px;">
                                        <label style="display:flex;align-items:center;gap:10px;">
                                            <input type="checkbox" form="dashboard-bulk-orders-form" class="dashboard-order-check" name="order_ids[]" value="<?php echo $ord['id']; ?>" aria-label="Chọn <?php echo htmlspecialchars($ord['order_number']); ?>">
                                            <strong style="color: var(--primary);"><?php echo htmlspecialchars($ord['order_number']); ?></strong>
                                        </label>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <strong><?php echo htmlspecialchars($ord['full_name']); ?></strong><br>
                                        <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($ord['email']); ?></span>
                                    </td>
                                    <td style="padding: 12px 16px; font-weight: 700; color: var(--text-main);"><?php echo number_format($ord['total_amount'], 0, ',', '.'); ?>đ</td>
                                    <td style="padding: 12px 16px;">
                                        <span style="color:var(--text-muted);font-weight:700;"><?php echo htmlspecialchars($payment_method_labels[$ord['payment_method']] ?? $ord['payment_method']); ?></span>
                                    </td>
                                    <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($ord['created_at'])); ?></td>
                                    <td style="padding: 12px 16px; text-align: center; display: flex; gap: 8px; justify-content: center;">
                                        
                                        <form action="dashboard.php" method="POST" data-confirm="Bạn có chắc chắn duyệt mở khóa đơn hàng này?">
                                            <input type="hidden" name="action_order" value="1">
                                            <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                            <input type="hidden" name="action" value="APPROVE">
                                            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; background: linear-gradient(135deg, #10b981, #059669); height: 32px;">Duyệt</button>
                                        </form>

                                        
                                        <form action="dashboard.php" method="POST" data-confirm="Bạn có chắc chắn muốn hủy đơn hàng này?">
                                            <input type="hidden" name="action_order" value="1">
                                            <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                            <input type="hidden" name="action" value="CANCEL">
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Hủy</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

        </main>

    </div>

    <script>
        
        lucide.createIcons();

        const dashboardBulkToolbar = document.getElementById('dashboard-bulk-toolbar');
        const dashboardCheckAll = document.getElementById('dashboard-check-all-orders');
        const dashboardOrderChecks = Array.from(document.querySelectorAll('.dashboard-order-check'));
        const dashboardBulkForm = document.getElementById('dashboard-bulk-orders-form');

        function updateDashboardBulkToolbar() {
            if (!dashboardBulkToolbar) return;
            const hasSelected = dashboardOrderChecks.some(function(check) {
                return check.checked;
            });
            dashboardBulkToolbar.style.display = hasSelected
                ? (window.matchMedia('(max-width: 760px)').matches ? 'grid' : 'flex')
                : 'none';

            if (dashboardCheckAll) {
                dashboardCheckAll.checked = dashboardOrderChecks.length > 0 && dashboardOrderChecks.every(function(check) {
                    return check.checked;
                });
            }
        }

        if (dashboardCheckAll) {
            dashboardCheckAll.addEventListener('change', function() {
                dashboardOrderChecks.forEach(function(check) {
                    check.checked = dashboardCheckAll.checked;
                });
                updateDashboardBulkToolbar();
            });
        }

        dashboardOrderChecks.forEach(function(check) {
            check.addEventListener('change', updateDashboardBulkToolbar);
        });

        document.querySelectorAll('#dashboard-bulk-toolbar button[data-confirm]').forEach(function(button) {
            button.addEventListener('click', function() {
                if (dashboardBulkForm) {
                    dashboardBulkForm.setAttribute('data-confirm', button.getAttribute('data-confirm'));
                }
            });
        });

        updateDashboardBulkToolbar();
    </script>
    <script src="../assets/js/admin-confirm.js"></script>
    <script src="../assets/js/admin-menu.js"></script>
</body>
</html>
