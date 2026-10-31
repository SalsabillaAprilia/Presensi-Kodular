<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>Tes Koneksi Database InfinityFree</h2>";
echo "<hr>";

// ========================================================
// LANGKAH 1: Cek versi PHP
// ========================================================
echo "<p><b>PHP Version:</b> " . phpversion() . "</p>";

// ========================================================
// LANGKAH 2: Cek apakah mysqli tersedia
// ========================================================
if (!function_exists('mysqli_connect')) {
    echo "<p style='color:red'><b>GAGAL:</b> Ekstensi MySQLi tidak aktif di server ini!</p>";
    exit;
}
echo "<p style='color:green'><b>OK:</b> Ekstensi MySQLi tersedia.</p>";

// ========================================================
// LANGKAH 3: Coba koneksi database
// ========================================================
// GANTI NILAI-NILAI INI SESUAI DATA INFINITYFREE ANDA:
$db_host = 'sql304.infinityfree.com';       // Ganti dengan MySQL Hostname Anda
$db_user = 'if0_38123456';                  // Ganti dengan MySQL Username Anda
$db_pass = 'password_anda';                 // Ganti dengan Password Anda
$db_name = 'if0_38123456_absensi';          // Ganti dengan Nama Database Lengkap Anda
$db_port = 3306;

echo "<p><b>Mencoba koneksi ke:</b> {$db_host} / {$db_name} ...</p>";

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    echo "<p style='color:red'><b>GAGAL KONEKSI:</b> " . htmlspecialchars($conn->connect_error) . "</p>";
    echo "<p><b>Error Number:</b> " . $conn->connect_errno . "</p>";
    
    if ($conn->connect_errno === 2002) {
        echo "<p style='color:orange'>Kemungkinan penyebab: DB_HOST salah. Pastikan mengisi MySQL Hostname dari dashboard InfinityFree (bukan localhost).</p>";
    } elseif (strpos($conn->connect_error, 'Access denied') !== false) {
        echo "<p style='color:orange'>Kemungkinan penyebab: Username atau Password database salah.</p>";
    } elseif (strpos($conn->connect_error, 'Unknown database') !== false) {
        echo "<p style='color:orange'>Kemungkinan penyebab: Nama database salah. Pastikan menggunakan nama lengkap termasuk prefix (contoh: if0_38xxxxxx_absensi).</p>";
    }
    exit;
}

echo "<p style='color:green'><b>KONEKSI BERHASIL!</b></p>";

// ========================================================
// LANGKAH 4: Cek apakah tabel users ada
// ========================================================
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<p><b>Tabel yang ditemukan:</b></p><ul>";
    while ($row = $result->fetch_row()) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>Gagal membaca daftar tabel: " . htmlspecialchars($conn->error) . "</p>";
}

// ========================================================
// LANGKAH 5: Cek apakah tabel users berisi data
// ========================================================
$result2 = $conn->query("SELECT id, nip, nama, role FROM users LIMIT 5");
if ($result2 && $result2->num_rows > 0) {
    echo "<p style='color:green'><b>Tabel users ditemukan! Data:</b></p>";
    echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>ID</th><th>NIP</th><th>Nama</th><th>Role</th></tr>";
    while ($row = $result2->fetch_assoc()) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . htmlspecialchars($row['nip']) . "</td><td>" . htmlspecialchars($row['nama']) . "</td><td>" . $row['role'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Tabel 'users' kosong atau tidak ditemukan. Pastikan file absensi_db.sql sudah diimport via phpMyAdmin!</p>";
}

// ========================================================
// LANGKAH 6: Cek file koneksi.php
// ========================================================
echo "<hr><h3>Cek File koneksi.php</h3>";
$koneksiPath = __DIR__ . '/api/koneksi.php';
if (file_exists($koneksiPath)) {
    echo "<p style='color:green'><b>OK:</b> File api/koneksi.php ditemukan.</p>";
    
    // Baca isi file untuk cek kredensial
    $isi = file_get_contents($koneksiPath);
    
    if (strpos($isi, "'localhost'") !== false) {
        echo "<p style='color:red'><b>MASALAH DITEMUKAN:</b> DB_HOST di api/koneksi.php masih berisi <code>'localhost'</code>! Ganti dengan MySQL Hostname InfinityFree Anda.</p>";
    }
    if (strpos($isi, "'root'") !== false) {
        echo "<p style='color:red'><b>MASALAH DITEMUKAN:</b> DB_USER di api/koneksi.php masih berisi <code>'root'</code>! Ganti dengan MySQL Username InfinityFree Anda.</p>";
    }
    if (strpos($isi, "DB_PASS', '')") !== false) {
        echo "<p style='color:red'><b>MASALAH DITEMUKAN:</b> DB_PASS di api/koneksi.php masih kosong <code>''</code>! Isi dengan password InfinityFree Anda.</p>";
    }
    if (strpos($isi, "'absensi_db'") !== false) {
        echo "<p style='color:red'><b>MASALAH DITEMUKAN:</b> DB_NAME di api/koneksi.php masih berisi <code>'absensi_db'</code>! Ganti dengan nama database lengkap dari InfinityFree (dengan prefix).</p>";
    }
    
    if (strpos($isi, 'Throwable') !== false) {
        echo "<p style='color:orange'><b>PERINGATAN:</b> File koneksi.php menggunakan <code>catch (Throwable)</code>, sebaiknya diganti <code>catch (Exception)</code> agar kompatibel dengan semua versi PHP.</p>";
    }
} else {
    echo "<p style='color:red'><b>GAGAL:</b> File api/koneksi.php TIDAK DITEMUKAN di server!</p>";
}

$conn->close();
echo "<hr><p style='color:green; font-size:16px'><b>Tes selesai.</b></p>";
echo "<p><i>HAPUS file test_db.php ini setelah selesai debugging demi keamanan!</i></p>";
?>
