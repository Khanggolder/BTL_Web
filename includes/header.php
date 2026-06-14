<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';


$cart_count = 0;
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(ci.id) 
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_count = $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $cart_count = 0;
    }
}


$current_user = null;
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();
    } catch (PDOException $e) {
        $current_user = null;
    }
}


function is_active($page_name) {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    return ($current_script == $page_name) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - LearnHub" : "LearnHub - Nền tảng học trực tuyến số 1"; ?></title>
    
    
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

    
    <header id="main-header">
        <div class="navbar-container">
            
            
            <a href="index.php" class="logo">
                <i data-lucide="book-open" style="width: 32px; height: 32px;"></i>
                <span>LearnHub</span>
            </a>

            
            <ul class="nav-links">
                <li><a href="index.php" class="<?php echo is_active('index.php'); ?>">Trang chủ</a></li>
                <li><a href="courses.php" class="<?php echo is_active('courses.php'); ?>">Khóa học</a></li>
                <li><a href="about.php" class="<?php echo is_active('about.php'); ?>">Về chúng tôi</a></li>
                <li><a href="contact.php" class="<?php echo is_active('contact.php'); ?>">Liên hệ</a></li>
            </ul>

            
            <div class="nav-actions">
                <button class="icon-btn mobile-menu-toggle" id="mobile-menu-toggle" type="button" title="Menu" aria-label="Mở menu" aria-expanded="false">
                    <i data-lucide="menu"></i>
                </button>
                
                
                <button class="icon-btn" id="search-toggle-btn" title="Tìm kiếm">
                    <i data-lucide="search"></i>
                </button>

                <?php if (is_logged_in()): ?>
                    
                    <div class="notification-menu" id="notification-menu">
                        <button class="icon-btn" id="notification-toggle" type="button" title="Thông báo" aria-label="Mở thông báo" aria-expanded="false">
                            <i data-lucide="bell"></i>
                            <span class="badge" style="top: 4px; right: 4px; padding: 3px;"></span>
                        </button>
                        <div class="notification-dropdown">
                            <div class="notification-head">
                                <strong>Thông báo</strong>
                                <span>Mới nhất</span>
                            </div>
                            <div class="notification-item">
                                <i data-lucide="book-open" style="width: 18px; height: 18px;"></i>
                                <div>
                                    <strong>Khóa học của bạn đã sẵn sàng</strong>
                                    <p>Vào hồ sơ để tiếp tục học các khóa đã đăng ký.</p>
                                </div>
                            </div>
                            <div class="notification-item">
                                <i data-lucide="shopping-bag" style="width: 18px; height: 18px;"></i>
                                <div>
                                    <strong>Theo dõi đơn hàng</strong>
                                    <p>Kiểm tra trạng thái thanh toán trong mục đơn hàng.</p>
                                </div>
                            </div>
                            <a href="orders.php" class="notification-link">Xem đơn hàng của tôi</a>
                        </div>
                    </div>

                    
                    <a href="cart.php" class="icon-btn" title="Giỏ hàng">
                        <i data-lucide="shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>

                    
                    <div class="user-menu" id="user-menu">
                        <button type="button" class="avatar" id="user-menu-toggle" aria-label="Mở menu tài khoản" aria-expanded="false">
                            <?php 
                                $name_char = mb_substr($current_user['full_name'] ?? 'U', 0, 1, 'UTF-8');
                                echo mb_strtoupper($name_char, 'UTF-8'); 
                            ?>
                        </button>
                        <div class="user-dropdown">
                            <div class="user-info">
                                <p>Đã đăng nhập với</p>
                                <h4><?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?></h4>
                            </div>
                            
                            <a href="profile.php">
                                <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                                Hồ sơ của tôi
                            </a>
                            <a href="orders.php">
                                <i data-lucide="shopping-bag" style="width: 16px; height: 16px;"></i>
                                Đơn hàng của tôi
                            </a>

                            <?php if (is_admin($pdo)): ?>
                                <hr>
                                <a href="admin/dashboard.php" style="color: var(--secondary); font-weight: 800;">
                                    <i data-lucide="shield-check" style="width: 16px; height: 16px;"></i>
                                    Admin Panel
                                </a>
                            <?php endif; ?>

                            <hr>
                            <a href="logout.php" class="logout-btn">
                                <i data-lucide="log-out" style="width: 16px; height: 16px;"></i>
                                Đăng xuất
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <a href="login.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 14px;">Đăng nhập</a>
                    <a href="signup.php" class="btn btn-primary" style="padding: 8px 20px; font-size: 14px;">Đăng ký</a>
                <?php endif; ?>

            </div>
        </div>
    </header>

    
    <div id="search-dropdown-wrapper" style="display: none; background-color: var(--bg-card); border-bottom: 1px solid var(--border); position: fixed; top: 80px; left: 0; right: 0; z-index: 999; padding: 20px 0; box-shadow: var(--shadow-md); transition: var(--transition);">
        <div class="container">
            <form action="courses.php" method="GET" style="position: relative;">
                <input type="text" name="search" placeholder="Nhập từ khóa tìm kiếm khóa học..." class="form-control" style="padding-left: 48px; border-radius: var(--radius-md); font-size: 16px;" required>
                <i data-lucide="search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </form>
        </div>
    </div>

    
    <div style="height: 80px;"></div>
