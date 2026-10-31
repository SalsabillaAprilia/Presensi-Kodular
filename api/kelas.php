<?php
// ============================================
// Smart School Attendance - API Kelas
// GET  → daftar kelas
// POST → tambah/ubah/hapus kelas
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: daftar semua kelas ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = $conn->query("
        SELECT k.*,
               (SELECT COUNT(*) FROM users u WHERE u.kelas_id = k.id AND u.role='siswa') AS jumlah_siswa,
               (SELECT u.nama FROM users u WHERE u.id = k.id) AS nama_wali
        FROM kelas k
        ORDER BY k.tingkat, k.nama_kelas
    ");
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    $conn->close();
    sendResponse('success', 'Data kelas', $data);
}

// ---- POST: tambah / ubah / hapus ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json  = json_decode(file_get_contents('php://input'), true);
    $input = !empty($json) ? $json : $_POST;

    $action = isset($input['action']) ? $input['action'] : 'create';

    // HAPUS
    if ($action === 'delete') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID kelas diperlukan'); }

        $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Kelas berhasil dihapus');
    }

    // Validasi input
    $nama  = isset($input['nama_kelas']) ? trim($input['nama_kelas']) : '';
    $tingkat = isset($input['tingkat']) ? trim($input['tingkat']) : '';
    $tahun = isset($input['tahun_ajaran']) ? trim($input['tahun_ajaran']) : '2024/2025';

    if (empty($nama) || empty($tingkat)) {
        $conn->close();
        sendResponse('error', 'Nama kelas dan tingkat wajib diisi');
    }

    // UPDATE
    if ($action === 'update') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID kelas diperlukan'); }

        $stmt = $conn->prepare("UPDATE kelas SET nama_kelas=?, tingkat=?, tahun_ajaran=? WHERE id=?");
        $stmt->bind_param('sssi', $nama, $tingkat, $tahun, $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Kelas berhasil diperbarui');
    }

    // CREATE
    $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat, tahun_ajaran) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $nama, $tingkat, $tahun);
    $stmt->execute();
    $newId = $conn->insert_id;
    $conn->close();
    sendResponse('success', 'Kelas berhasil ditambahkan', ['id' => $newId]);
}

sendResponse('error', 'Method tidak diizinkan');
?>
