<?php
// ============================================
// Smart Attendance - API Riwayat Presensi Pegawai & Guru
// ============================================
require_once 'koneksi.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$nip    = isset($_GET['nip']) ? trim($_GET['nip']) : (isset($_GET['nomor_induk']) ? trim($_GET['nomor_induk']) : '');

if (!$userId && empty($nip)) {
    sendResponse('error', 'Parameter user_id atau nip diperlukan');
}

$conn = getConnection();

if (!empty($nip)) {
    $uStmt = $conn->prepare("SELECT id, nama, nip, role, jabatan FROM users WHERE nip = ?");
    $uStmt->bind_param('s', $nip);
    $uStmt->execute();
    $userInfo = $uStmt->get_result()->fetch_assoc();
    if (!$userInfo) {
        $conn->close();
        sendResponse('error', 'Pegawai/Guru dengan NIP tersebut tidak ditemukan');
    }
    $userId = (int)$userInfo['id'];
} else {
    $uStmt = $conn->prepare("SELECT id, nama, nip, role, jabatan FROM users WHERE id = ?");
    $uStmt->bind_param('i', $userId);
    $uStmt->execute();
    $userInfo = $uStmt->get_result()->fetch_assoc();
}

$stmt = $conn->prepare("
    SELECT
        id,
        tanggal,
        jam_masuk,
        foto_masuk,
        lat_masuk,
        lng_masuk,
        jarak_masuk,
        jam_pulang,
        foto_pulang,
        lat_pulang,
        lng_pulang,
        jarak_pulang,
        status,
        keterangan,
        created_at
    FROM absensi
    WHERE user_id = ?
    ORDER BY tanggal DESC
    LIMIT 60
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$stats = array('hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0, 'total' => 0);

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    $statusStr = isset($row['status']) ? $row['status'] : 'hadir';
    $st = strtolower($statusStr);
    if (isset($stats[$st])) {
        $stats[$st]++;
    }
    $stats['total']++;
}

$conn->close();

sendResponse('success', 'Data riwayat presensi pegawai', [
    'pegawai'   => $userInfo,
    'ringkasan' => $stats,
    'riwayat'   => $data
]);
?>
