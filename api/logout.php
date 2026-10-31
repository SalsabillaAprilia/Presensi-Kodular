<?php
// ============================================
// Smart Attendance - API Logout
// Mengakhiri sesi login pengguna dan menghapus data sesi
// ============================================
require_once 'koneksi.php';
startAppSession();

// Hapus variabel sesi pengguna
unset($_SESSION['pegawai_user']);
unset($_SESSION['admin_user']);

// Kosongkan dan hancurkan sesi
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

sendResponse('success', 'Sesi berhasil diakhiri');
?>
