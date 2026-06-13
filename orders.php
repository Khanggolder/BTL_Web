<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';


require_login();

$user_id = $_SESSION['user_id'];
$error_msg = '';

try {
    
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as total_courses
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.id DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $error_msg = 'Không thể tải lịch sử hóa đơn: ' . $e->getMessage();
}

$page_title = "Đơn hàng của tôi";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="background-color: var(--bg-main); min-height: 80vh; padding: 40px 0;">
    <div class="container" style="max-width: 1000px;">
        
        
        <a href="profile.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); font-weight: 600; font-size: 14px; margin-bottom: 24px;">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i> Quay lại Hồ sơ của tôi
        </a>

        
        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 32px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Đơn hàng của tôi</h1>
            <p style="color: var(--text-muted); font-size: 15px;">Quản lý và tra cứu trạng thái kích hoạt của tất cả hóa đơn đặt mua khóa học.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger" style="margin-bottom: 24px;">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 80px 0; background-color: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                <i data-lucide="shopping-bag" style="width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Bạn chưa có đơn hàng nào!</h3>
                <p style="color: var(--text-muted); margin-bottom: 24px;">Hãy chọn các khóa học yêu thích và bắt đầu nâng cao kỹ năng của mình.</p>
                <a href="courses.php" class="btn btn-primary" style="font-size: 14px;">Khám phá khóa học ngay</a>
            </div>
        <?php else: ?>
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($orders as $order): ?>
                    <div style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-sm); transition: var(--transition);">
                        
                        
                        <div style="background-color: var(--bg-main); padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">MÃ ĐƠN HÀNG</span>
                                <strong style="font-size: 16px; color: var(--primary);"><?php echo htmlspecialchars($order['order_number']); ?></strong>
                            </div>

                            <div style="text-align: right;">
                                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">NGÀY ĐẶT MUA</span>
                                <strong style="font-size: 14px; color: var(--text-main);"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></strong>
                            </div>
                        </div>

                        
                        <div style="padding: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                            
                            
                            <div style="display: flex; gap: 40px; flex-wrap: wrap;">
                                <div>
                                    <span style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 4px;">Số lượng</span>
                                    <span style="font-weight: 700; color: var(--text-main); font-size: 15px;"><?php echo $order['total_courses']; ?> khóa học</span>
                                </div>
                                <div>
                                    <span style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 4px;">Thanh toán</span>
                                    <span style="font-weight: 700; color: var(--text-main); font-size: 15px; display: inline-flex; align-items: center; gap: 6px;">
                                        <?php if ($order['payment_method'] === 'MOMO'): ?>
                                            <span style="width: 8px; height: 8px; background-color: #a50064; border-radius: 50%;"></span> Ví MoMo
                                        <?php else: ?>
                                            <span style="width: 8px; height: 8px; background-color: var(--primary); border-radius: 50%;"></span> Chuyển khoản
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div>
                                    <span style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 4px;">Tổng số tiền</span>
                                    <span style="font-weight: 800; color: var(--primary); font-size: 18px;"><?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ</span>
                                </div>
                            </div>

                            
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <div>
                                    <?php 
                                        $st = $order['status'];
                                        if ($st === 'COMPLETED') {
                                            echo '<span style="background-color: #d1fae5; color: var(--success); padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span style="width:6px;height:6px;background-color:var(--success);border-radius:50%;"></span>Đã hoàn tất</span>';
                                        } elseif ($st === 'PENDING') {
                                            echo '<span style="background-color: #fef3c7; color: #d97706; padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span style="width:6px;height:6px;background-color:#d97706;border-radius:50%;"></span>Đang xử lý</span>';
                                        } else {
                                            echo '<span style="background-color: #fee2e2; color: var(--danger); padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span style="width:6px;height:6px;background-color:var(--danger);border-radius:50%;"></span>Đã hủy</span>';
                                        }
                                    ?>
                                </div>

                                
                                <button class="btn btn-outline" style="padding: 8px 16px; font-size: 13px; font-weight: 700; border-radius: var(--radius-sm);" onclick="toggleOrderItems('items-<?php echo $order['id']; ?>')">
                                    Chi tiết
                                </button>
                            </div>

                        </div>

                        
                        <div id="items-<?php echo $order['id']; ?>" style="display: none; background-color: var(--bg-main); border-top: 1px solid var(--border); padding: 20px 24px;">
                            <h4 style="font-size: 14px; font-weight: 800; color: var(--text-main); margin-bottom: 12px;">Các khóa học đã đặt sắm:</h4>
                            
                            <?php
                                
                                try {
                                    $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                                    $stmt_items->execute([$order['id']]);
                                    $items = $stmt_items->fetchAll() ?: [];
                                } catch (PDOException $ex) {
                                    $items = [];
                                }
                            ?>

                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach ($items as $item): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: white; border: 1px solid var(--border); padding: 12px 16px; border-radius: var(--radius-sm);">
                                        <span style="font-weight: 700; font-size: 13px; color: var(--text-main);"><?php echo htmlspecialchars($item['course_name']); ?></span>
                                        <span style="font-weight: 700; color: var(--primary); font-size: 13px;"><?php echo number_format($item['price'], 0, ',', '.'); ?>đ</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<script>
    
    function toggleOrderItems(elementId) {
        const target = document.getElementById(elementId);
        if (target.style.display === 'none') {
            target.style.display = 'block';
        } else {
            target.style.display = 'none';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
