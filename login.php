<?php
$page_title = "Đăng nhập";
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu!';
    } else {
        try {
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['active']) {
                    $error = 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ hỗ trợ!';
                } else {
                    
                    $_SESSION['user_id'] = $user['id'];
                    
                    
                    $_SESSION['user_roles'] = get_user_roles($pdo, $user['id']);
                    
                    
                    $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
                    unset($_SESSION['redirect_url']);
                    
                    header("Location: " . $redirect_url);
                    exit();
                }
            } else {
                $error = 'Email hoặc mật khẩu không chính xác!';
            }
        } catch (PDOException $e) {
            $error = 'Có lỗi xảy ra trong quá trình xử lý: ' . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card">
            
            <div class="auth-header">
                <h2>Chào mừng trở lại!</h2>
                <p>Đăng nhập vào tài khoản LearnHub của bạn</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            
            <form action="login.php" method="POST">
                
                <div class="form-group">
                    <label for="email">Địa chỉ Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px; height: 50px;">
                    <i data-lucide="log-in" style="width: 20px; height: 20px;"></i>
                    Đăng nhập
                </button>

            </form>

            <div style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-muted); font-weight: 500;">
                Bạn chưa có tài khoản? <a href="signup.php" style="color: var(--primary); font-weight: 700;">Đăng ký ngay</a>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
