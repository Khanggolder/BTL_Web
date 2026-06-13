<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$course_id = intval($_GET['id'] ?? 0);

try {
    
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND published = 1");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header("Location: courses.php");
        exit();
    }

    $page_title = $course['title'];

    
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll() ?: [];

    
    $is_enrolled = false;
    if (is_logged_in()) {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND active = 1");
        $stmt->execute([$_SESSION['user_id'], $course_id]);
        if ($stmt->fetch()) {
            $is_enrolled = true;
        }
    }

    
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE category = ? AND id != ? AND published = 1 LIMIT 3");
    $stmt->execute([$course['category'], $course_id]);
    $related_courses = $stmt->fetchAll() ?: [];

} catch (PDOException $e) {
    die("Có lỗi xảy ra: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';


$discount_pct = 0;
$has_discount = $course['discount_price'] > 0 && $course['discount_price'] < $course['price'];
if ($has_discount) {
    $discount_pct = round((($course['price'] - $course['discount_price']) / $course['price']) * 100);
}
?>


<div style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 60px 0;">
    <div class="container">
        
        
        <a href="courses.php" style="display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.9); font-weight: 600; margin-bottom: 24px;">
            <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
            Quay lại danh sách khóa học
        </a>

        <div class="detail-grid">
            
            
            <div class="course-info">
                <span class="badge-tag" style="background-color: rgba(255,255,255,0.2); color: white; font-weight: 700; font-size: 12px; margin-bottom: 16px; display: inline-block;">
                    <?php echo htmlspecialchars($course['category']); ?>
                </span>
                
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                
                <p style="font-size: 18px; color: rgba(255, 255, 255, 0.85); margin-bottom: 24px; line-height: 1.6;">
                    <?php echo htmlspecialchars($course['description']); ?>
                </p>

                <div class="course-badge-info">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="star" style="fill: #fbbf24; stroke: #fbbf24; width: 18px; height: 18px;"></i>
                        <span style="font-weight: 700;"><?php echo number_format($course['rating'] ?: 5.0, 1); ?></span>
                        <span style="color: rgba(255,255,255,0.85);"> (<?php echo htmlspecialchars($course['rating_count'] ?: 0); ?> đánh giá)</span>
                    </div>
                    <span style="color: rgba(255,255,255,0.6);">|</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                        <span><?php echo htmlspecialchars($course['enrollment_count'] ?: 0); ?> học viên</span>
                    </div>
                    <span style="color: rgba(255,255,255,0.6);">|</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                        <span>Giảng viên: <strong><?php echo htmlspecialchars($course['instructor'] ?? 'Khánh Nguyễn'); ?></strong></span>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 14px; font-weight: 600;">
                    <div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="clock" style="width: 16px; height: 16px;"></i> <?php echo round(($course['duration'] ?? 0) / 60); ?> giờ học</div>
                    <div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="book-open" style="width: 16px; height: 16px;"></i> <?php echo htmlspecialchars($course['total_lectures'] ?? 0); ?> bài giảng</div>
                    <div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="bar-chart-2" style="width: 16px; height: 16px;"></i> <?php echo htmlspecialchars($course['level']); ?></div>
                    <div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="globe" style="width: 16px; height: 16px;"></i> Tiếng Việt</div>
                </div>

            </div>

            
            <div style="display: flex; align-items: center; justify-content: center;">
                <img src="<?php echo htmlspecialchars($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" style="width: 100%; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); border: 2px solid rgba(255,255,255,0.2);">
            </div>

        </div>

    </div>
</div>


<div class="section" style="background-color: var(--bg-main); padding-top: 50px;">
    <div class="container">
        
        <div class="detail-grid">
            
            
            <div>
                
                
                <div style="display: flex; border-bottom: 2px solid var(--border); margin-bottom: 30px; background-color: var(--bg-card); border-radius: var(--radius-sm); padding: 4px;">
                    <button class="tab-btn active" onclick="switchTab('overview', this)">Tổng quan</button>
                    <button class="tab-btn" onclick="switchTab('curriculum', this)">Bài học (<?php echo count($lessons); ?>)</button>
                    <button class="tab-btn" onclick="switchTab('instructor', this)">Giảng viên</button>
                </div>

                
                <div id="tab-overview" class="tab-content-panel active-panel">
                    <div class="course-description">
                        <h3 class="description-title">Bạn sẽ học được gì</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px;">
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <i data-lucide="check-circle" style="color: var(--success); width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px;"></i>
                                <span style="font-weight: 500; font-size: 15px;">Nắm vững kiến thức nền tảng thực tế.</span>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <i data-lucide="check-circle" style="color: var(--success); width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px;"></i>
                                <span style="font-weight: 500; font-size: 15px;">Xây dựng các sản phẩm thực chiến từ đầu.</span>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <i data-lucide="check-circle" style="color: var(--success); width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px;"></i>
                                <span style="font-weight: 500; font-size: 15px;">Kỹ năng giải quyết bài toán tư duy lập trình.</span>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <i data-lucide="check-circle" style="color: var(--success); width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px;"></i>
                                <span style="font-weight: 500; font-size: 15px;">Nhận chứng chỉ LearnHub hoàn thành khóa học.</span>
                            </div>
                        </div>

                        <h3 class="description-title">Mô tả chi tiết</h3>
                        <div style="color: var(--text-muted); font-size: 15px; line-height: 1.8;">
                            <p style="margin-bottom: 16px;">Khóa học này sẽ hướng dẫn bạn từ cơ bản đến nâng cao thông qua các ví dụ minh họa trực quan sinh động và hệ thống bài học thực chiến. Bạn sẽ học hỏi trực tiếp từ kinh nghiệm nhiều năm làm việc của giảng viên kỳ cựu.</p>
                            <p>Không chỉ là lý thuyết suông, khóa học tập trung chủ yếu vào thực hành thực tế, giải quyết các lỗi hay gặp phải trong thực tế giúp bạn nhanh chóng vững bước làm chủ công nghệ.</p>
                        </div>

                        <h3 class="description-title" style="margin-top: 32px;">Yêu cầu đầu vào</h3>
                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; color: var(--text-muted); font-size: 15px; font-weight: 500;">
                            <li style="display: flex; align-items: center; gap: 8px;"><i data-lucide="chevron-right" style="width: 16px; height: 16px; color: var(--primary);"></i> Có máy tính cá nhân kết nối Internet.</li>
                            <li style="display: flex; align-items: center; gap: 8px;"><i data-lucide="chevron-right" style="width: 16px; height: 16px; color: var(--primary);"></i> Tinh thần ham học hỏi và tự giác rèn luyện bài tập.</li>
                        </ul>
                    </div>
                </div>

                
                <div id="tab-curriculum" class="tab-content-panel">
                    <div class="playlist-container" style="margin-top: 0; padding: 24px;">
                        <div class="playlist-header">
                            <h3>Chương trình học</h3>
                            <span style="font-size: 14px; color: var(--text-muted); font-weight: 600;"><?php echo count($lessons); ?> bài giảng</span>
                        </div>

                        <?php if (empty($lessons)): ?>
                            <p style="color: var(--text-muted); font-style: italic;">Chương trình bài học đang được biên soạn.</p>
                        <?php else: ?>
                            <div class="playlist-items">
                                <?php foreach ($lessons as $index => $lesson): ?>
                                    <div class="playlist-item">
                                        <div class="playlist-item-left">
                                            <i data-lucide="play-circle"></i>
                                            <span>Bài <?php echo htmlspecialchars($lesson['order_index']); ?>: <?php echo htmlspecialchars($lesson['title']); ?></span>
                                        </div>
                                        
                                        <div class="playlist-item-right">
                                            <span class="duration-tag"><?php echo htmlspecialchars($lesson['duration'] ?? 10); ?> phút</span>
                                            
                                            <?php if ($lesson['is_free'] == 1): ?>
                                                <button class="preview-link" onclick="openPreviewModal('<?php echo htmlspecialchars($lesson['title']); ?>', '<?php echo htmlspecialchars($lesson['video_url']); ?>')" style="border: none; cursor: pointer;">
                                                    <i data-lucide="eye" style="width: 12px; height: 12px;"></i> Học thử miễn phí
                                                </button>
                                            <?php elseif ($is_enrolled): ?>
                                                <span class="preview-link" style="background-color: var(--primary-light); color: var(--primary);">
                                                    <i data-lucide="check-square" style="width: 12px; height: 12px;"></i> Sẵn sàng học
                                                </span>
                                            <?php else: ?>
                                                <i data-lucide="lock" style="width: 16px; height: 16px; color: var(--text-muted);" title="Mua khóa học để mở khóa"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div id="tab-instructor" class="tab-content-panel">
                    <div class="course-description" style="padding: 32px;">
                        <div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
                            <div class="avatar" style="width: 100px; height: 100px; font-size: 36px; border-radius: 50%; font-weight: 800; flex-shrink: 0;">
                                <?php 
                                    $inst_char = mb_substr($course['instructor'] ?? 'K', 0, 1, 'UTF-8');
                                    echo mb_strtoupper($inst_char, 'UTF-8'); 
                                ?>
                            </div>
                            <div style="flex-grow: 1;">
                                <h3 style="font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 4px;"><?php echo htmlspecialchars($course['instructor'] ?? 'Khánh Nguyễn'); ?></h3>
                                <p style="color: var(--primary); font-weight: 700; font-size: 14px; margin-bottom: 12px;">Chuyên gia công nghệ đào tạo thực chiến</p>
                                
                                <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                                    <div style="background-color: var(--bg-main); padding: 10px 16px; border-radius: var(--radius-sm); text-align: center; border: 1px solid var(--border);">
                                        <div style="font-size: 18px; font-weight: 800; color: var(--primary);"><?php echo number_format($course['rating'] ?: 5.0, 1); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Đánh giá</div>
                                    </div>
                                    <div style="background-color: var(--bg-main); padding: 10px 16px; border-radius: var(--radius-sm); text-align: center; border: 1px solid var(--border);">
                                        <div style="font-size: 18px; font-weight: 800; color: var(--primary);"><?php echo htmlspecialchars($course['enrollment_count'] ?: 0); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Học viên</div>
                                    </div>
                                </div>
                                <p style="color: var(--text-muted); font-size: 14px; line-height: 1.7;"><?php echo htmlspecialchars($course['instructor'] ?? 'Khánh Nguyễn'); ?> là lập trình viên Full-Stack kỳ cựu với hơn 10 năm kinh nghiệm trong các dự án công nghệ lớn. Thầy đam mê chia sẻ kiến thức và có cách giảng dạy vô cùng thực chiến, dễ hiểu, giúp học viên thực hành và làm việc được ngay sau khóa học.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            
            <div class="sidebar-sticky">
                <div class="pricing-card">
                    
                    <div class="pricing-amount">
                        <?php if ($has_discount): ?>
                            <span class="price-new"><?php echo number_format($course['discount_price'], 0, ',', '.'); ?>đ</span>
                            <span class="price-old" style="font-size: 16px;"><?php echo number_format($course['price'], 0, ',', '.'); ?>đ</span>
                            <span class="badge" style="background-color: var(--success); color: white; padding: 2px 8px; font-size: 12px; font-weight: 700; border-radius: 4px; border: none; position: static;">Giảm <?php echo $discount_pct; ?>%</span>
                        <?php else: ?>
                            <span class="price-new"><?php echo number_format($course['price'], 0, ',', '.'); ?>đ</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_enrolled): ?>
                        <a href="profile.php" class="btn btn-primary" style="width: 100%; text-align: center; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="book-open" style="width: 20px; height: 20px;"></i>
                            Vào học ngay
                        </a>
                    <?php else: ?>
                        
                        <form action="cart.php" method="POST">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i data-lucide="shopping-cart" style="width: 20px; height: 20px;"></i>
                                Thêm vào giỏ hàng
                            </button>
                        </form>
                        
                        
                        <form action="cart.php" method="POST">
                            <input type="hidden" name="action" value="buy_now">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" class="btn btn-secondary" style="width: 100%; height: 50px; display: flex; align-items: center; justify-content: center;">
                                Mua ngay
                            </button>
                        </form>
                    <?php endif; ?>

                    <ul class="pricing-features">
                        <li><i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> Truy cập trọn đời tất cả bài giảng</li>
                        <li><i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> Học qua video sắc nét mọi lúc mọi nơi</li>
                        <li><i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> Nhận chứng chỉ tốt nghiệp điện tử</li>
                        <li><i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> Bảo đảm hoàn tiền 30 ngày</li>
                    </ul>

                </div>
            </div>

        </div>

        
        <?php if (!empty($related_courses)): ?>
            <div style="margin-top: 80px;">
                <h2 class="section-title" style="margin-bottom: 40px; font-size: 26px;">Các khóa học liên quan</h2>
                <div class="grid-3">
                    <?php foreach ($related_courses as $rel): ?>
                        <?php $rel_has_discount = $rel['discount_price'] > 0 && $rel['discount_price'] < $rel['price']; ?>
                        <div class="course-card">
                            <div class="card-banner">
                                <img src="<?php echo htmlspecialchars($rel['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" alt="<?php echo htmlspecialchars($rel['title']); ?>">
                                <span class="card-tag">
                                    <?php 
                                        $rlvl = $rel['level'];
                                        if ($rlvl === 'BEGINNER') echo 'Cơ bản';
                                        elseif ($rlvl === 'INTERMEDIATE') echo 'Trung cấp';
                                        elseif ($rlvl === 'ADVANCED') echo 'Nâng cao';
                                        else echo htmlspecialchars($rlvl);
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <span class="card-category"><?php echo htmlspecialchars($rel['category']); ?></span>
                                <h3 class="card-title">
                                    <a href="course-detail.php?id=<?php echo $rel['id']; ?>">
                                        <?php echo htmlspecialchars($rel['title']); ?>
                                    </a>
                                </h3>
                                <div class="card-meta">
                                    <div class="card-meta-item"><i data-lucide="clock" style="width: 14px; height: 14px;"></i> <?php echo round(($rel['duration'] ?? 0) / 60); ?> giờ</div>
                                    <div class="card-meta-item"><i data-lucide="book-open" style="width: 14px; height: 14px;"></i> <?php echo htmlspecialchars($rel['total_lectures'] ?? 0); ?> bài</div>
                                </div>
                                <div class="card-footer">
                                    <div class="card-price">
                                        <?php if ($rel_has_discount): ?>
                                            <span class="price-new"><?php echo number_format($rel['discount_price'], 0, ',', '.'); ?>đ</span>
                                        <?php else: ?>
                                            <span class="price-new"><?php echo number_format($rel['price'], 0, ',', '.'); ?>đ</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="course-detail.php?id=<?php echo $rel['id']; ?>" class="btn btn-outline" style="padding: 6px 14px; font-size: 13px;">Chi tiết</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>


<div id="preview-video-modal" style="display: none; position: fixed; inset: 0; background-color: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center; padding: 20px; transition: var(--transition);">
    <div style="background-color: white; width: 100%; max-width: 800px; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-lg); position: relative; animation: zoomIn 0.3s;">
        
        
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modal-video-title" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0; max-width: 90%;">Bài giảng xem thử</h3>
            <button onclick="closePreviewModal()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center;">
                <i data-lucide="x" style="width: 24px; height: 24px;"></i>
            </button>
        </div>

        
        <div style="aspect-ratio: 16/9; background-color: black; width: 100%; display: flex; align-items: center; justify-content: center;">
            <video id="modal-video-player" controls autoplay style="width: 100%; height: 100%; object-fit: contain;">
                Trình duyệt của bạn không hỗ trợ thẻ video.
            </video>
        </div>

    </div>
</div>

<style>
    
    .tab-btn {
        flex-grow: 1;
        background: none;
        border: none;
        padding: 14px 20px;
        font-weight: 700;
        font-size: 15px;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: var(--radius-sm);
        transition: var(--transition);
    }
    .tab-btn:hover {
        color: var(--primary);
    }
    .tab-btn.active {
        color: var(--primary);
        background-color: var(--primary-light);
    }
    .tab-content-panel {
        display: none;
    }
    .tab-content-panel.active-panel {
        display: block;
    }

    
    @keyframes zoomIn {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
</style>

<script>
    
    function switchTab(tabId, buttonElement) {
        
        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => btn.classList.remove('active'));
        
        const tabContents = document.querySelectorAll('.tab-content-panel');
        tabContents.forEach(panel => panel.classList.remove('active-panel'));

        
        buttonElement.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active-panel');
    }

    
    const modal = document.getElementById('preview-video-modal');
    const modalTitle = document.getElementById('modal-video-title');
    const videoPlayer = document.getElementById('modal-video-player');

    function openPreviewModal(title, videoUrl) {
        modalTitle.textContent = title;
        videoPlayer.src = videoUrl;
        modal.style.display = 'flex';
    }

    function closePreviewModal() {
        modal.style.display = 'none';
        videoPlayer.pause();
        videoPlayer.src = '';
    }

    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closePreviewModal();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
