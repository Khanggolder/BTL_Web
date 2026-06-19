<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/video_helper.php';
require_login();

$user_id = (int) $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$lesson_id = intval($_GET['lesson_id'] ?? 0);
$active_tab = $_GET['tab'] ?? 'overview';
if (!in_array($active_tab, ['overview', 'notes'], true)) $active_tab = 'overview';
if (empty($_SESSION['learning_csrf'])) $_SESSION['learning_csrf'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['learning_csrf'];

function sync_learning_progress($pdo, $user_id, $course_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $total = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.user_id = ? AND l.course_id = ? AND lp.completed = 1");
    $stmt->execute([$user_id, $course_id]);
    $completed = (int) $stmt->fetchColumn();
    $percent = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    $stmt = $pdo->prepare("UPDATE enrollments SET progress = ?, completed_at = CASE WHEN ? >= 100 THEN COALESCE(completed_at, CURRENT_TIMESTAMP) ELSE NULL END WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$percent, $percent, $user_id, $course_id]);
    return $percent;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? AND active = 1");
    $stmt->execute([$user_id, $course_id]);
    $enrollment = $stmt->fetch();
    if (!$enrollment) {
        header('Location: course-detail.php?id=' . $course_id . '&error=' . urlencode('Bạn chưa mua khóa học này!'));
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC, id ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll() ?: [];
    if (!$course || empty($lessons)) die('Khóa học hiện tại chưa có bài học nào được tải lên!');

    $lesson_map = [];
    foreach ($lessons as $lesson) $lesson_map[(int) $lesson['id']] = $lesson;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_action = $_POST['action'] ?? '';
        $post_lesson_id = intval($_POST['lesson_id'] ?? 0);
        $valid_csrf = hash_equals($csrf_token, $_POST['csrf_token'] ?? '');
        if (!$valid_csrf || !isset($lesson_map[$post_lesson_id])) {
            if ($post_action === 'save_video_progress') {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false]);
                exit();
            }
            header('Location: learning.php?course_id=' . $course_id);
            exit();
        }

        if ($post_action === 'save_video_progress') {
            $position = max(0, intval($_POST['video_position'] ?? 0));
            $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id, video_position, last_watched_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE video_position = VALUES(video_position), last_watched_at = CURRENT_TIMESTAMP");
            $stmt->execute([$user_id, $post_lesson_id, $position]);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            exit();
        }

        if ($post_action === 'toggle_complete') {
            $completed = !empty($_POST['completed']) ? 1 : 0;
            $video_position = max(0, intval($_POST['video_position'] ?? 0));
            $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id, completed, video_position, last_watched_at, completed_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, IF(? = 1, CURRENT_TIMESTAMP, NULL)) ON DUPLICATE KEY UPDATE completed = VALUES(completed), video_position = GREATEST(video_position, VALUES(video_position)), last_watched_at = CURRENT_TIMESTAMP, completed_at = IF(VALUES(completed) = 1, COALESCE(completed_at, CURRENT_TIMESTAMP), NULL)");
            $stmt->execute([$user_id, $post_lesson_id, $completed, $video_position, $completed]);
            sync_learning_progress($pdo, $user_id, $course_id);
            header('Location: learning.php?course_id=' . $course_id . '&lesson_id=' . $post_lesson_id);
            exit();
        }

        if ($post_action === 'save_note') {
            $note_id = intval($_POST['note_id'] ?? 0);
            $note_content = trim($_POST['content'] ?? '');
            $video_position = max(0, intval($_POST['video_position'] ?? 0));
            if ($note_content !== '') {
                if ($note_id > 0) {
                    $stmt = $pdo->prepare("UPDATE lesson_notes SET content = ?, video_position = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND lesson_id = ?");
                    $stmt->execute([$note_content, $video_position, $note_id, $user_id, $post_lesson_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO lesson_notes (user_id, lesson_id, content, video_position) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $post_lesson_id, $note_content, $video_position]);
                }
            }
            header('Location: learning.php?course_id=' . $course_id . '&lesson_id=' . $post_lesson_id . '&tab=notes');
            exit();
        }

        if ($post_action === 'delete_note') {
            $note_id = intval($_POST['note_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM lesson_notes WHERE id = ? AND user_id = ? AND lesson_id = ?");
            $stmt->execute([$note_id, $user_id, $post_lesson_id]);
            header('Location: learning.php?course_id=' . $course_id . '&lesson_id=' . $post_lesson_id . '&tab=notes');
            exit();
        }

    }

    $stmt = $pdo->prepare("SELECT lp.* FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.user_id = ? AND l.course_id = ? ORDER BY lp.last_watched_at DESC, lp.updated_at DESC");
    $stmt->execute([$user_id, $course_id]);
    $progress_rows = $stmt->fetchAll() ?: [];
    $progress_map = [];
    foreach ($progress_rows as $row) $progress_map[(int) $row['lesson_id']] = $row;

    if ($lesson_id <= 0 && !empty($progress_rows)) $lesson_id = (int) $progress_rows[0]['lesson_id'];
    if (!isset($lesson_map[$lesson_id])) $lesson_id = (int) $lessons[0]['id'];

    $lesson_access = [];
    foreach ($lessons as $index => $lesson) {
        $id = (int) $lesson['id'];
        $lesson_access[$id] = $index === 0 || !empty($progress_map[(int) $lessons[$index - 1]['id']]['completed']);
    }
    if (empty($lesson_access[$lesson_id])) {
        foreach ($lessons as $lesson) {
            $id = (int) $lesson['id'];
            if (!empty($lesson_access[$id]) && empty($progress_map[$id]['completed'])) {
                $lesson_id = $id;
                break;
            }
        }
    }

    $current_lesson = $lesson_map[$lesson_id];
    $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id, last_watched_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE last_watched_at = CURRENT_TIMESTAMP");
    $stmt->execute([$user_id, $lesson_id]);

    $current_index = 0;
    foreach ($lessons as $index => $lesson) if ((int) $lesson['id'] === $lesson_id) {$current_index = $index; break;}
    $previous_lesson = $lessons[$current_index - 1] ?? null;
    $next_lesson = $lessons[$current_index + 1] ?? null;
    $lesson_count = count($lessons);
    $completed_count = 0;
    $total_duration = 0;
    $completed_duration = 0;
    foreach ($lessons as $lesson) {
        $id = (int) $lesson['id'];
        $duration = (int) ($lesson['duration'] ?? 0);
        $total_duration += $duration;
        if (!empty($progress_map[$id]['completed'])) {$completed_count++; $completed_duration += $duration;}
    }
    $remaining_duration = max(0, $total_duration - $completed_duration);
    $progress_percent = $lesson_count > 0 ? round(($completed_count / $lesson_count) * 100) : 0;
    sync_learning_progress($pdo, $user_id, $course_id);
    $current_progress = $progress_map[$lesson_id] ?? ['completed' => 0, 'video_position' => 0];

    $note_search = trim($_GET['note_search'] ?? '');
    if ($note_search !== '') {
        $stmt = $pdo->prepare("SELECT n.*, l.title AS lesson_title FROM lesson_notes n JOIN lessons l ON l.id = n.lesson_id WHERE n.user_id = ? AND l.course_id = ? AND (n.content COLLATE utf8mb4_unicode_ci LIKE ? OR l.title COLLATE utf8mb4_unicode_ci LIKE ?) ORDER BY n.updated_at DESC");
        $search_pattern = '%' . $note_search . '%';
        $stmt->execute([$user_id, $course_id, $search_pattern, $search_pattern]);
    } else {
        $stmt = $pdo->prepare("SELECT n.*, l.title AS lesson_title FROM lesson_notes n JOIN lessons l ON l.id = n.lesson_id WHERE n.user_id = ? AND n.lesson_id = ? ORDER BY n.updated_at DESC");
        $stmt->execute([$user_id, $lesson_id]);
    }
    $notes = $stmt->fetchAll() ?: [];

} catch (PDOException $e) {
    die('Lỗi xử lý học trực tuyến: ' . $e->getMessage());
}

$youtube_video_id = extract_youtube_video_id($current_lesson['video_url'] ?? '');
$google_drive_preview_url = google_drive_preview_url($current_lesson['video_url'] ?? '');
$current_title_has_lesson_prefix = preg_match('/^\s*Bài\s+\d+\s*:/iu', $current_lesson['title'] ?? '') === 1;
$page_title = 'Đang học: ' . $course['title'];
require_once __DIR__ . '/includes/header.php';
?>
<div class="learning-page">
    <div class="learning-container">
        <div class="learning-topbar" id="learning-topbar">
            <div class="learning-video-topbar">
                <a href="profile.php" class="learning-back"><i data-lucide="chevron-left"></i>Hồ sơ của tôi</a>
                <div class="learning-title-block"><span><?php echo htmlspecialchars($course['category']); ?></span><h1><?php echo htmlspecialchars($course['title']); ?></h1></div>
            </div>
            <div class="learning-progress"><strong><?php echo $completed_count; ?>/<?php echo $lesson_count; ?> bài</strong><span><?php echo $progress_percent; ?>% hoàn thành</span></div>
        </div>

        <div class="classroom-layout" id="classroom-layout">
            <main class="lesson-main">
                <div class="video-shell">
                    <?php if ($youtube_video_id): ?>
                        <div id="classroom-youtube" data-video-id="<?php echo htmlspecialchars($youtube_video_id); ?>"></div>
                    <?php elseif ($google_drive_preview_url): ?>
                        <iframe src="<?php echo htmlspecialchars($google_drive_preview_url); ?>" title="<?php echo htmlspecialchars($current_lesson['title']); ?>" allow="autoplay; fullscreen" allowfullscreen></iframe>
                    <?php elseif (!empty($current_lesson['video_url'])): ?>
                        <video id="classroom-player" src="<?php echo htmlspecialchars($current_lesson['video_url']); ?>" controls controlslist="nodownload noremoteplayback" disablepictureinpicture oncontextmenu="return false;"></video>
                    <?php else: ?>
                        <div class="video-empty"><i data-lucide="video-off"></i><p>Bài học này chưa có video.</p></div>
                    <?php endif; ?>
                </div>

                <section class="lesson-panel">
                    <div class="lesson-heading">
                        <div><?php if (!$current_title_has_lesson_prefix): ?><span>Bài <?php echo htmlspecialchars($current_lesson['order_index']); ?></span><?php endif; ?><h2><?php echo htmlspecialchars($current_lesson['title']); ?></h2></div>
                        <div class="lesson-meta">
                            <span><i data-lucide="clock"></i><?php echo (int) $current_lesson['duration']; ?> phút</span>
                            <span><i data-lucide="history"></i>Đã học <?php echo $completed_duration; ?> phút</span>
                            <span><i data-lucide="hourglass"></i>Còn <?php echo $remaining_duration; ?> phút</span>
                        </div>
                    </div>

                    <div class="learning-tabs" role="tablist">
                        <button type="button" data-learning-tab="overview" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>"><i data-lucide="book-open"></i>Nội dung</button>
                        <button type="button" data-learning-tab="notes" class="<?php echo $active_tab === 'notes' ? 'active' : ''; ?>"><i data-lucide="notebook-pen"></i>Ghi chú</button>

                    </div>

                    <div class="learning-tab-panel <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" data-learning-panel="overview">
                        <div class="lesson-description">
                            <h3>Nội dung bài học</h3>
                            <p><?php echo nl2br(htmlspecialchars($current_lesson['description'] ?: 'Bài học này giúp củng cố kiến thức thực hành. Hãy xem kỹ video và tự thực hành lại nội dung vừa học.')); ?></p>
                        </div>
                        <form method="POST" class="completion-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                            <input type="hidden" name="action" value="toggle_complete">
                            <input type="hidden" name="completed" value="<?php echo !empty($current_progress['completed']) ? 0 : 1; ?>">
                            <input type="hidden" name="video_position" value="0" data-completion-position>
                            <button type="submit" class="btn <?php echo !empty($current_progress['completed']) ? 'btn-outline' : 'btn-primary'; ?>"><i data-lucide="<?php echo !empty($current_progress['completed']) ? 'rotate-ccw' : 'check-circle'; ?>"></i><?php echo !empty($current_progress['completed']) ? 'Đánh dấu chưa hoàn thành' : 'Đánh dấu đã hoàn thành'; ?></button>
                        </form>
                    </div>

                    <div class="learning-tab-panel <?php echo $active_tab === 'notes' ? 'active' : ''; ?>" data-learning-panel="notes">
                        <form method="POST" class="note-create-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                            <input type="hidden" name="action" value="save_note">
                            <input type="hidden" name="video_position" value="0" data-note-position>
                            <textarea name="content" required placeholder="Ghi lại ý chính, đoạn mã hoặc điều bạn cần nhớ..."></textarea>
                            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i>Lưu ghi chú tại thời điểm hiện tại</button>
                        </form>

                        <form method="GET" action="learning.php" class="note-search-form">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                            <input type="hidden" name="tab" value="notes">
                            <input type="search" name="note_search" value="<?php echo htmlspecialchars($note_search); ?>" placeholder="Tìm trong ghi chú của khóa học...">
                            <button type="submit" class="btn btn-outline"><i data-lucide="search"></i>Tìm</button>
                        </form>
                        <?php if ($note_search !== ''): ?>
                            <div class="note-search-summary">
                                <span>Tìm thấy <?php echo count($notes); ?> kết quả cho “<?php echo htmlspecialchars($note_search); ?>”</span>
                                <a href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson_id; ?>&tab=notes">Xóa tìm kiếm</a>
                            </div>
                        <?php endif; ?>

                        <div class="notes-list">
                            <?php if (empty($notes)): ?>
                                <p class="learning-empty">Chưa có ghi chú phù hợp.</p>
                            <?php else: ?>
                                <?php foreach ($notes as $note): ?>
                                    <article class="note-item">
                                        <div class="note-item-head">
                                            <div><?php if ($note_search !== ''): ?><strong><?php echo htmlspecialchars($note['lesson_title']); ?></strong><?php endif; ?><button type="button" class="note-time" data-seek="<?php echo (int) $note['video_position']; ?>"><i data-lucide="play"></i><?php echo sprintf('%02d:%02d', floor($note['video_position'] / 60), $note['video_position'] % 60); ?></button></div>
                                            <span><?php echo date('d/m/Y H:i', strtotime($note['updated_at'])); ?></span>
                                        </div>
                                        <form method="POST" class="note-edit-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                            <input type="hidden" name="lesson_id" value="<?php echo (int) $note['lesson_id']; ?>">
                                            <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
                                            <input type="hidden" name="action" value="save_note">
                                            <input type="hidden" name="video_position" value="<?php echo (int) $note['video_position']; ?>">
                                            <textarea name="content" required><?php echo htmlspecialchars($note['content']); ?></textarea>
                                            <button type="submit" class="btn btn-outline"><i data-lucide="save"></i>Cập nhật</button>
                                        </form>
                                        <form method="POST" class="note-delete-form" data-confirm="Xóa ghi chú này?">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                            <input type="hidden" name="lesson_id" value="<?php echo (int) $note['lesson_id']; ?>">
                                            <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
                                            <input type="hidden" name="action" value="delete_note">
                                            <button type="submit" class="btn btn-danger"><i data-lucide="trash-2"></i>Xóa</button>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="lesson-actions">
                        <?php if ($previous_lesson): ?>
                            <a class="btn btn-outline" href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo (int) $previous_lesson['id']; ?>"><i data-lucide="arrow-left"></i>Bài trước</a>
                        <?php else: ?><span></span><?php endif; ?>
                        <?php if ($next_lesson && !empty($lesson_access[(int) $next_lesson['id']])): ?>
                            <a class="btn btn-primary" href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo (int) $next_lesson['id']; ?>">Bài tiếp theo<i data-lucide="arrow-right"></i></a>
                        <?php elseif ($next_lesson): ?>
                            <button type="button" class="btn btn-outline" disabled><i data-lucide="lock"></i>Hoàn thành bài này để tiếp tục</button>
                        <?php endif; ?>
                    </div>
                </section>
            </main>

            <aside class="lesson-sidebar" id="lesson-sidebar">
                <div class="playlist-head">
                    <div><h3>Nội dung khóa học</h3><p><?php echo $lesson_count; ?> bài · <?php echo $total_duration; ?> phút</p></div>
                    <div class="playlist-head-actions"><div class="progress-ring"><?php echo $progress_percent; ?>%</div><button type="button" class="sidebar-toggle" id="lesson-sidebar-toggle" title="Thu gọn danh sách"><i data-lucide="panel-right-close"></i></button></div>
                </div>
                <div class="progress-track"><div style="width:<?php echo $progress_percent; ?>%"></div></div>
                <div class="playlist-list">
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <?php
                            $id = (int) $lesson['id'];
                            $is_active = $id === $lesson_id;
                            $is_completed = !empty($progress_map[$id]['completed']);
                            $is_unlocked = !empty($lesson_access[$id]);
                        ?>
                        <?php if ($is_unlocked): ?>
                            <a href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $id; ?>" class="playlist-link <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
                        <?php else: ?><div class="playlist-link locked"><?php endif; ?>
                            <div class="playlist-index">
                                <?php if ($is_completed): ?><i data-lucide="check"></i><?php elseif ($is_active): ?><i data-lucide="play"></i><?php elseif (!$is_unlocked): ?><i data-lucide="lock"></i><?php else: ?><?php echo $index + 1; ?><?php endif; ?>
                            </div>
                            <div class="playlist-text"><strong><?php echo htmlspecialchars($lesson['title']); ?></strong><span><?php echo (int) $lesson['duration']; ?> phút<?php echo $is_completed ? ' · Đã hoàn thành' : ''; ?></span></div>
                        <?php if ($is_unlocked): ?></a><?php else: ?></div><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </div>
</div>
<style>
.learning-page{background:#0f172a;color:white;min-height:calc(100vh - 80px)}.learning-container{width:min(1480px,calc(100% - 48px));margin:0 auto;padding:28px 0 40px}.learning-topbar{display:grid;grid-template-columns:minmax(0,2.7fr) minmax(320px,.95fr);align-items:center;gap:24px;margin-bottom:24px}.learning-video-topbar{position:relative;min-width:0;display:flex;align-items:center;justify-content:center;min-height:62px}.learning-back{position:absolute;left:0;color:#94a3b8;display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:14px}.learning-back svg{width:18px;height:18px}.learning-title-block{text-align:center;min-width:0}.learning-title-block>span{display:inline-flex;margin-bottom:8px;padding:4px 12px;border-radius:99px;background:#172554;color:#bfdbfe;font-size:12px;font-weight:800}.learning-title-block h1{color:white;font-size:24px;font-weight:800;line-height:1.3;margin:0}.learning-progress{justify-self:end;text-align:right;color:#94a3b8;font-size:12px;font-weight:700}.learning-progress strong{display:block;color:white;font-size:18px}.classroom-layout{display:grid;grid-template-columns:minmax(0,2.7fr) minmax(320px,.95fr);gap:24px;align-items:start}.lesson-main,.lesson-sidebar,.lesson-panel{min-width:0}.video-shell{aspect-ratio:16/9;background:#020617;border:1px solid #1e293b;border-radius:8px;overflow:hidden;box-shadow:0 18px 40px rgba(2,6,23,.35)}.video-shell video,.video-shell iframe,#classroom-youtube{width:100%;height:100%;object-fit:contain;border:0}.video-empty{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:12px;font-weight:700}.video-empty svg{width:44px;height:44px}.lesson-panel,.lesson-sidebar{background:#1e293b;border:1px solid #334155;border-radius:8px;box-shadow:0 12px 30px rgba(2,6,23,.22)}.lesson-panel{margin-top:24px;padding:24px}.lesson-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding-bottom:18px;border-bottom:1px solid #334155;margin-bottom:18px}.lesson-heading>div>span{color:#93c5fd;font-size:12px;font-weight:800;text-transform:uppercase}.lesson-heading h2{color:white;font-size:24px;font-weight:800;line-height:1.35;margin:6px 0 0}.lesson-meta{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;color:#cbd5e1;font-size:12px;font-weight:700}.lesson-meta span{display:inline-flex;align-items:center;gap:6px}.lesson-meta svg{width:15px;height:15px}.learning-tabs{display:flex;gap:6px;border-bottom:1px solid #334155;margin-bottom:20px;overflow-x:auto}.learning-tabs button{border:0;background:transparent;color:#94a3b8;padding:11px 14px;display:inline-flex;align-items:center;gap:7px;font-weight:800;white-space:nowrap;cursor:pointer;border-bottom:2px solid transparent}.learning-tabs button.active{color:white;border-bottom-color:#3b82f6}.learning-tabs svg{width:16px;height:16px}.learning-tab-panel{display:none}.learning-tab-panel.active{display:block}.lesson-description h3{color:#93c5fd;font-size:15px;font-weight:800;margin-bottom:10px}.lesson-description p{color:#cbd5e1;font-size:15px;line-height:1.8;margin:0}.completion-form{margin-top:22px}.completion-form .btn{min-height:42px}.completion-form .btn-primary{background:#2563eb;border:2px solid #2563eb;color:#fff}.completion-form .btn-primary:hover{background:#1d4ed8;border-color:#1d4ed8;color:#fff}.completion-form .btn-outline{background:#fff;border:2px solid #cbd5e1;color:#0f172a}.completion-form .btn-outline:hover{background:#f1f5f9;border-color:#94a3b8;color:#0f172a}
.note-search-form{display:flex;gap:10px;margin:18px 0 14px}.note-search-form input,.note-create-form textarea,.note-edit-form textarea{width:100%;border:1px solid #475569;border-radius:6px;background:#0f172a;color:white;padding:12px;font:inherit;outline:none}.note-search-form input{height:42px}.note-search-form .btn{height:42px;min-height:42px;padding:0 16px;flex:0 0 auto;white-space:nowrap;background:#2563eb;border-color:#2563eb;color:white}.note-search-form .btn:hover{background:#1d4ed8;border-color:#1d4ed8;color:white}.note-edit-form .btn{border-color:#2563eb;background:#2563eb;color:white}.note-edit-form .btn:hover{border-color:#1d4ed8;background:#1d4ed8;color:white}.note-create-form textarea{min-height:130px;resize:vertical}.note-create-form .btn{margin-top:10px}.notes-list{display:grid;gap:12px;margin-top:18px}.note-item{position:relative;border:1px solid #334155;border-radius:7px;background:#111827;padding:14px}.note-item-head{display:flex;justify-content:space-between;gap:12px;margin-bottom:10px}.note-item-head>div{display:flex;align-items:center;gap:10px;min-width:0}.note-item-head strong{color:white;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.note-item-head>span{color:#64748b;font-size:11px;white-space:nowrap}.note-time{border:0;background:#1d4ed8;color:white;border-radius:5px;padding:5px 8px;display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800;cursor:pointer}.note-time svg{width:12px;height:12px}.note-edit-form textarea{min-height:82px;resize:vertical}.note-edit-form .btn,.note-delete-form .btn{margin-top:8px;min-height:34px;height:34px;font-size:12px}.note-delete-form{position:absolute;right:14px;bottom:14px;display:flex;width:max-content;margin:0}.learning-empty{color:#94a3b8;text-align:center;padding:28px 0}.lesson-actions{display:flex;justify-content:space-between;gap:12px;margin-top:24px;padding-top:18px;border-top:1px solid #334155}.lesson-actions .btn{min-height:42px;border-radius:6px;font-size:14px}.lesson-actions button[disabled]{opacity:1;cursor:not-allowed;background:#334155;border-color:#475569;color:#f8fafc}
.lesson-sidebar{padding:18px;max-height:calc(100vh - 150px);position:sticky;top:96px;overflow-y:auto}.playlist-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}.playlist-head h3{color:white;font-size:17px;font-weight:800;margin:0 0 4px}.playlist-head p{color:#94a3b8;font-size:13px;margin:0;font-weight:700}.playlist-head-actions{display:flex;align-items:center;gap:8px}.progress-ring{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#0f172a;color:#bfdbfe;font-size:12px;font-weight:800;border:1px solid #334155}.sidebar-toggle{width:36px;height:36px;border:1px solid #475569;border-radius:6px;background:#0f172a;color:white;display:grid;place-items:center;cursor:pointer}.sidebar-toggle svg{width:17px;height:17px}.progress-track{height:8px;background:#0f172a;border-radius:99px;overflow:hidden;margin-bottom:18px;border:1px solid #334155}.progress-track div{height:100%;background:#2563eb;border-radius:99px}.playlist-list{display:flex;flex-direction:column;gap:9px}.playlist-link{display:grid;grid-template-columns:34px minmax(0,1fr);gap:12px;align-items:center;padding:12px;border-radius:7px;border:1px solid #334155;background:#0f172a;color:#cbd5e1;transition:var(--transition)}.playlist-link:hover{border-color:#475569;background:#111c30}.playlist-link.active{border-color:#2563eb;background:#172554;color:white}.playlist-link.completed{border-color:#166534}.playlist-link.locked{opacity:.55;cursor:not-allowed}.playlist-index{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#1e293b;color:#94a3b8;font-size:12px;font-weight:800}.playlist-link.active .playlist-index{background:#2563eb;color:white}.playlist-link.completed .playlist-index{background:#15803d;color:white}.playlist-index svg{width:14px;height:14px}.playlist-text{min-width:0}.playlist-text strong{display:block;color:inherit;font-size:13px;font-weight:800;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.playlist-text span{display:block;color:#64748b;font-size:12px;font-weight:700;margin-top:3px}.classroom-layout.sidebar-collapsed,.learning-topbar.sidebar-collapsed{grid-template-columns:minmax(0,1fr) 78px}.learning-topbar.sidebar-collapsed{position:relative}.learning-topbar.sidebar-collapsed .learning-progress{position:absolute;right:0;white-space:nowrap}.classroom-layout.sidebar-collapsed .lesson-sidebar{display:flex;align-items:center;justify-content:center;padding:14px}.classroom-layout.sidebar-collapsed .playlist-head{width:100%;justify-content:center;margin:0}.classroom-layout.sidebar-collapsed .playlist-head-actions{width:100%;justify-content:center}.classroom-layout.sidebar-collapsed .playlist-head>div:first-child,.classroom-layout.sidebar-collapsed .progress-ring,.classroom-layout.sidebar-collapsed .progress-track,.classroom-layout.sidebar-collapsed .playlist-list{display:none}
@media(max-width:992px){.classroom-layout,.classroom-layout.sidebar-collapsed{grid-template-columns:1fr}.lesson-sidebar{position:static;max-height:none}.learning-topbar,.learning-topbar.sidebar-collapsed{grid-template-columns:1fr;text-align:left}.learning-video-topbar{align-items:flex-start;flex-direction:column;gap:14px}.learning-back{position:static}.learning-title-block{text-align:left}.learning-progress{justify-self:start;text-align:left}.learning-topbar.sidebar-collapsed .learning-progress{position:static;white-space:normal}.lesson-heading{flex-direction:column}.lesson-meta{justify-content:flex-start}.classroom-layout.sidebar-collapsed .lesson-sidebar{padding:14px}.classroom-layout.sidebar-collapsed .playlist-head>div:first-child,.classroom-layout.sidebar-collapsed .progress-ring,.classroom-layout.sidebar-collapsed .progress-track,.classroom-layout.sidebar-collapsed .playlist-list{display:none}}@media(max-width:640px){.learning-container{width:min(100% - 28px,1480px)}.lesson-sidebar{order:-1}.classroom-layout.sidebar-collapsed .lesson-sidebar{width:64px;min-height:58px;justify-self:end;padding:10px}.lesson-panel,.lesson-sidebar{padding:16px}.lesson-actions{flex-direction:column}.lesson-actions>span{display:none}.note-search-form{flex-direction:column}.note-search-form .btn{width:100%;justify-content:center}.note-item-head{flex-direction:column}.note-delete-form{position:static;width:max-content;margin:8px 0 0 auto}.learning-tabs button{padding:10px}.lesson-heading h2{font-size:20px}}
.admin-confirm-overlay{position:fixed;inset:0;background:rgba(2,6,23,.72);display:none;align-items:center;justify-content:center;padding:18px;z-index:3000}.admin-confirm-overlay.is-open{display:flex}.admin-confirm-dialog{width:min(430px,100%);background:white;color:#0f172a;border-radius:8px;padding:22px;display:grid;grid-template-columns:42px 1fr;gap:14px;box-shadow:0 24px 60px rgba(0,0,0,.35)}.admin-confirm-icon{width:42px;height:42px;border-radius:50%;background:#fee2e2;color:#dc2626;display:grid;place-items:center}.admin-confirm-icon svg{width:21px;height:21px}.admin-confirm-content h3{font-size:18px;margin:0 0 7px}.admin-confirm-content p{color:#64748b;line-height:1.55;margin:0}.admin-confirm-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px;margin-top:8px}.admin-confirm-actions .btn{min-height:40px}.note-search-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:-2px 0 14px;padding:10px 12px;border:1px solid #334155;border-radius:6px;background:#111827;color:#cbd5e1;font-size:13px;font-weight:700}.note-search-summary a{color:#93c5fd;font-weight:800;white-space:nowrap}</style>
<script>
const html5Player=document.getElementById('classroom-player');
const youtubeContainer=document.getElementById('classroom-youtube');
let youtubePlayer=null;
let youtubeSaveTimer=null;
const savedVideoPosition=<?php echo (int) ($current_progress['video_position'] ?? 0); ?>;
const learningPayload={csrf_token:<?php echo json_encode($csrf_token); ?>,course_id:<?php echo $course_id; ?>,lesson_id:<?php echo $lesson_id; ?>};
function currentVideoTime(){if(html5Player)return html5Player.currentTime||0;if(youtubePlayer&&typeof youtubePlayer.getCurrentTime==='function')return youtubePlayer.getCurrentTime()||0;return 0}
function currentVideoDuration(){if(html5Player)return html5Player.duration||0;if(youtubePlayer&&typeof youtubePlayer.getDuration==='function')return youtubePlayer.getDuration()||0;return 0}
function saveVideoPosition(force){const position=Math.max(0,Math.floor(currentVideoTime()));const last=Number(document.body.dataset.learningLastSaved||0);if(!force&&Math.abs(position-last)<10)return;document.body.dataset.learningLastSaved=position;const body=new URLSearchParams({...learningPayload,action:'save_video_progress',video_position:position});fetch('learning.php?course_id='+learningPayload.course_id+'&lesson_id='+learningPayload.lesson_id,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString(),keepalive:true})}
function completeCurrentLesson(){const form=document.querySelector('.completion-form');if(!form||form.dataset.autoSubmitted==='1')return;form.dataset.autoSubmitted='1';form.querySelector('input[name=completed]').value='1';const position=form.querySelector('[data-completion-position]');if(position)position.value=Math.floor(currentVideoDuration()||currentVideoTime()||0);form.submit()}
function resumePlayer(){const duration=currentVideoDuration();if(savedVideoPosition>0&&savedVideoPosition<duration-3){if(html5Player)html5Player.currentTime=savedVideoPosition;if(youtubePlayer)youtubePlayer.seekTo(savedVideoPosition,true)}document.body.dataset.learningLastSaved=savedVideoPosition}
if(html5Player){html5Player.addEventListener('loadedmetadata',resumePlayer);html5Player.addEventListener('timeupdate',function(){saveVideoPosition(false)});html5Player.addEventListener('pause',function(){saveVideoPosition(true)});html5Player.addEventListener('ended',function(){saveVideoPosition(true);completeCurrentLesson()})}
if(youtubeContainer){window.onYouTubeIframeAPIReady=function(){youtubePlayer=new YT.Player('classroom-youtube',{videoId:youtubeContainer.dataset.videoId,playerVars:{rel:0,playsinline:1},events:{onReady:function(){resumePlayer()},onStateChange:function(event){if(event.data===YT.PlayerState.PLAYING){clearInterval(youtubeSaveTimer);youtubeSaveTimer=setInterval(function(){saveVideoPosition(false)},5000)}else{clearInterval(youtubeSaveTimer);saveVideoPosition(true)}if(event.data===YT.PlayerState.ENDED)completeCurrentLesson()}}})};const apiScript=document.createElement('script');apiScript.src='https://www.youtube.com/iframe_api';document.head.appendChild(apiScript)}
window.addEventListener('beforeunload',function(){saveVideoPosition(true)});
document.querySelectorAll('[data-learning-tab]').forEach(function(button){button.addEventListener('click',function(){const tab=button.dataset.learningTab;document.querySelectorAll('[data-learning-tab]').forEach(function(item){item.classList.toggle('active',item===button)});document.querySelectorAll('[data-learning-panel]').forEach(function(panel){panel.classList.toggle('active',panel.dataset.learningPanel===tab)})})});
document.querySelectorAll('.completion-form').forEach(function(form){form.addEventListener('submit',function(){const input=form.querySelector('[data-completion-position]');if(input)input.value=Math.floor(currentVideoTime())})});
document.querySelectorAll('.note-create-form').forEach(function(form){form.addEventListener('submit',function(){const input=form.querySelector('[data-note-position]');if(input)input.value=Math.floor(currentVideoTime())})});
document.querySelectorAll('[data-seek]').forEach(function(button){button.addEventListener('click',function(){const target=Number(button.dataset.seek||0);if(html5Player){html5Player.currentTime=target;html5Player.play()}if(youtubePlayer){youtubePlayer.seekTo(target,true);youtubePlayer.playVideo()}window.scrollTo({top:document.querySelector('.video-shell').offsetTop-90,behavior:'smooth'})})});
const sidebarToggle=document.getElementById('lesson-sidebar-toggle');const classroomLayout=document.getElementById('classroom-layout');const learningTopbar=document.getElementById('learning-topbar');if(sidebarToggle&&classroomLayout){const collapsed=localStorage.getItem('learningSidebarCollapsed')==='1';classroomLayout.classList.toggle('sidebar-collapsed',collapsed);if(learningTopbar)learningTopbar.classList.toggle('sidebar-collapsed',collapsed);sidebarToggle.innerHTML='<i data-lucide="'+(collapsed?'panel-right-open':'panel-right-close')+'"></i>';sidebarToggle.addEventListener('click',function(){const isCollapsed=classroomLayout.classList.toggle('sidebar-collapsed');if(learningTopbar)learningTopbar.classList.toggle('sidebar-collapsed',isCollapsed);localStorage.setItem('learningSidebarCollapsed',isCollapsed?'1':'0');sidebarToggle.innerHTML='<i data-lucide="'+(isCollapsed?'panel-right-open':'panel-right-close')+'"></i>';lucide.createIcons()})}
lucide.createIcons();
</script><script src="assets/js/admin-confirm.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>