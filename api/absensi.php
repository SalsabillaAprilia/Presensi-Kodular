<?php
// ============================================
// Smart Attendance - API Absensi Pegawai & Guru
// Mendukung Cek Status Harian & Alur Bertahap (Masuk & Pulang)
// ============================================
require_once 'koneksi.php';
startAppSession();

$conn = getConnection();
$today = date('Y-m-d');

// ========================================================
// 1. GET: Cek Status Presensi Pegawai Hari Ini & Riwayat
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessUser = getPegawaiSession();
    $userId   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($sessUser ? (int)$sessUser['user_id'] : 0);
    
    if (!$userId) {
        $conn->close();
        sendResponse('error', 'Sesi tidak aktif atau user_id tidak disertakan');
    }

    // Ambil data absensi hari ini untuk user ini
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ?");
    $stmt->bind_param('is', $userId, $today);
    $stmt->execute();
    $resToday = $stmt->get_result()->fetch_assoc();

    $statusHarian = 'belum_masuk'; // default
    if ($resToday) {
        $st = strtolower(isset($resToday['status']) ? $resToday['status'] : '');
        if ($st === 'izin' || $st === 'sakit') {
            $statusHarian = $st;
        } elseif (!empty($resToday['jam_masuk']) && empty($resToday['jam_pulang'])) {
            $statusHarian = 'sudah_masuk';
        } elseif (!empty($resToday['jam_masuk']) && !empty($resToday['jam_pulang'])) {
            $statusHarian = 'sudah_pulang';
        }
    }

    // Ambil 30 riwayat terakhir user ini
    $stmtRiwayat = $conn->prepare(
        "SELECT id, tanggal, jam_masuk, foto_masuk, jam_pulang, foto_pulang, status, jarak_masuk, jarak_pulang, keterangan 
         FROM absensi 
         WHERE user_id = ? 
         ORDER BY tanggal DESC 
         LIMIT 30"
    );
    $stmtRiwayat->bind_param('i', $userId);
    $stmtRiwayat->execute();
    $resRiwayat = $stmtRiwayat->get_result();

    $riwayat = [];
    $summary = array('hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0);

    while ($row = $resRiwayat->fetch_assoc()) {
        $riwayat[] = $row;
        $statusStr = isset($row['status']) ? $row['status'] : 'hadir';
        $st = strtolower($statusStr);
        if (isset($summary[$st])) {
            $summary[$st]++;
        }
    }

    $conn->close();

    sendResponse('success', 'Data presensi berhasil dimuat', [
        'tanggal_hari_ini' => $today,
        'status_hari_ini'  => $statusHarian,
        'detail_hari_ini'  => $resToday,
        'ringkasan'        => $summary,
        'riwayat'          => $riwayat
    ]);
}

// ========================================================
// 2. POST: Transaksi Presensi (Absen Masuk / Absen Pulang)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonDecoded = json_decode($rawInput, true);
    $input = !empty($jsonDecoded) ? $jsonDecoded : $_POST;

    $sessUser  = getPegawaiSession();
    $userId    = ($sessUser !== null) ? (int)$sessUser['user_id'] : (isset($input['user_id']) ? (int)$input['user_id'] : 0);
    $tipe      = isset($input['tipe']) ? strtolower(trim($input['tipe'])) : 'masuk'; // 'masuk', 'pulang', 'izin', 'sakit'

    // ==========================================
    // ALUR KHUSUS: PENGAJUAN IZIN / SAKIT (Bebas Radius GPS)
    // ==========================================
    if ($tipe === 'izin' || $tipe === 'sakit') {
        if (!$userId) {
            $conn->close();
            sendResponse('error', 'Sesi login tidak aktif atau user_id tidak disertakan');
        }

        $keterangan = trim(isset($input['keterangan']) ? $input['keterangan'] : '');
        if (empty($keterangan)) {
            $conn->close();
            sendResponse('error', 'Alasan / keterangan ' . ucfirst($tipe) . ' wajib diisi.');
        }

        // Cek apakah sudah ada catatan presensi hari ini
        $cekStmt = $conn->prepare("SELECT id, status, jam_masuk FROM absensi WHERE user_id = ? AND tanggal = ?");
        $cekStmt->bind_param('is', $userId, $today);
        $cekStmt->execute();
        $existing = $cekStmt->get_result()->fetch_assoc();

        if ($existing) {
            $conn->close();
            sendResponse('error', 'Anda sudah memiliki catatan presensi hari ini (' . strtoupper($existing['status']) . ').');
        }

        $nowTime = date('H:i:s');
        $statusDb = ($tipe === 'sakit') ? 'sakit' : 'izin';

        $insStmt = $conn->prepare(
            "INSERT INTO absensi (user_id, tanggal, jam_masuk, status, keterangan) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $insStmt->bind_param('issss', $userId, $today, $nowTime, $statusDb, $keterangan);
        $insStmt->execute();

        $conn->close();
        sendResponse('success', 'Pengajuan ' . ucfirst($statusDb) . ' berhasil diajukan.', [
            'status'     => $statusDb,
            'keterangan' => $keterangan,
            'jam_masuk'  => $nowTime
        ]);
    }

    $latitude  = isset($input['latitude']) ? (float)$input['latitude'] : 0;
    $longitude = isset($input['longitude']) ? (float)$input['longitude'] : 0;
    $foto      = isset($input['foto']) ? $input['foto'] : '';

    if (!$userId || !$latitude || !$longitude) {
        $conn->close();
        sendResponse('error', 'Sesi tidak aktif atau data presensi tidak lengkap');
    }

    // Ambil konfigurasi sekolah dari tabel settings
    $cfg = [];
    $resCfg = $conn->query("SELECT setting_key, setting_val FROM settings");
    if ($resCfg) {
        while ($r = $resCfg->fetch_assoc()) {
            $cfg[$r['setting_key']] = $r['setting_val'];
        }
    }

    $kampusLat  = isset($cfg['kampus_lat'])  ? (float)$cfg['kampus_lat']  : -6.2088;
    $kampusLng  = isset($cfg['kampus_lng'])  ? (float)$cfg['kampus_lng']  : 106.8456;
    $maxRadius  = isset($cfg['max_radius'])  ? (int)$cfg['max_radius']    : 150;
    $batasJam   = isset($cfg['batas_jam'])   ? (int)$cfg['batas_jam']     : 7;
    $batasMenit = isset($cfg['batas_menit']) ? (int)$cfg['batas_menit']   : 15;

    // Hitung jarak menggunakan Haversine Formula
    function hitungJarak($lat1, $lng1, $lat2, $lng2) {
        $R    = 6371000; // meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) * sin($dLat / 2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLng / 2) * sin($dLng / 2);
        $c    = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    $jarak = hitungJarak($latitude, $longitude, $kampusLat, $kampusLng);

    // Validasi Geofencing
    if ($jarak > $maxRadius) {
        $conn->close();
        sendResponse('error', 'Anda berada di luar area sekolah! Jarak: ' . round($jarak) . ' meter (Maksimal toleransi: ' . $maxRadius . ' meter)');
    }

    // Proses simpan foto selfie
    $fotoPath = '';
    if (!empty($foto)) {
        $fotoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $foto));
        if ($fotoData !== false) {
            $fotoName = 'selfie_' . $userId . '_' . $tipe . '_' . time() . '.jpg';
            $fotoDir  = __DIR__ . '/../uploads/';
            if (!is_dir($fotoDir)) mkdir($fotoDir, 0755, true);
            file_put_contents($fotoDir . $fotoName, $fotoData);
            $fotoPath = 'uploads/' . $fotoName;
        }
    }

    $nowTime = date('H:i:s');
    $jarakInt = (int)round($jarak);

    // Cek apakah sudah ada record absensi hari ini
    $cekStmt = $conn->prepare("SELECT id, jam_masuk, jam_pulang FROM absensi WHERE user_id = ? AND tanggal = ?");
    $cekStmt->bind_param('is', $userId, $today);
    $cekStmt->execute();
    $existing = $cekStmt->get_result()->fetch_assoc();

    // ==========================================
    // ALUR 1: ABSEN MASUK
    // ==========================================
    if ($tipe === 'masuk') {
        if ($existing && !empty($existing['jam_masuk'])) {
            $conn->close();
            sendResponse('error', 'Anda sudah melakukan Absen Masuk hari ini pada pukul ' . substr($existing['jam_masuk'], 0, 5));
        }

        // Tentukan status kehadiran (Hadir vs Terlambat)
        $jamNow   = (int)date('H');
        $menitNow = (int)date('i');
        $totalMenitNow   = $jamNow * 60 + $menitNow;
        $totalMenitBatas = $batasJam * 60 + $batasMenit;

        $status = ($totalMenitNow <= $totalMenitBatas) ? 'hadir' : 'terlambat';

        $insertStmt = $conn->prepare(
            "INSERT INTO absensi (user_id, tanggal, jam_masuk, foto_masuk, lat_masuk, lng_masuk, jarak_masuk, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insertStmt->bind_param('isssddis', $userId, $today, $nowTime, $fotoPath, $latitude, $longitude, $jarakInt, $status);
        
        if ($insertStmt->execute()) {
            $insertId = $conn->insert_id;
            $conn->close();
            sendResponse('success', 'Absen Masuk berhasil dicatat!', [
                'absensi_id' => $insertId,
                'tipe'       => 'masuk',
                'jam'        => substr($nowTime, 0, 5),
                'status'     => $status,
                'jarak'      => $jarakInt . 'm',
                'foto'       => $fotoPath
            ]);
        } else {
            $err = $conn->error;
            $conn->close();
            sendResponse('error', 'Gagal menyimpan absen masuk: ' . $err);
        }
    }

    // ==========================================
    // ALUR 2: ABSEN PULANG
    // ==========================================
    if ($tipe === 'pulang') {
        if (!$existing || empty($existing['jam_masuk'])) {
            $conn->close();
            sendResponse('error', 'Anda belum melakukan Absen Masuk. Absen Pulang hanya dapat dilakukan setelah Absen Masuk.');
        }

        if (!empty($existing['jam_pulang'])) {
            $conn->close();
            sendResponse('error', 'Anda sudah melakukan Absen Pulang hari ini pada pukul ' . substr($existing['jam_pulang'], 0, 5));
        }

        $updateStmt = $conn->prepare(
            "UPDATE absensi 
             SET jam_pulang = ?, foto_pulang = ?, lat_pulang = ?, lng_pulang = ?, jarak_pulang = ? 
             WHERE id = ?"
        );
        $updateStmt->bind_param('ssddii', $nowTime, $fotoPath, $latitude, $longitude, $jarakInt, $existing['id']);

        if ($updateStmt->execute()) {
            $conn->close();
            sendResponse('success', 'Absen Pulang berhasil dicatat! Terima kasih atas dedikasi Anda hari ini.', [
                'absensi_id' => $existing['id'],
                'tipe'       => 'pulang',
                'jam'        => substr($nowTime, 0, 5),
                'jarak'      => $jarakInt . 'm',
                'foto'       => $fotoPath
            ]);
        } else {
            $err = $conn->error;
            $conn->close();
            sendResponse('error', 'Gagal menyimpan absen pulang: ' . $err);
        }
    }

    $conn->close();
    sendResponse('error', 'Tipe absensi tidak valid (harus masuk atau pulang)');
}

sendResponse('error', 'Method tidak diizinkan');
?>
