<?php
// ============================================
// Smart Attendance - Logout Admin
// ============================================
require_once '../api/koneksi.php';
startAppSession();
unset($_SESSION['admin_user']);
$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: login.php');
exit();
?>
