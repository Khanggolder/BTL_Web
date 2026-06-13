<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

require_login();

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? 0);
$supported_methods = ['PAYPAL', 'VNPAY', 'CREDIT_CARD'];
$method_labels = [
    'PAYPAL' => 'PayPal Sandbox',
    'VNPAY' => 'VNPay Sandbox',
    'CREDIT_CARD' => 'Thẻ tín dụng / ghi nợ',
];
$method_icons = [
    'PAYPAL' => 'wallet-cards',
    'VNPAY' => 'landmark',
    'CREDIT_CARD' => 'credit-card',
];

function complete_demo_order($pdo, $order_id, $user_id, $transaction_id) {
    $stmt = $pdo->prepare("
        UPDATE orders
        SET status = 'COMPLETED',
            payment_transaction_id = ?,
            paid_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ? AND status = 'PENDING'
    ");
    $stmt->execute([$transaction_id, $order_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $course_ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($course_ids as $course_id) {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);

        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, active) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $course_id]);

            $stmt = $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = ?");
            $stmt->execute([$course_id]);
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();
    if ($cart) {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cart['id']]);
    }

    return true;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'PENDING'");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order || !in_array($order['payment_method'], $supported_methods, true)) {
    header("Location: cart.php");
    exit();
}

$error = '';
$method = $order['payment_method'];
$method_label = $method_labels[$method];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_demo_payment'])) {
    try {
        $pdo->beginTransaction();
        $transaction_id = $method . '-SANDBOX-' . date('ymdHis') . '-' . bin2hex(random_bytes(3));

        if (!complete_demo_order($pdo, $order_id, $user_id, $transaction_id)) {
            throw new Exception('Đơn hàng không còn ở trạng thái chờ thanh toán.');
        }

        $pdo->commit();
        header("Location: order-success.php?order_number=" . urlencode($order['order_number']) . "&paid=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$page_title = "Thanh toán " . $method_label;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 60px 0;">
    <div style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); padding: 42px; max-width: 700px; width: 100%;">
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 26px;">
            <div style="width: 58px; height: 58px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="<?php echo htmlspecialchars($method_icons[$method]); ?>" style="width: 30px; height: 30px;"></i>
            </div>
            <div>
                <h1 style="font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0;"><?php echo htmlspecialchars($method_label); ?></h1>
                <p style="color: var(--text-muted); margin: 4px 0 0;">Môi trường thanh toán demo cho LearnHub.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; gap: 16px; margin-bottom: 10px;">
                <span style="color: var(--text-muted);">Mã đơn hàng</span>
                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; gap: 16px;">
                <span style="color: var(--text-muted);">Số tiền</span>
                <strong style="color: var(--primary); font-size: 20px;"><?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ</strong>
            </div>
        </div>

        <div style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: var(--radius-sm); padding: 14px 16px; font-size: 14px; line-height: 1.6; margin-bottom: 22px;">
            Đây là cổng thanh toán sandbox/demo để hoàn thiện luồng kiểm thử. Khi có tài khoản merchant thật, phần này có thể được thay bằng API chính thức của <?php echo htmlspecialchars($method_label); ?>.
        </div>

        <form method="POST" action="gateway_payment.php?order_id=<?php echo $order_id; ?>" style="display: flex; flex-direction: column; gap: 12px;">
            <input type="hidden" name="complete_demo_payment" value="1">
            <button type="submit" class="btn btn-primary" style="height: 48px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
                Hoàn tất thanh toán demo
            </button>
            <a href="checkout.php" class="btn btn-outline" style="height: 46px; display: flex; align-items: center; justify-content: center;">Quay lại chọn phương thức khác</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
