<?php
// ============================================
// Smart School Attendance - API Jadwal
// GET  → daftar jadwal (filter by kelas/guru/hari)
// POST → tambah/ubah/hapus jadwal
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: daftar jadwal ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $kelasId = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
    $guruId  = isset($_GET['guru_id'])  ? (int)$_GET['guru_id']  : 0;
    $hari    = isset($_GET['hari'])     ? $_GET['hari']          : '';

    $where = [];
    $params = [];
    $types  = '';

    if ($kelasId) { $where[] = 'j.kelas_id = ?'; $params[] = $kelasId; $types .= 'i'; }
    if ($guruId)  { $where[] = 'j.guru_id = ?';  $params[] = $guruId;  $types .= 'i'; }
    if ($hari)    { $where[] = 'j.hari = ?';      $params[] = $hari;    $types .= 's'; }

    $sql = "
        SELECT j.*, k.nama_kelas, k.tingkat,
               m.nama AS mapel, m.kode AS kode_mapel,
               u.nama AS nama_guru
        FROM jadwal j
        JOIN kelas k ON j.kelas_id = k.id
        JOIN mata_pelajaran m ON j.mapel_id = m.id
        JOIN users u ON j.guru_id = u.id
    ";
    if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY FIELD(j.hari,"Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"), j.jam_mulai';

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $conn->close();
    sendResponse('success', 'Data jadwal', $data);
}

// ---- POST: tambah / ubah / hapus ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json  = json_decode(file_get_contents('php://input'), true);
    $input = !empty($json) ? $json : $_POST;

    $action = isset($input['action']) ? $input['action'] : 'create';

    // HAPUS
    if ($action === 'delete') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID jadwal diperlukan'); }

        $stmt = $conn->prepare("DELETE FROM jadwal WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Jadwal berhasil dihapus');
    }

    // Validasi
    $kelasId    = isset($input['kelas_id'])    ? (int)$input['kelas_id']    : 0;
    $mapelId    = isset($input['mapel_id'])    ? (int)$input['mapel_id']    : 0;
    $guruId     = isset($input['guru_id'])     ? (int)$input['guru_id']     : 0;
    $hari       = isset($input['hari'])        ? trim($input['hari'])       : '';
    $jamMulai   = isset($input['jam_mulai'])   ? trim($input['jam_mulai'])  : '';
    $jamSelesai = isset($input['jam_selesai']) ? trim($input['jam_selesai']) : '';

    if (!$kelasId || !$mapelId || !$guruId || !$hari || !$jamMulai || !$jamSelesai) {
        $conn->close();
        sendResponse('error', 'Semua field jadwal wajib diisi');
    }

    // UPDATE
    if ($action === 'update') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID jadwal diperlukan'); }

        $stmt = $conn->prepare("UPDATE jadwal SET kelas_id=?, mapel_id=?, guru_id=?, hari=?, jam_mulai=?, jam_selesai=? WHERE id=?");
        $stmt->bind_param('iiisssi', $kelasId, $mapelId, $guruId, $hari, $jamMulai, $jamSelesai, $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Jadwal berhasil diperbarui');
    }

    // CREATE
    $stmt = $conn->prepare("INSERT INTO jadwal (kelas_id, mapel_id, guru_id, hari, jam_mulai, jam_selesai) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('iiisss', $kelasId, $mapelId, $guruId, $hari, $jamMulai, $jamSelesai);
    $stmt->execute();
    $newId = $conn->insert_id;
    $conn->close();
    sendResponse('success', 'Jadwal berhasil ditambahkan', ['id' => $newId]);
}

sendResponse('error', 'Method tidak diizinkan');
?>
