<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

require_login();

$user_id = $_SESSION['user_id'];
$momo_order_id = $_GET['orderId'] ?? '';
$display_order_number = $momo_order_id;
$result_code = intval($_GET['resultCode'] ?? -1);
$message_momo = $_GET['message'] ?? 'Thất bại';
$momo_access_key = env_value('MOMO_ACCESS_KEY', '');
$momo_secret_key = env_value('MOMO_SECRET_KEY', '');

function verify_momo_signature($params, $access_key, $secret_key) {
    $signature = $params['signature'] ?? '';
    if ($signature === '') {
        return false;
    }

    $full_fields = [
        'accessKey' => $access_key,
        'amount' => $params['amount'] ?? '',
        'extraData' => $params['extraData'] ?? '',
        'message' => $params['message'] ?? '',
        'orderId' => $params['orderId'] ?? '',
        'orderInfo' => $params['orderInfo'] ?? '',
        'orderType' => $params['orderType'] ?? '',
        'partnerCode' => $params['partnerCode'] ?? '',
        'payType' => $params['payType'] ?? '',
        'requestId' => $params['requestId'] ?? '',
        'responseTime' => $params['responseTime'] ?? '',
        'resultCode' => $params['resultCode'] ?? '',
        'transId' => $params['transId'] ?? '',
    ];

    $simple_fields = [
        'accessKey' => $access_key,
        'amount' => $params['amount'] ?? '',
        'extraData' => $params['extraData'] ?? '',
        'message' => $params['message'] ?? '',
        'orderId' => $params['orderId'] ?? '',
        'orderInfo' => $params['orderInfo'] ?? '',
        'partnerCode' => $params['partnerCode'] ?? '',
        'requestId' => $params['requestId'] ?? '',
        'responseTime' => $params['responseTime'] ?? '',
        'resultCode' => $params['resultCode'] ?? '',
    ];

    foreach ([$full_fields, $simple_fields] as $fields) {
        $raw = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        $raw = urldecode($raw);
        $calculated = hash_hmac('sha256', $raw, $secret_key);
        if (hash_equals($calculated, $signature)) {
            return true;
        }
    }

    return false;
}

$success = false;
$error_title = '';
$error_desc = '';

if ($result_code === 0 && !empty($momo_order_id)) {
    try {
        if (!verify_momo_signature($_GET, $momo_access_key, $momo_secret_key)) {
            throw new Exception('Chữ ký xác thực từ MoMo không hợp lệ.');
        }

        $pdo->beginTransaction();

        
        $stmt = $pdo->prepare("
            SELECT *
            FROM orders
            WHERE (momo_order_id = ? OR order_number = ?)
              AND user_id = ?
              AND status = 'PENDING'
        ");
        $stmt->execute([$momo_order_id, $momo_order_id, $user_id]);
        $order = $stmt->fetch();

        if ($order) {
            $order_id = $order['id'];
            $display_order_number = $order['order_number'];
            $callback_amount = (int) ($_GET['amount'] ?? 0);

            if ($callback_amount !== (int) $order['total_amount']) {
                throw new Exception('Số tiền MoMo trả về không khớp với đơn hàng.');
            }

            
            $stmt = $pdo->prepare("UPDATE orders SET status = 'COMPLETED', momo_transaction_id = ?, payment_transaction_id = ?, paid_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$_GET['transId'] ?? null, $_GET['transId'] ?? $momo_order_id, $order_id]);

            
            $stmt = $pdo->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($order_items as $course_id) {
                
                $stmt_check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
                $stmt_check->execute([$user_id, $course_id]);
                
                if (!$stmt_check->fetch()) {
                    
                    $stmt_enroll = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, active) VALUES (?, ?, 1)");
                    $stmt_enroll->execute([$user_id, $course_id]);

                    
                    $stmt_inc = $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = ?");
                    $stmt_inc->execute([$course_id]);
                }
            }

            
            $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $cart = $stmt->fetch();
            if ($cart) {
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                $stmt->execute([$cart['id']]);
            }

            $pdo->commit();
            $success = true;
        } else {
            
            $stmt_check_completed = $pdo->prepare("
                SELECT order_number
                FROM orders
                WHERE (momo_order_id = ? OR order_number = ?)
                  AND user_id = ?
                  AND status = 'COMPLETED'
            ");
            $stmt_check_completed->execute([$momo_order_id, $momo_order_id, $user_id]);
            $completed_order_number = $stmt_check_completed->fetchColumn();
            if ($completed_order_number) {
                $display_order_number = $completed_order_number;
                $success = true; 
            } else {
                $pdo->rollBack();
                $error_title = 'Không tìm thấy hóa đơn';
                $error_desc = 'Mã đơn hàng không khớp hoặc không thuộc sở hữu của bạn.';
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_title = 'Không thể xác thực thanh toán';
        $error_desc = 'Giao dịch chưa được ghi nhận vì hệ thống không xác thực được dữ liệu từ MoMo: ' . $e->getMessage();
    }
} else {
    $error_title = 'Thanh toán bị hủy hoặc thất bại';
    $error_desc = 'Cổng MoMo báo lỗi hoặc bạn đã hủy giao dịch: ' . htmlspecialchars($message_momo);
}

$page_title = $success ? "Thanh toán thành công" : "Thanh toán thất bại";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 60px 0;">
    <div style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); padding: 48px; max-width: 600px; width: 100%; text-align: center;">
        
        <?php if ($success): ?>
            
            <div style="width: 80px; height: 80px; background-color: #d1fae5; color: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: var(--shadow-sm);">
                <i data-lucide="check-circle" style="width: 48px; height: 48px; stroke-width: 2.5;"></i>
            </div>

            <h2 style="font-size: 30px; font-weight: 800; color: var(--text-main); margin-bottom: 12px;">Đã thanh toán qua MoMo!</h2>
            
            <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 32px; line-height: 1.6;">
                Tuyệt vời! Giao dịch của bạn đã được cổng thanh toán MoMo xác thực thành công. Các khóa học bạn đã mua giờ đây đã có sẵn để bắt đầu học tập ngay lập tức!
            </p>

            <div style="background-color: var(--primary-light); padding: 20px; border-radius: var(--radius-sm); border: 1px solid rgba(37, 99, 235, 0.15); margin-bottom: 32px; text-align: left;">
                <h4 style="font-weight: 800; color: var(--primary); font-size: 15px; margin-bottom: 8px;">Mã hóa đơn: <?php echo htmlspecialchars($display_order_number); ?></h4>
                <p style="font-size: 13px; color: var(--text-muted); margin: 0; display: flex; align-items: center; gap: 4px;">
                    <i data-lucide="unlock" style="width: 14px; height: 14px; color: var(--success);"></i>
                    Quyền học tập đã tự động mở khóa vĩnh viễn.
                </p>
            </div>

            <div style="display: flex; gap: 16px; flex-direction: column;">
                <a href="profile.php" class="btn btn-primary" style="height: 48px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i data-lucide="play-circle" style="width: 18px; height: 18px;"></i>
                    Vào học ngay bây giờ
                </a>
                <a href="index.php" class="btn btn-secondary" style="height: 48px; display: flex; align-items: center; justify-content: center;">
                    Về trang chủ
                </a>
            </div>

        <?php else: ?>
            
            <div style="width: 80px; height: 80px; background-color: #fee2e2; color: var(--danger); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <i data-lucide="alert-triangle" style="width: 48px; height: 48px; stroke-width: 2.5;"></i>
            </div>

            <h2 style="font-size: 26px; font-weight: 800; color: var(--text-main); margin-bottom: 12px;"><?php echo htmlspecialchars($error_title); ?></h2>
            
            <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 32px; line-height: 1.6;">
                <?php echo htmlspecialchars($error_desc); ?>
            </p>

            <div style="display: flex; gap: 16px; flex-direction: column;">
                <a href="cart.php" class="btn btn-primary" style="height: 48px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i data-lucide="rotate-ccw" style="width: 18px; height: 18px;"></i>
                    Quay lại Giỏ hàng thử lại
                </a>
                <a href="index.php" class="btn btn-secondary" style="height: 48px; display: flex; align-items: center; justify-content: center;">
                    Về trang chủ
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
