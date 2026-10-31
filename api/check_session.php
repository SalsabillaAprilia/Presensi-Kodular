<?php
// ============================================
// Smart Attendance - Check Session Status
// Digunakan frontend untuk memeriksa apakah sesi login masih aktif
// ============================================
require_once 'koneksi.php';
startAppSession();

// Prioritas 1: Sesi Pegawai / Guru
$pegawai = getPegawaiSession();
if ($pegawai !== null) {
    sendResponse('success', 'Sesi pegawai aktif', [
        'logged_in' => true,
        'role_type' => 'pegawai',
        'user'      => $pegawai
    ]);
}

// Prioritas 2: Sesi Admin
$admin = getAdminSession();
if ($admin !== null) {
    sendResponse('success', 'Sesi administrator aktif', [
        'logged_in' => true,
        'role_type' => 'admin',
        'user'      => $admin
    ]);
}

// Tidak ada sesi aktif
sendResponse('error', 'Sesi login tidak aktif atau telah kedaluwarsa', [
    'logged_in' => false
]);
?>
