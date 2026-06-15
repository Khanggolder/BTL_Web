<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_admin($pdo);

$action = $_GET['action'] ?? 'list';
$order_id = intval($_GET['id'] ?? 0);
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$success_msg = $_GET['success'] ?? '';
$error_msg = '';
$order_data = [];
$order_course_ids = [];
$order_items = [];
$payment_methods = [
    'BANK_TRANSFER' => 'Chuyển khoản',
    'MOMO' => 'MoMo',
    'PAYPAL' => 'PayPal',
    'VNPAY' => 'VNPay',
    'CREDIT_CARD' => 'Thẻ tín dụng',
];
$order_status_labels = [
    'PENDING' => 'Chờ duyệt',
    'COMPLETED' => 'Hoàn tất',
    'CANCELLED' => 'Đã hủy',
];

function enroll_order_courses($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $user_id = $stmt->fetchColumn();
    if (!$user_id) return;

    $stmt = $pdo->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $course_ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($course_ids as $course_id) {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, active) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $course_id]);
            $stmt = $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = ?");
            $stmt->execute([$course_id]);
        }
    }
}

function sync_user_entitlements($pdo, $user_id) {
    if (!$user_id) return;

    
    
    $stmt = $pdo->prepare("
        DELETE e
        FROM enrollments e
        WHERE e.user_id = ?
          AND NOT EXISTS (
              SELECT 1
              FROM orders o
              JOIN order_items oi ON oi.order_id = o.id
              WHERE o.user_id = e.user_id
                AND o.status = 'COMPLETED'
                AND oi.course_id = e.course_id
          )
    ");
    $stmt->execute([$user_id]);

    $pdo->exec("
        UPDATE courses c
        SET enrollment_count = (
            SELECT COUNT(*)
            FROM enrollments e
            WHERE e.course_id = c.id AND e.active = 1
        )
    ");
}

function sync_order_items($pdo, $order_id, $course_ids) {
    $course_ids = array_values(array_unique(array_map('intval', $course_ids)));
    if (empty($course_ids)) {
        throw new Exception('Vui lòng chọn ít nhất một khóa học.');
    }

    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, title, price, discount_price FROM courses WHERE id IN ($placeholders)");
    $stmt->execute($course_ids);
    $courses = $stmt->fetchAll() ?: [];

    if (empty($courses)) {
        throw new Exception('Không tìm thấy khóa học hợp lệ.');
    }

    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);

    $total = 0;
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, course_id, course_name, price, original_price) VALUES (?, ?, ?, ?, ?)");
    foreach ($courses as $course) {
        $has_discount = $course['discount_price'] > 0 && $course['discount_price'] < $course['price'];
        $price = (float) ($has_discount ? $course['discount_price'] : $course['price']);
        $total += $price;
        $stmt->execute([$order_id, $course['id'], $course['title'], $price, $course['price']]);
    }

    $stmt = $pdo->prepare("UPDATE orders SET total_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$total, $order_id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    $quick_order_id = intval($_POST['order_id'] ?? 0);
    $quick_action = $_POST['quick_action'] ?? '';

    if ($quick_order_id > 0 && in_array($quick_action, ['APPROVE', 'CANCEL'], true)) {
        try {
            $pdo->beginTransaction();
            $status = $quick_action === 'APPROVE' ? 'COMPLETED' : 'CANCELLED';
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, paid_at = IF(? = 'COMPLETED', CURRENT_TIMESTAMP, paid_at), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, $status, $quick_order_id]);
            $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$quick_order_id]);
            $quick_user_id = (int) $stmt->fetchColumn();
            if ($status === 'COMPLETED') {
                enroll_order_courses($pdo, $quick_order_id);
            }
            sync_user_entitlements($pdo, $quick_user_id);
            $pdo->commit();
            header("Location: orders.php?status=" . urlencode($status_filter) . "&search=" . urlencode($search) . "&success=" . urlencode("Đã cập nhật đơn hàng."));
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Xử lý đơn hàng thất bại: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_orders'])) {
    $selected_order_ids = array_values(array_unique(array_map('intval', $_POST['order_ids'] ?? [])));
    $selected_order_ids = array_values(array_filter($selected_order_ids, function ($id) {
        return $id > 0;
    }));

    if (empty($selected_order_ids)) {
        $error_msg = 'Vui lòng chọn ít nhất một đơn hàng để xóa.';
    } else {
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($selected_order_ids), '?'));

            $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM orders WHERE id IN ($placeholders)");
            $stmt->execute($selected_order_ids);
            $affected_user_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

            $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
            $stmt->execute($selected_order_ids);
            $deleted_count = $stmt->rowCount();

            foreach ($affected_user_ids as $affected_user_id) {
                sync_user_entitlements($pdo, $affected_user_id);
            }

            $pdo->commit();
            header("Location: orders.php?status=" . urlencode($status_filter) . "&search=" . urlencode($search) . "&success=" . urlencode("Đã xóa " . $deleted_count . " đơn hàng."));
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = 'Xóa nhiều đơn hàng thất bại: ' . $e->getMessage();
        }
    }
}

if ($action === 'delete' && $order_id > 0) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $deleted_order_user_id = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        sync_user_entitlements($pdo, $deleted_order_user_id);

        $pdo->commit();
        header("Location: orders.php?success=" . urlencode("Đã xóa đơn hàng."));
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "Xóa đơn hàng thất bại: " . $e->getMessage();
        $action = 'list';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'BANK_TRANSFER';
    $status = $_POST['status'] ?? 'PENDING';
    $course_ids = $_POST['course_ids'] ?? [];

    if ($user_id <= 0 || !array_key_exists($payment_method, $payment_methods) || !in_array($status, ['PENDING', 'COMPLETED', 'CANCELLED'], true)) {
        $error_msg = 'Thông tin đơn hàng không hợp lệ.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'add') {
                $order_number = 'LH-' . date('ymd') . '-' . random_int(1000, 9999);
                $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, total_amount, status, payment_method, paid_at) VALUES (?, ?, 0, ?, ?, IF(? = 'COMPLETED', CURRENT_TIMESTAMP, NULL))");
                $stmt->execute([$order_number, $user_id, $status, $payment_method, $status]);
                $order_id = (int) $pdo->lastInsertId();
            } elseif ($action === 'edit' && $order_id > 0) {
                $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $previous_user_id = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("UPDATE orders SET user_id = ?, status = ?, payment_method = ?, paid_at = IF(? = 'COMPLETED', COALESCE(paid_at, CURRENT_TIMESTAMP), NULL), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user_id, $status, $payment_method, $status, $order_id]);
            }

            sync_order_items($pdo, $order_id, $course_ids);
            if ($status === 'COMPLETED') {
                enroll_order_courses($pdo, $order_id);
            }
            sync_user_entitlements($pdo, $user_id);
            if (!empty($previous_user_id) && $previous_user_id !== $user_id) {
                sync_user_entitlements($pdo, $previous_user_id);
            }

            $pdo->commit();
            header("Location: orders.php?success=" . urlencode("Đã lưu đơn hàng."));
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Lưu đơn hàng thất bại: " . $e->getMessage();
        }
    }
}

$users = $pdo->query("SELECT id, full_name, email FROM users WHERE active = 1 ORDER BY full_name")->fetchAll() ?: [];
$courses = $pdo->query("SELECT id, title, price, discount_price FROM courses ORDER BY title")->fetchAll() ?: [];

if (($action === 'edit' || $action === 'view') && $order_id > 0) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order_data = $stmt->fetch();
    if (!$order_data) {
        header("Location: orders.php");
        exit();
    }
    $stmt = $pdo->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order_course_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if ($action === 'view') {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll() ?: [];
    }
}

$orders = [];
if ($action === 'list') {
    $sql = "
        SELECT o.*, u.full_name, u.email, COUNT(oi.id) AS course_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
    ";
    $where = [];
    $params = [];
    if ($status_filter !== 'all') {
        $where[] = "o.status = ?";
        $params[] = $status_filter;
    }
    if ($search !== '') {
        $where[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " GROUP BY o.id ORDER BY o.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll() ?: [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - LearnHub Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-logo"><i data-lucide="shield-check" style="stroke:var(--primary);width:28px;height:28px;"></i><span>Admin Hub</span></div>
            <ul class="admin-menu-list">
                <li class="admin-menu-item"><a href="dashboard.php"><i data-lucide="layout-dashboard" style="width:18px;height:18px;"></i> Bảng điều khiển</a></li>
                <li class="admin-menu-item"><a href="courses.php"><i data-lucide="book-open" style="width:18px;height:18px;"></i> Quản lý khóa học</a></li>
                <li class="admin-menu-item"><a href="lessons.php"><i data-lucide="play-circle" style="width:18px;height:18px;"></i> Quản lý bài học</a></li>
                <li class="admin-menu-item active"><a href="orders.php"><i data-lucide="credit-card" style="width:18px;height:18px;"></i> Quản lý đơn hàng</a></li>
                <li class="admin-menu-item" style="margin-top:20px;border-top:1px solid #1e293b;padding-top:16px;"><a href="users.php"><i data-lucide="users" style="width:18px;height:18px;"></i> Quản lý người dùng</a></li>
                <li class="admin-menu-item" style="margin-top:20px;border-top:1px solid #1e293b;padding-top:16px;"><a href="../index.php" style="color:#ef4444;"><i data-lucide="arrow-left-right" style="width:18px;height:18px;"></i> Quay lại Client</a></li>
            </ul>
        </aside>

        <main class="admin-container admin-orders-page">
            <div class="admin-orders-page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;gap:16px;">
                <div>
                    <h1 style="font-size:30px;font-weight:800;color:var(--text-main);margin-bottom:4px;"><?php echo $action === 'list' ? 'Quản lý đơn hàng' : ($action === 'add' ? 'Tạo đơn hàng' : ($action === 'view' ? 'Chi tiết đơn hàng' : 'Sửa đơn hàng')); ?></h1>
                    <p style="color:var(--text-muted);font-size:14px;">Cập nhật đơn hàng và kích hoạt khóa học cho học viên.</p>
                </div>
                <?php if ($action === 'list'): ?>
                    <a href="orders.php?action=add" class="btn btn-primary admin-orders-top-btn" style="height:42px;font-size:14px;border-radius:var(--radius-sm);"><i data-lucide="plus-circle" style="width:18px;height:18px;"></i> Tạo đơn hàng</a>
                <?php else: ?>
                    <a href="orders.php" class="btn btn-outline admin-orders-top-btn" style="height:42px;font-size:14px;border-radius:var(--radius-sm);"><i data-lucide="arrow-left" style="width:18px;height:18px;"></i> Quay lại</a>
                <?php endif; ?>
            </div>

            <?php if ($success_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
            <?php if ($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="admin-table-card" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 24px;">
                        <div>
                            <h2 style="font-size: 24px; font-weight: 800; color: var(--primary); margin-bottom: 4px;"><?php echo htmlspecialchars($order_data['order_number']); ?></h2>
                            <p style="color: var(--text-muted);">Ngày tạo: <?php echo date('d/m/Y H:i', strtotime($order_data['created_at'])); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 26px; font-weight: 800; color: var(--text-main);"><?php echo number_format($order_data['total_amount'], 0, ',', '.'); ?>đ</div>
                            <span style="color: var(--text-muted);"><?php echo htmlspecialchars($order_status_labels[$order_data['status']] ?? $order_data['status']); ?> · <?php echo htmlspecialchars($payment_methods[$order_data['payment_method']] ?? $order_data['payment_method']); ?></span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
                        <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px;">
                            <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 12px;">Học viên</h3>
                            <p><strong><?php echo htmlspecialchars($order_data['full_name']); ?></strong></p>
                            <p style="color: var(--text-muted);"><?php echo htmlspecialchars($order_data['email']); ?></p>
                            <p style="color: var(--text-muted);"><?php echo htmlspecialchars($order_data['phone'] ?: 'Chưa cập nhật'); ?></p>
                        </div>
                        <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px;">
                            <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 12px;">Thanh toán</h3>
                            <p><strong>Mã MoMo:</strong> <?php echo htmlspecialchars($order_data['momo_order_id'] ?: 'Không có'); ?></p>
                            <p><strong>Request ID:</strong> <?php echo htmlspecialchars($order_data['momo_request_id'] ?: 'Không có'); ?></p>
                            <p><strong>Ngày thanh toán:</strong> <?php echo $order_data['paid_at'] ? date('d/m/Y H:i', strtotime($order_data['paid_at'])) : 'Chưa thanh toán'; ?></p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 22px;">
                        <a href="orders.php?action=edit&id=<?php echo $order_id; ?>" class="btn btn-primary" style="height: 40px;">Sửa đơn hàng</a>
                        <a href="orders.php" class="btn btn-outline" style="height: 40px;">Quay lại danh sách</a>
                    </div>
                </div>
                <div class="admin-table-card">
                    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px;">Khóa học trong đơn</h3>
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead><tr style="border-bottom: 1px solid var(--border); background: var(--bg-main);"><th style="padding: 12px 16px;">Khóa học</th><th style="padding: 12px 16px;">Giá bán</th><th style="padding: 12px 16px;">Giá gốc</th></tr></thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr style="border-bottom: 1px solid var(--border);"><td style="padding: 12px 16px;"><a href="courses.php?action=view&id=<?php echo $item['course_id']; ?>" style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($item['course_name']); ?></a></td><td style="padding: 12px 16px;"><?php echo number_format($item['price'], 0, ',', '.'); ?>đ</td><td style="padding: 12px 16px;"><?php echo number_format($item['original_price'], 0, ',', '.'); ?>đ</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="admin-table-card">
                    <form method="POST" action="orders.php?action=<?php echo htmlspecialchars($action); ?><?php echo $order_id ? '&id=' . $order_id : ''; ?>">
                        <input type="hidden" name="save_order" value="1">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="user_id">Học viên</label>
                                <?php
                                    $selected_user_label = '';
                                    foreach ($users as $u) {
                                        if ((int)($order_data['user_id'] ?? 0) === (int)$u['id'] || $selected_user_label === '') {
                                            $selected_user_label = $u['full_name'] . ' - ' . $u['email'];
                                            if ((int)($order_data['user_id'] ?? 0) === (int)$u['id']) break;
                                        }
                                    }
                                ?>
                                <select class="form-control admin-mobile-native-select" id="user_id" name="user_id" required>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo (int)($order_data['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name'] . ' - ' . $u['email']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="admin-custom-select admin-mobile-custom-select" data-mobile-select>
                                    <input type="hidden" name="user_id" value="<?php echo (int)($order_data['user_id'] ?? ($users[0]['id'] ?? 0)); ?>" disabled>
                                    <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                        <span><?php echo htmlspecialchars($selected_user_label); ?></span>
                                        <i data-lucide="chevron-down"></i>
                                    </button>
                                    <div class="admin-custom-select-menu">
                                        <?php foreach ($users as $u): ?>
                                            <?php $user_label = $u['full_name'] . ' - ' . $u['email']; ?>
                                            <button type="button" data-value="<?php echo $u['id']; ?>" class="<?php echo (int)($order_data['user_id'] ?? 0) === (int)$u['id'] ? 'is-selected' : ''; ?>"><?php echo htmlspecialchars($user_label); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="payment_method">Phương thức</label>
                                <?php $selected_payment_method = $order_data['payment_method'] ?? array_key_first($payment_methods); ?>
                                <select class="form-control admin-mobile-native-select" id="payment_method" name="payment_method">
                                    <?php foreach ($payment_methods as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($order_data['payment_method'] ?? '') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="admin-custom-select admin-mobile-custom-select" data-mobile-select>
                                    <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($selected_payment_method); ?>" disabled>
                                    <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                        <span><?php echo htmlspecialchars($payment_methods[$selected_payment_method] ?? reset($payment_methods)); ?></span>
                                        <i data-lucide="chevron-down"></i>
                                    </button>
                                    <div class="admin-custom-select-menu">
                                        <?php foreach ($payment_methods as $value => $label): ?>
                                            <button type="button" data-value="<?php echo htmlspecialchars($value); ?>" class="<?php echo $selected_payment_method === $value ? 'is-selected' : ''; ?>"><?php echo htmlspecialchars($label); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:20px;">
                            <label for="status">Trạng thái</label>
                            <?php $selected_status = $order_data['status'] ?? 'PENDING'; ?>
                            <select class="form-control admin-mobile-native-select" id="status" name="status">
                                <?php foreach ($order_status_labels as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($order_data['status'] ?? 'PENDING') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="admin-custom-select admin-mobile-custom-select" data-mobile-select>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($selected_status); ?>" disabled>
                                <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                    <span><?php echo htmlspecialchars($order_status_labels[$selected_status] ?? $selected_status); ?></span>
                                    <i data-lucide="chevron-down"></i>
                                </button>
                                <div class="admin-custom-select-menu">
                                    <?php foreach ($order_status_labels as $value => $label): ?>
                                        <button type="button" data-value="<?php echo htmlspecialchars($value); ?>" class="<?php echo $selected_status === $value ? 'is-selected' : ''; ?>"><?php echo htmlspecialchars($label); ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:24px;">
                            <label>Khóa học trong đơn</label>
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:8px;">
                                <?php foreach ($courses as $c): ?>
                                    <?php $price = $c['discount_price'] > 0 ? $c['discount_price'] : $c['price']; ?>
                                    <label style="display:flex;align-items:center;gap:8px;background:var(--bg-main);border:1px solid var(--border);border-radius:8px;padding:10px;font-weight:600;">
                                        <input type="checkbox" name="course_ids[]" value="<?php echo $c['id']; ?>" <?php echo in_array((int)$c['id'], $order_course_ids, true) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($c['title']); ?><br><small style="color:var(--text-muted);"><?php echo number_format($price, 0, ',', '.'); ?>d</small></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit" style="width:100%;height:48px;"><i data-lucide="save" style="width:18px;height:18px;"></i> Lưu đơn hàng</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div style="margin-bottom:24px;display:flex;gap:8px;flex-wrap:wrap;">
                    <?php foreach (['all' => 'Tất cả'] + $order_status_labels as $value => $label): ?>
                        <a href="orders.php?status=<?php echo $value; ?>&search=<?php echo urlencode($search); ?>" class="btn" style="padding:6px 14px;font-size:13px;border-radius:99px;font-weight:600;<?php echo $status_filter === $value ? 'background-color:var(--primary);color:white;' : 'background-color:#e2e8f0;color:var(--text-main);'; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="admin-table-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
                        <form method="GET" action="orders.php" style="display:flex;gap:12px;align-items:center;flex:1;min-width:420px;">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm mã đơn, tên hoặc email học viên..." style="height:42px;max-width:420px;">
                            <button type="submit" class="btn btn-primary" style="height:42px;font-size:14px;border-radius:var(--radius-sm);"><i data-lucide="search" style="width:18px;height:18px;"></i> Tìm kiếm</button>
                            <?php if ($search !== ''): ?>
                                <a href="orders.php?status=<?php echo urlencode($status_filter); ?>" class="btn btn-outline" style="height:42px;font-size:14px;border-radius:var(--radius-sm);">Xóa lọc</a>
                            <?php endif; ?>
                        </form>
                        <?php if (!empty($orders)): ?>
                            <button type="submit" form="bulk-orders-form" id="bulk-delete-orders-btn" name="bulk_delete_orders" value="1" class="btn btn-danger" style="display:none;height:42px;font-size:14px;border-radius:var(--radius-sm);align-items:center;gap:8px;">
                                <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Xóa mục đã chọn
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($orders)): ?>
                        <p style="color:var(--text-muted);font-style:italic;text-align:center;padding:40px 0;">Không tìm thấy đơn hàng phù hợp.</p>
                    <?php else: ?>
                        <form id="bulk-orders-form" method="POST" action="orders.php?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" data-confirm="Xóa các đơn hàng đã chọn? Quyền sở hữu khóa học liên quan sẽ được đồng bộ lại."></form>
                        <table class="admin-orders-table" style="width:100%;border-collapse:collapse;text-align:left;font-size:14px;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border);font-weight:700;color:var(--text-main);background-color:var(--bg-main);">
                                    <th style="padding:12px 16px;width:48px;text-align:center;"><input type="checkbox" id="select-all-orders" aria-label="Chọn tất cả đơn hàng"></th>
                                    <th style="padding:12px 16px;">Đơn hàng</th>
                                    <th style="padding:12px 16px;">Học viên</th>
                                    <th style="padding:12px 16px;">Tổng tiền</th>
                                    <th style="padding:12px 16px;">Phương thức</th>
                                    <th style="padding:12px 16px;">Trạng thái</th>
                                    <th style="padding:12px 16px;text-align:center;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr style="border-bottom:1px solid var(--border);font-weight:500;">
                                        <td style="padding:12px 16px;text-align:center;"><input type="checkbox" form="bulk-orders-form" class="order-row-check" name="order_ids[]" value="<?php echo $o['id']; ?>" aria-label="Chọn <?php echo htmlspecialchars($o['order_number']); ?>"></td>
                                        <td style="padding:12px 16px;"><strong style="color:var(--primary);"><?php echo htmlspecialchars($o['order_number']); ?></strong><br><span style="font-size:12px;color:var(--text-muted);"><?php echo $o['course_count']; ?> khóa học</span></td>
                                        <td style="padding:12px 16px;"><strong><?php echo htmlspecialchars($o['full_name']); ?></strong><br><span style="font-size:12px;color:var(--text-muted);"><?php echo htmlspecialchars($o['email']); ?></span></td>
                                        <td style="padding:12px 16px;font-weight:800;"><?php echo number_format($o['total_amount'], 0, ',', '.'); ?>d</td>
                                        <td style="padding:12px 16px;"><?php echo htmlspecialchars($payment_methods[$o['payment_method']] ?? $o['payment_method']); ?></td>
                                        <td style="padding:12px 16px;"><?php echo htmlspecialchars($order_status_labels[$o['status']] ?? $o['status']); ?></td>
                                        <td style="padding:12px 16px;text-align:center;">
                                            <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                                <?php if ($o['status'] === 'PENDING'): ?>
                                                    <form method="POST" action="orders.php?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>" data-confirm="Duyệt đơn hàng này?">
                                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                        <button class="btn btn-primary" name="quick_action" value="APPROVE" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;background:linear-gradient(135deg,#10b981,#059669);">Duyệt</button>
                                                    </form>
                                                <?php endif; ?>
                                                <a class="btn btn-secondary" href="orders.php?action=view&id=<?php echo $o['id']; ?>" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;">Xem</a>
                                                                                                <a class="btn btn-outline" href="orders.php?action=edit&id=<?php echo $o['id']; ?>" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;">Sửa</a>
                                                <a class="btn btn-danger" href="orders.php?action=delete&id=<?php echo $o['id']; ?>" data-confirm="Xóa đơn hàng này?" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;">Xóa</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        const adminMobileSelectQuery = window.matchMedia('(max-width: 900px)');

        function syncAdminMobileSelectMode() {
            const useCustomSelect = adminMobileSelectQuery.matches;

            document.querySelectorAll('.admin-mobile-native-select').forEach(function(nativeSelect) {
                nativeSelect.disabled = useCustomSelect;
            });

            document.querySelectorAll('[data-mobile-select] input[type="hidden"]').forEach(function(customInput) {
                customInput.disabled = !useCustomSelect;
            });
        }

        document.querySelectorAll('[data-mobile-select]').forEach(function(selectBox) {
            const toggle = selectBox.querySelector('.admin-custom-select-toggle');
            const hiddenInput = selectBox.querySelector('input[type="hidden"]');
            const label = toggle ? toggle.querySelector('span') : null;
            const options = selectBox.querySelectorAll('.admin-custom-select-menu button');

            if (!toggle || !hiddenInput || !label) return;

            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                document.querySelectorAll('[data-mobile-select].is-open').forEach(function(openBox) {
                    if (openBox !== selectBox) {
                        openBox.classList.remove('is-open');
                        openBox.querySelector('.admin-custom-select-toggle')?.setAttribute('aria-expanded', 'false');
                    }
                });

                const isOpen = selectBox.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            options.forEach(function(option) {
                option.addEventListener('click', function() {
                    hiddenInput.value = option.dataset.value || '';
                    label.textContent = option.textContent.trim();
                    options.forEach(function(item) {
                        item.classList.remove('is-selected');
                    });
                    option.classList.add('is-selected');
                    selectBox.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            });
        });

        document.addEventListener('click', function() {
            document.querySelectorAll('[data-mobile-select].is-open').forEach(function(selectBox) {
                selectBox.classList.remove('is-open');
                selectBox.querySelector('.admin-custom-select-toggle')?.setAttribute('aria-expanded', 'false');
            });
        });

        syncAdminMobileSelectMode();
        adminMobileSelectQuery.addEventListener?.('change', syncAdminMobileSelectMode);

        const selectAllOrders = document.getElementById('select-all-orders');
        const bulkDeleteOrdersBtn = document.getElementById('bulk-delete-orders-btn');
        const orderCheckboxes = document.querySelectorAll('.order-row-check');

        function updateBulkDeleteOrdersVisibility() {
            if (!bulkDeleteOrdersBtn) return;
            const hasSelected = Array.from(orderCheckboxes).some(function(checkbox) {
                return checkbox.checked;
            });
            bulkDeleteOrdersBtn.style.display = hasSelected ? 'inline-flex' : 'none';
        }

        if (selectAllOrders) {
            selectAllOrders.addEventListener('change', function() {
                orderCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllOrders.checked;
                });
                updateBulkDeleteOrdersVisibility();
            });
        }

        orderCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                if (selectAllOrders) {
                    selectAllOrders.checked = Array.from(orderCheckboxes).every(function(item) {
                        return item.checked;
                    });
                }
                updateBulkDeleteOrdersVisibility();
            });
        });

        updateBulkDeleteOrdersVisibility();
        lucide.createIcons();
    </script>
    <script src="../assets/js/admin-confirm.js"></script>
</body>
</html>
