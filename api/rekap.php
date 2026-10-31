<?php
// ============================================
// Smart Attendance - API Monitoring & Rekap Admin
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Method tidak diizinkan');
}

$type     = isset($_GET['type']) ? trim($_GET['type']) : 'live'; // 'live', 'overview', 'bulanan'
$tanggal  = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');
$bulan    = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// ========================================================
// 1. LIVE FEED PRESENSI HARI INI (Untuk Live Table Admin)
// ========================================================
if ($type === 'live') {
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id,
            u.nip,
            u.nama,
            u.role,
            u.jabatan,
            a.id AS absensi_id,
            a.tanggal,
            a.jam_masuk,
            a.foto_masuk,
            a.lat_masuk,
            a.lng_masuk,
            a.jarak_masuk,
            a.jam_pulang,
            a.foto_pulang,
            a.lat_pulang,
            a.lng_pulang,
            a.jarak_pulang,
            a.status,
            a.keterangan
        FROM users u
        LEFT JOIN absensi a ON u.id = a.user_id AND a.tanggal = ?
        WHERE u.role IN ('guru', 'pegawai')
        ORDER BY 
            CASE 
                WHEN a.jam_masuk IS NOT NULL THEN 1 
                ELSE 2 
            END,
            a.jam_masuk DESC,
            u.nama ASC
    ");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();

    $feed = [];
    $hadir = 0;
    $terlambat = 0;
    $izin_sakit = 0;
    $belum = 0;
    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $total++;
        if (empty($row['jam_masuk'])) {
            $row['status_kehadiran'] = 'Belum Hadir';
            $belum++;
        } else {
            if ($row['status'] === 'izin') {
                $row['status_kehadiran'] = 'Izin';
                $izin_sakit++;
            } elseif ($row['status'] === 'sakit') {
                $row['status_kehadiran'] = 'Sakit';
                $izin_sakit++;
            } elseif ($row['status'] === 'terlambat') {
                $row['status_kehadiran'] = 'Terlambat';
                $terlambat++;
            } else {
                $row['status_kehadiran'] = 'Tepat Waktu';
                $hadir++;
            }
        }
        $feed[] = $row;
    }

    $conn->close();

    sendResponse('success', 'Live feed presensi hari ini', [
        'tanggal' => $tanggal,
        'statistik' => [
            'total_pegawai' => $total,
            'hadir'         => $hadir,
            'terlambat'     => $terlambat,
            'izin_sakit'    => $izin_sakit,
            'belum_hadir'   => $belum,
            'persentase'    => $total > 0 ? round((($hadir + $terlambat) / $total) * 100, 1) : 0
        ],
        'data' => $feed
    ]);
}

// ========================================================
// 2. REKAP BULANAN (Untuk Laporan Bulanan TU/Kepsek)
// ========================================================
if ($type === 'bulanan') {
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id,
            u.nip,
            u.nama,
            u.role,
            u.jabatan,
            COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) AS total_hadir,
            COUNT(CASE WHEN a.status = 'terlambat' THEN 1 END) AS total_terlambat,
            COUNT(CASE WHEN a.status = 'izin' THEN 1 END) AS total_izin,
            COUNT(CASE WHEN a.status = 'sakit' THEN 1 END) AS total_sakit,
            COUNT(CASE WHEN a.status = 'alpha' THEN 1 END) AS total_alpha,
            COUNT(a.id) AS total_presensi
        FROM users u
        LEFT JOIN absensi a ON u.id = a.user_id 
             AND MONTH(a.tanggal) = ? 
             AND YEAR(a.tanggal) = ?
        WHERE u.role IN ('guru', 'pegawai')
        GROUP BY u.id, u.nip, u.nama, u.role, u.jabatan
        ORDER BY u.nama ASC
    ");
    $stmt->bind_param('ii', $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    $rekap = [];
    while ($row = $result->fetch_assoc()) {
        $rekap[] = $row;
    }

    $conn->close();

    sendResponse('success', 'Rekapitulasi bulanan', [
        'bulan' => $bulan,
        'tahun' => $tahun,
        'data'  => $rekap
    ]);
}

$conn->close();
sendResponse('error', 'Tipe rekap tidak valid (pilih live atau bulanan)');
?>
