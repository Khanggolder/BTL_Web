<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function app_base_path() {
    $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return preg_replace('#/admin$#', '', $base_path);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_roles($pdo, $user_id) {
    if (!$user_id) return [];

    try {
        $stmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function is_admin($pdo) {
    if (!is_logged_in()) return false;

    if (isset($_SESSION['user_roles'])) {
        return in_array('ROLE_ADMIN', $_SESSION['user_roles']);
    }

    $roles = get_user_roles($pdo, $_SESSION['user_id']);
    $_SESSION['user_roles'] = $roles;

    return in_array('ROLE_ADMIN', $roles);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . app_base_path() . "/login.php");
        exit();
    }
}

function require_admin($pdo) {
    require_login();

    if (!is_admin($pdo)) {
        header("Location: " . app_base_path() . "/index.php?error=" . urlencode("Bạn không có quyền truy cập trang quản trị!"));
        exit();
    }
}
?>
