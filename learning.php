<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';


require_login();

$user_id = $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$lesson_id = intval($_GET['lesson_id'] ?? 0);

try {
    
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND active = 1");
    $stmt->execute([$user_id, $course_id]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        
        header("Location: course-detail.php?id=" . $course_id . "&error=" . urlencode("Bạn chưa mua khóa học này!"));
        exit();
    }

    
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll() ?: [];

    if (empty($lessons)) {
        die("Khóa học hiện tại chưa có bài học nào được tải lên!");
    }

    
    $current_lesson = null;
    if ($lesson_id > 0) {
        foreach ($lessons as $l) {
            if ((int) $l['id'] === $lesson_id) {
                $current_lesson = $l;
                break;
            }
        }
    }
    
    
    if (!$current_lesson) {
        $current_lesson = $lessons[0];
    }

    $current_index = 0;
    foreach ($lessons as $index => $lesson) {
        if ((int) $lesson['id'] === (int) $current_lesson['id']) {
            $current_index = $index;
            break;
        }
    }

    $previous_lesson = $lessons[$current_index - 1] ?? null;
    $next_lesson = $lessons[$current_index + 1] ?? null;
    $lesson_count = count($lessons);
    $progress_percent = $lesson_count > 0 ? round((($current_index + 1) / $lesson_count) * 100) : 0;

} catch (PDOException $e) {
    die("Lỗi xử lý học trực tuyến: " . $e->getMessage());
}

$page_title = "Đang học: " . $course['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="learning-page">
    <div class="learning-container">
        <div class="learning-topbar">
            <a href="profile.php" class="learning-back">
                <i data-lucide="chevron-left"></i>
                Hồ sơ của tôi
            </a>
            <div class="learning-title-block">
                <span><?php echo htmlspecialchars($course['category']); ?></span>
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
            </div>
            <div class="learning-progress">
                <strong><?php echo $current_index + 1; ?>/<?php echo $lesson_count; ?></strong>
                <span><?php echo $progress_percent; ?>%</span>
            </div>
        </div>

        <div class="classroom-layout">
            <main class="lesson-main">
                <div class="video-shell">
                    <?php if (!empty($current_lesson['video_url'])): ?>
                        <video id="classroom-player" src="<?php echo htmlspecialchars($current_lesson['video_url']); ?>" controls style="width: 100%; height: 100%; object-fit: contain;">
                            Trình duyệt của bạn không hỗ trợ phát video HTML5.
                        </video>
                    <?php else: ?>
                        <div class="video-empty">
                            <i data-lucide="video-off"></i>
                            <p>Bài học này chưa có video.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <section class="lesson-panel">
                    <div class="lesson-heading">
                        <div>
                            <span>Bài <?php echo htmlspecialchars($current_lesson['order_index']); ?></span>
                            <h2><?php echo htmlspecialchars($current_lesson['title']); ?></h2>
                        </div>
                        <div class="lesson-meta">
                            <span><i data-lucide="clock"></i><?php echo htmlspecialchars($current_lesson['duration']); ?> phút</span>
                            <span><i data-lucide="monitor-play"></i>Video bài học</span>
                        </div>
                    </div>

                    <div class="lesson-description">
                        <h3>Nội dung bài học</h3>
                        <p>
                            <?php echo nl2br(htmlspecialchars(($current_lesson['description'] ?? $current_lesson['content']) ?? 'Bài học này giúp củng cố kiến thức thực hành thực tế, hãy xem kỹ video và thực hành lại mã nguồn mẫu.')); ?>
                        </p>
                    </div>

                    <div class="lesson-actions">
                        <?php if ($previous_lesson): ?>
                            <a class="btn btn-outline" href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $previous_lesson['id']; ?>">
                                <i data-lucide="arrow-left"></i>
                                Bài trước
                            </a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <?php if ($next_lesson): ?>
                            <a class="btn btn-primary" href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $next_lesson['id']; ?>">
                                Bài tiếp theo
                                <i data-lucide="arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            </main>

            <aside class="lesson-sidebar">
                <div class="playlist-head">
                    <div>
                        <h3>Nội dung khóa học</h3>
                        <p><?php echo $lesson_count; ?> bài học</p>
                    </div>
                    <div class="progress-ring"><?php echo $progress_percent; ?>%</div>
                </div>
                <div class="progress-track">
                    <div style="width: <?php echo $progress_percent; ?>%;"></div>
                </div>

                <div class="playlist-list">
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <?php $is_active = ((int) $lesson['id'] === (int) $current_lesson['id']); ?>
                        <a href="learning.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson['id']; ?>" class="playlist-link <?php echo $is_active ? 'active' : ''; ?>">
                            <div class="playlist-index">
                                <?php if ($is_active): ?>
                                    <i data-lucide="play"></i>
                                <?php else: ?>
                                    <?php echo $index + 1; ?>
                                <?php endif; ?>
                            </div>
                            <div class="playlist-text">
                                <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                <span><?php echo htmlspecialchars($lesson['duration']); ?> phút</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </div>
</div>

<style>
    .learning-page {
        background: #0f172a;
        color: white;
        min-height: calc(100vh - 80px);
    }

    .learning-container {
        width: min(1480px, calc(100% - 48px));
        margin: 0 auto;
        padding: 28px 0 40px;
    }

    .learning-topbar {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr) 110px;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
    }

    .learning-back {
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 14px;
    }

    .learning-back svg {
        width: 18px;
        height: 18px;
    }

    .learning-title-block {
        text-align: center;
        min-width: 0;
    }

    .learning-title-block span {
        display: inline-flex;
        margin-bottom: 8px;
        padding: 4px 12px;
        border-radius: 99px;
        background: rgba(37, 99, 235, 0.18);
        color: #bfdbfe;
        font-size: 12px;
        font-weight: 800;
    }

    .learning-title-block h1 {
        color: white;
        font-size: 24px;
        font-weight: 800;
        line-height: 1.3;
        margin: 0;
    }

    .learning-progress {
        justify-self: end;
        text-align: right;
        color: #94a3b8;
        font-size: 12px;
        font-weight: 700;
    }

    .learning-progress strong {
        display: block;
        color: white;
        font-size: 18px;
    }

    .classroom-layout {
        display: grid;
        grid-template-columns: minmax(0, 2.7fr) minmax(320px, 0.95fr);
        gap: 24px;
        align-items: start;
    }

    .lesson-main,
    .lesson-sidebar,
    .lesson-panel {
        min-width: 0;
    }

    .video-shell {
        aspect-ratio: 16 / 9;
        background: #020617;
        border: 1px solid #1e293b;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 18px 40px rgba(2, 6, 23, 0.35);
    }

    .video-empty {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        gap: 12px;
        font-weight: 700;
    }

    .video-empty svg {
        width: 44px;
        height: 44px;
    }

    .lesson-panel,
    .lesson-sidebar {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 10px;
        box-shadow: 0 12px 30px rgba(2, 6, 23, 0.22);
    }

    .lesson-panel {
        margin-top: 24px;
        padding: 24px;
    }

    .lesson-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid #334155;
        margin-bottom: 20px;
    }

    .lesson-heading span {
        color: #93c5fd;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .lesson-heading h2 {
        color: white;
        font-size: 24px;
        font-weight: 800;
        line-height: 1.35;
        margin: 6px 0 0;
    }

    .lesson-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 10px;
        color: #cbd5e1;
        font-size: 13px;
        font-weight: 700;
    }

    .lesson-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #cbd5e1;
        text-transform: none;
    }

    .lesson-meta svg {
        width: 15px;
        height: 15px;
    }

    .lesson-description h3 {
        color: #93c5fd;
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .lesson-description p {
        color: #cbd5e1;
        font-size: 15px;
        line-height: 1.8;
        margin: 0;
    }

    .lesson-actions {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 24px;
        padding-top: 18px;
        border-top: 1px solid #334155;
    }

    .lesson-actions .btn {
        height: 42px;
        border-radius: 6px;
        font-size: 14px;
    }

    .lesson-sidebar {
        padding: 18px;
        max-height: calc(100vh - 150px);
        position: sticky;
        top: 96px;
        overflow-y: auto;
    }

    .playlist-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 14px;
    }

    .playlist-head h3 {
        color: white;
        font-size: 17px;
        font-weight: 800;
        margin: 0 0 4px;
    }

    .playlist-head p {
        color: #94a3b8;
        font-size: 13px;
        margin: 0;
        font-weight: 700;
    }

    .progress-ring {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #0f172a;
        color: #bfdbfe;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid #334155;
    }

    .progress-track {
        height: 8px;
        background: #0f172a;
        border-radius: 99px;
        overflow: hidden;
        margin-bottom: 18px;
        border: 1px solid #334155;
    }

    .progress-track div {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 99px;
    }

    .playlist-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .playlist-link {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #334155;
        background: #0f172a;
        color: #cbd5e1;
        transition: var(--transition);
    }

    .playlist-link:hover {
        border-color: #475569;
        background: #111c30;
    }

    .playlist-link.active {
        border-color: var(--primary);
        background: rgba(37, 99, 235, 0.18);
        color: white;
    }

    .playlist-index {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #1e293b;
        color: #94a3b8;
        font-size: 12px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .playlist-link.active .playlist-index {
        background: var(--primary);
        color: white;
    }

    .playlist-index svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    .playlist-text {
        min-width: 0;
    }

    .playlist-text strong {
        display: block;
        color: inherit;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .playlist-text span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 3px;
    }

    @media (max-width: 992px) {
        .classroom-layout {
            grid-template-columns: 1fr;
        }

        .lesson-sidebar {
            position: static;
            max-height: none;
        }

        .learning-topbar {
            grid-template-columns: 1fr;
            text-align: left;
        }

        .learning-title-block {
            text-align: left;
        }

        .learning-progress {
            justify-self: start;
            text-align: left;
        }

        .lesson-heading {
            flex-direction: column;
        }

        .lesson-meta {
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .learning-container {
            width: min(100% - 28px, 1480px);
        }

        .lesson-panel,
        .lesson-sidebar {
            padding: 16px;
        }

        .lesson-actions {
            flex-direction: column;
        }

        .lesson-actions span {
            display: none;
        }
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
