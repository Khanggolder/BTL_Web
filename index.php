<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$page_title = "Trang chủ - Nền tảng học trực tuyến số 1";
require_once __DIR__ . '/includes/header.php';


$featured_courses = [];
try {
    $stmt = $pdo->query("
        SELECT * 
        FROM courses 
        WHERE published = 1 
        ORDER BY enrollment_count DESC 
        LIMIT 3
    ");
    $featured_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_courses = [];
}
?>


<section class="hero cinematic-hero">
    <video class="hero-video" autoplay loop muted playsinline aria-hidden="true">
        <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260314_131748_f2ca2a28-fed7-44c8-b9a9-bd9acdd5ec31.mp4" type="video/mp4">
    </video>
    <div class="hero-overlay"></div>

    <div class="container hero-inner">
        <div class="hero-content">
            <div class="hero-tag liquid-glass animate-fade-rise">
                <i data-lucide="sparkles" style="width: 16px; height: 16px;"></i>
                <span>LearnHub</span>
            </div>

            <h1 class="hero-title animate-fade-rise">
                Học kỹ năng mới,
                <span>mở khóa tương lai</span>
                của bạn.
            </h1>

            <p class="hero-desc animate-fade-rise-delay">
                Khám phá các khóa học trực tuyến chất lượng, học theo video, theo dõi tiến độ và bắt đầu hành trình phát triển kỹ năng ngay hôm nay.
            </p>

            <div class="hero-btns animate-fade-rise-delay-2">
                <a href="courses.php" class="btn hero-cta liquid-glass">
                    <span>Khám phá khóa học</span>
                    <i data-lucide="arrow-right" style="width: 18px; height: 18px;"></i>
                </a>
                <a href="enterprise.php" class="btn hero-cta-secondary liquid-glass">
                    <span>Dành cho doanh nghiệp</span>
                </a>
            </div>
        </div>
    </div>
</section>


<section class="section" style="background-color: var(--bg-main);">
    <div class="container">
        
        <div class="section-header">
            <h2 class="section-title">Khóa học Nổi bật</h2>
            <a href="courses.php" style="color: var(--primary); font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                Xem tất cả 
                <i data-lucide="arrow-right" style="width: 16px; height: 16px;"></i>
            </a>
        </div>

        <?php if (empty($featured_courses)): ?>
            <div style="text-align: center; padding: 60px 0; background-color: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <i data-lucide="book-x" style="width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Chưa có khóa học nào hoạt động</h3>
                <p style="color: var(--text-muted);">Hãy đăng nhập trang quản trị để thêm và xuất bản khóa học của bạn.</p>
            </div>
        <?php else: ?>
            <div class="grid-3">
                <?php foreach ($featured_courses as $course): ?>
                    <?php $has_discount = $course['discount_price'] > 0 && $course['discount_price'] < $course['price']; ?>
                    <div class="course-card">
                        
                        
                        <div class="card-banner">
                            <img src="<?php echo htmlspecialchars($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <span class="card-tag">
                                <?php 
                                    $level = $course['level'];
                                    if ($level === 'BEGINNER') echo 'Cơ bản';
                                    elseif ($level === 'INTERMEDIATE') echo 'Trung cấp';
                                    elseif ($level === 'ADVANCED') echo 'Nâng cao';
                                    else echo htmlspecialchars($level);
                                ?>
                            </span>
                        </div>

                        
                        <div class="card-body">
                            <span class="card-category"><?php echo htmlspecialchars($course['category']); ?></span>
                            <h3 class="card-title">
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </a>
                            </h3>

                            
                            <div class="card-meta">
                                <div class="card-meta-item">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo round(($course['duration'] ?? 0) / 60); ?> giờ</span>
                                </div>
                                <div class="card-meta-item">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo htmlspecialchars($course['total_lectures'] ?? 0); ?> bài</span>
                                </div>
                            </div>

                            
                            <div class="card-rating">
                                <div class="stars">
                                    <?php 
                                        $rating = $course['rating'] ?: 5.0;
                                        $full_stars = floor($rating);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $full_stars) {
                                                echo '<i data-lucide="star" style="fill: var(--accent); stroke: var(--accent); width: 14px; height: 14px;"></i>';
                                            } else {
                                                echo '<i data-lucide="star" style="width: 14px; height: 14px;"></i>';
                                            }
                                        }
                                    ?>
                                </div>
                                <span><?php echo number_format($rating, 1); ?> (<?php echo htmlspecialchars($course['rating_count'] ?: 0); ?>)</span>
                            </div>

                            
                            <div class="card-footer">
                                <div class="card-price">
                                    <?php if ($has_discount): ?>
                                        <span class="price-old"><?php echo number_format($course['price'], 0, ',', '.'); ?>đ</span>
                                        <span class="price-new"><?php echo number_format($course['discount_price'], 0, ',', '.'); ?>đ</span>
                                    <?php else: ?>
                                        <span class="price-new"><?php echo number_format($course['price'], 0, ',', '.'); ?>đ</span>
                                    <?php endif; ?>
                                </div>
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: var(--radius-sm);">
                                    Xem chi tiết
                                </a>
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
