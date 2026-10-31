<?php
// ============================================
// Smart Attendance - API Manajemen Pegawai & Guru (Admin Only)
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: Daftar Guru & Pegawai ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
    
    $sql = "SELECT id, nip, nama, role, jabatan, no_hp, created_at FROM users WHERE role IN ('guru', 'pegawai')";
    if ($roleFilter === 'guru' || $roleFilter === 'pegawai') {
        $sql .= " AND role = '$roleFilter'";
    }
    $sql .= " ORDER BY role ASC, nama ASC";

    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $conn->close();
    sendResponse('success', 'Data pegawai & guru berhasil diambil', $data);
}

// ---- POST: Tambah / Ubah / Hapus Pegawai & Guru ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!getAdminSession()) {
        $conn->close();
        sendResponse('error', 'Akses ditolak: Sesi Administrator tidak aktif atau telah kedaluwarsa. Silakan login kembali.');
    }

    $raw   = file_get_contents('php://input');
    $jsonDecoded = json_decode($raw, true);
    $input = !empty($jsonDecoded) ? $jsonDecoded : $_POST;

    $action = isset($input['action']) ? $input['action'] : 'create';

    // 1. HAPUS PEGAWAI / GURU
    if ($action === 'delete') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            $conn->close();
            sendResponse('error', 'ID user diperlukan');
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('guru','pegawai')");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->close();
        sendResponse('success', 'Akun pegawai/guru berhasil dihapus');
    }

    // Input data umum
    $nama     = trim(isset($input['nama']) ? $input['nama'] : '');
    $nip      = trim(isset($input['nip']) ? $input['nip'] : (isset($input['nomor_induk']) ? $input['nomor_induk'] : ''));
    $roleRaw  = isset($input['role']) ? $input['role'] : '';
    $role     = in_array($roleRaw, array('guru', 'pegawai')) ? $roleRaw : 'guru';
    $jabatan  = trim(isset($input['jabatan']) ? $input['jabatan'] : '');
    $no_hp    = trim(isset($input['no_hp']) ? $input['no_hp'] : '');
    $password = trim(isset($input['password']) ? $input['password'] : '');

    if (empty($nama) || empty($nip)) {
        $conn->close();
        sendResponse('error', 'Nama dan NIP wajib diisi');
    }

    // 2. UPDATE PEGAWAI / GURU
    if ($action === 'update') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            $conn->close();
            sendResponse('error', 'ID user diperlukan');
        }

        if (!empty($password)) {
            $passMd5 = md5($password);
            $stmt = $conn->prepare("UPDATE users SET nama = ?, nip = ?, role = ?, jabatan = ?, no_hp = ?, password = ? WHERE id = ?");
            $stmt->bind_param('ssssssi', $nama, $nip, $role, $jabatan, $no_hp, $passMd5, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama = ?, nip = ?, role = ?, jabatan = ?, no_hp = ? WHERE id = ?");
            $stmt->bind_param('sssssi', $nama, $nip, $role, $jabatan, $no_hp, $id);
        }

        if (!$stmt->execute()) {
            $err = $conn->error;
            $conn->close();
            sendResponse('error', 'Gagal memperbarui data (NIP mungkin sudah digunakan): ' . $err);
        }

        $conn->close();
        sendResponse('success', 'Data pegawai/guru berhasil diperbarui');
    }

    // 3. CREATE PEGAWAI / GURU BARU (Admin only)
    if (empty($password)) {
        $password = 'guru123'; // password default
    }
    $passMd5 = md5($password);

    $stmt = $conn->prepare("INSERT INTO users (nama, nip, role, jabatan, no_hp, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssss', $nama, $nip, $role, $jabatan, $no_hp, $passMd5);

    if (!$stmt->execute()) {
        $err = $conn->error;
        $conn->close();
        sendResponse('error', 'Gagal mendaftarkan akun (NIP sudah terdaftar): ' . $err);
    }

    $newId = $conn->insert_id;
    $conn->close();
    sendResponse('success', 'Pegawai/Guru berhasil didaftarkan oleh Admin', ['id' => $newId]);
}

sendResponse('error', 'Method tidak diizinkan');
?>
