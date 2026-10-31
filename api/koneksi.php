<?php
// ============================================
// Smart Attendance System - Database Config & Auto-Migration
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'absensi_db');

// Timezone WIB
date_default_timezone_set('Asia/Jakarta');

// Header CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Inisialisasi PHP Session seragam dan aman di seluruh aplikasi
 */
function startAppSession() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            ini_set('session.cookie_path', '/');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_maxlifetime', '604800'); // 7 hari
        }
        session_start();
    }
}

/**
 * Mendapatkan data sesi Administrator yang aktif
 */
function getAdminSession() {
    startAppSession();
    if (!empty($_SESSION['admin_user']) && isset($_SESSION['admin_user']['role']) && $_SESSION['admin_user']['role'] === 'admin') {
        return $_SESSION['admin_user'];
    }
    return null;
}

/**
 * Mendapatkan data sesi Pegawai/Guru yang aktif
 */
function getPegawaiSession() {
    startAppSession();
    if (!empty($_SESSION['pegawai_user']) && isset($_SESSION['pegawai_user']['user_id'])) {
        return $_SESSION['pegawai_user'];
    }
    return null;
}

function getConnection() {
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 'error',
                'message' => 'Koneksi database gagal: ' . $conn->connect_error
            ]);
            exit();
        }
        $conn->set_charset('utf8mb4');

        // Otomatis verifikasi dan sinkronkan skema database jika belum ada
        ensureDatabaseSchema($conn);

        return $conn;
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Koneksi database gagal: ' . $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Self-Healing Schema Migration
 * Memastikan tabel dan kolom yang dibutuhkan sistem presensi pegawai sudah tersedia
 */
function ensureDatabaseSchema($conn) {
    // 1. Pastikan tabel absensi tersedia
    $conn->query("
        CREATE TABLE IF NOT EXISTS absensi (
          id           INT AUTO_INCREMENT PRIMARY KEY,
          user_id      INT NOT NULL,
          tanggal      DATE NOT NULL,
          jam_masuk    TIME NULL,
          foto_masuk   VARCHAR(255) NULL,
          lat_masuk    DECIMAL(10, 8) NULL,
          lng_masuk    DECIMAL(11, 8) NULL,
          jarak_masuk  INT NULL,
          jam_pulang   TIME NULL,
          foto_pulang  VARCHAR(255) NULL,
          lat_pulang   DECIMAL(10, 8) NULL,
          lng_pulang   DECIMAL(11, 8) NULL,
          jarak_pulang INT NULL,
          status       ENUM('hadir','terlambat','izin','sakit','alpha') DEFAULT 'hadir',
          keterangan   TEXT NULL,
          created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY unique_user_tanggal (user_id, tanggal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Pastikan tabel settings tersedia
    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          setting_key VARCHAR(50)  NOT NULL UNIQUE,
          setting_val VARCHAR(255) NOT NULL,
          label       VARCHAR(100) NOT NULL,
          updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Default settings jika belum ada
    $defaults = [
        ['nama_sekolah',      'SMK Negeri 1 Nusantara', 'Nama Sekolah / Instansi'],
        ['kampus_lat',        '-6.208800',              'Latitude Lokasi Sekolah'],
        ['kampus_lng',        '106.845600',             'Longitude Lokasi Sekolah'],
        ['max_radius',        '150',                    'Radius Maksimal Toleransi (Meter)'],
        ['batas_jam',         '7',                      'Batas Jam Masuk (Jam)'],
        ['batas_menit',       '15',                     'Batas Jam Masuk (Menit)'],
        ['jam_pulang_mulai',  '15:00',                  'Jam Mulai Diizinkan Absen Pulang']
    ];
    foreach ($defaults as $d) {
        $key = $d[0];
        $val = $d[1];
        $lbl = $d[2];
        $cek = $conn->query("SELECT id FROM settings WHERE setting_key = '$key'");
        if ($cek && $cek->num_rows === 0) {
            $conn->query("INSERT INTO settings (setting_key, setting_val, label) VALUES ('$key', '$val', '$lbl')");
        }
    }

    // 3. Pastikan kolom di tabel users selaras
    $checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
    if ($checkUsers && $checkUsers->num_rows > 0) {
        $cols = [];
        $resCols = $conn->query("SHOW COLUMNS FROM users");
        if ($resCols) {
            while ($r = $resCols->fetch_assoc()) {
                $cols[] = $r['Field'];
            }
        }

        // Jika kolom nip belum ada tapi nomor_induk ada
        if (!in_array('nip', $cols)) {
            $conn->query("ALTER TABLE users ADD COLUMN nip VARCHAR(30) NULL AFTER id");
            if (in_array('nomor_induk', $cols)) {
                $conn->query("UPDATE users SET nip = nomor_induk WHERE nip IS NULL OR nip = ''");
            }
        }

        // Tambah kolom jabatan jika belum ada
        if (!in_array('jabatan', $cols)) {
            $conn->query("ALTER TABLE users ADD COLUMN jabatan VARCHAR(100) NULL AFTER role");
        }

        // Tambah kolom no_hp jika belum ada
        if (!in_array('no_hp', $cols)) {
            $conn->query("ALTER TABLE users ADD COLUMN no_hp VARCHAR(20) NULL AFTER jabatan");
        }

        // Tambah kolom foto_profil jika belum ada
        if (!in_array('foto_profil', $cols)) {
            $conn->query("ALTER TABLE users ADD COLUMN foto_profil VARCHAR(255) NULL AFTER no_hp");
        }

        // Pastikan role enum mendukung 'admin', 'guru', 'pegawai'
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','guru','pegawai','siswa') DEFAULT 'guru'");
    }
}

function sendResponse($status, $message, $data = null) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}
?>
