<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';


require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';


try {
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        $stmt = $pdo->prepare("INSERT INTO carts (user_id, total_price) VALUES (?, 0.00)");
        $stmt->execute([$user_id]);
        $cart_id = $pdo->lastInsertId();
    } else {
        $cart_id = $cart['id'];
    }
} catch (PDOException $e) {
    die("Lỗi giỏ hàng: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $course_id = intval($_POST['course_id'] ?? 0);

    if ($action === 'add' || $action === 'buy_now') {
        try {
            
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND published = 1");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();

            if (!$course) {
                $error = 'Khóa học không khả dụng!';
            } else {
                
                $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND active = 1");
                $stmt->execute([$user_id, $course_id]);
                
                if ($stmt->fetch()) {
                    $error = 'Bạn đã sở hữu khóa học này rồi!';
                } else {
                    
                    $has_discount = $course['discount_price'] > 0 && $course['discount_price'] < $course['price'];
                    $actual_price = $has_discount ? $course['discount_price'] : $course['price'];
                    
                    
                    $stmt = $pdo->prepare("SELECT id FROM cart_items WHERE cart_id = ? AND course_id = ?");
                    $stmt->execute([$cart_id, $course_id]);
                    
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, course_id, price) VALUES (?, ?, ?)");
                        $stmt->execute([$cart_id, $course_id, $actual_price]);
                    }
                    
                    if ($action === 'buy_now') {
                        header("Location: checkout.php");
                        exit();
                    } else {
                        header("Location: cart.php?success=1");
                        exit();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }

    if ($action === 'remove') {
        $item_id = intval($_POST['item_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
            $stmt->execute([$item_id, $cart_id]);
            header("Location: cart.php");
            exit();
        } catch (PDOException $e) {
            $error = 'Xóa thất bại: ' . $e->getMessage();
        }
    }
}


$cart_items = [];
$total_amount = 0.00;
try {
    $stmt = $pdo->prepare("
        SELECT ci.id as item_id,
               CASE
                   WHEN c.discount_price > 0 AND c.discount_price < c.price THEN c.discount_price
                   ELSE c.price
               END as cart_price,
               c.id as course_id, c.title, c.thumbnail, c.price as original_price, c.instructor, c.category
        FROM cart_items ci
        JOIN courses c ON ci.course_id = c.id
        WHERE ci.cart_id = ?
    ");
    $stmt->execute([$cart_id]);
    $cart_items = $stmt->fetchAll() ?: [];

    
    foreach ($cart_items as $item) {
        $total_amount += $item['cart_price'];
    }

    
    $stmt = $pdo->prepare("UPDATE carts SET total_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$total_amount, $cart_id]);

} catch (PDOException $e) {
    $error = 'Không thể tải giỏ hàng: ' . $e->getMessage();
}

$page_title = "Giỏ hàng của tôi";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="background-color: var(--bg-main); min-height: 80vh; padding: 40px 0;">
    <div class="container">
        
        
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: 36px; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">Giỏ hàng của tôi</h1>
            <p style="color: var(--text-muted); font-size: 16px;">Xem các khóa học đã thêm và chuẩn bị thanh toán học tập.</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="margin-bottom: 24px;">
                <i data-lucide="check-circle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                Khóa học đã được thêm vào giỏ hàng thành công!
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 24px;">
                <i data-lucide="alert-triangle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            
            <div style="text-align: center; padding: 80px 0; background-color: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                <i data-lucide="shopping-cart" style="width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Giỏ hàng trống rỗng!</h3>
                <p style="color: var(--text-muted); margin-bottom: 24px;">Bạn chưa thêm bất kỳ khóa học nào vào giỏ hàng của mình.</p>
                <a href="courses.php" class="btn btn-primary" style="font-size: 14px;">Khám phá khóa học ngay</a>
            </div>
        <?php else: ?>
            
            <div class="cart-wrapper">
                
                
                <div class="cart-table-card">
                    <h3 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">Chi tiết sản phẩm</h3>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item-row">
                            
                            <div class="cart-item-info">
                                <img src="<?php echo htmlspecialchars($item['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <div>
                                    <span style="font-size: 11px; background-color: var(--primary-light); color: var(--primary); padding: 2px 8px; border-radius: 99px; font-weight: 700; display: inline-block; margin-bottom: 4px;"><?php echo htmlspecialchars($item['category']); ?></span>
                                    <h4><a href="course-detail.php?id=<?php echo $item['course_id']; ?>" style="color: var(--text-main); font-weight: 700;"><?php echo htmlspecialchars($item['title']); ?></a></h4>
                                    <p>Giảng viên: <?php echo htmlspecialchars($item['instructor']); ?></p>
                                </div>
                            </div>

                            <div class="cart-item-price">
                                <span style="font-size: 18px; font-weight: 800; color: var(--primary);"><?php echo number_format($item['cart_price'], 0, ',', '.'); ?>đ</span>
                                <?php if ($item['original_price'] > $item['cart_price']): ?>
                                    <span style="font-size: 12px; text-decoration: line-through; color: var(--text-muted);"><?php echo number_format($item['original_price'], 0, ',', '.'); ?>đ</span>
                                <?php endif; ?>
                                
                                
                                <form action="cart.php" method="POST" style="margin-top: 8px;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                    <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--danger); font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                        <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Xóa
                                    </button>
                                </form>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

                
                <div class="cart-summary-card">
                    <h3 class="cart-summary-title">Tóm tắt đơn hàng</h3>
                    
                    <div class="summary-row">
                        <span style="color: var(--text-muted);">Tạm tính (<?php echo count($cart_items); ?> khóa học)</span>
                        <span><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</span>
                    </div>
                    
                    <div class="summary-row">
                        <span style="color: var(--text-muted);">Khuyến mãi</span>
                        <span style="color: var(--success);">- 0đ</span>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Tổng thanh toán</span>
                        <span style="color: var(--primary);"><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</span>
                    </div>

                    <a href="checkout.php" class="btn btn-primary" style="width: 100%; text-align: center; margin-top: 24px; height: 50px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i data-lucide="credit-card" style="width: 20px; height: 20px;"></i>
                        Tiến hành thanh toán
                    </a>
                    
                    <a href="courses.php" class="btn btn-outline" style="width: 100%; text-align: center; margin-top: 12px; height: 50px; display: flex; align-items: center; justify-content: center;">
                        Tiếp tục mua hàng
                    </a>

                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
