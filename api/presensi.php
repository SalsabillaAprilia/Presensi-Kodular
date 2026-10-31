<?php
// ============================================
// Smart School Attendance - API Presensi
// GET  → data presensi (filter by jadwal/tanggal/kelas)
// POST → simpan presensi (bulk - guru input semua siswa sekaligus)
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: ambil data presensi ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jadwalId = isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0;
    $tanggal  = isset($_GET['tanggal'])   ? $_GET['tanggal']        : date('Y-m-d');
    $kelasId  = isset($_GET['kelas_id'])  ? (int)$_GET['kelas_id']  : 0;
    $siswaId  = isset($_GET['siswa_id'])  ? (int)$_GET['siswa_id']  : 0;

    // Riwayat presensi siswa
    if ($siswaId) {
        $stmt = $conn->prepare("
            SELECT p.*, j.hari, j.jam_mulai, j.jam_selesai,
                   m.nama AS mapel, m.kode AS kode_mapel,
                   k.nama_kelas
            FROM presensi p
            JOIN jadwal j ON p.jadwal_id = j.id
            JOIN mata_pelajaran m ON j.mapel_id = m.id
            JOIN kelas k ON j.kelas_id = k.id
            WHERE p.siswa_id = ?
            ORDER BY p.tanggal DESC, j.jam_mulai
            LIMIT 100
        ");
        $stmt->bind_param('i', $siswaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        $conn->close();
        sendResponse('success', 'Riwayat presensi siswa', $data);
    }

    // Data presensi per jadwal
    if ($jadwalId) {
        $stmt = $conn->prepare("
            SELECT p.*, u.nama AS nama_siswa, u.nomor_induk
            FROM presensi p
            JOIN users u ON p.siswa_id = u.id
            WHERE p.jadwal_id = ? AND p.tanggal = ?
            ORDER BY u.nama
        ");
        $stmt->bind_param('is', $jadwalId, $tanggal);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        $conn->close();
        sendResponse('success', 'Data presensi', $data);
    }

    // Presensi per kelas hari ini
    if ($kelasId) {
        $stmt = $conn->prepare("
            SELECT p.*, u.nama AS nama_siswa, u.nomor_induk,
                   j.hari, j.jam_mulai, j.jam_selesai,
                   m.nama AS mapel, m.kode AS kode_mapel
            FROM presensi p
            JOIN users u ON p.siswa_id = u.id
            JOIN jadwal j ON p.jadwal_id = j.id
            JOIN mata_pelajaran m ON j.mapel_id = m.id
            WHERE j.kelas_id = ? AND p.tanggal = ?
            ORDER BY j.jam_mulai, u.nama
        ");
        $stmt->bind_param('is', $kelasId, $tanggal);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        $conn->close();
        sendResponse('success', 'Data presensi kelas', $data);
    }

    $conn->close();
    sendResponse('error', 'Parameter jadwal_id, kelas_id, atau siswa_id diperlukan');
}

// ---- POST: simpan presensi (bulk) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json  = json_decode(file_get_contents('php://input'), true);
    $input = !empty($json) ? $json : $_POST;

    $jadwalId    = isset($input['jadwal_id'])    ? (int)$input['jadwal_id']    : 0;
    $tanggal     = isset($input['tanggal'])      ? $input['tanggal']           : date('Y-m-d');
    $dicatatOleh = isset($input['dicatat_oleh']) ? (int)$input['dicatat_oleh'] : 0;
    $dataPresensi = isset($input['data'])        ? $input['data']              : [];

    if (!$jadwalId || !$dicatatOleh || empty($dataPresensi)) {
        $conn->close();
        sendResponse('error', 'Data tidak lengkap (jadwal_id, dicatat_oleh, data wajib)');
    }

    // Validasi jadwal exists
    $cekJadwal = $conn->prepare("SELECT id FROM jadwal WHERE id = ?");
    $cekJadwal->bind_param('i', $jadwalId);
    $cekJadwal->execute();
    if ($cekJadwal->get_result()->num_rows === 0) {
        $conn->close();
        sendResponse('error', 'Jadwal tidak ditemukan');
    }

    $inserted = 0;
    $updated  = 0;

    foreach ($dataPresensi as $item) {
        $siswaId    = isset($item['siswa_id'])   ? (int)$item['siswa_id']    : 0;
        $status     = isset($item['status'])     ? $item['status']           : 'hadir';
        $keterangan = isset($item['keterangan']) ? trim($item['keterangan']) : '';

        if (!$siswaId) continue;
        if (!in_array($status, ['hadir', 'izin', 'sakit', 'alpha'])) $status = 'hadir';

        // Cek apakah sudah ada
        $cek = $conn->prepare("SELECT id FROM presensi WHERE jadwal_id=? AND siswa_id=? AND tanggal=?");
        $cek->bind_param('iis', $jadwalId, $siswaId, $tanggal);
        $cek->execute();
        $existing = $cek->get_result();

        if ($existing->num_rows > 0) {
            // Update existing
            $upd = $conn->prepare("UPDATE presensi SET status=?, keterangan=?, dicatat_oleh=? WHERE jadwal_id=? AND siswa_id=? AND tanggal=?");
            $upd->bind_param('ssiiis', $status, $keterangan, $dicatatOleh, $jadwalId, $siswaId, $tanggal);
            $upd->execute();
            $updated++;
        } else {
            // Insert new
            $ins = $conn->prepare("INSERT INTO presensi (jadwal_id, siswa_id, tanggal, status, keterangan, dicatat_oleh) VALUES (?,?,?,?,?,?)");
            $ins->bind_param('iisssi', $jadwalId, $siswaId, $tanggal, $status, $keterangan, $dicatatOleh);
            $ins->execute();
            $inserted++;
        }
    }

    $conn->close();
    sendResponse('success', "Presensi berhasil disimpan ($inserted baru, $updated diperbarui)", [
        'inserted' => $inserted,
        'updated'  => $updated
    ]);
}

sendResponse('error', 'Method tidak diizinkan');
?>
