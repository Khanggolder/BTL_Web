<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/video_helper.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/quiz_schema.php';
ensure_quiz_schema($pdo);


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
function lesson_admin_redirect($params) {
    $query = http_build_query($params);
    header('Location: lessons.php' . ($query ? '?' . $query : ''));
    exit();
}


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
                    $new_lesson_id = (int) $pdo->lastInsertId();
                    $create_quiz_with_lesson = isset($_POST['create_quiz_with_lesson']) || trim($_POST['quiz_title'] ?? '') !== '';
                    if ($create_quiz_with_lesson) {
                        $quiz_title = trim($_POST['quiz_title'] ?? 'Quiz bài học');
                        $quiz_description = trim($_POST['quiz_description'] ?? '');
                        $quiz_pass_score = max(1, min(100, intval($_POST['quiz_pass_score'] ?? 70)));
                        $quiz_active = isset($_POST['quiz_active']) ? 1 : 0;
                        $stmt_quiz = $pdo->prepare("INSERT INTO quizzes (lesson_id, title, description, pass_score, active) VALUES (?, ?, ?, ?, ?)");
                        $stmt_quiz->execute([$new_lesson_id, $quiz_title, $quiz_description, $quiz_pass_score, $quiz_active]);
                    }
                    
                    
                    $stmt_up = $pdo->prepare("UPDATE courses SET total_lectures = total_lectures + 1, duration = duration + ? WHERE id = ?");
                    $stmt_up->execute([$duration, $course_id]);

                    header("Location: lessons.php?action=edit&id={$new_lesson_id}&success=" . urlencode("Đã thêm bài học mới."));
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

                    header("Location: lessons.php?action=edit&id={$lesson_id}&success=" . urlencode("Cập nhật bài học thành công."));
                    exit();
                }
            } catch (PDOException $e) {
                $error_msg = 'Lỗi ghi nhận dữ liệu: ' . $e->getMessage();
            }
        }
    }
    if (isset($_POST['save_lesson_quiz'])) {
        $post_lesson_id = intval($_POST['lesson_id'] ?? 0);
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $title = trim($_POST['quiz_title'] ?? '');
        $description = trim($_POST['quiz_description'] ?? '');
        $pass_score = max(1, min(100, intval($_POST['quiz_pass_score'] ?? 70)));
        $active = isset($_POST['quiz_active']) ? 1 : 0;
        if ($post_lesson_id <= 0 || $title === '') lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'error' => 'Vui lòng nhập tiêu đề quiz.']);
        if ($quiz_id > 0) {
            $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, pass_score = ?, active = ? WHERE id = ? AND lesson_id = ?");
            $stmt->execute([$title, $description, $pass_score, $active, $quiz_id, $post_lesson_id]);
            lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'success' => 'Đã cập nhật quiz.']);
        }
        $stmt = $pdo->prepare("INSERT INTO quizzes (lesson_id, title, description, pass_score, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$post_lesson_id, $title, $description, $pass_score, $active]);
        lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'success' => 'Đã tạo quiz cho bài học.']);
    }

    if (isset($_POST['delete_lesson_quiz'])) {
        $post_lesson_id = intval($_POST['lesson_id'] ?? 0);
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND lesson_id = ?");
        $stmt->execute([$quiz_id, $post_lesson_id]);
        lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'success' => 'Đã xóa quiz khỏi bài học.']);
    }

    if (isset($_POST['save_lesson_quiz_question'])) {
        $post_lesson_id = intval($_POST['lesson_id'] ?? 0);
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $question_id = intval($_POST['question_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $order_index = max(1, intval($_POST['question_order'] ?? 1));
        $options = $_POST['options'] ?? [];
        $correct = intval($_POST['correct_option'] ?? 0);
        $clean_options = [];
        foreach ($options as $index => $option_text) {
            $option_text = trim($option_text);
            if ($option_text !== '') $clean_options[] = ['text' => $option_text, 'correct' => (int) $index === $correct ? 1 : 0];
        }
        if ($quiz_id <= 0 || $question_text === '' || count($clean_options) < 2 || array_sum(array_column($clean_options, 'correct')) <= 0) lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'error' => 'Mỗi câu hỏi cần nội dung, ít nhất 2 đáp án và 1 đáp án đúng.']);
        $pdo->beginTransaction();
        if ($question_id > 0) {
            $stmt = $pdo->prepare("UPDATE quiz_questions SET question_text = ?, order_index = ? WHERE id = ? AND quiz_id = ?");
            $stmt->execute([$question_text, $order_index, $question_id, $quiz_id]);
            $pdo->prepare("DELETE FROM quiz_options WHERE question_id = ?")->execute([$question_id]);
            $saved_question_id = $question_id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, order_index) VALUES (?, ?, ?)");
            $stmt->execute([$quiz_id, $question_text, $order_index]);
            $saved_question_id = (int) $pdo->lastInsertId();
        }
        $stmt = $pdo->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct, order_index) VALUES (?, ?, ?, ?)");
        foreach ($clean_options as $index => $option) $stmt->execute([$saved_question_id, $option['text'], $option['correct'], $index + 1]);
        $pdo->commit();
        lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'success' => 'Đã lưu câu hỏi quiz.']);
    }

    if (isset($_POST['delete_lesson_quiz_question'])) {
        $post_lesson_id = intval($_POST['lesson_id'] ?? 0);
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $question_id = intval($_POST['question_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?");
        $stmt->execute([$question_id, $quiz_id]);
        lesson_admin_redirect(['action' => 'edit', 'id' => $post_lesson_id, 'success' => 'Đã xóa câu hỏi quiz.']);
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

$lesson_quiz = null;
$lesson_quiz_questions = [];
$lesson_quiz_options = [];
$edit_quiz_question = null;
$edit_quiz_options = [];
if (($action === 'edit' || $action === 'view') && $lesson_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE lesson_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$lesson_id]);
    $lesson_quiz = $stmt->fetch();
    if ($lesson_quiz) {
        $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC, id ASC");
        $stmt->execute([(int) $lesson_quiz['id']]);
        $lesson_quiz_questions = $stmt->fetchAll() ?: [];
        if ($lesson_quiz_questions) {
            $question_ids = array_column($lesson_quiz_questions, 'id');
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id IN ($placeholders) ORDER BY order_index ASC, id ASC");
            $stmt->execute($question_ids);
            foreach ($stmt->fetchAll() as $option) $lesson_quiz_options[(int) $option['question_id']][] = $option;
        }
        $edit_question_id = intval($_GET['quiz_question_id'] ?? 0);
        if ($edit_question_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ? AND quiz_id = ?");
            $stmt->execute([$edit_question_id, (int) $lesson_quiz['id']]);
            $edit_quiz_question = $stmt->fetch();
            if ($edit_quiz_question) {
                $stmt = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY order_index ASC, id ASC");
                $stmt->execute([$edit_question_id]);
                $edit_quiz_options = $stmt->fetchAll() ?: [];
            }
        }
    }
}

$lesson_youtube_id = extract_youtube_video_id($lesson_data['video_url'] ?? '');
$lesson_google_drive_url = google_drive_preview_url($lesson_data['video_url'] ?? '');
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

        .admin-lesson-view-card {
            padding: 28px;
        }

        .admin-lesson-view-header {
            padding-bottom: 20px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .admin-lesson-view-header h2 {
            margin: 0 0 8px;
            color: var(--text-main);
            font-size: 24px;
            font-weight: 800;
            line-height: 1.3;
        }

        .admin-lesson-view-header p {
            max-width: 900px;
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .admin-lesson-view-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(300px, .75fr);
            gap: 24px;
            align-items: stretch;
        }

        .admin-lesson-view-media {
            min-width: 0;
            display: flex;
            align-items: stretch;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg-main);
        }

        .admin-lesson-view-media > iframe,
        .admin-lesson-view-media > video,
        .admin-lesson-view-media > div {
            width: 100%;
            min-height: 100%;
            margin: 0;
            align-self: stretch;
        }

        .admin-lesson-view-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg-main);
        }

        .admin-lesson-view-info h3 {
            margin: 0 0 8px;
            color: var(--text-main);
            font-size: 17px;
            font-weight: 800;
        }

        .admin-lesson-view-details {
            display: grid;
            flex: 1;
        }

        .admin-lesson-view-details > div {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            line-height: 1.55;
        }

        .admin-lesson-view-details > div:last-child {
            border-bottom: 0;
        }

        .admin-lesson-view-edit {
            width: 100%;
            height: 42px;
            margin-top: 18px;
        }



        .admin-lesson-quiz-view {
            margin-top: 24px;
            padding: 28px;
        }

        .admin-lesson-quiz-view-head {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 22px;
        }

        .admin-lesson-quiz-view-head h2 {
            margin: 0 0 8px;
            color: var(--text-main);
            font-size: 22px;
            font-weight: 800;
        }

        .admin-lesson-quiz-view-head p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.65;
        }

        .admin-lesson-quiz-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .admin-lesson-quiz-badge {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 5px 10px;
            border-radius: 999px;
            background: var(--bg-main);
            color: var(--text-muted);
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .admin-lesson-quiz-badge.is-on {
            background: #d1fae5;
            color: var(--success);
            border-color: #a7f3d0;
        }

        .admin-lesson-quiz-badge.is-off {
            background: #f1f5f9;
            color: var(--text-muted);
        }

        .admin-lesson-quiz-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 2%;
            margin-bottom: 22px;
        }

        .admin-lesson-quiz-summary > div {
            min-width: 0;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg-main);
        }

        .admin-lesson-quiz-summary strong {
            display: block;
            color: var(--text-main);
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .admin-lesson-quiz-summary span {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .admin-lesson-quiz-view-list {
            display: grid;
            gap: 14px;
        }

        .admin-lesson-quiz-view-question {
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: #ffffff;
        }

        .admin-lesson-quiz-view-question h3 {
            margin: 0 0 12px;
            color: var(--text-main);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.5;
        }

        .admin-lesson-quiz-view-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .admin-lesson-quiz-view-options li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            min-width: 0;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-main);
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .admin-lesson-quiz-view-options li.is-correct {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
        }

        .admin-lesson-quiz-empty {
            padding: 22px;
            border: 1px dashed var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg-main);
            color: var(--text-muted);
            text-align: center;
            line-height: 1.6;
        }

        .admin-lesson-quiz-empty a {
            color: var(--primary);
            font-weight: 800;
        }

        .admin-lessons-table-card {
            overflow: hidden;
        }

        .admin-lessons-table-wrap {
            width: 100%;
            overflow-x: hidden;
        }

        .admin-lessons-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            text-align: left;
            font-size: 13px;
        }

        .admin-lessons-table th,
        .admin-lessons-table td {
            padding: 11px 10px !important;
            vertical-align: middle;
        }

        .admin-lessons-table th {
            white-space: nowrap;
            font-size: 13px;
        }

        .admin-lessons-table th:nth-child(1),
        .admin-lessons-table td:nth-child(1) {
            width: 6% !important;
            text-align: center;
        }

        .admin-lessons-table th:nth-child(2),
        .admin-lessons-table td:nth-child(2) {
            width: 15%;
        }

        .admin-lessons-table th:nth-child(3),
        .admin-lessons-table td:nth-child(3) {
            width: 16%;
        }

        .admin-lessons-table th:nth-child(4),
        .admin-lessons-table td:nth-child(4) {
            width: 12%;
        }

        .admin-lessons-table th:nth-child(5),
        .admin-lessons-table td:nth-child(5) {
            width: 9%;
        }

        .admin-lessons-table th:nth-child(6),
        .admin-lessons-table td:nth-child(6) {
            width: 9%;
            white-space: nowrap;
        }

        .admin-lessons-table th:nth-child(7),
        .admin-lessons-table td:nth-child(7) {
            display: none;
        }

        .admin-lessons-table th:nth-child(8),
        .admin-lessons-table td:nth-child(8) {
            width: 11%;
        }

        .admin-lessons-table th:nth-child(9),
        .admin-lessons-table td:nth-child(9) {
            width: 22%;
        }

        .admin-lessons-title,
        .admin-lessons-course-link {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .admin-lessons-video-url {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-lessons-actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: nowrap;
            white-space: normal;
        }

        .admin-lessons-actions .btn {
            flex: 0 0 auto;
            padding: 5px 8px !important;
            font-size: 11px !important;
            height: 30px !important;
        }
        @media (max-width: 900px) {
            .admin-lesson-view-card {
                padding: 18px;
            }

            .admin-lesson-view-grid {
                grid-template-columns: 1fr;
            }

            .admin-lesson-view-media,
            .admin-lesson-view-info {
                padding: 18px;
            }

            .admin-lesson-quiz-view {
                padding: 18px;
            }

            .admin-lesson-quiz-view-head {
                display: grid;
            }

            .admin-lesson-quiz-badges {
                justify-content: flex-start;
            }

            .admin-lesson-quiz-summary,
            .admin-lesson-quiz-view-options {
                grid-template-columns: 1fr;
                gap: 12px;
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
                    <a href="lessons.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-outline lesson-back-btn" style="height: 42px; font-size: 14px; border-radius: var(--radius-sm);">
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
                            <table class="admin-lessons-course-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
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
                                                <a href="lessons.php?course_id=<?php echo $course_option['id']; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&level=<?php echo urlencode($level_filter); ?>" class="btn btn-primary admin-lessons-view-btn" style="height: 32px; padding: 6px 12px; font-size: 12px; border-radius: 4px;">Xem bài giảng</a>
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

                    
                    <div class="admin-table-card admin-lessons-table-card">
                        <?php if (empty($lessons)): ?>
                            <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 40px 0;">Không tìm thấy bài giảng phù hợp.</p>
                        <?php else: ?>
                            <div class="admin-lessons-table-wrap">
                                <table class="admin-lessons-table">
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
                                            <td><strong class="admin-lessons-title"><?php echo htmlspecialchars($l['title']); ?></strong></td>
                                            <td><a class="admin-lessons-course-link" href="courses.php?action=view&id=<?php echo $l['course_id']; ?>" style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($l['course_title']); ?></a></td>
                                            <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($l['category']); ?></td>
                                            <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($level_labels[$l['level']] ?? $l['level']); ?></td>
                                            <td style="padding: 12px 16px; font-weight: 700; color: var(--text-main); white-space: nowrap;"><?php echo htmlspecialchars($l['duration']); ?> phút</td>
                                            <td style="color: var(--text-muted); font-size: 12px;" title="<?php echo htmlspecialchars($l['video_url']); ?>">
                                                <span class="admin-lessons-video-url"><?php echo htmlspecialchars($l['video_url']); ?></span>
                                            </td>
                                            <td style="padding: 12px 16px;">
                                                <?php 
                                                    echo $l['is_free'] == 1 
                                                        ? '<span style="background-color:#d1fae5;color:var(--success);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;">Học thử (Free)</span>' 
                                                        : '<span style="background-color:#f1f5f9;color:var(--text-muted);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;">Bảo mật</span>';
                                                ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <div class="admin-lessons-actions">
                                                    <a href="lessons.php?action=view&id=<?php echo $l['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Xem</a>
                                                    <a href="lessons.php?action=edit&id=<?php echo $l['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;">Sửa</a>
                                                    <a href="lessons.php?action=delete&id=<?php echo $l['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: 32px;" data-confirm="Bạn có chắc chắn muốn xóa bài giảng này?">Xóa</a>
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

            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="admin-table-card admin-lesson-view-card">
                    <div class="admin-lesson-view-header">
                        <h2><?php echo htmlspecialchars($lesson_data['title']); ?></h2>
                        <p><?php echo nl2br(htmlspecialchars($lesson_data['description'] ?: 'Chưa có nội dung mô tả.')); ?></p>
                    </div>
                    <div class="admin-lesson-view-grid">
                        <div class="admin-lesson-view-media">
                            <?php if ($lesson_youtube_id): ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($lesson_youtube_id); ?>" title="<?php echo htmlspecialchars($lesson_data['title']); ?>" allowfullscreen style="width:100%;aspect-ratio:16/9;border:1px solid var(--border);border-radius:var(--radius-sm);"></iframe>
                            <?php elseif ($lesson_google_drive_url): ?>
                                <iframe src="<?php echo htmlspecialchars($lesson_google_drive_url); ?>" title="<?php echo htmlspecialchars($lesson_data['title']); ?>" allow="autoplay; fullscreen" allowfullscreen style="width:100%;aspect-ratio:16/9;border:1px solid var(--border);border-radius:var(--radius-sm);"></iframe>
                            <?php elseif (!empty($lesson_data['video_url'])): ?>
                                <video controls controlslist="nodownload noremoteplayback" disablepictureinpicture oncontextmenu="return false;" style="width: 100%; aspect-ratio: 16/9; background: #020617; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                                    <source src="<?php echo htmlspecialchars($lesson_data['video_url']); ?>">
                                </video>
                            <?php else: ?>
                                <div style="aspect-ratio: 16/9; background: var(--bg-main); border: 1px dashed var(--border); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">Chưa có video</div>
                            <?php endif; ?>
                        </div>
                        <aside class="admin-lesson-view-info">
                            <h3>Thông tin bài học</h3>
                            <div class="admin-lesson-view-details">
                                <div><strong>Khóa học</strong><br><a href="courses.php?action=view&id=<?php echo $lesson_data['course_id']; ?>" style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($lesson_data['course_title']); ?></a></div>
                                <div><strong>Thứ tự</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($lesson_data['order_index']); ?></span></div>
                                <div><strong>Thời lượng</strong><br><span style="color: var(--text-muted);"><?php echo htmlspecialchars($lesson_data['duration']); ?> phút</span></div>
                                <div><strong>Học thử miễn phí</strong><br><span style="color: var(--text-muted);"><?php echo $lesson_data['is_free'] ? 'Có' : 'Không'; ?></span></div>
                                <div><strong>Video URL</strong><br><span style="color: var(--text-muted); word-break: break-all;"><?php echo htmlspecialchars($lesson_data['video_url'] ?: 'Chưa cập nhật'); ?></span></div>
                            </div>
                            <a href="lessons.php?action=edit&id=<?php echo $lesson_id; ?>" class="btn btn-primary admin-lesson-view-edit">Sửa bài học</a>
                        </aside>
                    </div>
                </div>

                <div class="admin-table-card admin-lesson-quiz-view">
                    <div class="admin-lesson-quiz-view-head">
                        <div>
                            <h2>Quiz của bài học</h2>
                            <p>Xem nhanh quiz, điểm đạt, trạng thái và các câu hỏi đang gắn với bài học này.</p>
                        </div>
                        <?php if ($lesson_quiz): ?>
                            <div class="admin-lesson-quiz-badges">
                                <span class="admin-lesson-quiz-badge <?php echo $lesson_quiz['active'] ? 'is-on' : 'is-off'; ?>"><?php echo $lesson_quiz['active'] ? 'Đang bật' : 'Đang tắt'; ?></span>
                                <span class="admin-lesson-quiz-badge">Điểm đạt <?php echo (int) $lesson_quiz['pass_score']; ?>%</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($lesson_quiz): ?>
                        <div class="admin-lesson-quiz-summary">
                            <div>
                                <strong><?php echo htmlspecialchars($lesson_quiz['title']); ?></strong>
                                <span>Tiêu đề quiz</span>
                            </div>
                            <div>
                                <strong><?php echo count($lesson_quiz_questions); ?></strong>
                                <span>Số câu hỏi</span>
                            </div>
                            <div>
                                <strong><?php echo (int) $lesson_quiz['pass_score']; ?>%</strong>
                                <span>Điểm cần đạt</span>
                            </div>
                        </div>

                        <?php if (!empty($lesson_quiz['description'])): ?>
                            <p style="color: var(--text-muted); line-height: 1.7; margin: 0 0 18px;"><?php echo nl2br(htmlspecialchars($lesson_quiz['description'])); ?></p>
                        <?php endif; ?>

                        <?php if (empty($lesson_quiz_questions)): ?>
                            <div class="admin-lesson-quiz-empty">Quiz này chưa có câu hỏi. <a href="lessons.php?action=edit&id=<?php echo $lesson_id; ?>#lesson-quiz">Thêm câu hỏi</a></div>
                        <?php else: ?>
                            <div class="admin-lesson-quiz-view-list">
                                <?php foreach ($lesson_quiz_questions as $question): ?>
                                    <div class="admin-lesson-quiz-view-question">
                                        <h3><?php echo (int) $question['order_index']; ?>. <?php echo htmlspecialchars($question['question_text']); ?></h3>
                                        <ul class="admin-lesson-quiz-view-options">
                                            <?php foreach (($lesson_quiz_options[(int) $question['id']] ?? []) as $option): ?>
                                                <li class="<?php echo $option['is_correct'] ? 'is-correct' : ''; ?>">
                                                    <span><?php echo $option['is_correct'] ? 'Đúng' : '•'; ?></span>
                                                    <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="admin-lesson-quiz-empty">Bài học này chưa có quiz. <a href="lessons.php?action=edit&id=<?php echo $lesson_id; ?>#lesson-quiz">Tạo quiz cho bài học</a></div>
                    <?php endif; ?>
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
                            <label for="video_url">Đường dẫn Video MP4, YouTube hoặc Google Drive (URL)</label>
                            <input type="text" id="video_url" name="video_url" class="form-control" value="<?php echo htmlspecialchars($lesson_data['video_url'] ?? 'https://www.w3schools.com/html/mov_bbb.mp4'); ?>" required placeholder="YouTube, Google Drive hoặc đường dẫn MP4">
                            <small style="display:block;margin-top:7px;color:var(--text-muted);">Video Google Drive cần bật quyền “Bất kỳ ai có liên kết” để học viên xem được.</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 30px; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="is_free" name="is_free" value="1" <?php echo isset($lesson_data['is_free']) && $lesson_data['is_free'] == 1 ? 'checked' : ''; ?> style="width: 20px; height: 20px; cursor: pointer;">
                            <label for="is_free" style="margin-bottom: 0; cursor: pointer; font-weight: 700; color: var(--text-main);">Cho phép Học thử miễn phí (Free Trial)</label>
                        </div>


                        <?php if ($action === 'add'): ?>
                            <div class="admin-table-card lesson-inline-quiz-box">
                                <h3>Quiz cho bài học này</h3>
                                <label class="lesson-inline-check"><input type="checkbox" name="create_quiz_with_lesson" value="1"> Tạo quiz ngay khi lưu bài học</label>
                                <div class="form-grid">
                                    <div class="form-group"><label>Tiêu đề quiz</label><input type="text" name="quiz_title" class="form-control" placeholder="Ví dụ: Kiểm tra nhanh sau bài học"></div>
                                    <div class="form-group"><label>Điểm đạt (%)</label><input type="number" name="quiz_pass_score" class="form-control" value="70" min="1" max="100"></div>
                                </div>
                                <div class="form-group"><label>Mô tả quiz</label><textarea name="quiz_description" class="form-control" style="height: 90px;" placeholder="Mô tả ngắn về quiz..."></textarea></div>
                                <label class="lesson-inline-check"><input type="checkbox" name="quiz_active" value="1" checked> Bật quiz cho học viên</label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px;">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            Lưu bài giảng
                        </button>
                    </form>
                </div>
            <?php endif; ?>
                <?php if ($action === 'edit'): ?>
                    <div class="admin-table-card lesson-quiz-manager" id="lesson-quiz">
                        <div class="lesson-quiz-head">
                            <div><h2>Quiz của bài học</h2><p>Quản lý trực tiếp quiz, câu hỏi và đáp án ngay trong trang bài học.</p></div>
                            <?php if ($lesson_quiz): ?><span class="quiz-status <?php echo $lesson_quiz['active'] ? 'is-on' : 'is-off'; ?>"><?php echo $lesson_quiz['active'] ? 'Đang bật' : 'Đang tắt'; ?></span><?php endif; ?>
                        </div>
                        <form method="POST" class="quiz-admin-form">
                            <input type="hidden" name="save_lesson_quiz" value="1">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                            <input type="hidden" name="quiz_id" value="<?php echo (int) ($lesson_quiz['id'] ?? 0); ?>">
                            <div class="form-grid">
                                <div class="form-group"><label>Tiêu đề quiz</label><input type="text" name="quiz_title" class="form-control" value="<?php echo htmlspecialchars($lesson_quiz['title'] ?? ''); ?>" required placeholder="Kiểm tra nhanh sau bài học"></div>
                                <div class="form-group"><label>Điểm đạt (%)</label><input type="number" name="quiz_pass_score" class="form-control" value="<?php echo htmlspecialchars($lesson_quiz['pass_score'] ?? 70); ?>" min="1" max="100" required></div>
                            </div>
                            <div class="form-group"><label>Mô tả quiz</label><textarea name="quiz_description" class="form-control" style="height: 90px;"><?php echo htmlspecialchars($lesson_quiz['description'] ?? ''); ?></textarea></div>
                            <label class="lesson-inline-check"><input type="checkbox" name="quiz_active" value="1" <?php echo empty($lesson_quiz) || !empty($lesson_quiz['active']) ? 'checked' : ''; ?>> Bật quiz cho học viên</label>
                            <div class="lesson-quiz-actions"><button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:18px;height:18px;"></i> Lưu quiz</button></div>
                        </form>
                        <?php if ($lesson_quiz): ?>
                            <form method="POST" class="lesson-quiz-delete" data-confirm="Xóa quiz này và tất cả câu hỏi?">
                                <input type="hidden" name="delete_lesson_quiz" value="1"><input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>"><input type="hidden" name="quiz_id" value="<?php echo (int) $lesson_quiz['id']; ?>">
                                <button type="submit" class="btn btn-danger"><i data-lucide="trash-2" style="width:18px;height:18px;"></i> Xóa quiz</button>
                            </form>
                            <div class="lesson-quiz-question-box">
                                <h3><?php echo $edit_quiz_question ? 'Sửa câu hỏi' : 'Thêm câu hỏi'; ?></h3>
                                <form method="POST" class="quiz-admin-form">
                                    <input type="hidden" name="save_lesson_quiz_question" value="1"><input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>"><input type="hidden" name="quiz_id" value="<?php echo (int) $lesson_quiz['id']; ?>"><input type="hidden" name="question_id" value="<?php echo (int) ($edit_quiz_question['id'] ?? 0); ?>">
                                    <div class="form-group"><label>Nội dung câu hỏi</label><textarea name="question_text" class="form-control" style="height: 90px;" required><?php echo htmlspecialchars($edit_quiz_question['question_text'] ?? ''); ?></textarea></div>
                                    <div class="form-group"><label>Thứ tự</label><input type="number" name="question_order" class="form-control" value="<?php echo htmlspecialchars($edit_quiz_question['order_index'] ?? (count($lesson_quiz_questions) + 1)); ?>" min="1" required></div>
                                    <div class="quiz-option-editor">
                                        <?php for ($i = 0; $i < 4; $i++): $option = $edit_quiz_options[$i] ?? ['option_text' => '', 'is_correct' => 0]; ?>
                                            <label class="quiz-option-row"><input type="radio" name="correct_option" value="<?php echo $i; ?>" <?php echo !empty($option['is_correct']) || (!$edit_quiz_question && $i === 0) ? 'checked' : ''; ?>><input type="text" name="options[<?php echo $i; ?>]" class="form-control" value="<?php echo htmlspecialchars($option['option_text']); ?>" placeholder="Đáp án <?php echo $i + 1; ?>"></label>
                                        <?php endfor; ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:18px;height:18px;"></i> Lưu câu hỏi</button>
                                </form>
                            </div>
                            <div class="lesson-quiz-list">
                                <?php if (empty($lesson_quiz_questions)): ?><p class="quiz-empty">Quiz này chưa có câu hỏi.</p><?php endif; ?>
                                <?php foreach ($lesson_quiz_questions as $question): ?>
                                    <div class="quiz-question-item"><div><strong><?php echo (int) $question['order_index']; ?>. <?php echo htmlspecialchars($question['question_text']); ?></strong><ul><?php foreach (($lesson_quiz_options[(int) $question['id']] ?? []) as $option): ?><li class="<?php echo $option['is_correct'] ? 'is-correct' : ''; ?>"><?php echo htmlspecialchars($option['option_text']); ?></li><?php endforeach; ?></ul></div><div class="quiz-admin-actions"><a class="btn btn-outline" href="lessons.php?action=edit&id=<?php echo $lesson_id; ?>&quiz_question_id=<?php echo (int) $question['id']; ?>">Sửa</a><form method="POST" data-confirm="Xóa câu hỏi này?"><input type="hidden" name="delete_lesson_quiz_question" value="1"><input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>"><input type="hidden" name="quiz_id" value="<?php echo (int) $lesson_quiz['id']; ?>"><input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>"><button type="submit" class="btn btn-danger">Xóa</button></form></div></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
    <script src="../assets/js/admin-menu.js"></script>
</body>
</html>
