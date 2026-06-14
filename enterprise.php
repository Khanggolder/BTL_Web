<?php
$page_title = "Giải pháp cho doanh nghiệp";
require_once __DIR__ . '/includes/header.php';
?>

<section class="section enterprise-section" style="background: var(--bg-main);">
    <div class="container" style="max-width: 1080px;">
        <div class="enterprise-grid" style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 32px; align-items: center;">
            <div class="enterprise-content">
                <span class="enterprise-kicker" style="color: var(--primary); font-weight: 800;">LearnHub Business</span>
                <h1 class="enterprise-title" style="font-size: 42px; line-height: 1.15; margin: 12px 0 16px; color: var(--text-main);">Đào tạo đội ngũ công nghệ theo nhu cầu doanh nghiệp</h1>
                <p class="enterprise-desc" style="color: var(--text-muted); font-size: 17px; line-height: 1.8; margin-bottom: 24px;">Xây dựng lộ trình học tập riêng cho nhân sự, theo dõi tiến độ và chuẩn hóa kỹ năng lập trình cho từng phòng ban.</p>
                <div class="enterprise-actions" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="contact.php" class="btn btn-primary">Liên hệ tư vấn</a>
                    <a href="courses.php" class="btn btn-secondary">Xem khóa học</a>
                </div>
            </div>

            <div class="enterprise-card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-md);">
                <h3 class="enterprise-card-title" style="margin-bottom: 18px; color: var(--text-main);">Gói doanh nghiệp gồm</h3>
                <div class="enterprise-list" style="display: grid; gap: 16px;">
                    <p><strong>Lộ trình riêng:</strong> Thiết kế theo vai trò Frontend, Backend, Fullstack hoặc Data.</p>
                    <p><strong>Báo cáo tiến độ:</strong> Theo dõi học viên, tỷ lệ hoàn thành và kết quả từng khóa.</p>
                    <p><strong>Hỗ trợ triển khai:</strong> Tư vấn chọn khóa, onboard nhân sự và hỗ trợ kỹ thuật.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    @media (max-width: 576px) {
        .enterprise-section {
            padding: 36px 0 !important;
        }

        .enterprise-section .container {
            padding: 0 16px !important;
        }

        .enterprise-grid {
            grid-template-columns: 1fr !important;
            gap: 20px !important;
            align-items: start !important;
        }

        .enterprise-kicker {
            display: inline-flex !important;
            align-items: center !important;
            min-height: 30px !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
            background: var(--primary-light) !important;
            font-size: 12px !important;
        }

        .enterprise-title {
            font-size: 28px !important;
            line-height: 1.25 !important;
            margin: 14px 0 12px !important;
        }

        .enterprise-desc {
            font-size: 14px !important;
            line-height: 1.65 !important;
            margin-bottom: 18px !important;
        }

        .enterprise-actions {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }

        .enterprise-actions .btn {
            width: 100% !important;
            justify-content: center !important;
            min-height: 44px !important;
            padding: 11px 14px !important;
            font-size: 14px !important;
        }

        .enterprise-card {
            padding: 18px !important;
            border-radius: 12px !important;
            box-shadow: var(--shadow-sm) !important;
        }

        .enterprise-card-title {
            font-size: 18px !important;
            margin-bottom: 14px !important;
        }

        .enterprise-list {
            gap: 12px !important;
        }

        .enterprise-list p {
            margin: 0 !important;
            font-size: 13px !important;
            line-height: 1.6 !important;
            padding: 12px !important;
            border: 1px solid var(--border) !important;
            border-radius: 10px !important;
            background: var(--bg-main) !important;
        }

        .enterprise-list strong {
            display: block !important;
            margin-bottom: 4px !important;
            color: var(--text-main) !important;
        }
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
