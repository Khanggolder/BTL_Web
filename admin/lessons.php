<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';


require_admin($pdo);

$action = $_GET['action'] ?? 'list';
$lesson_id = intval($_GET['id'] ?? 0);
$selected_course_id = intval($_GET['course_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? 'all');
$level_filter = trim($_GET['level'] ?? 'all');
$level_labels = [
    'all' => 'Tất cả cấp độ',
    'BEGINNER' => 'Cơ bản',
    'INTERMEDIATE' => 'Trung cấp',
    'ADVANCED' => 'Nâng cao',
];

$success_msg = '';
$error_msg = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    if (isset($_POST['save_lesson'])) {
        $course_id = intval($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $duration = intval($_POST['duration'] ?? 10);
        $order_index = intval($_POST['order_index'] ?? 1);
        $video_url = trim($_POST['video_url'] ?? '');
        $is_free = isset($_POST['is_free']) ? 1 : 0;

        if ($course_id <= 0 || empty($title)) {
            $error_msg = 'Khóa học và tiêu đề bài giảng không được để trống!';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO lessons (course_id, title, description, duration, order_index, video_url, is_free) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$course_id, $title, $content, $duration, $order_index, $video_url, $is_free]);
                    
                    
                    $stmt_up = $pdo->prepare("UPDATE courses SET total_lectures = total_lectures + 1, duration = duration + ? WHERE id = ?");
                    $stmt_up->execute([$duration, $course_id]);

                    header("Location: lessons.php?course_id={$course_id}&success=" . urlencode("Thêm bài học mới thành công!"));
                    exit();
                } elseif ($action === 'edit' && $lesson_id > 0) {
                    
                    $stmt_old = $pdo->prepare("SELECT duration, course_id FROM lessons WHERE id = ?");
                    $stmt_old->execute([$lesson_id]);
                    $old_data = $stmt_old->fetch();
                    
                    $stmt = $pdo->prepare("
                        UPDATE lessons 
                        SET course_id = ?, title = ?, description = ?, duration = ?, order_index = ?, video_url = ?, is_free = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$course_id, $title, $content, $duration, $order_index, $video_url, $is_free, $lesson_id]);
                    
                    
                    if ($old_data) {
                        $diff_dur = $duration - $old_data['duration'];
                        if ($diff_dur != 0) {
                            $stmt_up = $pdo->prepare("UPDATE courses SET duration = duration + ? WHERE id = ?");
                            $stmt_up->execute([$diff_dur, $course_id]);
                        }
                    }

                    header("Location: lessons.php?course_id={$course_id}&success=" . urlencode("Cập nhật bài học thành công!"));
                    exit();
                }
            } catch (PDOException $e) {
                $error_msg = 'Lỗi ghi nhận dữ liệu: ' . $e->getMessage();
            }
        }
    }
}


if ($action === 'delete' && $lesson_id > 0) {
    try {
        
        $stmt_les = $pdo->prepare("SELECT course_id, duration FROM lessons WHERE id = ?");
        $stmt_les->execute([$lesson_id]);
        $les = $stmt_les->fetch();

        if ($les) {
            $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
            $stmt->execute([$lesson_id]);
            
            
            $stmt_up = $pdo->prepare("UPDATE courses SET total_lectures = total_lectures - 1, duration = GREATEST(0, duration - ?) WHERE id = ?");
            $stmt_up->execute([$les['duration'], $les['course_id']]);
            
            header("Location: lessons.php?course_id={$les['course_id']}&success=" . urlencode("Xóa bài học thành công!"));
            exit();
        }
    } catch (PDOException $e) {
        $error_msg = 'Không thể xóa bài học này: ' . $e->getMessage();
        $action = 'list';
    }
}


$all_courses = [];
$filtered_courses = [];
$categories = [];
try {
    $all_courses = $pdo->query("SELECT id, title, category, level FROM courses ORDER BY id DESC")->fetchAll() ?: [];
    $categories = $pdo->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $course_sql = "SELECT id, title, category, level FROM courses";
    $course_where = [];
    $course_params = [];

    if ($search !== '') {
        $course_where[] = "(title LIKE ? OR category LIKE ? OR instructor LIKE ?)";
        $search_param = "%$search%";
        $course_params[] = $search_param;
        $course_params[] = $search_param;
        $course_params[] = $search_param;
    }

    if ($category_filter !== 'all') {
        $course_where[] = "category = ?";
        $course_params[] = $category_filter;
    }

    if ($level_filter !== 'all') {
        $course_where[] = "level = ?";
        $course_params[] = $level_filter;
    }

    if (!empty($course_where)) {
        $course_sql .= " WHERE " . implode(" AND ", $course_where);
    }

    $course_sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($course_sql);
    $stmt->execute($course_params);
    $filtered_courses = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $error_msg = 'Không thể tải danh sách khóa học phục vụ bộ lọc.';
}

$selected_course_title = '';
foreach ($all_courses as $course_option) {
    if ((int) $course_option['id'] === $selected_course_id) {
        $selected_course_title = $course_option['title'];
        break;
    }
}


$lesson_data = [];
if (($action === 'edit' || $action === 'view') && $lesson_id > 0) {
    $stmt = $pdo->prepare("
        SELECT l.*, c.title AS course_title
        FROM lessons l
        JOIN courses c ON c.id = l.course_id
        WHERE l.id = ?
    ");
    $stmt->execute([$lesson_id]);
    $lesson_data = $stmt->fetch();
    if (!$lesson_data) {
        header("Location: lessons.php");
        exit();
    }
    $selected_course_id = $lesson_data['course_id'];
    $selected_course_title = $lesson_data['course_title'];
}


$lessons = [];
if ($action === 'list' && $selected_course_id > 0) {
    try {
        $sql = "
            SELECT l.*, c.title AS course_title, c.category, c.level
            FROM lessons l
            JOIN courses c ON c.id = l.course_id
            WHERE l.course_id = ?
        ";
        $sql .= " ORDER BY c.id DESC, l.order_index ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selected_course_id]);
        $lessons = $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        $error_msg = 'Không thể tải danh sách bài giảng.';
    }
}

if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Bài giảng - LearnHub Admin</title>
    
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
                <li class="admin-menu-item">
                    <a href="courses.php">
                        <i data-lucide="book-open" style="width: 18px; height: 18px;"></i>
                        Quản lý khóa học
                    </a>
                </li>
                <li class="admin-menu-item active">
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
                    <h1 style="font-size: 30px; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
                        <?php 
                            if ($action === 'list') echo 'Quản lý Bài giảng';
                            elseif ($action === 'add') echo 'Thêm bài học mới';
                            elseif ($action === 'edit') echo 'Sửa bài học';
                            elseif ($action === 'view') echo 'Chi tiết bài học';
                        ?>
                    </h1>
                    <p style="color: var(--text-muted); font-size: 14px;">Quản lý nội dung bài giảng, học liệu, cấu trúc thứ tự bài học trong từng chương.</p>
                </div>

                <?php if ($action !== 'list'): ?>
                    <a href="lessons.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-outline" style="height: 42px; font-size: 14px; border-radius: var(--radius-sm);">
                        <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                        Quay lại danh sách
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
                
                <?php if ($selected_course_id <= 0): ?>
                    <?php
                        $selected_category_label = $category_filter === 'all' ? 'Tất cả danh mục' : $category_filter;
                        $level_labels = [
                            'all' => 'Tất cả cấp độ',
                            'BEGINNER' => 'Cơ bản',
                            'INTERMEDIATE' => 'Trung cấp',
                            'ADVANCED' => 'Nâng cao',
                        ];
                        $selected_level_label = $level_labels[$level_filter] ?? 'Tất cả cấp độ';
                    ?>
                    
                    <div class="admin-table-card" style="margin-bottom: 30px; padding: 20px;">
                        <form action="lessons.php" method="GET" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm khóa học..." style="height: 44px; min-width: 280px; flex: 1;">
                            <select name="category" class="form-control admin-native-filter" style="height: 44px; width: 220px;">
                                <option value="all">Tất cả danh mục</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="admin-custom-select" data-admin-select>
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>" disabled>
                                <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                    <span><?php echo htmlspecialchars($selected_category_label); ?></span>
                                    <i data-lucide="chevron-down"></i>
                                </button>
                                <div class="admin-custom-select-menu">
                                    <button type="button" data-value="all" class="<?php echo $category_filter === 'all' ? 'is-selected' : ''; ?>">Tất cả danh mục</button>
                                    <?php foreach ($categories as $cat): ?>
                                        <button type="button" data-value="<?php echo htmlspecialchars($cat); ?>" class="<?php echo $category_filter === $cat ? 'is-selected' : ''; ?>">
                                            <?php echo htmlspecialchars($cat); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <select name="level" class="form-control admin-native-filter" style="height: 44px; width: 220px;">
                                <option value="all">Tất cả cấp độ</option>
                                <option value="BEGINNER" <?php echo $level_filter === 'BEGINNER' ? 'selected' : ''; ?>>Cơ bản</option>
                                <option value="INTERMEDIATE" <?php echo $level_filter === 'INTERMEDIATE' ? 'selected' : ''; ?>>Trung cấp</option>
                                <option value="ADVANCED" <?php echo $level_filter === 'ADVANCED' ? 'selected' : ''; ?>>Nâng cao</option>
                            </select>
                            <div class="admin-custom-select" data-admin-select>
                                <input type="hidden" name="level" value="<?php echo htmlspecialchars($level_filter); ?>" disabled>
                                <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                    <span><?php echo htmlspecialchars($selected_level_label); ?></span>
                                    <i data-lucide="chevron-down"></i>
                                </button>
                                <div class="admin-custom-select-menu">
                                    <?php foreach ($level_labels as $level_value => $level_label): ?>
                                        <button type="button" data-value="<?php echo htmlspecialchars($level_value); ?>" class="<?php echo $level_filter === $level_value ? 'is-selected' : ''; ?>">
                                            <?php echo htmlspecialchars($level_label); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="height:44px;font-size:14px;border-radius:var(--radius-sm);"><i data-lucide="search" style="width:18px;height:18px;"></i> Tìm kiếm</button>
                            <?php if ($search !== '' || $category_filter !== 'all' || $level_filter !== 'all'): ?>
                                <a href="lessons.php" class="btn btn-outline" style="height:44px;font-size:14px;border-radius:var(--radius-sm);">Xóa lọc</a>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($selected_course_id <= 0): ?>
                    <div class="admin-table-card" style="margin-bottom: 30px;">
                        <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px;">Chọn khóa học để xem bài giảng</h3>
                        <?php if (empty($filtered_courses)): ?>
                            <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 40px 0;">Không tìm thấy khóa học phù hợp.</p>
                        <?php else: ?>
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border); font-weight: 700; color: var(--text-main); background-color: var(--bg-main);">
                                        <th style="padding: 12px 16px;">Khóa học</th>
                                        <th style="padding: 12px 16px;">Danh mục</th>
                                        <th style="padding: 12px 16px;">Cấp độ học tập</th>
                                        <th style="padding: 12px 16px; text-align: center;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtered_courses as $course_option): ?>
                                        <tr style="border-bottom: 1px solid var(--border); font-weight: 500;">
                                            <td style="padding: 12px 16px;"><strong><?php echo htmlspecialchars($course_option['title']); ?></strong></td>
                                            <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($course_option['category']); ?></td>
                                            <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($level_labels[$course_option['level']] ?? $course_option['level']); ?></td>
                                            <td style="padding: 12px 16px; text-align: center;">
                                                <a href="lessons.php?course_id=<?php echo $course_option['id']; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&level=<?php echo urlencode($level_filter); ?>" class="btn btn-primary" style="height: 32px; padding: 6px 12px; font-size: 12px; border-radius: 4px;">Xem bài giảng</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 24px; flex-wrap: wrap;">
                        <div style="color: var(--text-muted); font-weight: 600;">
                            Đang xem bài giảng của: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($selected_course_title); ?></strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 16px; margin-left: auto;">
                            <a href="lessons.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&level=<?php echo urlencode($level_filter); ?>" class="btn btn-outline" style="height: 36px; font-size: 13px; border-radius: var(--radius-sm);">Chọn khóa khác</a>
                            <a href="lessons.php?action=add&course_id=<?php echo $selected_course_id; ?>" class="btn btn-primary" style="height: 36px; font-size: 13px; border-radius: var(--radius-sm);">
                                <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                                Thêm bài giảng mới
                            </a>
                        </div>
                    </div>

                    
                    <div class="admin-table-card">
                        <?php if (empty($lessons)): ?>
                            <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 40px 0;">Không tìm thấy bài giảng phù hợp.</p>
                        <?php else: ?>
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border); font-weight: 700; color: var(--text-main); background-color: var(--bg-main);">
                                        <th style="padding: 12px 16px; width: 80px; text-align: center;">Thứ tự</th>
                                        <th style="padding: 12px 16px;">Tên bài học</th>
                                        <th style="padding: 12px 16px;">Khóa học</th>
                                        <th style="padding: 12px 16px;">Danh mục</th>
                                        <th style="padding: 12px 16px;">Cấp độ</th>
                                        <th style="padding: 12px 16px; white-space: nowrap; min-width: 96px;">Thời lượng</th>
                                        <th style="padding: 12px 16px;">Đường dẫn Video</th>
                                        <th style="padding: 12px 16px;">Học thử</th>
                                        <th style="padding: 12px 16px; text-align: center;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $l): ?>
                                        <tr style="border-bottom: 1px solid var(--border); font-weight: 500;">
                                            <td style="padding: 12px 16px; font-weight: 800; color: var(--primary); text-align: center;"><?php echo htmlspecialchars($l['order_index']); ?></td>
                                            <td style="padding: 12px 16px;"><strong><?php echo htmlspecialchars($l['title']); ?></strong></td>
                                            <td style="padding: 12px 16px;"><a href="courses.php?action=view&id=<?php echo $l['course_id']; ?>" style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($l['course_title']); ?></a></td>
                                            <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($l['category']); ?></td>
                                            <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($level_labels[$l['level']] ?? $l['level']); ?></td>
                                            <td style="padding: 12px 16px; font-weight: 700; color: var(--text-main); white-space: nowrap;"><?php echo htmlspecialchars($l['duration']); ?> phút</td>
                                            <td style="padding: 12px 16px; color: var(--text-muted); font-size: 12px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($l['video_url']); ?>">
                                                <?php echo htmlspecialchars($l['video_url']); ?>
                                            </td>
                                            <td style="padding: 12px 16px;">
                                                <?php 
                                                    echo $l['is_free'] == 1 
                                                        ? '<span style="background-color:#d1fae5;color:var(--success);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;">Học thử (Free)</span>' 
                                                        : '<span style="background-color:#f1f5f9;color:var(--text-muted);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;">Bảo mật</span>';
                                                ?>
                                            </td>
                                            <td style="padding: 12px 16px; text-align: center; display: flex; gap: 8px; justify-content: center;">
                                                <a href="lessons.php?action=view&id=<?php echo $l['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Xem</a>
                                                <a href="lessons.php?action=edit&id=<?php echo $l['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Sửa</a>
                                                <a href="lessons.php?action=delete&id=<?php echo $l['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;" data-confirm="Bạn có chắc chắn muốn xóa bài giảng này?">Xóa</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="admin-table-card">
                    <div style="display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr); gap: 24px; align-items: start;">
                        <div>
                            <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 8px;"><?php echo htmlspecialchars($lesson_data['title']); ?></h2>
                            <p style="color: var(--text-muted); margin-bottom: 18px;"><?php echo nl2br(htmlspecialchars($lesson_data['description'] ?: 'Chưa có nội dung mô tả.')); ?></p>
                            <?php if (!empty($lesson_data['video_url'])): ?>
                                <video controls style="width: 100%; aspect-ratio: 16/9; background: #020617; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                                    <source src="<?php echo htmlspecialchars($lesson_data['video_url']); ?>">
                                </video>
                            <?php else: ?>
                                <div style="aspect-ratio: 16/9; background: var(--bg-main); border: 1px dashed var(--border); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">Chưa có video</div>
                            <?php endif; ?>
                        </div>
                        <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 20px;">
                            <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 16px;">Thông tin bài học</h3>
                            <div style="display: grid; gap: 12px;">
                                <div><strong>Khóa học</strong><br><a href="courses.php?action=view&id=<?php echo $lesson_data['course_id']; ?>" style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($lesson_data['course_title']); ?></a></div>
                                <div><strong>Thứ tự</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($lesson_data['order_index']); ?></span></div>
                                <div><strong>Thời lượng</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($lesson_data['duration']); ?> phút</span></div>
                                <div><strong>Học thử miễn phí</strong><br><span style="color: var(--text-muted);"><?php echo $lesson_data['is_free'] ? 'Có' : 'Không'; ?></span></div>
                                <div><strong>Video URL</strong><br><span style="color: var(--text-muted); word-break: break-all;"><?php echo htmlspecialchars($lesson_data['video_url'] ?: 'Chưa cập nhật'); ?></span></div>
                            </div>
                            <a href="lessons.php?action=edit&id=<?php echo $lesson_id; ?>" class="btn btn-primary" style="height: 40px; margin-top: 20px; width: 100%;">Sửa bài học</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="admin-table-card">
                    <form action="lessons.php?action=<?php echo $action; ?><?php echo $lesson_id > 0 ? '&id='.$lesson_id : ''; ?>" method="POST">
                        <input type="hidden" name="save_lesson" value="1">
                        
                        <?php if ($action === 'add' && $selected_course_id > 0): ?>
                            <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                        <?php else: ?>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="course_id">Thuộc khóa học <span style="color: var(--danger);">*</span></label>
                                <?php
                                    $selected_form_course_title = 'Chọn khóa học';
                                    foreach ($all_courses as $ac) {
                                        $current_course_value = $selected_course_id ?: (int) ($lesson_data['course_id'] ?? 0);
                                        if ((int) $ac['id'] === $current_course_value) {
                                            $selected_form_course_title = $ac['title'];
                                            break;
                                        }
                                    }
                                ?>
                                <select name="course_id" id="course_id" class="form-control admin-native-filter lesson-course-native-select" required style="min-height: 48px; height: auto; line-height: 1.35; padding-top: 10px; padding-bottom: 10px;">
                                    <?php foreach ($all_courses as $ac): ?>
                                        <option value="<?php echo $ac['id']; ?>" <?php echo ($selected_course_id ?: (int) ($lesson_data['course_id'] ?? 0)) === (int) $ac['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ac['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="admin-custom-select lesson-course-custom-select" data-admin-select>
                                    <input type="hidden" name="course_id" value="<?php echo htmlspecialchars((string) ($selected_course_id ?: (int) ($lesson_data['course_id'] ?? 0))); ?>" disabled>
                                    <button type="button" class="admin-custom-select-toggle" aria-expanded="false">
                                        <span><?php echo htmlspecialchars($selected_form_course_title); ?></span>
                                        <i data-lucide="chevron-down"></i>
                                    </button>
                                    <div class="admin-custom-select-menu">
                                        <?php foreach ($all_courses as $ac): ?>
                                            <button type="button" data-value="<?php echo htmlspecialchars((string) $ac['id']); ?>" class="<?php echo ($selected_course_id ?: (int) ($lesson_data['course_id'] ?? 0)) === (int) $ac['id'] ? 'is-selected' : ''; ?>">
                                                <?php echo htmlspecialchars($ac['title']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="title">Tiêu đề bài học <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($lesson_data['title'] ?? ''); ?>" required placeholder="Ví dụ: Cài đặt và cấu hình PHP Development Environment">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="content">Nội dung / Chi tiết giảng dạy</label>
                            <textarea id="content" name="content" class="form-control" style="height: 120px;" placeholder="Tóm tắt nội dung bài học, hướng dẫn chuẩn bị mã nguồn..."><?php echo htmlspecialchars($lesson_data['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="duration">Thời lượng giảng dạy (phút)</label>
                                <input type="number" id="duration" name="duration" class="form-control" value="<?php echo htmlspecialchars($lesson_data['duration'] ?? 10); ?>" min="1" required style="height: 44px;">
                            </div>

                            <div class="form-group">
                                <label for="order_index">Thứ tự hiển thị (Ví dụ: 1, 2, 3...)</label>
                                <input type="number" id="order_index" name="order_index" class="form-control" value="<?php echo htmlspecialchars($lesson_data['order_index'] ?? 1); ?>" min="1" required style="height: 44px;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="video_url">Đường dẫn tệp Video phát giảng dạy (URL)</label>
                            <input type="text" id="video_url" name="video_url" class="form-control" value="<?php echo htmlspecialchars($lesson_data['video_url'] ?? 'https://www.w3schools.com/html/mov_bbb.mp4'); ?>" required placeholder="https://example.com/video.mp4">
                        </div>

                        <div class="form-group" style="margin-bottom: 30px; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="is_free" name="is_free" value="1" <?php echo isset($lesson_data['is_free']) && $lesson_data['is_free'] == 1 ? 'checked' : ''; ?> style="width: 20px; height: 20px; cursor: pointer;">
                            <label for="is_free" style="margin-bottom: 0; cursor: pointer; font-weight: 700; color: var(--text-main);">Cho phép Học thử miễn phí (Free Trial)</label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px;">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            Lưu bài giảng
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </main>

    </div>

    <script>
        lucide.createIcons();

        const adminFilterMobileQuery = window.matchMedia('(max-width: 900px)');

        function syncAdminFilterMode() {
            const useCustomFilters = adminFilterMobileQuery.matches;

            document.querySelectorAll('.admin-native-filter').forEach(function(nativeFilter) {
                nativeFilter.disabled = useCustomFilters;
            });

            document.querySelectorAll('[data-admin-select] input[type="hidden"]').forEach(function(customInput) {
                customInput.disabled = !useCustomFilters;
            });
        }

        document.querySelectorAll('[data-admin-select]').forEach(function(selectBox) {
            const toggle = selectBox.querySelector('.admin-custom-select-toggle');
            const hiddenInput = selectBox.querySelector('input[type="hidden"]');
            const label = toggle ? toggle.querySelector('span') : null;
            const options = selectBox.querySelectorAll('.admin-custom-select-menu button');

            if (!toggle || !hiddenInput || !label) return;

            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                document.querySelectorAll('[data-admin-select].is-open').forEach(function(openBox) {
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
            document.querySelectorAll('[data-admin-select].is-open').forEach(function(selectBox) {
                selectBox.classList.remove('is-open');
                selectBox.querySelector('.admin-custom-select-toggle')?.setAttribute('aria-expanded', 'false');
            });
        });

        syncAdminFilterMode();
        adminFilterMobileQuery.addEventListener?.('change', syncAdminFilterMode);
    </script>
    <script src="../assets/js/admin-confirm.js"></script>
</body>
</html>
