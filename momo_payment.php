<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

require_login();

$order_id = intval($_GET['order_id'] ?? 0);

function momo_post_json($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($payload))
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($result === false || $result === '') {
        throw new Exception($error ?: 'Không nhận được phản hồi từ MoMo.');
    }

    $json = json_decode($result, true);
    if (!is_array($json)) {
        throw new Exception('Phản hồi từ MoMo không đúng định dạng JSON.');
    }

    return $json;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'PENDING'");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: cart.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_bank'])) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE orders SET payment_method = 'BANK_TRANSFER', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);

        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cart = $stmt->fetch();
        if ($cart) {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cart['id']]);
        }

        $pdo->commit();

        header("Location: order-success.php?order_number=" . urlencode($order['order_number']));
        exit();
    }

    $endpoint = env_value('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create');
    $partnerCode = env_value('MOMO_PARTNER_CODE', '');
    $accessKey = env_value('MOMO_ACCESS_KEY', '');
    $secretKey = env_value('MOMO_SECRET_KEY', '');

    if ($partnerCode === '' || $accessKey === '' || $secretKey === '') {
        throw new Exception('Thiếu cấu hình MoMo trong file .env.');
    }

    $orderInfo = "Thanh toán đơn hàng LearnHub " . $order['order_number'];
    $amount = (int) $order['total_amount'];
    $uniqueSuffix = date('ymdHis') . '-' . bin2hex(random_bytes(3));
    $orderId = $order['order_number'] . '-' . $uniqueSuffix;
    $requestId = $order['id'] . "_" . $uniqueSuffix;

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $uri_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    $redirectUrl = $protocol . $host . $uri_dir . "/momo_callback.php";
    $ipnUrl = $protocol . $host . $uri_dir . "/momo_callback.php";

    $extraData = "";
    $requestType = "captureWallet";

    $rawHash = "accessKey=" . $accessKey .
        "&amount=" . $amount .
        "&extraData=" . $extraData .
        "&ipnUrl=" . $ipnUrl .
        "&orderId=" . $orderId .
        "&orderInfo=" . $orderInfo .
        "&partnerCode=" . $partnerCode .
        "&redirectUrl=" . $redirectUrl .
        "&requestId=" . $requestId .
        "&requestType=" . $requestType;

    $signature = hash_hmac("sha256", $rawHash, $secretKey);

    $payload = [
        'partnerCode' => $partnerCode,
        'partnerName' => 'LearnHub',
        'storeId' => 'LearnHubStore',
        'requestId' => $requestId,
        'amount' => $amount,
        'orderId' => $orderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $redirectUrl,
        'ipnUrl' => $ipnUrl,
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => $requestType,
        'signature' => $signature
    ];

    $jsonResult = momo_post_json($endpoint, $payload);

    if (empty($jsonResult['payUrl'])) {
        throw new Exception($jsonResult['message'] ?? 'MoMo không trả về đường dẫn thanh toán.');
    }

    $payUrl = $jsonResult['payUrl'];
    $deeplink = $jsonResult['deeplink'] ?? $payUrl;
    $qrImage = $jsonResult['qrCodeUrl'] ?? '';
    $hasOfficialQr = $qrImage !== '';

    $stmt = $pdo->prepare("
        UPDATE orders
        SET momo_order_id = ?, momo_request_id = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$orderId, $requestId, $order_id, $_SESSION['user_id']]);

    $page_title = "Quét mã QR MoMo";
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="container momo-page-container" style="min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 60px 0;">
        <div class="momo-payment-card" style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); padding: 36px; max-width: 760px; width: 100%;">
            <div class="momo-payment-grid" style="display: grid; grid-template-columns: 300px 1fr; gap: 32px; align-items: center;">
                <div style="text-align: center;">
                    <?php if ($hasOfficialQr): ?>
                    <div class="momo-qr-box" style="width: 260px; height: 260px; margin: 0 auto; background: white; border: 1px solid var(--border); border-radius: 12px; padding: 12px;">
                        <img src="<?php echo htmlspecialchars($qrImage); ?>" alt="Mã QR thanh toán MoMo" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); margin-top: 12px;">Mở ứng dụng MoMo và quét mã QR để thanh toán.</p>
                    <?php else: ?>
                        <div class="momo-qr-box" style="width: 260px; height: 260px; margin: 0 auto; background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px;">
                            <i data-lucide="external-link" style="width: 58px; height: 58px; color: #a50064;"></i>
                            <strong style="font-size: 18px; color: var(--text-main);">Cổng MoMo Sandbox</strong>
                            
                        </div>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 12px;">Vui lòng mở cổng thanh toán MoMo Sandbox bằng nút bên cạnh.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px; color: #a50064; font-weight: 800; margin-bottom: 12px;">
                        <span style="width: 36px; height: 36px; border-radius: 8px; background: #a50064; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 900;">M</span>
                        MoMo Sandbox
                    </div>
                    <h1 style="font-size: 28px; font-weight: 800; color: var(--text-main); margin-bottom: 10px;"><?php echo $hasOfficialQr ? 'Quét QR để thanh toán' : 'Thanh toán qua MoMo Sandbox'; ?></h1>
                    <p style="color: var(--text-muted); margin-bottom: 22px;">Sau khi thanh toán thành công, MoMo sẽ chuyển bạn về LearnHub và khóa học sẽ được mở khóa tự động.</p>

                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 22px;">
                        <div style="display: flex; justify-content: space-between; gap: 16px; margin-bottom: 8px;">
                            <span style="color: var(--text-muted);">Mã đơn hàng</span>
                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px;">
                            <span style="color: var(--text-muted);">Số tiền</span>
                            <strong style="color: var(--primary); font-size: 20px;"><?php echo number_format($amount, 0, ',', '.'); ?>đ</strong>
                        </div>
                    </div>

                    <div style="background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; border-radius: var(--radius-sm); padding: 12px 14px; font-size: 13px; margin-bottom: 14px;">
                        <?php if ($hasOfficialQr): ?>
                            Mã QR MoMo Sandbox có thời hạn ngắn. Nếu app báo hết hạn, hãy tải lại trang để tạo mã mới.
                        <?php endif; ?>
                            Vui lòng chụp lại màn hình sau khi thanh toán
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php if ($hasOfficialQr): ?>
                            <a href="<?php echo htmlspecialchars($deeplink); ?>" class="btn btn-primary" style="height: 48px; display: flex; align-items: center; justify-content: center;">Mở ứng dụng MoMo</a>
                            <a href="<?php echo htmlspecialchars($payUrl); ?>" class="btn btn-outline" style="height: 48px; display: flex; align-items: center; justify-content: center;">Mở cổng thanh toán</a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($payUrl); ?>" class="btn btn-primary" style="height: 48px; display: flex; align-items: center; justify-content: center;">Mở cổng thanh toán MoMo Sandbox</a>
                        <?php endif; ?>
                        <form action="momo_payment.php?order_id=<?php echo $order_id; ?>" method="POST">
                            <input type="hidden" name="convert_to_bank" value="1">
                            <button type="submit" class="btn btn-secondary" style="width: 100%; height: 46px;">Chuyển sang chuyển khoản ngân hàng</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        @media (max-width: 760px) {
            .momo-page-container {
                padding: 32px 0 !important;
            }

            .momo-payment-card {
                padding: 18px !important;
                border-radius: 12px !important;
                max-width: 100% !important;
                overflow: hidden !important;
            }

            .momo-payment-grid {
                grid-template-columns: 1fr !important;
                gap: 22px !important;
            }

            .momo-qr-box {
                width: min(240px, 100%) !important;
                height: auto !important;
                aspect-ratio: 1 / 1 !important;
            }

            .momo-payment-card h1 {
                font-size: 24px !important;
                line-height: 1.25 !important;
            }

            .momo-payment-card .btn {
                height: auto !important;
                min-height: 46px !important;
                padding: 11px 14px !important;
                line-height: 1.3 !important;
            }
        }
    </style>
    <?php
    require_once __DIR__ . '/includes/footer.php';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $page_title = "Lỗi kết nối thanh toán";
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 60px 0;">
        <div style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); padding: 40px; max-width: 560px; width: 100%; text-align: center;">
            <div style="width: 70px; height: 70px; background-color: #fee2e2; color: var(--danger); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i data-lucide="alert-triangle" style="width: 38px; height: 38px;"></i>
            </div>
            <h3 style="font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">Không tạo được QR MoMo</h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px; line-height: 1.6;">
                Chi tiết lỗi: <strong style="color: var(--danger);"><?php echo htmlspecialchars($e->getMessage()); ?></strong>
            </p>
            <div style="display: flex; gap: 12px; flex-direction: column;">
                <form action="momo_payment.php?order_id=<?php echo $order_id; ?>" method="POST">
                    <input type="hidden" name="convert_to_bank" value="1">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 46px;">Chuyển sang chuyển khoản ngân hàng</button>
                </form>
                <a href="cart.php" class="btn btn-outline" style="height: 46px; display: flex; align-items: center; justify-content: center;">Quay lại giỏ hàng</a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
}
?>
