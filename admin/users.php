<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_admin($pdo);

$current_admin_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$target_user_id = intval($_GET['id'] ?? 0);
$success_msg = $_GET['success'] ?? '';
$error_msg = '';
$search = trim($_GET['search'] ?? '');
$user_data = [];
$user_orders = [];
$user_enrollments = [];
$user_stats = ['orders' => 0, 'enrollments' => 0];
$order_status_labels = [
    'PENDING' => 'Chờ duyệt',
    'COMPLETED' => 'Hoàn tất',
    'CANCELLED' => 'Đã hủy',
];

if ($action === 'revoke_course' && $target_user_id > 0) {
    $course_id = intval($_GET['course_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$target_user_id, $course_id]);

        $stmt = $pdo->prepare("
            UPDATE courses
            SET enrollment_count = (
                SELECT COUNT(*)
                FROM enrollments e
                WHERE e.course_id = courses.id AND e.active = 1
            )
            WHERE id = ?
        ");
        $stmt->execute([$course_id]);

        $pdo->commit();
        header("Location: users.php?action=view&id=" . $target_user_id . "&success=" . urlencode("Đã thu hồi quyền sở hữu khóa học."));
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "Thu hồi quyền sở hữu thất bại: " . $e->getMessage();
        $action = 'view';
    }
}

if ($action === 'delete' && $target_user_id > 0) {
    if ($target_user_id === $current_admin_id) {
        $error_msg = 'Không thể xóa tài khoản đang đăng nhập.';
        $action = 'list';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$target_user_id]);
            header("Location: users.php?success=" . urlencode("Đã xóa người dùng thành công."));
            exit();
        } catch (PDOException $e) {
            $error_msg = "Xóa người dùng thất bại: " . $e->getMessage();
            $action = 'list';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');
    $password = $_POST['password'] ?? '';
    $active = isset($_POST['active']) ? 1 : 0;
    $is_admin_role = isset($_POST['is_admin']) ? 1 : 0;

    if ($full_name === '' || $email === '') {
        $error_msg = 'Họ tên và email là bắt buộc.';
    } elseif ($action === 'add' && strlen($password) < 6) {
        $error_msg = 'Mật khẩu khi tạo mới phải từ 6 ký tự.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'add') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, phone, avatar, active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$email, $hash, $full_name, $phone, $avatar, $active]);
                $target_user_id = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'ROLE_USER')");
                $stmt->execute([$target_user_id]);
            } elseif ($action === 'edit' && $target_user_id > 0) {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        throw new Exception('Mật khẩu mới phải từ 6 ký tự.');
                    }
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ?, full_name = ?, phone = ?, avatar = ?, active = ? WHERE id = ?");
                    $stmt->execute([$email, $hash, $full_name, $phone, $avatar, $active, $target_user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, avatar = ?, active = ? WHERE id = ?");
                    $stmt->execute([$email, $full_name, $phone, $avatar, $active, $target_user_id]);
                }

                $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role) VALUES (?, 'ROLE_USER')");
                $stmt->execute([$target_user_id]);
            }

            if ($target_user_id !== $current_admin_id) {
                if ($is_admin_role) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role) VALUES (?, 'ROLE_ADMIN')");
                    $stmt->execute([$target_user_id]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role = 'ROLE_ADMIN'");
                    $stmt->execute([$target_user_id]);
                }
            }

            $pdo->commit();
            header("Location: users.php?success=" . urlencode("Đã lưu người dùng thành công."));
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Lưu người dùng thất bại: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_users'])) {
    $selected_user_ids = array_values(array_unique(array_map('intval', $_POST['user_ids'] ?? [])));
    $selected_user_ids = array_values(array_filter($selected_user_ids, function ($id) use ($current_admin_id) {
        return $id > 0 && $id !== $current_admin_id;
    }));

    if (empty($selected_user_ids)) {
        $error_msg = 'Vui lòng chọn ít nhất một người dùng hợp lệ để xóa.';
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($selected_user_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            $stmt->execute($selected_user_ids);
            header("Location: users.php?success=" . urlencode("Đã xóa " . $stmt->rowCount() . " người dùng."));
            exit();
        } catch (PDOException $e) {
            $error_msg = "Xóa nhiều người dùng thất bại: " . $e->getMessage();
            $action = 'list';
        }
    }
}

if (($action === 'edit' || $action === 'view') && $target_user_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.*, GROUP_CONCAT(ur.role) AS roles
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$target_user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        header("Location: users.php");
        exit();
    }
}

if ($action === 'view' && $target_user_id > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $user_stats['orders'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND active = 1");
    $stmt->execute([$target_user_id]);
    $user_stats['enrollments'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$target_user_id]);
    $user_orders = $stmt->fetchAll() ?: [];

    $stmt = $pdo->prepare("
        SELECT e.id AS enrollment_id, e.course_id, e.enrolled_at, e.active, c.title, c.category
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        WHERE e.user_id = ? AND e.active = 1
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$target_user_id]);
    $user_enrollments = $stmt->fetchAll() ?: [];
}

$users = [];
if ($action === 'list') {
    $sql = "
        SELECT u.*, GROUP_CONCAT(ur.role) AS roles
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
    ";
    $params = [];

    if ($search !== '') {
        $sql .= " WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }

    $sql .= " GROUP BY u.id ORDER BY u.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll() ?: [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - LearnHub Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-logo">
                <i data-lucide="shield-check" style="stroke: var(--primary); width: 28px; height: 28px;"></i>
                <span>Admin Hub</span>
            </div>
            <ul class="admin-menu-list">
                <li class="admin-menu-item"><a href="dashboard.php"><i data-lucide="layout-dashboard" style="width:18px;height:18px;"></i> Bảng điều khiển</a></li>
                <li class="admin-menu-item"><a href="courses.php"><i data-lucide="book-open" style="width:18px;height:18px;"></i> Quản lý khóa học</a></li>
                <li class="admin-menu-item"><a href="lessons.php"><i data-lucide="play-circle" style="width:18px;height:18px;"></i> Quản lý bài học</a></li>
                
                <li class="admin-menu-item"><a href="orders.php"><i data-lucide="credit-card" style="width:18px;height:18px;"></i> Quản lý đơn hàng</a></li>
                <li class="admin-menu-item active" style="margin-top:20px;border-top:1px solid #1e293b;padding-top:16px;"><a href="users.php"><i data-lucide="users" style="width:18px;height:18px;"></i> Quản lý người dùng</a></li>
                <li class="admin-menu-item" style="margin-top:20px;border-top:1px solid #1e293b;padding-top:16px;"><a href="../index.php" style="color:#ef4444;"><i data-lucide="arrow-left-right" style="width:18px;height:18px;"></i> Quay lại Client</a></li>
            </ul>
        </aside>

        <main class="admin-container admin-users-page">
            <div class="admin-users-page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;gap:16px;">
                <div>
                    <h1 class="admin-users-page-title" style="font-size:30px;font-weight:800;color:var(--text-main);margin-bottom:4px;">
                        <?php echo $action === 'list' ? 'Quản lý người dùng' : ($action === 'add' ? 'Thêm người dùng' : ($action === 'view' ? 'Chi tiết người dùng' : 'Sửa người dùng')); ?>
                    </h1>
                    <p style="color:var(--text-muted);font-size:14px;">Cập nhật tài khoản, trạng thái kích hoạt và quyền quản trị.</p>
                </div>
                <?php if ($action === 'list'): ?>
                    <a href="users.php?action=add" class="btn btn-primary admin-users-top-btn" style="height:42px;font-size:14px;border-radius:var(--radius-sm);">
                        <i data-lucide="user-plus" style="width:18px;height:18px;"></i> Thêm người dùng
                    </a>
                <?php else: ?>
                    <a href="users.php" class="btn btn-outline admin-users-top-btn" style="height:42px;font-size:14px;border-radius:var(--radius-sm);">
                        <i data-lucide="arrow-left" style="width:18px;height:18px;"></i> Quay lại
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <?php $roles = $user_data['roles'] ?? ''; ?>
                <div class="admin-table-card" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap;">
                        <div style="display: flex; gap: 16px; align-items: center;">
                            <div class="admin-user-avatar" style="width: 72px; height: 72px; flex-basis: 72px; font-size: 28px;">
                                <?php
                                    $detail_user_avatar = trim($user_data['avatar'] ?? '');
                                    $detail_user_char = mb_strtoupper(mb_substr($user_data['full_name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
                                ?>
                                <?php if ($detail_user_avatar !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($detail_user_avatar); ?>" alt="<?php echo htmlspecialchars($user_data['full_name']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <span style="width:100%;height:100%;align-items:center;justify-content:center;<?php echo $detail_user_avatar !== '' ? 'display:none;' : 'display:flex;'; ?>"><?php echo $detail_user_char; ?></span>
                            </div>
                            <div>
                                <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 4px;"><?php echo htmlspecialchars($user_data['full_name']); ?></h2>
                                <p style="color: var(--text-muted);"><?php echo htmlspecialchars($user_data['email']); ?></p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px 18px; min-width: 130px;"><strong style="font-size: 22px;"><?php echo $user_stats['orders']; ?></strong><br><span style="color: var(--text-muted);">Đơn hàng</span></div>
                            <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px 18px; min-width: 130px;"><strong style="font-size: 22px;"><?php echo $user_stats['enrollments']; ?></strong><br><span style="color: var(--text-muted);">Khóa học</span></div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 24px;">
                        <div><strong>Điện thoại</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($user_data['phone'] ?: 'Chưa cập nhật'); ?></span></div>
                        <div><strong>Vai trò</strong><br><span style="color: var(--text-muted);"><?php echo strpos($roles, 'ROLE_ADMIN') !== false ? 'Quản trị viên' : 'Học viên'; ?></span></div>
                        <div><strong>Trạng thái</strong><br><span style="color: var(--text-muted);"><?php echo $user_data['active'] ? 'Đang hoạt động' : 'Bị khóa'; ?></span></div>
                        <div><strong>Ngày tạo</strong><br><span style="color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($user_data['created_at'])); ?></span></div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 24px;">
                        <a href="users.php?action=edit&id=<?php echo $target_user_id; ?>" class="btn btn-primary" style="height: 40px;">Sửa người dùng</a>
                        <a href="users.php" class="btn btn-outline" style="height: 40px;">Quay lại danh sách</a>
                    </div>
                </div>

                <div class="admin-table-card" style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px;">Khóa học đang sở hữu</h3>
                    <?php if (empty($user_enrollments)): ?>
                        <p style="color: var(--text-muted); font-style: italic;">Người dùng này chưa sở hữu khóa học nào.</p>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border);background:var(--bg-main);">
                                    <th style="padding:12px 16px;">Khóa học</th>
                                    <th style="padding:12px 16px;">Danh mục</th>
                                    <th style="padding:12px 16px;">Ngày sở hữu</th>
                                    <th style="padding:12px 16px;text-align:center;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_enrollments as $enrollment): ?>
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:12px 16px;">
                                            <a href="courses.php?action=view&id=<?php echo $enrollment['course_id']; ?>" style="color:var(--primary);font-weight:700;"><?php echo htmlspecialchars($enrollment['title']); ?></a>
                                        </td>
                                        <td style="padding:12px 16px;color:var(--text-muted);"><?php echo htmlspecialchars($enrollment['category']); ?></td>
                                        <td style="padding:12px 16px;color:var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($enrollment['enrolled_at'])); ?></td>
                                        <td style="padding:12px 16px;text-align:center;">
                                            <a href="users.php?action=revoke_course&id=<?php echo $target_user_id; ?>&course_id=<?php echo $enrollment['course_id']; ?>" class="btn btn-danger" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;" data-confirm="Thu hồi quyền sở hữu khóa học này? Người dùng sẽ phải mua lại nếu muốn học tiếp.">Thu hồi</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="admin-table-card">
                    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px;">Đơn hàng gần đây</h3>
                    <?php if (empty($user_orders)): ?>
                        <p style="color: var(--text-muted); font-style: italic;">Người dùng này chưa có đơn hàng.</p>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px;">
                            <thead><tr style="border-bottom:1px solid var(--border);background:var(--bg-main);"><th style="padding:12px 16px;">Mã đơn</th><th style="padding:12px 16px;">Số tiền</th><th style="padding:12px 16px;">Trạng thái</th><th style="padding:12px 16px;">Ngày tạo</th></tr></thead>
                            <tbody>
                                <?php foreach ($user_orders as $order): ?>
                                    <tr style="border-bottom:1px solid var(--border);"><td style="padding:12px 16px;"><a href="orders.php?action=view&id=<?php echo $order['id']; ?>" style="color:var(--primary);font-weight:700;"><?php echo htmlspecialchars($order['order_number']); ?></a></td><td style="padding:12px 16px;"><?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ</td><td style="padding:12px 16px;"><?php echo htmlspecialchars($order_status_labels[$order['status']] ?? $order['status']); ?></td><td style="padding:12px 16px;"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <?php $roles = $user_data['roles'] ?? ''; ?>
                <div class="admin-table-card">
                    <form method="POST" action="users.php?action=<?php echo htmlspecialchars($action); ?><?php echo $target_user_id ? '&id=' . $target_user_id : ''; ?>">
                        <input type="hidden" name="save_user" value="1">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="full_name">Họ tên *</label>
                                <input class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input class="form-control" type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="phone">Điện thoại</label>
                                <input class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="avatar">Avatar URL</label>
                                <input class="form-control" id="avatar" name="avatar" value="<?php echo htmlspecialchars($user_data['avatar'] ?? ''); ?>">
                                <?php if (!empty($user_data['avatar'])): ?>
                                    <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
                                        <div class="admin-user-avatar">
                                            <img src="<?php echo htmlspecialchars($user_data['avatar']); ?>" alt="<?php echo htmlspecialchars($user_data['full_name'] ?? 'Avatar'); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <span style="width:100%;height:100%;align-items:center;justify-content:center;display:none;"><?php echo mb_strtoupper(mb_substr($user_data['full_name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8'); ?></span>
                                        </div>
                                        <span style="font-size:12px;color:var(--text-muted);font-weight:600;">Preview avatar</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:20px;">
                            <label for="password"><?php echo $action === 'add' ? 'Mật khẩu *' : 'Mật khẩu mới (bỏ trống nếu không đổi)'; ?></label>
                            <input class="form-control" type="password" id="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                        </div>
                        <div style="display:flex;gap:20px;align-items:center;margin-bottom:28px;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:8px;font-weight:700;"><input type="checkbox" name="active" value="1" <?php echo ($user_data['active'] ?? 1) ? 'checked' : ''; ?>> Kích hoạt</label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:700;"><input type="checkbox" name="is_admin" value="1" <?php echo strpos($roles, 'ROLE_ADMIN') !== false ? 'checked' : ''; ?> <?php echo $target_user_id === $current_admin_id ? 'disabled' : ''; ?>> Quản trị viên</label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;height:48px;"><i data-lucide="save" style="width:18px;height:18px;"></i> Lưu người dùng</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="admin-table-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
                        <form method="GET" action="users.php" style="display:flex;gap:12px;align-items:center;flex:1;min-width:420px;">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm theo tên, email hoặc số điện thoại..." style="height:42px;max-width:420px;">
                            <button type="submit" class="btn btn-primary" style="height:42px;font-size:14px;border-radius:var(--radius-sm);"><i data-lucide="search" style="width:18px;height:18px;"></i> Tìm kiếm</button>
                            <?php if ($search !== ''): ?>
                                <a href="users.php" class="btn btn-outline" style="height:42px;font-size:14px;border-radius:var(--radius-sm);">Xóa lọc</a>
                            <?php endif; ?>
                        </form>
                        <?php if (!empty($users)): ?>
                            <button type="submit" form="bulk-users-form" id="bulk-delete-users-btn" name="bulk_delete_users" value="1" class="btn btn-danger" style="display:none;height:42px;font-size:14px;border-radius:var(--radius-sm);align-items:center;gap:8px;">
                                <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Xóa mục đã chọn
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($users)): ?>
                        <p style="color:var(--text-muted);font-style:italic;text-align:center;padding:40px 0;">Không tìm thấy người dùng nào.</p>
                    <?php else: ?>
                        <form id="bulk-users-form" method="POST" action="users.php?search=<?php echo urlencode($search); ?>" data-confirm="Xóa các người dùng đã chọn? Tất cả dữ liệu liên quan sẽ bị xóa theo.">
                            <div style="display:flex;justify-content:flex-start;align-items:center;margin-bottom:14px;gap:12px;">
                                <span style="color:var(--text-muted);font-size:13px;font-weight:600;">Đang hiển thị <?php echo count($users); ?> người dùng</span>
                            </div>
                            <table class="admin-users-table" style="width:100%;border-collapse:collapse;text-align:left;font-size:14px;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border);font-weight:700;color:var(--text-main);background-color:var(--bg-main);">
                                        <th style="padding:12px 16px;width:48px;text-align:center;"><input type="checkbox" id="select-all-users" aria-label="Chọn tất cả người dùng"></th>
                                        <th style="padding:12px 16px;">Người dùng</th>
                                        <th style="padding:12px 16px;">Email</th>
                                        <th style="padding:12px 16px;">Điện thoại</th>
                                        <th style="padding:12px 16px;">Vai trò</th>
                                        <th style="padding:12px 16px;">Trạng thái</th>
                                        <th style="padding:12px 16px;text-align:center;">Chỉnh sửa</th>
                                        <th style="padding:12px 16px;text-align:center;">Xóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <?php $is_target_admin = strpos($u['roles'] ?? '', 'ROLE_ADMIN') !== false; ?>
                                        <tr style="border-bottom:1px solid var(--border);font-weight:500;">
                                            <td style="padding:12px 16px;text-align:center;">
                                                <input type="checkbox" class="user-row-check" name="user_ids[]" value="<?php echo $u['id']; ?>" <?php echo $u['id'] === $current_admin_id ? 'disabled' : ''; ?> aria-label="Chọn <?php echo htmlspecialchars($u['full_name']); ?>">
                                            </td>
                                            <td style="padding:12px 16px;">
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div class="admin-user-avatar">
                                                        <?php
                                                            $admin_user_avatar = trim($u['avatar'] ?? '');
                                                            $admin_user_char = mb_strtoupper(mb_substr($u['full_name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
                                                        ?>
                                                        <?php if ($admin_user_avatar !== ''): ?>
                                                            <img src="<?php echo htmlspecialchars($admin_user_avatar); ?>" alt="<?php echo htmlspecialchars($u['full_name']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                        <?php endif; ?>
                                                        <span style="width:100%;height:100%;align-items:center;justify-content:center;<?php echo $admin_user_avatar !== '' ? 'display:none;' : 'display:flex;'; ?>"><?php echo $admin_user_char; ?></span>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br><span style="font-size:12px;color:var(--text-muted);">ID #<?php echo $u['id']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding:12px 16px;color:var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td style="padding:12px 16px;color:var(--text-muted);"><?php echo htmlspecialchars($u['phone'] ?: 'Chưa cập nhật'); ?></td>
                                            <td style="padding:12px 16px;"><?php echo $is_target_admin ? '<span style="background:#fee2e2;color:var(--danger);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">Admin</span>' : '<span style="background:#eff6ff;color:var(--primary);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">User</span>'; ?></td>
                                            <td style="padding:12px 16px;"><?php echo $u['active'] ? '<span style="background:#d1fae5;color:var(--success);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">Đang hoạt động</span>' : '<span style="background:#f1f5f9;color:var(--text-muted);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">Bị khóa</span>'; ?></td>
                                            <td style="padding:12px 16px;text-align:center;">
                                                <div style="display:flex;gap:8px;justify-content:center;">
                                                    <a href="users.php?action=view&id=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;">Xem</a>
                                                    <a href="users.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-outline" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;">Sửa</a>
                                                </div>
                                            </td>
                                            <td style="padding:12px 16px;text-align:center;">
                                                <?php if ($u['id'] !== $current_admin_id): ?>
                                                    <a href="users.php?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-danger" style="padding:4px 10px;font-size:11px;border-radius:4px;height:28px;" data-confirm="Xóa người dùng này? Tất cả dữ liệu liên quan sẽ bị xóa theo.">Xóa</a>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);font-size:12px;"></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        const selectAllUsers = document.getElementById('select-all-users');
        const bulkDeleteUsersBtn = document.getElementById('bulk-delete-users-btn');
        const userCheckboxes = document.querySelectorAll('.user-row-check:not(:disabled)');

        function updateBulkDeleteVisibility() {
            if (!bulkDeleteUsersBtn) return;
            const hasSelected = Array.from(userCheckboxes).some(function(checkbox) {
                return checkbox.checked;
            });
            bulkDeleteUsersBtn.style.display = hasSelected ? 'inline-flex' : 'none';
        }

        if (selectAllUsers) {
            selectAllUsers.addEventListener('change', function() {
                userCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllUsers.checked;
                });
                updateBulkDeleteVisibility();
            });
        }

        userCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                if (selectAllUsers) {
                    selectAllUsers.checked = Array.from(userCheckboxes).every(function(item) {
                        return item.checked;
                    });
                }
                updateBulkDeleteVisibility();
            });
        });

        updateBulkDeleteVisibility();
        lucide.createIcons();
    </script>
    <script src="../assets/js/admin-confirm.js"></script>
    <script src="../assets/js/admin-menu.js"></script>
</body>
</html>
