<?php
$page_title = "Đăng ký tài khoản";
require_once __DIR__ . '/includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ các thông tin bắt buộc (*)!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng Email không hợp lệ!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có độ dài từ 6 ký tự trở lên!';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } else {
        try {
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email này đã được sử dụng bởi một tài khoản khác!';
            } else {
                
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                
                $pdo->beginTransaction();
                
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password, full_name, phone, avatar, active) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $avatar_url = "https://api.dicebear.com/7.x/adventurer/svg?seed=" . urlencode($full_name);
                $stmt->execute([$email, $hashed_password, $full_name, $phone, $avatar_url]);
                $new_user_id = $pdo->lastInsertId();
                
                
                $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'ROLE_USER')");
                $stmt->execute([$new_user_id]);
                
                $pdo->commit();
                
                
                header("Location: login.php?success=" . urlencode("Đăng ký tài khoản thành công! Vui lòng đăng nhập tại đây."));
                exit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Có lỗi xảy ra trong quá trình xử lý: ' . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <div class="auth-wrapper" style="min-height: calc(100vh - 160px); padding: 20px 0;">
        <div class="auth-card" style="max-width: 520px; padding: 30px;">
            
            <div class="auth-header" style="margin-bottom: 24px;">
                <h2>Tạo tài khoản mới</h2>
                <p>Khởi đầu hành trình học lập trình cùng LearnHub</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="padding: 12px 16px; margin-bottom: 20px;">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            
            <form action="signup.php" method="POST">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="full_name">Họ và tên <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Nguyễn Văn A" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="email">Địa chỉ Email <span style="color: var(--danger);">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="phone">Số điện thoại</label>
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="09xxxxxxxx" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="password">Mật khẩu (từ 6 ký tự trở lên) <span style="color: var(--danger);">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="confirm_password">Xác nhận mật khẩu <span style="color: var(--danger);">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px;">
                    <i data-lucide="user-plus" style="width: 20px; height: 20px;"></i>
                    Đăng ký tài khoản
                </button>

            </form>

            <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--text-muted); font-weight: 500;">
                Bạn đã có tài khoản? <a href="login.php" style="color: var(--primary); font-weight: 700;">Đăng nhập ngay</a>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
