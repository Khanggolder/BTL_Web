<?php
$page_title = "Giải pháp cho doanh nghiệp";
require_once __DIR__ . '/includes/header.php';
?>

<section class="section" style="background: var(--bg-main);">
    <div class="container" style="max-width: 1080px;">
        <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 32px; align-items: center;">
            <div>
                <span style="color: var(--primary); font-weight: 800;">LearnHub Business</span>
                <h1 style="font-size: 42px; line-height: 1.15; margin: 12px 0 16px; color: var(--text-main);">Đào tạo đội ngũ công nghệ theo nhu cầu doanh nghiệp</h1>
                <p style="color: var(--text-muted); font-size: 17px; line-height: 1.8; margin-bottom: 24px;">Xây dựng lộ trình học tập riêng cho nhân sự, theo dõi tiến độ và chuẩn hóa kỹ năng lập trình cho từng phòng ban.</p>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="contact.php" class="btn btn-primary">Liên hệ tư vấn</a>
                    <a href="courses.php" class="btn btn-secondary">Xem khóa học</a>
                </div>
            </div>
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-md);">
                <h3 style="margin-bottom: 18px; color: var(--text-main);">Gói doanh nghiệp gồm</h3>
                <div style="display: grid; gap: 16px;">
                    <p><strong>Lộ trình riêng:</strong> Thiết kế theo vai trò Frontend, Backend, Fullstack hoặc Data.</p>
                    <p><strong>Báo cáo tiến độ:</strong> Theo dõi học viên, tỷ lệ hoàn thành và kết quả từng khóa.</p>
                    <p><strong>Hỗ trợ triển khai:</strong> Tư vấn chọn khóa, onboard nhân sự và hỗ trợ kỹ thuật.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
