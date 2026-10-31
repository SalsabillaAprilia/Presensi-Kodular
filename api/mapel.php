<?php
// ============================================
// Smart School Attendance - API Mata Pelajaran
// GET  → daftar mapel
// POST → tambah/ubah/hapus mapel
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: daftar semua mata pelajaran ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = $conn->query("SELECT * FROM mata_pelajaran ORDER BY nama");
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    $conn->close();
    sendResponse('success', 'Data mata pelajaran', $data);
}

// ---- POST: tambah / ubah / hapus ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json  = json_decode(file_get_contents('php://input'), true);
    $input = !empty($json) ? $json : $_POST;

    $action = isset($input['action']) ? $input['action'] : 'create';

    // HAPUS
    if ($action === 'delete') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID mapel diperlukan'); }

        $stmt = $conn->prepare("DELETE FROM mata_pelajaran WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Mata pelajaran berhasil dihapus');
    }

    // Validasi
    $nama = isset($input['nama']) ? trim($input['nama']) : '';
    $kode = isset($input['kode']) ? strtoupper(trim($input['kode'])) : '';

    if (empty($nama) || empty($kode)) {
        $conn->close();
        sendResponse('error', 'Nama dan kode mata pelajaran wajib diisi');
    }

    // UPDATE
    if ($action === 'update') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID mapel diperlukan'); }

        $stmt = $conn->prepare("UPDATE mata_pelajaran SET nama=?, kode=? WHERE id=?");
        $stmt->bind_param('ssi', $nama, $kode, $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Mata pelajaran berhasil diperbarui');
    }

    // CREATE
    $stmt = $conn->prepare("INSERT INTO mata_pelajaran (nama, kode) VALUES (?, ?)");
    $stmt->bind_param('ss', $nama, $kode);
    if (!$stmt->execute()) {
        $conn->close();
        sendResponse('error', 'Kode mata pelajaran sudah digunakan');
    }
    $newId = $conn->insert_id;
    $conn->close();
    sendResponse('success', 'Mata pelajaran berhasil ditambahkan', ['id' => $newId]);
}

sendResponse('error', 'Method tidak diizinkan');
?>
