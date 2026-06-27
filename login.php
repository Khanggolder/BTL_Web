<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$error = '';
$success = '';

if (isset($_GET['success'])) {
    $success = trim($_GET['success']);
    $success = preg_replace('/\s*tại đây\.?\s*$/u', '', $success);
    $success = htmlspecialchars($success);
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

$page_title = "Đăng nhập";
require_once __DIR__ . '/includes/header.php';
?>
<main class="learnhub-login-page">
    <section class="learnhub-login-layout">
        <div class="learnhub-login-panel">
            <a href="index.php" class="learnhub-login-brand">
                <i data-lucide="book-open"></i>
                <span>LearnHub</span>
            </a>

            <div class="learnhub-login-heading">
                <span>Không gian học tập</span>
                <h1>Đăng nhập LearnHub</h1>
                <p>Tiếp tục khóa học của bạn, xem tiến độ và hoàn thành các bài quiz trong một nơi duy nhất.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger learnhub-login-alert">
                    <i data-lucide="alert-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success learnhub-login-alert">
                    <i data-lucide="check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="learnhub-login-form">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="student@learnhub.com" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>

                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>

                <button type="submit" class="learnhub-login-submit">
                    <i data-lucide="log-in"></i>
                    Đăng nhập
                </button>
            </form>

            <p class="learnhub-login-note">Bạn chưa có tài khoản? <a href="signup.php">Đăng ký ngay</a></p>
        </div>

        <div class="learnhub-login-visual">
            <img src="assets/images/login.webp" alt="Không gian học tập LearnHub">
            <div>
                <h2>Học tập mạch lạc hơn mỗi ngày.</h2>
                <p>Theo dõi bài học, ghi chú, quiz và tiến độ cá nhân trong cùng một trải nghiệm.</p>
            </div>
        </div>
    </section>
</main>

<style>
.learnhub-login-page{min-height:calc(100vh - 80px);display:grid;place-items:center;padding:40px 24px;background:linear-gradient(135deg,#eef6ff 0%,#f8fafc 46%,#e0f2fe 100%)}.learnhub-login-layout{display:grid;grid-template-columns:minmax(360px,.48fr) minmax(0,.52fr);width:min(1180px,100%);min-height:620px;overflow:hidden;border:1px solid #dbeafe;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.14)}.learnhub-login-panel{display:flex;flex-direction:column;justify-content:center;padding:48px 52px;background:#fff}.learnhub-login-brand{display:inline-flex;align-items:center;gap:10px;width:max-content;color:#2563eb;font-size:24px;font-weight:900}.learnhub-login-brand svg{width:29px;height:29px}.learnhub-login-heading{margin:30px 0 24px}.learnhub-login-heading span{display:inline-block;color:#2563eb;font-size:13px;font-weight:900;text-transform:uppercase}.learnhub-login-heading h1{margin:10px 0 12px;color:#0f172a;font-size:36px;line-height:1.16;font-weight:900}.learnhub-login-heading p{color:#64748b;line-height:1.72;margin:0}.learnhub-login-form{display:grid;gap:12px}.learnhub-login-form label{color:#0f172a;font-weight:800}.learnhub-login-form input{width:100%;height:48px;border:1px solid #dbe3ef;border-radius:10px;background:#f8fafc;color:#0f172a;outline:none;padding:0 15px;transition:border-color .2s ease,box-shadow .2s ease,background .2s ease}.learnhub-login-form input:focus{border-color:#2563eb;background:#fff;box-shadow:0 0 0 4px rgba(37,99,235,.12)}.learnhub-login-options{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:8px 0 12px;font-size:14px}.learnhub-login-options label{display:flex;align-items:center;gap:8px;color:#64748b}.learnhub-login-options input{width:16px;height:16px}.learnhub-login-options a,.learnhub-login-note a{color:#2563eb;font-weight:900}.learnhub-login-submit{min-height:48px;border:1px solid #2563eb;border-radius:10px;background:#2563eb;color:#fff;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:900;cursor:pointer;transition:background .2s ease,border-color .2s ease,box-shadow .2s ease}.learnhub-login-submit:hover{background:#1d4ed8;border-color:#1d4ed8;box-shadow:0 12px 24px rgba(37,99,235,.22)}.learnhub-login-submit svg,.learnhub-login-alert svg{width:18px;height:18px}.learnhub-login-note{margin:24px 0 0;color:#64748b;font-size:14px;text-align:center;font-weight:600}.learnhub-login-alert{display:flex;align-items:flex-start;gap:8px;margin-bottom:16px;line-height:1.5}.learnhub-login-visual{position:relative;display:flex;flex-direction:column;justify-content:flex-end;margin:16px;border-radius:12px;background:#0f172a;color:#fff;overflow:hidden}.learnhub-login-visual img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center center;filter:contrast(1.05) saturate(1.04)}.learnhub-login-visual::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,23,42,.08) 0%,rgba(15,23,42,.34) 42%,rgba(15,23,42,.88) 100%)}.learnhub-login-visual div{position:relative;z-index:1;padding:34px;max-width:620px}.learnhub-login-visual h2{margin:0 0 10px;font-size:28px;line-height:1.18;font-weight:900}.learnhub-login-visual p{color:#dbeafe;margin:0;line-height:1.65}@media(max-width:980px){.learnhub-login-page{padding:24px 18px}.learnhub-login-layout{grid-template-columns:1fr;min-height:auto}.learnhub-login-panel{padding:36px 30px}.learnhub-login-visual{min-height:260px;order:-1}.learnhub-login-visual div{padding:26px}.learnhub-login-heading h1{font-size:32px}}@media(max-width:640px){.learnhub-login-page{box-sizing:border-box;display:grid;place-items:start center;min-height:calc(100dvh - 72px);overflow-x:hidden;padding:76px max(14px,calc((100vw - 390px)/2 + 14px)) 44px;background:linear-gradient(180deg,#f8f7ff 0%,#f8fafc 100%)}.learnhub-login-layout{width:92%;max-width:360px;min-height:auto;border:0;border-radius:0;background:transparent;box-shadow:none;overflow:visible}.learnhub-login-visual{display:none}.learnhub-login-panel{box-sizing:border-box;width:100%;min-height:0;justify-content:flex-start;padding:28px 24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 18px 34px rgba(15,23,42,.16)}.learnhub-login-brand{display:none}.learnhub-login-heading{margin:0 0 22px;text-align:center}.learnhub-login-heading span{display:none}.learnhub-login-heading h1{font-size:25px;line-height:1.2;margin:0 0 10px}.learnhub-login-heading p{font-size:14px;line-height:1.55;max-width:72%;margin:0 auto}.learnhub-login-form{gap:9px}.learnhub-login-form label{font-size:13px}.learnhub-login-form input{height:46px;border-radius:6px;font-size:15px;background:#fff}.learnhub-login-submit{min-height:46px;margin-top:10px;border-radius:7px;background:linear-gradient(135deg,#2563eb,#7c3aed);border-color:#2563eb}.learnhub-login-note{margin-top:18px;font-size:13px}.learnhub-login-alert{align-items:flex-start;line-height:1.45;font-size:13px;padding:10px 12px}}@media(max-width:380px){.learnhub-login-page{padding-top:58px;padding-left:3.5%;padding-right:3.5%}.learnhub-login-layout{width:94%}.learnhub-login-panel{padding:24px 6%}.learnhub-login-heading h1{font-size:23px}.learnhub-login-heading p{font-size:13px}.learnhub-login-form input{height:44px}.learnhub-login-submit{min-height:44px}}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
