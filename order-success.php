<?php
$page_title = "Đặt hàng thành công";
require_once __DIR__ . '/includes/header.php';

$order_number_raw = $_GET['order_number'] ?? '';
$order_number = htmlspecialchars($order_number_raw);
$order = null;
$payment_labels = [
    'BANK_TRANSFER' => 'Chuyển khoản Ngân hàng (MB Bank)',
    'MOMO' => 'MoMo Sandbox',
    'PAYPAL' => 'PayPal Sandbox',
    'VNPAY' => 'VNPay Sandbox',
    'CREDIT_CARD' => 'Thẻ tín dụng / ghi nợ',
];

if ($order_number_raw !== '' && is_logged_in()) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$order_number_raw, $_SESSION['user_id']]);
    $order = $stmt->fetch();
}

$is_paid = $order && $order['status'] === 'COMPLETED';
$payment_method = $order['payment_method'] ?? 'BANK_TRANSFER';
$payment_label = $payment_labels[$payment_method] ?? $payment_method;
?>

<div class="container" style="min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 60px 0;">
    <div style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); padding: 48px; max-width: 600px; width: 100%; text-align: center;">
        
        
        <div style="width: 80px; height: 80px; background-color: #d1fae5; color: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: var(--shadow-sm);">
            <i data-lucide="check-circle" style="width: 48px; height: 48px; stroke-width: 2.5;"></i>
        </div>

        <h2 style="font-size: 30px; font-weight: 800; color: var(--text-main); margin-bottom: 12px;"><?php echo $is_paid ? 'Thanh toán thành công!' : 'Đặt hàng thành công!'; ?></h2>
        
        <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 32px; line-height: 1.6;">
            <?php if ($is_paid): ?>
                Cảm ơn bạn đã lựa chọn LearnHub. Giao dịch đã được ghi nhận thành công và khóa học đã được mở trong tài khoản của bạn.
            <?php else: ?>
                Cảm ơn bạn đã lựa chọn LearnHub. Đơn hàng của bạn đã được ghi nhận vào hệ thống và đang chờ được phê duyệt kích hoạt khóa học.
            <?php endif; ?>
        </p>

        
        <div style="background-color: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 20px; text-align: left; margin-bottom: 36px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 2;">
                <tr>
                    <td style="color: var(--text-muted); width: 140px;">Mã đơn hàng:</td>
                    <td><strong style="color: var(--primary);"><?php echo $order_number; ?></strong></td>
                </tr>
                <tr>
                    <td style="color: var(--text-muted);">Trạng thái:</td>
                    <td>
                        <span style="background-color: <?php echo $is_paid ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $is_paid ? '#047857' : '#d97706'; ?>; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                            <span style="width: 6px; height: 6px; background-color: <?php echo $is_paid ? '#047857' : '#d97706'; ?>; border-radius: 50%;"></span>
                            <?php echo $is_paid ? 'Đã thanh toán' : 'Chờ kích hoạt'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color: var(--text-muted);">Phương thức:</td>
                    <td><strong><?php echo htmlspecialchars($payment_label); ?></strong></td>
                </tr>
            </table>
            
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed var(--border); font-size: 13px; color: var(--text-muted); line-height: 1.5;">
                <i data-lucide="info" style="width: 16px; height: 16px; color: var(--primary); vertical-align: middle; margin-right: 4px; display: inline-block;"></i>
                <?php echo $is_paid ? 'Bạn có thể vào hồ sơ để bắt đầu học ngay.' : 'Vui lòng hoàn thành giao dịch theo phương thức đã chọn. Chúng tôi sẽ duyệt đơn hàng của bạn trong vòng tối đa 2 giờ làm việc.'; ?>
            </div>
        </div>

        
        <div style="display: flex; gap: 16px; flex-direction: column;">
            <a href="orders.php" class="btn btn-primary" style="height: 48px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i data-lucide="shopping-bag" style="width: 18px; height: 18px;"></i>
                Xem danh sách Đơn hàng của tôi
            </a>
            <a href="index.php" class="btn btn-secondary" style="height: 48px; display: flex; align-items: center; justify-content: center;">
                Quay lại Trang chủ
            </a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
