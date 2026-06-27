<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';


require_login();

$user_id = $_SESSION['user_id'];
$error = '';


try {
    $stmt = $pdo->prepare("
        SELECT ci.id as item_id,
               CASE
                   WHEN c.discount_price > 0 AND c.discount_price < c.price THEN c.discount_price
                   ELSE c.price
               END as cart_price,
               c.id as course_id, c.title, c.thumbnail, c.price as original_price
        FROM cart_items ci
        JOIN courses c ON ci.course_id = c.id
        JOIN carts ct ON ci.cart_id = ct.id
        WHERE ct.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll() ?: [];

    if (empty($cart_items)) {
        header("Location: cart.php");
        exit();
    }

    
    $total_amount = 0.00;
    foreach ($cart_items as $item) {
        $total_amount += $item['cart_price'];
    }

} catch (PDOException $e) {
    die("Có lỗi xảy ra: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? 'BANK_TRANSFER';
    $allowed_payment_methods = ['BANK_TRANSFER', 'MOMO', 'PAYPAL', 'VNPAY', 'CREDIT_CARD'];

    if (!in_array($payment_method, $allowed_payment_methods, true)) {
        $error = 'Phương thức thanh toán không hợp lệ!';
    } else {
        try {
            $pdo->beginTransaction();

            
            $order_number = 'LH-' . date('ymd') . '-' . rand(1000, 9999);

            
            $stmt = $pdo->prepare("
                INSERT INTO orders (order_number, user_id, total_amount, status, payment_method) 
                VALUES (?, ?, ?, 'PENDING', ?)
            ");
            $stmt->execute([$order_number, $user_id, $total_amount, $payment_method]);
            $order_id = $pdo->lastInsertId();

            
            $stmt_item = $pdo->prepare("
                INSERT INTO order_items (order_id, course_id, course_name, price, original_price) 
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($cart_items as $item) {
                $stmt_item->execute([
                    $order_id,
                    $item['course_id'],
                    $item['title'],
                    $item['cart_price'],
                    $item['original_price']
                ]);
            }

            
            
            if ($payment_method === 'BANK_TRANSFER') {
                $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $cart = $stmt->fetch();
                if ($cart) {
                    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                    $stmt->execute([$cart['id']]);
                }
                
                $pdo->commit();
                
                
                header("Location: order-success.php?order_number=" . urlencode($order_number));
                exit();
            } elseif ($payment_method === 'MOMO') {
                
                $pdo->commit();
                header("Location: momo_payment.php?order_id=" . $order_id);
                exit();
            } else {
                
                $pdo->commit();
                header("Location: gateway_payment.php?order_id=" . $order_id);
                exit();
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Đặt hàng thất bại: ' . $e->getMessage();
        }
    }
}

$page_title = "Tiến hành thanh toán";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="background-color: var(--bg-main); min-height: 80vh; padding: 40px 0;">
    <div class="container">
        
        
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: 36px; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">Thanh toán đơn hàng</h1>
            <p style="color: var(--text-muted); font-size: 16px;">Vui lòng kiểm tra lại thông tin đơn hàng và chọn phương thức thanh toán.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 24px;">
                <i data-lucide="alert-triangle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="cart-wrapper">
            
            
            <div class="cart-table-card">
                <form action="checkout.php" method="POST" id="checkout-form">
                    
                    <h3 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">Phương thức thanh toán</h3>

                    
                    <label class="payment-method-option" style="display: flex; gap: 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 20px; cursor: pointer; margin-bottom: 16px; transition: var(--transition);">
                        <input type="radio" name="payment_method" value="BANK_TRANSFER" checked style="width: 20px; height: 20px; margin-top: 2px;" onclick="togglePaymentDetails('bank')">
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 16px;">Chuyển khoản Ngân hàng (Thủ công)</strong>
                                <i data-lucide="building" style="color: var(--primary);"></i>
                            </div>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Thực hiện chuyển khoản ngân hàng trực tiếp. Đơn hàng sẽ được kích hoạt sau khi chúng tôi xác nhận giao dịch thành công.</p>
                        </div>
                    </label>

                    
                    <label class="payment-method-option" style="display: flex; gap: 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 20px; cursor: pointer; margin-bottom: 24px; transition: var(--transition);">
                        <input type="radio" name="payment_method" value="MOMO" style="width: 20px; height: 20px; margin-top: 2px;" onclick="togglePaymentDetails('momo')">
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 16px;">Cổng thanh toán MoMo (Tự động)</strong>
                                <span style="font-weight: 800; color: #a50064; font-size: 14px;">MoMo Sandbox</span>
                            </div>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Thanh toán trực tiếp qua ví MoMo. Tự động mở khóa học trong tài khoản của bạn ngay lập tức sau khi thanh toán thành công.</p>
                        </div>
                    </label>

                    <label class="payment-method-option" style="display: flex; gap: 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 20px; cursor: pointer; margin-bottom: 16px; transition: var(--transition);">
                        <input type="radio" name="payment_method" value="VNPAY" style="width: 20px; height: 20px; margin-top: 2px;" onclick="togglePaymentDetails('gateway')">
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 16px;">VNPay Sandbox</strong>
                                <i data-lucide="landmark" style="color: var(--primary);"></i>
                            </div>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Mô phỏng cổng thanh toán VNPay cho môi trường demo. Sau khi xác nhận thanh toán thử nghiệm, khóa học sẽ được mở tự động.</p>
                        </div>
                    </label>

                    <label class="payment-method-option" style="display: flex; gap: 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 20px; cursor: pointer; margin-bottom: 16px; transition: var(--transition);">
                        <input type="radio" name="payment_method" value="PAYPAL" style="width: 20px; height: 20px; margin-top: 2px;" onclick="togglePaymentDetails('gateway')">
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 16px;">PayPal Sandbox</strong>
                                <i data-lucide="wallet-cards" style="color: var(--secondary);"></i>
                            </div>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Thanh toán quốc tế dạng sandbox/demo, phù hợp để kiểm thử luồng mở khóa khóa học.</p>
                        </div>
                    </label>

                    <label class="payment-method-option" style="display: flex; gap: 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 20px; cursor: pointer; margin-bottom: 24px; transition: var(--transition);">
                        <input type="radio" name="payment_method" value="CREDIT_CARD" style="width: 20px; height: 20px; margin-top: 2px;" onclick="togglePaymentDetails('gateway')">
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 16px;">Thẻ tín dụng / ghi nợ</strong>
                                <i data-lucide="credit-card" style="color: var(--accent);"></i>
                            </div>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Mô phỏng thanh toán bằng thẻ trong môi trường demo, chưa kết nối ngân hàng thật.</p>
                        </div>
                    </label>

                    
                    <div id="bank-payment-details" style="background-color: var(--bg-main); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 24px;">
                        <h4 style="font-weight: 800; color: var(--text-main); margin-bottom: 12px; font-size: 15px;">Thông tin chuyển khoản Ngân hàng</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 2;">
                            <tr>
                                <td style="color: var(--text-muted); width: 32%; min-width: 0;">Ngân hàng:</td>
                                <td><strong>Ngân hàng quân đội (MB Bank)</strong></td>
                            </tr>
                            <tr>
                                <td style="color: var(--text-muted);">Số tài khoản:</td>
                                <td><strong style="color: var(--primary); font-size: 16px;">1902888888888</strong></td>
                            </tr>
                            <tr>
                                <td style="color: var(--text-muted);">Chủ tài khoản:</td>
                                <td><strong>CONG TY LEARNHUB VIET NAM</strong></td>
                            </tr>
                            <tr>
                                <td style="color: var(--text-muted);">Nội dung CK:</td>
                                <td><strong style="color: var(--accent);">LH Tên_Học_Viên (Ví dụ: LH Nguyen Van A)</strong></td>
                            </tr>
                        </table>
                        <div class="alert alert-danger" style="margin-top: 16px; margin-bottom: 0; padding: 12px 16px; font-size: 13px;">
                            <i data-lucide="info" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Lưu ý: Vui lòng chụp ảnh màn hình giao dịch sau khi chuyển khoản thành công để phục vụ đối chiếu khi cần thiết.
                        </div>
                    </div>

                    
                    <div id="momo-payment-details" style="display: none; background-color: var(--bg-main); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 48px; height: 48px; background-color: #a50064; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: white; font-weight: 900; font-size: 20px;">M</div>
                            <div>
                                <h4 style="font-weight: 800; color: var(--text-main); margin: 0; font-size: 15px;">Kết nối trực tiếp qua MoMo Sandbox</h4>
                                <p style="font-size: 13px; color: var(--text-muted); margin: 0; margin-top: 2px;">Nhấn nút "Xác nhận và Thanh toán" để chuyển tới cổng thanh toán an toàn của MoMo.</p>
                            </div>
                        </div>
                    </div>

                    <div id="gateway-payment-details" style="display: none; background-color: var(--bg-main); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: white;">
                                <i data-lucide="shield-check" style="width: 24px; height: 24px;"></i>
                            </div>
                            <div>
                                <h4 style="font-weight: 800; color: var(--text-main); margin: 0; font-size: 15px;">Cổng thanh toán Sandbox / Demo</h4>
                                <p style="font-size: 13px; color: var(--text-muted); margin: 0; margin-top: 2px;">Dùng để kiểm thử quy trình thanh toán. Cổng thật cần cấu hình merchant/API riêng trước khi đưa vào sử dụng thực tế.</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
                        Xác nhận và Thanh toán
                    </button>

                </form>
            </div>

            
            <div class="cart-summary-card">
                <h3 class="cart-summary-title">Tóm tắt đơn hàng</h3>
                
                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; max-height: 240px; overflow-y: auto; padding-right: 8px;">
                    <?php foreach ($cart_items as $item): ?>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <img src="<?php echo htmlspecialchars($item['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" style="width: 18%; max-width: 60px; aspect-ratio: 16/9; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                            <div style="flex-grow: 1; overflow: hidden;">
                                <h4 style="font-size: 13px; font-weight: 700; color: var(--text-main); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($item['title']); ?>"><?php echo htmlspecialchars($item['title']); ?></h4>
                                <span style="font-size: 12px; font-weight: 700; color: var(--primary);"><?php echo number_format($item['cart_price'], 0, ',', '.'); ?>đ</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row" style="border-top: 1px solid var(--border); padding-top: 16px;">
                    <span style="color: var(--text-muted); font-size: 14px;">Tổng tiền học</span>
                    <span><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</span>
                </div>
                
                <div class="summary-row">
                    <span style="color: var(--text-muted); font-size: 14px;">Giảm giá</span>
                    <span style="color: var(--success);">- 0đ</span>
                </div>

                <div class="summary-row summary-total" style="font-size: 18px; font-weight: 800;">
                    <span>Tổng thanh toán</span>
                    <span style="color: var(--primary);"><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</span>
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="cart.php" style="color: var(--text-muted); font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                        <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i> Quay lại sửa giỏ hàng
                    </a>
                </div>

            </div>

        </div>

    </div>
</div>

<style>
    
    .payment-method-option:hover {
        border-color: var(--primary) !important;
        background-color: var(--primary-light);
    }
    .payment-method-option input[type="radio"]:checked {
        border-color: var(--primary) !important;
    }

    @media (max-width: 640px) {
        .section > .container > div:first-child h1 {
            font-size: 28px !important;
            line-height: 1.2 !important;
        }

        .section > .container > div:first-child p {
            font-size: 14px !important;
            line-height: 1.6 !important;
        }


        #checkout-form,
        #checkout-form > div,
        .cart-table-card,
        .cart-summary-card {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }
        .cart-table-card,
        .cart-summary-card {
            padding: 18px !important;
            border-radius: 12px !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }

        .payment-method-option {
            gap: 12px !important;
            padding: 16px !important;
            align-items: flex-start !important;
        }

        .payment-method-option > div {
            min-width: 0 !important;
        }

        .payment-method-option > div > div:first-child {
            align-items: flex-start !important;
            flex-direction: column !important;
            gap: 6px !important;
        }

        .payment-method-option strong,
        .payment-method-option p,
        #bank-payment-details,
        #momo-payment-details,
        #gateway-payment-details {
            overflow-wrap: anywhere !important;
        }

        #bank-payment-details,
        #momo-payment-details,
        #gateway-payment-details {
            padding: 16px !important;
        }

        #bank-payment-details table,
        #bank-payment-details tbody,
        #bank-payment-details tr,
        #bank-payment-details td {
            display: block !important;
            width: 100% !important;
        }

        #bank-payment-details tr {
            margin-bottom: 12px !important;
        }

        #bank-payment-details td {
            line-height: 1.5 !important;
        }

        #bank-payment-details td:first-child {
            margin-bottom: 2px !important;
        }

        #momo-payment-details > div,
        #gateway-payment-details > div {
            align-items: flex-start !important;
        }

        #checkout-form > button[type="submit"] {
            height: auto !important;
            min-height: 48px !important;
            padding: 12px 14px !important;
            line-height: 1.3 !important;
        }
    }
</style>

<script>
    
    function togglePaymentDetails(type) {
        const bankDetails = document.getElementById('bank-payment-details');
        const momoDetails = document.getElementById('momo-payment-details');
        const gatewayDetails = document.getElementById('gateway-payment-details');

        if (type === 'bank') {
            bankDetails.style.display = 'block';
            momoDetails.style.display = 'none';
            gatewayDetails.style.display = 'none';
        } else if (type === 'momo') {
            bankDetails.style.display = 'none';
            momoDetails.style.display = 'block';
            gatewayDetails.style.display = 'none';
        } else {
            bankDetails.style.display = 'none';
            momoDetails.style.display = 'none';
            gatewayDetails.style.display = 'block';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
