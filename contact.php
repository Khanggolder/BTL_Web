<?php
$page_title = "Liên hệ - LearnHub";
require_once __DIR__ . '/includes/header.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
}
?>

<div class="contact-page">
    <div class="container contact-container">
        <section class="contact-hero">
            <span>Hỗ trợ LearnHub</span>
            <h1>Liên hệ với chúng tôi</h1>
            <p>Gửi câu hỏi, góp ý hoặc yêu cầu hỗ trợ. Đội ngũ LearnHub sẽ phản hồi trong thời gian sớm nhất.</p>
        </section>

        <section class="contact-layout">
            <aside class="contact-info-card">
                <div>
                    <h2>Thông tin liên hệ</h2>
                    <p>Chúng tôi luôn sẵn sàng hỗ trợ học viên và đối tác trong giờ làm việc.</p>
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
                        <div class="contact-icon contact-icon-purple"><i data-lucide="phone"></i></div>
                        <div>
                            <strong>Điện thoại hỗ trợ</strong>
                            <span>024 1234 5678</span>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon contact-icon-amber"><i data-lucide="mail"></i></div>
                        <div>
                            <strong>Email hỗ trợ</strong>
                            <span>support@learnhub.edu.vn</span>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon contact-icon-green"><i data-lucide="clock"></i></div>
                        <div>
                            <strong>Thời gian làm việc</strong>
                            <span>Thứ 2 - Thứ 6, 08:00 - 17:30</span>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="contact-form-card">
                <div class="contact-form-head">
                    <div>
                        <h2>Gửi thông điệp</h2>
                        <p>Điền thông tin bên dưới để LearnHub có thể hỗ trợ đúng nhu cầu của bạn.</p>
                    </div>
                    <i data-lucide="send"></i>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success contact-alert">
                        <i data-lucide="check-circle"></i>
                        Gửi lời nhắn thành công! Chúng tôi sẽ phản hồi lại bạn sớm nhất.
                    </div>
                <?php endif; ?>

                <form action="contact.php" method="POST" class="contact-form">
                    <div class="contact-form-grid">
                        <div class="form-group">
                            <label for="name">Họ và tên</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Nguyễn Văn A" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Địa chỉ Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Lời nhắn</label>
                        <textarea id="message" name="message" class="form-control" placeholder="Nhập nội dung bạn muốn đóng góp hoặc thắc mắc tại đây..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary contact-submit">
                        <i data-lucide="send"></i>
                        Gửi lời nhắn
                    </button>
                </form>
            </main>
        </section>

        <section class="contact-support-strip">
            <div>
                <i data-lucide="message-circle"></i>
                <strong>Hỗ trợ học tập</strong>
                <span>Giải đáp vấn đề tài khoản, khóa học và thanh toán.</span>
            </div>
            <div>
                <i data-lucide="briefcase-business"></i>
                <strong>Hợp tác doanh nghiệp</strong>
                <span>Tư vấn chương trình đào tạo cho đội nhóm.</span>
            </div>
            <div>
                <i data-lucide="shield-check"></i>
                <strong>Bảo mật thông tin</strong>
                <span>Thông tin liên hệ được xử lý cẩn thận.</span>
            </div>
        </section>
    </div>
</div>

<style>
    .contact-page {
        background: var(--bg-main);
        min-height: 80vh;
        padding: 56px 0 70px;
    }

    .contact-container {
        max-width: 1180px;
    }

    .contact-hero {
        max-width: 760px;
        margin-bottom: 34px;
    }

    .contact-hero span {
        color: var(--primary);
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .contact-hero h1 {
        color: var(--text-main);
        font-size: 42px;
        font-weight: 800;
        line-height: 1.15;
        margin: 10px 0 12px;
    }

    .contact-hero p {
        color: var(--text-muted);
        font-size: 16px;
        line-height: 1.7;
        margin: 0;
    }

    .contact-layout {
        display: grid;
        grid-template-columns: minmax(300px, 0.85fr) minmax(0, 1.15fr);
        gap: 28px;
        align-items: stretch;
    }

    .contact-info-card,
    .contact-form-card,
    .contact-support-strip {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
    }

    .contact-info-card,
    .contact-form-card {
        padding: 32px;
    }

    .contact-info-card h2,
    .contact-form-card h2 {
        color: var(--text-main);
        font-size: 24px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .contact-info-card p,
    .contact-form-head p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.7;
        margin: 0;
    }

    .contact-info-list {
        display: grid;
        gap: 18px;
        margin-top: 28px;
    }

    .contact-info-item {
        display: grid;
        grid-template-columns: 48px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--bg-main);
    }

    .contact-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: var(--primary-light);
        color: var(--primary);
    }

    .contact-icon svg {
        width: 22px;
        height: 22px;
    }

    .contact-icon-purple {
        background: #f5f3ff;
        color: var(--secondary);
    }

    .contact-icon-amber {
        background: #fffbeb;
        color: var(--accent);
    }

    .contact-icon-green {
        background: #d1fae5;
        color: var(--success);
    }

    .contact-info-item strong {
        display: block;
        color: var(--text-main);
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .contact-info-item span {
        display: block;
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.55;
    }

    .contact-form-head {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: flex-start;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 24px;
    }

    .contact-form-head > i {
        width: 44px;
        height: 44px;
        padding: 11px;
        border-radius: 12px;
        background: var(--primary-light);
        color: var(--primary);
        flex-shrink: 0;
    }

    .contact-alert {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 22px;
    }

    .contact-alert svg {
        width: 18px;
        height: 18px;
    }

    .contact-form {
        display: grid;
        gap: 18px;
    }

    .contact-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .contact-form .form-control {
        min-height: 46px;
    }

    .contact-form textarea.form-control {
        min-height: 160px;
        resize: vertical;
    }

    .contact-submit {
        height: 48px;
        border-radius: var(--radius-sm);
        justify-self: start;
        padding: 0 24px;
        font-size: 14px;
    }

    .contact-submit svg {
        width: 18px;
        height: 18px;
    }

    .contact-support-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0;
        margin-top: 28px;
        overflow: hidden;
    }

    .contact-support-strip div {
        padding: 22px;
        border-right: 1px solid var(--border);
    }

    .contact-support-strip div:last-child {
        border-right: none;
    }

    .contact-support-strip svg {
        width: 24px;
        height: 24px;
        color: var(--primary);
        margin-bottom: 10px;
    }

    .contact-support-strip strong {
        display: block;
        color: var(--text-main);
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .contact-support-strip span {
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.55;
    }

    @media (max-width: 920px) {
        .contact-layout,
        .contact-support-strip {
            grid-template-columns: 1fr;
        }

        .contact-support-strip div {
            border-right: none;
            border-bottom: 1px solid var(--border);
        }

        .contact-support-strip div:last-child {
            border-bottom: none;
        }
    }

    @media (max-width: 640px) {
        .contact-page {
            padding: 36px 0 50px;
        }

        .contact-hero h1 {
            font-size: 32px;
        }

        .contact-info-card,
        .contact-form-card {
            padding: 22px;
        }

        .contact-form-grid {
            grid-template-columns: 1fr;
        }

        .contact-submit {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
