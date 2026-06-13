<?php
$page_title = "Câu hỏi thường gặp";
require_once __DIR__ . '/includes/header.php';
?>

<section class="section" style="background: var(--bg-main);">
    <div class="container" style="max-width: 900px;">
        <h1 style="font-size: 36px; color: var(--text-main); margin-bottom: 16px;">Câu hỏi thường gặp</h1>
        <div style="display: grid; gap: 14px;">
            <?php
            $faqs = [
                ['Sau khi thanh toán, khi nào tôi học được?', 'Với thanh toán thành công, khóa học sẽ được mở tự động trong hồ sơ của bạn.'],
                ['Tôi có thể xem lại đơn hàng ở đâu?', 'Bạn có thể vào menu tài khoản, chọn “Đơn hàng của tôi” để theo dõi trạng thái.'],
                ['Có khóa học cho người mới bắt đầu không?', 'Có. Bạn có thể lọc khóa học theo cấp độ “Cơ bản” trong trang khóa học.'],
                ['Doanh nghiệp muốn mua nhiều tài khoản thì làm thế nào?', 'Vui lòng vào trang “Dành cho doanh nghiệp” hoặc liên hệ hỗ trợ để được tư vấn.'],
            ];
            foreach ($faqs as $faq):
            ?>
                <details style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px 20px;">
                    <summary style="cursor: pointer; font-weight: 800; color: var(--text-main);"><?php echo htmlspecialchars($faq[0]); ?></summary>
                    <p style="color: var(--text-muted); line-height: 1.7; margin-top: 12px;"><?php echo htmlspecialchars($faq[1]); ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
