<?php
// ============================================
// Smart Attendance - API Login
// Multi-role: Admin, Guru, Pegawai
// ============================================
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method tidak diizinkan');
}

// Ambil raw input
$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);
$queryInput = [];
parse_str(ltrim($rawBody, '?'), $queryInput);

// Helper pencarian key
function getFieldValue($key, $json, $post, $query) {
    if (isset($json[$key]) && $json[$key] !== '') return $json[$key];
    if (isset($post[$key]) && $post[$key] !== '') return $post[$key];
    if (isset($query[$key]) && $query[$key] !== '') return $query[$key];
    return null;
}

// Cari nomor identitas (nip / nomor_induk / username / nim)
$nipRaw = getFieldValue('nip', $jsonInput, $_POST, $queryInput);
if ($nipRaw === null) $nipRaw = getFieldValue('nomor_induk', $jsonInput, $_POST, $queryInput);
if ($nipRaw === null) $nipRaw = getFieldValue('username', $jsonInput, $_POST, $queryInput);
if ($nipRaw === null) $nipRaw = getFieldValue('nim', $jsonInput, $_POST, $queryInput);
if ($nipRaw === null) $nipRaw = '';

$passRaw = getFieldValue('password', $jsonInput, $_POST, $queryInput);
if ($passRaw === null) $passRaw = '';

$nip      = trim($nipRaw);
$password = trim($passRaw);

if (empty($nip) || empty($password)) {
    sendResponse('error', 'NIP / Username dan password wajib diisi');
}

startAppSession();

$conn    = getConnection();
$passMd5 = md5($password);

// Query user berdasarkan NIP / nomor_induk dan password
$stmt = $conn->prepare(
    "SELECT id, nip, nomor_induk, nama, role, jabatan, no_hp, foto_profil 
     FROM users 
     WHERE (nip = ? OR nomor_induk = ?) AND password = ?"
);
$stmt->bind_param('sss', $nip, $nip, $passMd5);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    sendResponse('error', 'NIP atau password salah');
}

$user = $result->fetch_assoc();
$conn->close();

// Regenerasi session ID untuk keamanan dan simpan sesi server
session_regenerate_id(true);

$resolvedNip = !empty($user['nip']) ? $user['nip'] : (isset($user['nomor_induk']) ? $user['nomor_induk'] : $nip);
$resolvedJabatan = isset($user['jabatan']) && $user['jabatan'] !== '' 
    ? $user['jabatan'] 
    : ($user['role'] === 'guru' ? 'Guru / Tenaga Pendidik' : ($user['role'] === 'admin' ? 'Administrator TU' : 'Staf Pegawai'));

$sessionData = [
    'user_id'     => (int)$user['id'],
    'nip'         => $resolvedNip,
    'nama'        => $user['nama'],
    'role'        => $user['role'],
    'jabatan'     => $resolvedJabatan,
    'no_hp'       => isset($user['no_hp']) ? $user['no_hp'] : '',
    'foto_profil' => isset($user['foto_profil']) ? $user['foto_profil'] : '',
    'login_time'  => time()
];

$_SESSION['pegawai_user'] = $sessionData;

// Jika user juga admin, sinkronkan sesi admin
if ($user['role'] === 'admin') {
    $_SESSION['admin_user'] = [
        'id'           => (int)$user['id'],
        'nip'          => $resolvedNip,
        'nama'         => $user['nama'],
        'role'         => 'admin',
        'jabatan'      => $resolvedJabatan,
        'no_hp'        => isset($user['no_hp']) ? $user['no_hp'] : '',
        'logged_in_at' => time()
    ];
}

sendResponse('success', 'Login berhasil', $sessionData);
?>
