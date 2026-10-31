<?php
// ============================================
// Smart Attendance - API Settings
// GET  → Ambil konfigurasi sistem & geofencing
// POST → Simpan pengaturan oleh Admin
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: Ambil semua pengaturan ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res  = $conn->query("SELECT setting_key, setting_val, label FROM settings ORDER BY id");
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[$row['setting_key']] = [
                'value' => $row['setting_val'],
                'label' => $row['label']
            ];
        }
    }
    $conn->close();
    sendResponse('success', 'Data settings berhasil diambil', $data);
}

// ---- POST: Simpan pengaturan oleh Admin ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!getAdminSession()) {
        $conn->close();
        sendResponse('error', 'Akses ditolak: Sesi Administrator tidak aktif atau telah kedaluwarsa. Silakan login kembali.');
    }

    $raw = file_get_contents('php://input');
    $jsonDecoded = json_decode($raw, true);
    $input = !empty($jsonDecoded) ? $jsonDecoded : $_POST;

    $allowed = [
        'nama_sekolah'     => 'Nama Sekolah / Instansi',
        'kampus_lat'       => 'Latitude Lokasi Sekolah',
        'kampus_lng'       => 'Longitude Lokasi Sekolah',
        'max_radius'       => 'Radius Maksimal Toleransi (Meter)',
        'batas_jam'        => 'Batas Jam Masuk (Jam)',
        'batas_menit'      => 'Batas Jam Masuk (Menit)',
        'jam_pulang_mulai' => 'Jam Mulai Diizinkan Absen Pulang'
    ];

    $updated = 0;

    foreach ($allowed as $key => $label) {
        if (!isset($input[$key])) continue;

        $val = trim((string)$input[$key]);
        if ($val === '') continue;

        // Cek apakah key sudah ada
        $cek = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
        $cek->bind_param('s', $key);
        $cek->execute();

        if ($cek->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE settings SET setting_val = ? WHERE setting_key = ?");
            $stmt->bind_param('ss', $val, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_val, label) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $key, $val, $label);
        }
        $stmt->execute();
        $updated++;
    }

    $conn->close();

    if ($updated === 0) {
        sendResponse('error', 'Tidak ada data pengaturan yang diperbarui');
    }
    sendResponse('success', "$updated pengaturan berhasil disimpan");
}

sendResponse('error', 'Method tidak diizinkan');
?>
