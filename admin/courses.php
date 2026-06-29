<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';


require_admin($pdo);

$action = $_GET['action'] ?? 'list';
$course_id = intval($_GET['id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$success_msg = '';
$error_msg = '';
$course_level_labels = [
    'BEGINNER' => 'Cơ bản',
    'INTERMEDIATE' => 'Trung cấp',
    'ADVANCED' => 'Nâng cao',
];

function make_course_slug($title) {
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = strtr($slug, [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ]);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'course';
}

function unique_course_slug($pdo, $title, $ignore_id = 0) {
    $base = make_course_slug($title);
    $slug = $base;
    $i = 2;

    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE slug = ? AND id <> ?");
        $stmt->execute([$slug, $ignore_id]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    if (isset($_POST['save_course'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $level = $_POST['level'] ?? 'BEGINNER';
        $instructor = trim($_POST['instructor'] ?? 'Khánh Nguyễn');
        $price = floatval($_POST['price'] ?? 0);
        $discount_price = floatval($_POST['discount_price'] ?? 0);
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $published = isset($_POST['published']) ? 1 : 0;
        $duration = intval($_POST['duration'] ?? 0);
        $total_lectures = intval($_POST['total_lectures'] ?? 0);

        if (empty($title) || empty($category)) {
            $error_msg = 'Tiêu đề và danh mục không được để trống!';
        } elseif ($price < 0 || $discount_price < 0) {
            $error_msg = 'Giá khóa học không được âm!';
        } elseif ($discount_price > 0 && $discount_price >= $price) {
            $error_msg = 'Giá khuyến mãi phải nhỏ hơn giá niêm yết hoặc bằng 0 nếu không giảm giá!';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ? AND id <> ? LIMIT 1");
                $stmt->execute([$title, $course_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Tiêu đề khóa học đã tồn tại. Vui lòng dùng tiêu đề khác để tránh tạo khóa học trùng.');
                }

                $slug = unique_course_slug($pdo, $title, $course_id);
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (title, slug, description, category, level, instructor, price, discount_price, thumbnail, published, duration, total_lectures) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $slug, $description, $category, $level, $instructor, $price, $discount_price, $thumbnail, $published, $duration, $total_lectures]);
                    header("Location: courses.php?success=" . urlencode("Thêm khóa học thành công!"));
                    exit();
                } elseif ($action === 'edit' && $course_id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE courses 
                        SET title = ?, slug = ?, description = ?, category = ?, level = ?, instructor = ?, price = ?, discount_price = ?, thumbnail = ?, published = ?, duration = ?, total_lectures = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $description, $category, $level, $instructor, $price, $discount_price, $thumbnail, $published, $duration, $total_lectures, $course_id]);
                    header("Location: courses.php?success=" . urlencode("Cập nhật khóa học thành công!"));
                    exit();
                }
            } catch (Exception $e) {
                $error_msg = 'Lỗi lưu dữ liệu: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['bulk_delete_courses'])) {
        $selected_course_ids = array_values(array_unique(array_map('intval', $_POST['course_ids'] ?? [])));
        $selected_course_ids = array_values(array_filter($selected_course_ids, function ($id) {
            return $id > 0;
        }));

        if (empty($selected_course_ids)) {
            $error_msg = 'Vui lòng chọn ít nhất một khóa học để xóa.';
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($selected_course_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id IN ($placeholders)");
                $stmt->execute($selected_course_ids);
                header("Location: courses.php?search=" . urlencode($search) . "&success=" . urlencode("Đã xóa " . $stmt->rowCount() . " khóa học."));
                exit();
            } catch (PDOException $e) {
                $error_msg = 'Xóa nhiều khóa học thất bại: ' . $e->getMessage();
            }
        }
    }
}


if ($action === 'delete' && $course_id > 0) {
    try {
        
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        header("Location: courses.php?success=" . urlencode("Xóa khóa học thành công!"));
        exit();
    } catch (PDOException $e) {
        $error_msg = 'Xóa khóa học thất bại: ' . $e->getMessage();
        $action = 'list'; 
    }
}


$course_data = [];
if (($action === 'edit' || $action === 'view') && $course_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_data = $stmt->fetch();
    if (!$course_data) {
        header("Location: courses.php");
        exit();
    }
}

$course_lessons = [];
if ($action === 'view' && $course_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
    $stmt->execute([$course_id]);
    $course_lessons = $stmt->fetchAll() ?: [];
}


if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}


$courses = [];
if ($action === 'list') {
    try {
        $sql = "SELECT * FROM courses";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE title LIKE ? OR category LIKE ? OR instructor LIKE ?";
            $search_param = "%$search%";
            $params = [$search_param, $search_param, $search_param];
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $courses = $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        $error_msg = 'Không thể tải danh sách khóa học.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Khóa học - LearnHub Admin</title>
    
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
        

        .admin-courses-table-wrap {
            width: 100%;
            overflow-x: hidden;
        }

        .admin-courses-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            text-align: left;
            font-size: 13px;
        }

        .admin-courses-table th,
        .admin-courses-table td {
            padding: 11px 10px !important;
            vertical-align: middle;
        }

        .admin-courses-table th {
            white-space: nowrap;
            font-size: 13px;
        }

        .admin-courses-table th:nth-child(1),
        .admin-courses-table td:nth-child(1) {
            width: 5% !important;
            text-align: center;
        }

        .admin-courses-table th:nth-child(2),
        .admin-courses-table td:nth-child(2) {
            width: 24%;
        }

        .admin-courses-table th:nth-child(3),
        .admin-courses-table td:nth-child(3) {
            width: 13%;
        }

        .admin-courses-table th:nth-child(4),
        .admin-courses-table td:nth-child(4) {
            width: 12%;
        }

        .admin-courses-table th:nth-child(5),
        .admin-courses-table td:nth-child(5),
        .admin-courses-table th:nth-child(6),
        .admin-courses-table td:nth-child(6) {
            width: 7%;
            text-align: center;
        }

        .admin-courses-table th:nth-child(7),
        .admin-courses-table td:nth-child(7) {
            width: 12%;
        }

        .admin-courses-table th:nth-child(8),
        .admin-courses-table td:nth-child(8) {
            width: 20%;
        }

        .admin-course-cell {
            display: grid;
            grid-template-columns: 22% minmax(0, 1fr);
            gap: 4%;
            align-items: center;
            min-width: 0;
        }

        .admin-course-cell img {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .admin-course-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            color: var(--text-main);
            overflow-wrap: anywhere;
        }

        .admin-course-actions {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            white-space: normal;
        }

        .admin-course-actions .btn {
            flex: 0 0 auto;
            padding: 5px 8px !important;
            font-size: 11px !important;
            height: 30px !important;
        }
        .admin-table-card {
            background-color: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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
                <li class="admin-menu-item">
                    <a href="dashboard.php">
                        <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i>
                        Bảng điều khiển
                    </a>
                </li>
                <li class="admin-menu-item active">
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
            
            
            <div class="admin-courses-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                <div>
                    <h1 style="font-size: 30px; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
                        <?php 
                            if ($action === 'list') echo 'Quản lý Khóa học';
                            elseif ($action === 'add') echo 'Thêm khóa học mới';
                            elseif ($action === 'edit') echo 'Sửa khóa học';
                            elseif ($action === 'view') echo 'Chi tiết khóa học';
                        ?>
                    </h1>
                    <p style="color: var(--text-muted); font-size: 14px;">Quản lý toàn bộ danh mục bài giảng, học phí của nền tảng LearnHub.</p>
                </div>

                <?php if ($action === 'list'): ?>
                    <a href="courses.php?action=add" class="btn btn-primary admin-courses-top-btn" style="height: 42px; font-size: 14px; border-radius: var(--radius-sm);">
                        <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                        Tạo khóa học mới
                    </a>
                <?php else: ?>
                    <a href="courses.php" class="btn btn-outline admin-courses-top-btn" style="height: 42px; font-size: 14px; border-radius: var(--radius-sm);">
                        <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                        <span class="lesson-back-text">
                            <span>Quay lại</span>
                        </span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    <i data-lucide="check-circle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            
            <?php if ($action === 'list'): ?>
                <div class="admin-table-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
                        <form method="GET" action="courses.php" style="display:flex;gap:12px;align-items:center;flex:1;min-width:420px;">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm theo tên khóa học, danh mục hoặc giảng viên..." style="height:42px;max-width:440px;">
                            <button type="submit" class="btn btn-primary" style="height:42px;font-size:14px;border-radius:var(--radius-sm);"><i data-lucide="search" style="width:18px;height:18px;"></i> Tìm kiếm</button>
                            <?php if ($search !== ''): ?>
                                <a href="courses.php" class="btn btn-outline" style="height:42px;font-size:14px;border-radius:var(--radius-sm);">Xóa lọc</a>
                            <?php endif; ?>
                        </form>
                        <?php if (!empty($courses)): ?>
                            <button type="submit" form="bulk-courses-form" id="bulk-delete-courses-btn" name="bulk_delete_courses" value="1" class="btn btn-danger" style="display:none;height:42px;font-size:14px;border-radius:var(--radius-sm);align-items:center;gap:8px;">
                                <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Xóa mục đã chọn
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($courses)): ?>
                        <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 40px 0;">Không có khóa học nào được tìm thấy.</p>
                    <?php else: ?>
                        <form id="bulk-courses-form" method="POST" action="courses.php?search=<?php echo urlencode($search); ?>" data-confirm="Xóa các khóa học đã chọn? Toàn bộ bài học và dữ liệu liên quan sẽ bị xóa theo."></form>
                        <div class="admin-courses-table-wrap">
                        <table class="admin-courses-table">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border); font-weight: 700; color: var(--text-main); background-color: var(--bg-main);">
                                    <th style="padding: 12px 16px; width:48px; text-align:center;"><input type="checkbox" id="select-all-courses" aria-label="Chọn tất cả khóa học"></th>
                                    <th style="padding: 12px 16px;">Ảnh & Tên khóa học</th>
                                    <th style="padding: 12px 16px;">Danh mục</th>
                                    <th style="padding: 12px 16px;">Học phí</th>
                                    <th style="padding: 12px 16px;">Học viên</th>
                                    <th style="padding: 12px 16px;">Bài học</th>
                                    <th style="padding: 12px 16px;">Trạng thái</th>
                                    <th style="padding: 12px 16px; text-align: center;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                    <?php $has_discount = $c['discount_price'] > 0 && $c['discount_price'] < $c['price']; ?>
                                    <tr style="border-bottom: 1px solid var(--border); font-weight: 500;">
                                        <td style="padding: 12px 16px; text-align:center;"><input type="checkbox" form="bulk-courses-form" class="course-row-check" name="course_ids[]" value="<?php echo $c['id']; ?>" aria-label="Chọn <?php echo htmlspecialchars($c['title']); ?>"></td>
                                        <td>
                                            <div class="admin-course-cell">
                                                <img src="<?php echo htmlspecialchars($c['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" alt="<?php echo htmlspecialchars($c['title']); ?>">
                                                <strong class="admin-course-title"><?php echo htmlspecialchars($c['title']); ?></strong>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($c['category']); ?></td>
                                        <td style="padding: 12px 16px; font-weight: 700; color: var(--primary);">
                                            <?php if ($has_discount): ?>
                                                <div style="color: var(--primary); font-weight: 800;"><?php echo number_format($c['discount_price'], 0, ',', '.'); ?>đ</div>
                                                <div style="color: var(--text-muted); font-size: 12px; text-decoration: line-through;"><?php echo number_format($c['price'], 0, ',', '.'); ?>đ</div>
                                            <?php else: ?>
                                                <?php echo $c['price'] == 0 ? 'Miễn phí' : number_format($c['price'], 0, ',', '.') . 'đ'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 16px; font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($c['enrollment_count']); ?></td>
                                        <td style="padding: 12px 16px; font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($c['total_lectures']); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <?php 
                                                echo $c['published'] == 1 
                                                    ? '<span style="background-color:#d1fae5;color:var(--success);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">Đã xuất bản</span>' 
                                                    : '<span style="background-color:#f1f5f9;color:var(--text-muted);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">Bản nháp</span>';
                                            ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="admin-course-actions">
                                                <a href="courses.php?action=view&id=<?php echo $c['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Xem</a>
                                                <a href="courses.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Sửa</a>
                                                <a href="courses.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;" data-confirm="Bạn có chắc chắn muốn xóa khóa học này và toàn bộ bài học của nó?">Xóa</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="admin-table-card" style="margin-bottom: 24px;">
                    <div style="display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 24px; align-items: start;">
                        <img src="<?php echo htmlspecialchars($course_data['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" style="width: 100%; aspect-ratio: 16/9; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <div>
                            <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 8px;"><?php echo htmlspecialchars($course_data['title']); ?></h2>
                            <p style="color: var(--text-muted); margin-bottom: 18px;"><?php echo nl2br(htmlspecialchars($course_data['description'] ?: 'Chưa có mô tả.')); ?></p>
                            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                <div><strong>Tiêu đề</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($course_data['title']); ?></span></div>
                                <div><strong>Danh mục</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($course_data['category']); ?></span></div>
                                <div><strong>Cấp độ</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($course_level_labels[$course_data['level']] ?? $course_data['level']); ?></span></div>
                                <div><strong>Giảng viên</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($course_data['instructor']); ?></span></div>
                                <div><strong>Giá gốc</strong><br><span style="color: var(--primary); font-weight: 800;"><?php echo number_format($course_data['price'], 0, ',', '.'); ?>đ</span></div>
                                <div><strong>Giá khuyến mãi</strong><br><span style="color: var(--success); font-weight: 800;"><?php echo number_format($course_data['discount_price'] ?: 0, 0, ',', '.'); ?>đ</span></div>
                                <div><strong>Thời lượng</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($course_data['duration']); ?> phút</span></div>
                                <div><strong>Trạng thái</strong><br><span style="color: var(--text-muted);"><?php echo $course_data['published'] ? 'Đã xuất bản' : 'Bản nháp'; ?></span></div>
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 24px;">
                                <a href="courses.php?action=edit&id=<?php echo $course_id; ?>" class="btn btn-primary" style="height: 40px;">Sửa khóa học</a>
                                <a href="../course-detail.php?id=<?php echo $course_id; ?>" class="btn btn-outline" style="height: 40px;">Xem ngoài website</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-table-card">
                    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px;">Bài học thuộc khóa này</h3>
                    <?php if (empty($course_lessons)): ?>
                        <p style="color: var(--text-muted); font-style: italic;">Khóa học này chưa có bài học.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border); background: var(--bg-main);">
                                    <th style="padding: 12px 16px;">Thứ tự</th>
                                    <th style="padding: 12px 16px;">Tiêu đề</th>
                                    <th style="padding: 12px 16px;">Thời lượng</th>
                                    <th style="padding: 12px 16px;">Học thử</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_lessons as $lesson): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 12px 16px;"><?php echo htmlspecialchars($lesson['order_index']); ?></td>
                                        <td style="padding: 12px 16px;"><a href="lessons.php?action=view&id=<?php echo $lesson['id']; ?>" style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($lesson['title']); ?></a></td>
                                        <td style="padding: 12px 16px;"><?php echo htmlspecialchars($lesson['duration']); ?> phút</td>
                                        <td style="padding: 12px 16px;"><?php echo $lesson['is_free'] ? 'Có' : 'Không'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="admin-table-card">
                    <form action="courses.php?action=<?php echo $action; ?><?php echo $course_id > 0 ? '&id='.$course_id : ''; ?>" method="POST">
                        <input type="hidden" name="save_course" value="1">
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="title">Tiêu đề khóa học <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($course_data['title'] ?? ''); ?>" required placeholder="Ví dụ: Lập trình PHP & MySQL thực chiến">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="description">Mô tả tóm tắt</label>
                            <textarea id="description" name="description" class="form-control" style="height: 120px;" placeholder="Giới thiệu sơ lược về mục tiêu học tập và lợi thế của khóa học..."><?php echo htmlspecialchars($course_data['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="category">Danh mục <span style="color: var(--danger);">*</span></label>
                                <input type="text" id="category" name="category" class="form-control" value="<?php echo htmlspecialchars($course_data['category'] ?? ''); ?>" required placeholder="Ví dụ: Web Development">
                            </div>

                            <div class="form-group">
                                <label for="level">Cấp độ học tập</label>
                                <?php $selected_level = $course_data['level'] ?? 'BEGINNER'; ?>
                                <select id="level" name="level" class="form-control admin-mobile-native-select">
                                    <option value="BEGINNER" <?php echo isset($course_data['level']) && $course_data['level'] === 'BEGINNER' ? 'selected' : ''; ?>>Cơ bản (Beginner)</option>
                                    <option value="INTERMEDIATE" <?php echo isset($course_data['level']) && $course_data['level'] === 'INTERMEDIATE' ? 'selected' : ''; ?>>Trung cấp (Intermediate)</option>
                                    <option value="ADVANCED" <?php echo isset($course_data['level']) && $course_data['level'] === 'ADVANCED' ? 'selected' : ''; ?>>Nâng cao (Advanced)</option>
                                </select>
                                <div class="admin-custom-select admin-mobile-custom-select" data-mobile-select>
                                    <input type="hidden" name="level" value="<?php echo htmlspecialchars($selected_level); ?>" disabled>
                                    <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                        <span><?php echo htmlspecialchars($course_level_labels[$selected_level] ?? $selected_level); ?></span>
                                        <i data-lucide="chevron-down"></i>
                                    </button>
                                    <div class="admin-custom-select-menu">
                                        <?php foreach ($course_level_labels as $value => $label): ?>
                                            <button type="button" data-value="<?php echo htmlspecialchars($value); ?>" class="<?php echo $selected_level === $value ? 'is-selected' : ''; ?>"><?php echo htmlspecialchars($label); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="price">Giá niêm yết (đ)</label>
                                <input type="number" id="price" name="price" class="form-control" value="<?php echo htmlspecialchars($course_data['price'] ?? 0); ?>" min="0">
                            </div>

                            <div class="form-group">
                                <label for="discount_price">Giá khuyến mãi (đ) (Để 0 nếu không giảm giá)</label>
                                <input type="number" id="discount_price" name="discount_price" class="form-control" value="<?php echo htmlspecialchars($course_data['discount_price'] ?? 0); ?>" min="0">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="instructor">Giảng viên đứng lớp</label>
                                <input type="text" id="instructor" name="instructor" class="form-control" value="<?php echo htmlspecialchars($course_data['instructor'] ?? 'Khánh Nguyễn'); ?>">
                            </div>

                            <div class="form-group">
                                <label for="thumbnail">Đường dẫn ảnh thu nhỏ Thumbnail (URL)</label>
                                <input type="text" id="thumbnail" name="thumbnail" class="form-control" value="<?php echo htmlspecialchars($course_data['thumbnail'] ?? ''); ?>" placeholder="https://example.com/course-thumbnail.jpg">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="duration">Tổng thời lượng (phút)</label>
                                <input type="number" id="duration" name="duration" class="form-control" value="<?php echo htmlspecialchars($course_data['duration'] ?? 0); ?>" min="0">
                            </div>

                            <div class="form-group">
                                <label for="total_lectures">Tổng số bài học</label>
                                <input type="number" id="total_lectures" name="total_lectures" class="form-control" value="<?php echo htmlspecialchars($course_data['total_lectures'] ?? 0); ?>" min="0">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 30px; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="published" name="published" value="1" <?php echo isset($course_data['published']) && $course_data['published'] == 1 ? 'checked' : ''; ?> style="width: 20px; height: 20px; cursor: pointer;">
                            <label for="published" style="margin-bottom: 0; cursor: pointer; font-weight: 700; color: var(--text-main);">Xuất bản công khai (Published)</label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px;">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            Lưu thông tin khóa học
                        </button>
                    </form>
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

        const selectAllCourses = document.getElementById('select-all-courses');
        const bulkDeleteCoursesBtn = document.getElementById('bulk-delete-courses-btn');
        const courseCheckboxes = document.querySelectorAll('.course-row-check');

        function updateBulkDeleteCoursesVisibility() {
            if (!bulkDeleteCoursesBtn) return;
            const hasSelected = Array.from(courseCheckboxes).some(function(checkbox) {
                return checkbox.checked;
            });
            bulkDeleteCoursesBtn.style.display = hasSelected ? 'inline-flex' : 'none';
        }

        if (selectAllCourses) {
            selectAllCourses.addEventListener('change', function() {
                courseCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllCourses.checked;
                });
                updateBulkDeleteCoursesVisibility();
            });
        }

        courseCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                if (selectAllCourses) {
                    selectAllCourses.checked = Array.from(courseCheckboxes).every(function(item) {
                        return item.checked;
                    });
                }
                updateBulkDeleteCoursesVisibility();
            });
        });

        updateBulkDeleteCoursesVisibility();

        const courseForm = document.querySelector('form input[name="save_course"]')?.closest('form');
        if (courseForm) {
            courseForm.addEventListener('submit', function() {
                const submitButton = courseForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.style.opacity = '0.7';
                    submitButton.style.cursor = 'not-allowed';
                }
            });
        }
        lucide.createIcons();
    </script>
    <script src="../assets/js/admin-confirm.js"></script>
    <script src="../assets/js/admin-menu.js"></script>
</body>
</html>
