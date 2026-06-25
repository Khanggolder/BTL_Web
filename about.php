<?php
$page_title = "Về chúng tôi - LearnHub";
require_once __DIR__ . '/includes/header.php';
?>

<main class="about-page">
    <section class="about-hero">
        <div class="container about-hero-grid">
            <div class="about-hero-content">
                <span>Về LearnHub</span>
                <h1>Nền tảng học lập trình thực chiến cho người Việt</h1>
                <p>
                    LearnHub được xây dựng với mục tiêu giúp học viên tiếp cận kiến thức lập trình một cách rõ ràng,
                    định hướng và gắn liền với các bài tập thực tế.
                </p>
                <div class="about-hero-actions">
                    <a href="courses.php" class="btn btn-primary">Khám phá khóa học</a>
                    <a href="contact.php" class="btn btn-outline">Liên hệ tư vấn</a>
                </div>
            </div>

            <div class="about-hero-panel">
                <div class="about-panel-top">
                    <i data-lucide="graduation-cap"></i>
                    <strong>LearnHub</strong>
                </div>
                <div class="about-panel-body">
                    <div>
                        <span>500+</span>
                        <p>Bài học thực hành</p>
                    </div>
                    <div>
                        <span>150+</span>
                        <p>Giảng viên & mentor</p>
                    </div>
                    <div>
                        <span>70K+</span>
                        <p>Học viên tin tưởng</p>
                    </div>
                    <div>
                        <span>98%</span>
                        <p>Mức độ hài lòng</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section about-mission-section">
        <div class="container about-mission-grid">
            <div>
                <span class="about-eyebrow">Sứ mệnh</span>
                <h2>Giúp học viên học đúng trọng tâm và làm được sản phẩm thật</h2>
            </div>
            <p>
                Chúng tôi tập trung vào trải nghiệm học có cấu trúc: kiến thức nền tảng, ví dụ minh họa,
                bài tập thực hành và dự án cuối khóa. Mỗi khóa học đều hướng tới việc giúp học viên hiểu bản chất,
                tự tin xây dựng sản phẩm và tiếp tục phát triển sau khóa học.
            </p>
        </div>
    </section>

    <section class="about-section about-values-section">
        <div class="container">
            <div class="about-section-head">
                <span class="about-eyebrow">Giá trị cốt lõi</span>
                <h2>Những điều LearnHub theo đuổi</h2>
            </div>

            <div class="about-values-grid">
                <article class="about-value-card">
                    <div class="about-value-icon"><i data-lucide="check-square"></i></div>
                    <h3>Thực chiến</h3>
                    <p>Giáo trình ưu tiên bài tập, dự án và tình huống gần với công việc thực tế.</p>
                </article>

                <article class="about-value-card">
                    <div class="about-value-icon about-value-purple"><i data-lucide="users"></i></div>
                    <h3>Đồng hành</h3>
                    <p>Học viên có định hướng rõ ràng, được hỗ trợ khi gặp khó khăn trong quá trình học.</p>
                </article>

                <article class="about-value-card">
                    <div class="about-value-icon about-value-amber"><i data-lucide="award"></i></div>
                    <h3>Chất lượng</h3>
                    <p>Nội dung được tổ chức mạch lạc, cập nhật và phù hợp với nhu cầu học lập trình hiện nay.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="about-section">
        <div class="container about-method-card">
            <div>
                <span class="about-eyebrow">Phương pháp học</span>
                <h2>Học theo lộ trình, luyện qua sản phẩm</h2>
                <p>
                    Thay vì chỉ xem video rời rạc, học viên được dẫn qua từng bước: hiểu lý thuyết,
                    thực hành theo ví dụ, hoàn thiện bài tập và áp dụng vào dự án cá nhân.
                </p>
            </div>

            <div class="about-steps">
                <div><strong>01</strong><span>Nắm nền tảng</span></div>
                <div><strong>02</strong><span>Thực hành bài học</span></div>
                <div><strong>03</strong><span>Xây dựng dự án</span></div>
                <div><strong>04</strong><span>Hoàn thiện kỹ năng</span></div>
            </div>
        </div>
    </section>
</main>

<style>
    .about-page {
        background: var(--bg-main);
    }

    .about-hero {
        background: radial-gradient(circle at 18% 18%, rgba(96, 165, 250, 0.42), transparent 30%), radial-gradient(circle at 78% 8%, rgba(168, 85, 247, 0.28), transparent 28%), radial-gradient(circle at 55% 85%, rgba(34, 211, 238, 0.16), transparent 34%), linear-gradient(135deg, #071329 0%, #0b1d3a 46%, #172554 100%);
        color: white;
        padding: 74px 0 64px;
        position: relative;
        overflow: hidden;
    }
    .about-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-image: radial-gradient(circle, rgba(255, 255, 255, 0.42) 1px, transparent 1.5px), radial-gradient(circle, rgba(147, 197, 253, 0.34) 1px, transparent 1.5px);
        background-size: 90px 90px, 140px 140px;
        background-position: 12px 20px, 58px 72px;
        opacity: 0.3;
    }

    .about-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
        gap: 56px;
        align-items: center;
        position: relative;
        z-index: 1;
    }

    .about-hero-content > span,
    .about-eyebrow {
        display: inline-flex;
        color: var(--primary);
        font-size: 20px;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .about-hero-content > span {
        color: #ffffffdc;
    }

    .about-hero-content h1 {
        font-size: 46px;
        font-weight: 900;
        line-height: 1.12;
        margin-bottom: 18px;
        color: white;
    }

    .about-hero-content p {
        max-width: 660px;
        color: rgba(255, 255, 255, 0.86);
        font-size: 17px;
        line-height: 1.75;
        margin-bottom: 26px;
    }

    .about-hero-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .about-hero-actions .btn-outline {
        color: white;
        border-color: rgba(255, 255, 255, 0.7);
        background: transparent;
    }

    .about-hero-panel {
        background: rgba(15, 23, 42, 0.68);
        border: 1px solid rgba(148, 163, 184, 0.28);
        border-radius: 16px;
        padding: 28px;
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(8px);
    }

    .about-panel-top {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        font-size: 20px;
        font-weight: 900;
        padding-bottom: 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.22);
        margin-bottom: 18px;
    }

    .about-panel-top svg {
        width: 34px;
        height: 34px;
        color: #fbbf24;
    }

    .about-panel-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .about-panel-body div {
        background: rgba(2, 6, 23, 0.58);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 10px;
        padding: 18px;
    }

    .about-panel-body span {
        display: block;
        color: white;
        font-size: 28px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .about-panel-body p {
        color: rgba(255, 255, 255, 0.76);
        font-size: 13px;
        font-weight: 700;
        margin: 0;
    }

    .about-section {
        padding: 64px 0;
    }

    .about-mission-section {
        background: var(--bg-card);
    }

    .about-mission-grid {
        display: grid;
        grid-template-columns: minmax(280px, 0.92fr) minmax(0, 1.08fr);
        gap: 40px;
        align-items: stretch;
    }

    .about-mission-grid > div,
    .about-mission-grid > p {
        background: var(--bg-main);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 34px;
    }

    .about-mission-grid > div {
        border-left: 4px solid var(--primary);
    }

    .about-mission-grid > p {
        display: flex;
        align-items: center;
    }

    .about-mission-grid h2,
    .about-section-head h2,
    .about-method-card h2 {
        color: var(--text-main);
        font-size: 34px;
        font-weight: 900;
        line-height: 1.25;
        margin: 0;
    }

    .about-mission-grid p,
    .about-method-card p {
        color: var(--text-muted);
        font-size: 16px;
        line-height: 1.8;
        margin: 0;
    }

    .about-values-section {
        padding-top: 64px;
        background: linear-gradient(180deg, #f8fafc 0%, #eef6ff 100%);
    }

    .about-section-head {
        text-align: center;
        max-width: 720px;
        margin: 0 auto 34px;
    }

    .about-values-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
    }

    .about-value-card,
    .about-method-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
    }

    .about-value-card {
        padding: 30px;
        min-height: 230px;
    }

    .about-value-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: var(--primary-light);
        color: var(--primary);
        margin-bottom: 20px;
    }

    .about-value-icon svg {
        width: 28px;
        height: 28px;
    }

    .about-value-purple {
        background: #ecfdf5;
        color: var(--success);
    }

    .about-value-amber {
        background: #fffbeb;
        color: var(--accent);
    }

    .about-value-card h3 {
        color: var(--text-main);
        font-size: 20px;
        font-weight: 900;
        margin-bottom: 10px;
    }

    .about-value-card p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.7;
        margin: 0;
    }

    .about-method-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
        gap: 34px;
        padding: 34px;
        align-items: center;
        border-top: 4px solid var(--secondary);
    }

    .about-method-card h2 {
        margin-bottom: 14px;
    }

    .about-steps {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .about-steps div {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 18px;
    }

    .about-steps div:nth-child(2) strong {
        color: var(--success);
    }

    .about-steps div:nth-child(3) strong {
        color: var(--accent);
    }

    .about-steps div:nth-child(4) strong {
        color: var(--secondary);
    }

    .about-steps strong {
        display: block;
        color: var(--primary);
        font-size: 24px;
        font-weight: 900;
        margin-bottom: 6px;
    }

    .about-steps span {
        color: var(--text-main);
        font-size: 14px;
        font-weight: 800;
    }

    @media (max-width: 920px) {
        .about-hero-grid,
        .about-mission-grid,
        .about-method-card,
        .about-values-grid {
            grid-template-columns: 1fr;
        }

        .about-hero-content h1 {
            font-size: 38px;
        }

        .about-mission-grid > div,
        .about-mission-grid > p {
            padding: 28px;
        }
    }

    @media (max-width: 640px) {
        .about-hero {
            padding: 50px 0;
        }

        .about-hero-content h1,
        .about-mission-grid h2,
        .about-section-head h2,
        .about-method-card h2 {
            font-size: 30px;
        }

        .about-panel-body,
        .about-steps {
            grid-template-columns: 1fr;
        }

        .about-section {
            padding: 46px 0;
        }
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
