<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';


require_login();

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
$payment_labels = [
    'BANK_TRANSFER' => 'Ngân hàng',
    'MOMO' => 'Ví MoMo',
    'PAYPAL' => 'PayPal',
    'VNPAY' => 'VNPay',
    'CREDIT_CARD' => 'Thẻ tín dụng',
];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');
    
    if (empty($full_name)) {
        $error_msg = 'Họ và tên không được để trống!';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $avatar, $user_id]);
            $success_msg = 'Cập nhật thông tin cá nhân thành công!';
        } catch (PDOException $e) {
            $error_msg = 'Lỗi cập nhật: ' . $e->getMessage();
        }
    }
}


try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}


$enrolled_courses = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.user_id = ? AND e.active = 1
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$user_id]);
    $enrolled_courses = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $error_msg = 'Không thể tải khóa học đã mua.';
}


$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM orders 
        WHERE user_id = ? 
        ORDER BY id DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $error_msg = 'Không thể tải lịch sử đơn hàng.';
}

$page_title = "Hồ sơ của tôi";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section profile-page" style="background-color: var(--bg-main); min-height: 85vh; padding: 40px 0;">
    <div class="container profile-container">
        
        <div class="profile-layout">
            
            
            <div style="background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 32px; height: fit-content; box-shadow: var(--shadow-sm);">
                
                <div style="text-align: center; margin-bottom: 24px;">
                    
                    <div class="profile-avatar" style="width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 800; margin: 0 auto 16px; border: 3px solid white; box-shadow: var(--shadow-md); overflow: hidden;">
                        <?php 
                            $avatar_char = mb_substr($user['full_name'] ?? 'U', 0, 1, 'UTF-8');
                            $profile_avatar_url = trim($user['avatar'] ?? '');
                        ?>
                        <?php if ($profile_avatar_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($profile_avatar_url); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        <span style="width:100%;height:100%;align-items:center;justify-content:center;<?php echo $profile_avatar_url !== '' ? 'display:none;' : 'display:flex;'; ?>"><?php echo mb_strtoupper($avatar_char, 'UTF-8'); ?></span>
                    </div>
                    <h3 style="font-weight: 800; font-size: 20px; color: var(--text-main); margin-bottom: 4px;"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p style="color: var(--text-muted); font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success" style="padding: 10px 14px; font-size: 13px;">
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger" style="padding: 10px 14px; font-size: 13px;">
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                
                <form action="profile.php" method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="full_name" style="font-size: 13px;">Họ và tên</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required style="height: 42px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="phone" style="font-size: 13px;">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Chưa cập nhật" style="height: 42px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="avatar" style="font-size: 13px;">Avatar URL</label>
                        <input type="url" id="avatar" name="avatar" class="form-control" value="<?php echo htmlspecialchars($user['avatar'] ?? ''); ?>" placeholder="https://example.com/avatar.jpg" style="height: 42px;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; font-size: 14px; border-radius: var(--radius-sm);">
                        Lưu thông tin cá nhân
                    </button>
                </form>

            </div>

            
            <div>
                
                
                <div style="display: flex; border-bottom: 2px solid var(--border); margin-bottom: 24px; background-color: var(--bg-card); border-radius: var(--radius-sm); padding: 4px;">
                    <button class="tab-btn active" onclick="switchProfileTab('courses', this)" style="flex-grow: 1;">Khóa học đã sở hữu (<?php echo count($enrolled_courses); ?>)</button>
                    <button class="tab-btn" onclick="switchProfileTab('orders', this)" style="flex-grow: 1;">Đơn hàng đã mua (<?php echo count($orders); ?>)</button>
                </div>

                
                <div id="profile-tab-courses" class="profile-tab-content active-panel">
                    <?php if (empty($enrolled_courses)): ?>
                        <div style="text-align: center; padding: 60px 0; background-color: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border);">
                            <i data-lucide="graduation-cap" style="width: 56px; height: 56px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Bạn chưa sở hữu khóa học nào!</h3>
                            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Khám phá danh sách khóa học phong phú của chúng tôi để bắt đầu học.</p>
                            <a href="courses.php" class="btn btn-primary" style="font-size: 13px; padding: 8px 20px;">Tìm kiếm khóa học</a>
                        </div>
                    <?php else: ?>
                        
                        <div class="profile-course-grid">
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="course-card">
                                    <div class="card-banner" style="aspect-ratio: 16/9;">
                                        <img src="<?php echo htmlspecialchars($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>">
                                        <span class="card-tag" style="background-color: var(--success);"><?php echo htmlspecialchars($course['category']); ?></span>
                                    </div>
                                    <div class="card-body" style="padding: 16px;">
                                        <h4 style="font-size: 15px; font-weight: 700; line-height: 1.45; color: var(--text-main); min-height: 44px; margin-bottom: 12px;"><?php echo htmlspecialchars($course['title']); ?></h4>
                                        <a href="learning.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary" style="width: 100%; font-size: 13px; height: 38px; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: var(--radius-sm);">
                                            <i data-lucide="play-circle" style="width: 16px; height: 16px;"></i> Bắt đầu học
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                
                <div id="profile-tab-orders" class="profile-tab-content">
                    <?php if (empty($orders)): ?>
                        <div style="text-align: center; padding: 60px 0; background-color: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border);">
                            <i data-lucide="shopping-bag" style="width: 56px; height: 56px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Không tìm thấy đơn hàng nào!</h3>
                            <p style="color: var(--text-muted); font-size: 14px;">Bạn chưa thực hiện bất kỳ giao dịch đặt khóa học nào.</p>
                        </div>
                    <?php else: ?>
                        
                        <div class="profile-orders-card">
                            <div class="profile-orders-scroll">
                                <table class="profile-orders-table" style="border-collapse: collapse; text-align: left; font-size: 14px;">
                                <thead>
                                    <tr style="background-color: var(--bg-main); border-bottom: 1px solid var(--border); font-weight: 700; color: var(--text-main);">
                                        <th style="padding: 16px 20px;">Mã đơn</th>
                                        <th style="padding: 16px 20px;">Ngày đặt</th>
                                        <th style="padding: 16px 20px;">Tổng tiền</th>
                                        <th style="padding: 16px 20px;">Thanh toán</th>
                                        <th style="padding: 16px 20px;">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr style="border-bottom: 1px solid var(--border); font-weight: 500;">
                                            <td style="padding: 16px 20px;"><strong style="color: var(--primary);"><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                            <td style="padding: 16px 20px; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                            <td style="padding: 16px 20px; font-weight: 700; color: var(--text-main);"><?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ</td>
                                            <td style="padding: 16px 20px; font-size: 12px; font-weight: 600;">
                                                <span style="color:<?php echo $order['payment_method'] === 'MOMO' ? '#a50064' : 'var(--text-main)'; ?>;"><?php echo htmlspecialchars($payment_labels[$order['payment_method']] ?? $order['payment_method']); ?></span>
                                            </td>
                                            <td style="padding: 16px 20px;">
                                                <?php 
                                                    $status = $order['status'];
                                                    if ($status === 'COMPLETED') {
                                                        echo '<span style="background-color: #d1fae5; color: var(--success); padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span style="width:5px;height:5px;background-color:var(--success);border-radius:50%;"></span>Đã hoàn tất</span>';
                                                    } elseif ($status === 'PENDING') {
                                                        echo '<span style="background-color: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span style="width:5px;height:5px;background-color:#d97706;border-radius:50%;"></span>Đang xử lý</span>';
                                                    } else {
                                                        echo '<span style="background-color: #fee2e2; color: var(--danger); padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span style="width:5px;height:5px;background-color:var(--danger);border-radius:50%;"></span>Đã hủy</span>';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>
</div>

<style>
    .profile-container {
        width: min(94%, 1320px); max-width: 100%;
    }

    .profile-layout {
        display: grid;
        grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
        gap: 32px;
        align-items: start;
    }

    .profile-layout > * {
        min-width: 0;
    }

    .profile-course-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
    }

    .profile-orders-card {
        width: 100%;
        max-width: 100%;
        background-color: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .profile-orders-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    .profile-orders-table {
        width: 100%;
        min-width: max-content;
    }

    .profile-orders-table th,
    .profile-orders-table td {
        white-space: nowrap;
    }

    
    .tab-btn {
        flex-grow: 1;
        background: none;
        border: none;
        padding: 14px;
        font-weight: 700;
        font-size: 14px;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: var(--radius-sm);
        transition: var(--transition);
    }
    .tab-btn:hover {
        color: var(--primary);
    }
    .tab-btn.active {
        color: var(--primary);
        background-color: var(--primary-light);
    }
    .profile-tab-content {
        display: none;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .profile-tab-content.active-panel {
        display: block;
    }

    @media (max-width: 1180px) {
        .profile-course-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .profile-layout {
            grid-template-columns: 1fr;
        }

        .profile-orders-scroll {
            padding-bottom: 4px;
        }

        .profile-orders-table {
            min-width: max-content;
        }
    }

    @media (max-width: 640px) {
        .profile-course-grid {
            grid-template-columns: 1fr;
        }

        .profile-orders-table {
            min-width: 100%;
        }
    }
</style>

<script>
    
    function switchProfileTab(tabId, buttonElement) {
        
        const btns = buttonElement.parentNode.querySelectorAll('.tab-btn');
        btns.forEach(b => b.classList.remove('active'));
        
        const contents = document.querySelectorAll('.profile-tab-content');
        contents.forEach(c => c.classList.remove('active-panel'));

        
        buttonElement.classList.add('active');
        document.getElementById('profile-tab-' + tabId).classList.add('active-panel');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
