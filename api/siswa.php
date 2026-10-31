<?php
// ============================================
// Smart School Attendance - API Siswa
// GET  → daftar siswa (filter by kelas_id)
// POST → tambah/ubah/hapus siswa
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: daftar siswa ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $kelasId = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

    if ($kelasId) {
        $stmt = $conn->prepare("
            SELECT u.id, u.nama, u.nomor_induk, u.kelas_id, u.no_hp_ortu, u.created_at,
                   k.nama_kelas, k.tingkat
            FROM users u
            LEFT JOIN kelas k ON u.kelas_id = k.id
            WHERE u.role = 'siswa' AND u.kelas_id = ?
            ORDER BY u.nama
        ");
        $stmt->bind_param('i', $kelasId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("
            SELECT u.id, u.nama, u.nomor_induk, u.kelas_id, u.no_hp_ortu, u.created_at,
                   k.nama_kelas, k.tingkat
            FROM users u
            LEFT JOIN kelas k ON u.kelas_id = k.id
            WHERE u.role = 'siswa'
            ORDER BY k.nama_kelas, u.nama
        ");
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $conn->close();
    sendResponse('success', 'Data siswa', $data);
}

// ---- POST: tambah / ubah / hapus ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json  = json_decode(file_get_contents('php://input'), true);
    $input = !empty($json) ? $json : $_POST;

    $action = isset($input['action']) ? $input['action'] : 'create';

    // HAPUS
    if ($action === 'delete') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID siswa diperlukan'); }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Siswa berhasil dihapus');
    }

    // Validasi
    $nama     = isset($input['nama'])         ? trim($input['nama'])         : '';
    $nis      = isset($input['nomor_induk'])  ? trim($input['nomor_induk'])  : '';
    $kelasId  = isset($input['kelas_id'])     ? (int)$input['kelas_id']      : 0;
    $hpOrtu   = isset($input['no_hp_ortu'])   ? trim($input['no_hp_ortu'])   : '';
    $password = isset($input['password'])     ? trim($input['password'])     : '';

    if (empty($nama) || empty($nis)) {
        $conn->close();
        sendResponse('error', 'Nama dan NIS wajib diisi');
    }

    // UPDATE
    if ($action === 'update') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) { $conn->close(); sendResponse('error', 'ID siswa diperlukan'); }

        if (!empty($password)) {
            $passMd5 = md5($password);
            $stmt = $conn->prepare("UPDATE users SET nama=?, nomor_induk=?, kelas_id=?, no_hp_ortu=?, password=? WHERE id=? AND role='siswa'");
            $stmt->bind_param('ssissi', $nama, $nis, $kelasId, $hpOrtu, $passMd5, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama=?, nomor_induk=?, kelas_id=?, no_hp_ortu=? WHERE id=? AND role='siswa'");
            $stmt->bind_param('ssisi', $nama, $nis, $kelasId, $hpOrtu, $id);
        }
        if (!$stmt->execute()) {
            $conn->close();
            sendResponse('error', 'NIS sudah digunakan');
        }
        $conn->close();
        sendResponse('success', 'Data siswa berhasil diperbarui');
    }

    // CREATE
    if (empty($password)) $password = 'siswa123'; // default password
    $passMd5 = md5($password);

    $stmt = $conn->prepare("INSERT INTO users (nama, nomor_induk, password, role, kelas_id, no_hp_ortu) VALUES (?,?,?,'siswa',?,?)");
    $stmt->bind_param('sssis', $nama, $nis, $passMd5, $kelasId, $hpOrtu);
    if (!$stmt->execute()) {
        $conn->close();
        sendResponse('error', 'NIS sudah digunakan');
    }
    $newId = $conn->insert_id;
    $conn->close();
    sendResponse('success', 'Siswa berhasil ditambahkan', ['id' => $newId]);
}

sendResponse('error', 'Method tidak diizinkan');
?>
