<?php
$page_title = "Liên hệ - LearnHub";
require_once __DIR__ . '/includes/header.php';
?>

<div class="contact-page">
    <div class="container contact-container">
        <section class="contact-hero">
            <span>Hỗ trợ LearnHub</span>
            <h1>Liên hệ với chúng tôi</h1>
            <p>Đội ngũ LearnHub hỗ trợ học viên, giảng viên và đối tác trong các vấn đề về tài khoản, khóa học, thanh toán và hợp tác đào tạo.</p>
        </section>

        <section class="contact-layout">
            <aside class="contact-info-card">
                <div class="contact-info-intro">
                    <span>Thông tin liên hệ</span>
                    <h2>LearnHub sẵn sàng hỗ trợ bạn</h2>
                    <p>Liên hệ trong giờ làm việc để được phản hồi nhanh và đúng bộ phận phụ trách.</p>
                </div>

                <div class="contact-info-list">
                    <div class="contact-info-item">
                        <div class="contact-icon"><i data-lucide="map-pin"></i></div>
                        <div>
                            <strong>Địa chỉ văn phòng</strong>
                            <span>Số 1 Đại Cồ Việt, Bách Khoa, Hai Bà Trưng, Hà Nội</span>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon"><i data-lucide="phone"></i></div>
                        <div>
                            <strong>Điện thoại hỗ trợ</strong>
                            <span>024 1234 5678</span>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon"><i data-lucide="mail"></i></div>
                        <div>
                            <strong>Email hỗ trợ</strong>
                            <span>support@learnhub.edu.vn</span>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon"><i data-lucide="clock"></i></div>
                        <div>
                            <strong>Thời gian làm việc</strong>
                            <span>Thứ 2 - Thứ 6, 08:00 - 17:30</span>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="contact-process-card">
                <div class="contact-process-head">
                    <span>Quy trình hỗ trợ</span>
                    <h2>Cách LearnHub xử lý yêu cầu</h2>
                    <p>Yêu cầu được tiếp nhận, phân loại và chuyển đến đúng nhóm phụ trách để tránh xử lý chậm hoặc sai nhu cầu.</p>
                </div>

                <div class="contact-process-list">
                    <div class="contact-process-item">
                        <span class="contact-step">01</span>
                        <div>
                            <strong>Tiếp nhận thông tin</strong>
                            <p>Ghi nhận vấn đề về tài khoản, khóa học, thanh toán hoặc hợp tác đào tạo học viên.</p>
                        </div>
                    </div>

                    <div class="contact-process-item">
                        <span class="contact-step">02</span>
                        <div>
                            <strong>Chuyển đúng bộ phận</strong>
                            <p>Phân loại yêu cầu cho nhóm kỹ thuật, học vụ, thanh toán hoặc tư vấn doanh nghiệp.</p>
                        </div>
                    </div>

                    <div class="contact-process-item">
                        <span class="contact-step">03</span>
                        <div>
                            <strong>Phản hồi kết quả</strong>
                            <p>LearnHub phản hồi trong giờ làm việc qua email hoặc số điện thoại bạn cung cấp.</p>
                        </div>
                    </div>
                </div>
            </main>
        </section>

        <section class="contact-support-grid">
            <div class="contact-support-card">
                <i data-lucide="message-circle"></i>
                <strong>Hỗ trợ học tập</strong>
                <span>Giải đáp vấn đề tài khoản, khóa học, bài học, quiz và tiến độ học.</span>
            </div>
            <div class="contact-support-card">
                <i data-lucide="credit-card"></i>
                <strong>Thanh toán</strong>
                <span>Hỗ trợ giỏ hàng, đơn hàng, xác nhận thanh toán và quyền truy cập khóa học.</span>
            </div>
            <div class="contact-support-card">
                <i data-lucide="briefcase-business"></i>
                <strong>Hợp tác doanh nghiệp</strong>
                <span>Tư vấn chương trình đào tạo phù hợp cho đội nhóm và tổ chức.</span>
            </div>
        </section>
    </div>
</div>

<style>
    .contact-page {
        background: linear-gradient(180deg, #f8fafc 0%, var(--bg-main) 48%, #eef2ff 100%);
        min-height: 80vh;
        padding: 58px 0 72px;
    }

    .contact-container {
        width: min(92%, 1180px);
        max-width: none;
    }

    .contact-hero {
        width: min(100%, 780px);
        margin: 0 auto 36px;
        text-align: center;
    }

    .contact-hero span,
    .contact-info-intro span,
    .contact-process-head span {
        display: inline-flex;
        width: fit-content;
        color: var(--primary);
        background: rgba(37, 99, 235, 0.1);
        border-radius: 999px;
        padding: 7px 13px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .contact-hero h1 {
        color: var(--text-main);
        font-size: 42px;
        font-weight: 800;
        line-height: 1.15;
        margin: 14px 0 12px;
    }

    .contact-hero p {
        color: var(--text-muted);
        font-size: 16px;
        line-height: 1.7;
        margin: 0 auto;
        width: min(100%, 680px);
    }

    .contact-layout {
        display: grid;
        grid-template-columns: 40% 57%;
        gap: 3%;
        align-items: stretch;
    }

    .contact-info-card,
    .contact-process-card,
    .contact-support-card {
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
    }

    .contact-info-card {
        display: flex;
        flex-direction: column;
        gap: 28px;
        padding: 34px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        color: var(--text-main);
    }

    .contact-info-intro span {
        color: var(--primary);
        background: rgba(37, 99, 235, 0.1);
    }

    .contact-info-intro h2,
    .contact-process-head h2 {
        font-size: 26px;
        font-weight: 800;
        line-height: 1.25;
        margin: 14px 0 10px;
    }

    .contact-info-intro h2 {
        color: var(--text-main);
    }

    .contact-info-intro p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.7;
        margin: 0;
    }

    .contact-info-list {
        display: grid;
        gap: 14px;
    }

    .contact-info-item {
        display: grid;
        grid-template-columns: 46px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.07), rgba(124, 58, 237, 0.05));
    }

    .contact-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: var(--primary-light);
        color: var(--primary);
    }

    .contact-icon svg {
        width: 21px;
        height: 21px;
    }

    .contact-info-item strong,
    .contact-process-item strong,
    .contact-support-card strong {
        display: block;
        font-size: 15px;
        font-weight: 800;
    }

    .contact-info-item strong {
        color: var(--text-main);
        margin-bottom: 4px;
    }

    .contact-info-item span {
        display: block;
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    .contact-process-card {
        display: grid;
        align-content: start;
        gap: 28px;
        padding: 34px;
        background: var(--bg-card);
        border: 1px solid var(--border);
    }

    .contact-process-head h2 {
        color: var(--text-main);
    }

    .contact-process-head p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.7;
        margin: 0;
        width: min(100%, 620px);
    }

    .contact-process-list {
        display: grid;
        gap: 16px;
    }

    .contact-process-item {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
        padding: 20px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.07), rgba(124, 58, 237, 0.06));
    }

    .contact-step {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: #111827;
        color: #ffffff;
        font-size: 15px;
        font-weight: 900;
    }

    .contact-process-item strong {
        color: var(--text-main);
        margin-bottom: 6px;
    }

    .contact-process-item p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }

    .contact-support-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2%;
        margin-top: 28px;
    }

    .contact-support-card {
        min-width: 0;
        padding: 24px;
        background: var(--bg-card);
        border: 1px solid var(--border);
    }

    .contact-support-card svg {
        width: 26px;
        height: 26px;
        color: var(--primary);
        margin-bottom: 14px;
    }

    .contact-support-card strong {
        color: var(--text-main);
        margin-bottom: 6px;
    }

    .contact-support-card span {
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.6;
    }

    @media (max-width: 920px) {
        .contact-layout,
        .contact-support-grid {
            grid-template-columns: 1fr;
            gap: 22px;
        }
    }

    @media (max-width: 640px) {
        .contact-page {
            padding: 36px 0 52px;
        }

        .contact-container {
            width: 92%;
        }

        .contact-hero {
            margin-bottom: 26px;
            text-align: left;
        }

        .contact-hero h1 {
            font-size: 32px;
        }

        .contact-info-card,
        .contact-process-card,
        .contact-support-card {
            padding: 7%;
        }

        .contact-info-intro h2,
        .contact-process-head h2 {
            font-size: 22px;
        }

        .contact-info-item,
        .contact-process-item {
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 12px;
            padding: 14px;
        }

        .contact-icon,
        .contact-step {
            width: 44px;
            height: 44px;
            border-radius: 12px;
        }

        .contact-step {
            font-size: 13px;
        }
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
