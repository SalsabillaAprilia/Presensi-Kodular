<?php
// ============================================
// Smart Attendance - Panel Manajemen Administrator
// Khusus Tata Usaha / Kepala Sekolah
// ============================================
require_once '../api/koneksi.php';
startAppSession();
$adminUser = getAdminSession();
if (!$adminUser) {
    header('Location: login.php');
    exit();
}

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$conn = getConnection();
$today = date('Y-m-d');

// Helper query aman (kompatibel semua driver PHP/MySQL)
function safeQueryAll($conn, $sql) {
    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function safeCount($conn, $sql) {
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        return isset($row['c']) ? (int)$row['c'] : 0;
    }
    return 0;
}

// 1. Data Statistik Guru & Pegawai
$totalGuru    = safeCount($conn, "SELECT COUNT(*) as c FROM users WHERE role='guru'");
$totalPegawai = safeCount($conn, "SELECT COUNT(*) as c FROM users WHERE role='pegawai'");
$totalStaf    = $totalGuru + $totalPegawai;

// 2. Data Presensi Hari Ini
$queryPresensiToday = "
    SELECT 
        u.id AS user_id,
        COALESCE(u.nip, u.nomor_induk, '') AS nip,
        u.nama,
        u.role,
        u.jabatan,
        u.no_hp,
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
    LEFT JOIN absensi a ON u.id = a.user_id AND a.tanggal = '$today'
    WHERE u.role IN ('guru', 'pegawai')
    ORDER BY 
        CASE WHEN a.jam_masuk IS NOT NULL THEN 1 ELSE 2 END,
        a.jam_masuk DESC,
        u.nama ASC
";
$listPresensiToday = safeQueryAll($conn, $queryPresensiToday);

$statHadir = 0;
$statTerlambat = 0;
$statIzin = 0;
$statBelum = 0;

foreach ($listPresensiToday as $p) {
    $st = strtolower(isset($p['status']) ? $p['status'] : '');
    if (empty($p['jam_masuk']) && empty($st)) {
        $statBelum++;
    } else {
        if ($st === 'terlambat') {
            $statTerlambat++;
        } elseif ($st === 'izin' || $st === 'sakit') {
            $statIzin++;
        } elseif ($st === 'hadir') {
            $statHadir++;
        } else {
            $statBelum++;
        }
    }
}

// 3. Data Seluruh Pegawai & Guru (Untuk Tab Manajemen Akun)
$listPegawai = safeQueryAll($conn, "
    SELECT id, COALESCE(nip, nomor_induk, '') AS nip, nama, role, jabatan, no_hp, created_at 
    FROM users 
    WHERE role IN ('guru', 'pegawai') 
    ORDER BY role ASC, nama ASC
");

// 4. Data Settings Sekolah & GPS Geofencing
$settings = [];
$resCfg = $conn->query("SELECT setting_key, setting_val FROM settings");
if ($resCfg) {
    while ($r = $resCfg->fetch_assoc()) {
        $settings[$r['setting_key']] = $r['setting_val'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Admin – Presensi Pegawai & Guru</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --bg:           #060d17;
      --navy:         #0a192f;
      --navy-light:   #0f2444;
      --card:         #0e1f38;
      --card2:        #142b4d;
      --border:       rgba(197, 155, 39, 0.22);
      --border-subtle:rgba(255, 255, 255, 0.08);
      
      --accent:       #d4af37;
      --accent2:      #c59b27;
      --green:        #10b981;
      --yellow:       #f59e0b;
      --blue:         #2563eb;
      --red:          #f43f5e;
      --muted:        rgba(255, 255, 255, 0.6);
      --text:         #f8fafc;
      --radius:       14px;
      --sidebar:      260px;
    }

    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      -webkit-font-smoothing: antialiased;
    }

    /* ---- SIDEBAR ---- */
    .sidebar {
      width: var(--sidebar);
      min-height: 100vh;
      background: var(--navy);
      border-right: 1px solid var(--border);
      position: fixed;
      top: 0; left: 0; z-index: 200;
      display: flex;
      flex-direction: column;
    }
    .sidebar-brand {
      padding: 22px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-icon {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, #0a192f, #142b4d);
      border: 1.5px solid var(--accent);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: var(--accent);
      flex-shrink: 0;
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .sidebar-brand h2 {
      font-family: 'Cinzel', serif;
      font-size: 0.92rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
      letter-spacing: 0.5px;
    }
    .sidebar-brand small {
      font-size: 0.65rem;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      font-weight: 700;
    }
    .sidebar-nav { flex: 1; padding: 14px 0; overflow-y: auto; }
    .nav-item {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 18px; margin: 4px 10px; border-radius: 10px;
      cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--muted);
      transition: all .2s; border: none; background: none; width: calc(100% - 20px); text-align: left;
    }
    .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
    .nav-item:hover { background: rgba(255,255,255,.05); color: #fff; }
    .nav-item.active {
      background: rgba(197, 155, 39, 0.12);
      color: var(--accent);
      font-weight: 700;
      border-left: 3px solid var(--accent);
    }
    .sidebar-footer {
      padding: 18px 20px;
      border-top: 1px solid var(--border);
      font-size: 0.75rem;
      color: var(--muted);
    }
    .sidebar-footer a {
      color: var(--accent);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
    }
    .sidebar-footer a:hover { text-decoration: underline; }

    /* ---- MAIN CONTENT ---- */
    .main { margin-left: var(--sidebar); flex: 1; min-height: 100vh; display: flex; flex-direction: column; }

    .topbar {
      background: var(--navy);
      border-bottom: 1px solid var(--border);
      padding: 16px 32px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
    }
    .topbar h1 {
      font-size: 1.15rem;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .topbar .right { display: flex; align-items: center; gap: 14px; font-size: 0.82rem; color: var(--muted); }

    .container { padding: 28px 32px; flex: 1; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; animation: fadeIn .2s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    /* ---- STATS CARDS ---- */
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-bottom: 24px; }
    .stat {
      background: var(--card);
      border: 1px solid var(--border);
      padding: 20px;
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }
    .stat .num { font-size: 2.1rem; font-weight: 800; margin-bottom: 4px; line-height: 1; color: #fff; }
    .stat .lbl { font-size: 0.74rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .stat-icon svg { width: 22px; height: 22px; }
    .stat.gold .stat-icon  { background: rgba(197, 155, 39, 0.15); color: var(--accent); }
    .stat.green .stat-icon { background: rgba(16, 185, 129, 0.15); color: var(--green); }
    .stat.amber .stat-icon { background: rgba(245, 158, 11, 0.15); color: var(--yellow); }
    .stat.blue .stat-icon  { background: rgba(37, 99, 235, 0.15); color: #60a5fa; }
    .stat.cyan .stat-icon  { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }

    /* ---- CARDS & TABLES ---- */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      margin-bottom: 24px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .card-header {
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      background: rgba(10, 25, 47, 0.4);
    }
    .card-header h3 {
      font-size: 0.95rem;
      font-weight: 700;
      color: #fff;
      display: flex; align-items: center; gap: 10px;
    }
    .card-header h3 svg { color: var(--accent); }
    .card-body { padding: 24px; }

    table { width: 100%; border-collapse: collapse; }
    th {
      background: rgba(10, 25, 47, 0.7);
      padding: 12px 16px;
      text-align: left;
      font-size: 0.74rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--accent);
      font-weight: 700;
      border-bottom: 1px solid var(--border);
    }
    td { padding: 12px 16px; border-bottom: 1px solid var(--border-subtle); font-size: 0.84rem; vertical-align: middle; }
    tr:hover td { background: rgba(197, 155, 39, 0.04); }

    /* Selfie Thumbnail */
    .selfie-thumb {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      object-fit: cover;
      border: 1.5px solid var(--accent);
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .selfie-thumb:hover {
      transform: scale(1.1);
      box-shadow: 0 0 12px rgba(212,175,55,0.6);
    }
    .selfie-thumb-none {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      background: rgba(255,255,255,0.05);
      border: 1px dashed var(--border-subtle);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
    }

    /* ---- BADGES ---- */
    .badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px; border-radius: 9999px;
      font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .badge.hadir     { background: rgba(16, 185, 129, 0.15); color: var(--green); border: 1px solid rgba(16, 185, 129, 0.3); }
    .badge.terlambat { background: rgba(245, 158, 11, 0.15); color: var(--yellow); border: 1px solid rgba(245, 158, 11, 0.3); }
    .badge.izin      { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
    .badge.sakit     { background: rgba(244, 63, 94, 0.15); color: var(--red); border: 1px solid rgba(244, 63, 94, 0.3); }
    .badge.belum     { background: rgba(255, 255, 255, 0.08); color: var(--muted); border: 1px solid var(--border-subtle); }
    .badge.role-guru { background: rgba(37, 99, 235, 0.15); color: #60a5fa; border: 1px solid rgba(37, 99, 235, 0.3); }
    .badge.role-pegawai { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }

    /* ---- BUTTONS ---- */
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 9px 18px; border-radius: 8px; border: none;
      font-size: 0.82rem; font-weight: 600; cursor: pointer;
      transition: all .2s; font-family: inherit; text-decoration: none;
    }
    .btn:hover { opacity: .9; transform: translateY(-1px); }
    .btn-primary {
      background: linear-gradient(135deg, var(--accent2), var(--accent));
      color: #060d17;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(197, 155, 39, 0.25);
    }
    .btn-red   { background: rgba(244, 63, 94, 0.15); color: var(--red); border: 1px solid rgba(244, 63, 94, 0.3); }
    .btn-red:hover { background: var(--red); color: #fff; }
    .btn-ghost { background: rgba(255,255,255,.06); color: var(--text); border: 1px solid var(--border-subtle); }
    .btn-ghost:hover { background: rgba(255,255,255,.12); color: #fff; }
    .btn-sm    { padding: 6px 12px; font-size: 0.75rem; }

    /* ---- FORMS ---- */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    .form-group input, .form-group select {
      width: 100%; padding: 10px 14px;
      background: rgba(255,255,255,.05);
      border: 1px solid var(--border-subtle); border-radius: 8px;
      color: var(--text); font-size: 0.85rem; font-family: inherit; outline: none;
      transition: border-color .2s;
    }
    .form-group input:focus, .form-group select:focus {
      border-color: var(--accent);
      background: rgba(255,255,255,.08);
      box-shadow: 0 0 0 3px rgba(197, 155, 39, 0.15);
    }
    .form-group select option { background: var(--navy); }
    .form-full { grid-column: 1 / -1; }

    /* ---- MODAL POPUP ---- */
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(6, 13, 23, 0.85); backdrop-filter: blur(8px);
      display: none; align-items: center; justify-content: center; z-index: 500;
    }
    .modal-overlay.show { display: flex; }
    .modal {
      background: var(--card); border: 1px solid var(--border);
      border-radius: 16px; width: 90%; max-width: 520px; max-height: 90vh;
      overflow-y: auto; animation: modalIn .2s ease;
      box-shadow: 0 20px 40px rgba(0,0,0,0.6);
    }
    @keyframes modalIn { from { opacity: 0; transform: scale(.95) translateY(10px); } }
    .modal-header {
      padding: 18px 24px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .modal-header h3 { font-size: 1rem; font-weight: 700; color: #fff; }
    .btn-close-modal {
      background: none; border: none; color: var(--muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center; padding: 4px;
    }
    .btn-close-modal:hover { color: #fff; }
    .modal-body { padding: 24px; }

    /* Popup Foto Preview */
    #modal-preview-foto .modal {
      max-width: 420px;
      text-align: center;
      background: #0a192f;
    }
    #preview-foto-img {
      width: 100%;
      border-radius: 12px;
      border: 1.5px solid var(--accent);
      box-shadow: 0 8px 24px rgba(0,0,0,0.5);
      margin-bottom: 14px;
    }
    .preview-info-box {
      background: rgba(255,255,255,0.05);
      padding: 12px;
      border-radius: 10px;
      text-align: left;
      font-size: 0.82rem;
      line-height: 1.5;
    }

    @media print {
      .sidebar, .topbar, .btn, .card-header .btn { display: none !important; }
      .main { margin-left: 0; }
      body { background: #fff; color: #000; }
      .card { border: 1px solid #ccc; box-shadow: none; }
      th { color: #000; background: #eee; }
      td { color: #000; }
    }
  </style>
</head>
<body>

  <!-- ==================== SIDEBAR ==================== -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 21h18M3 7v14M21 7v14M6 11h2M6 15h2M16 11h2M16 15h2M10 21V11h4v10M12 3l9 4H3l9-4z"/>
        </svg>
      </div>
      <div>
        <h2>SMK Nusantara</h2>
        <small>Portal Tata Usaha</small>
      </div>
    </div>

    <nav class="sidebar-nav">
      <button class="nav-item active" onclick="switchTab('tab-live', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>Live Monitoring</span>
      </button>
      <button class="nav-item" onclick="switchTab('tab-pegawai', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Data Pegawai & Guru</span>
      </button>
      <button class="nav-item" onclick="switchTab('tab-rekap', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        <span>Rekapitulasi Bulanan</span>
      </button>
      <button class="nav-item" onclick="switchTab('tab-settings', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        <span>Pengaturan Sekolah & GPS</span>
      </button>
    </nav>

    <div class="sidebar-footer">
      <a href="../frontend/index.html">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        <span>Presensi Mobile Pegawai</span>
      </a>
      <p style="margin-top:6px; opacity:0.6;">Sistem Presensi Pegawai v2.0</p>
    </div>
  </aside>

  <!-- ==================== MAIN CONTENT ==================== -->
  <div class="main">
    
    <!-- Topbar -->
    <header class="topbar">
      <h1 id="topbar-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>Live Feed Presensi Hari Ini</span>
      </h1>
      <div class="right">
        <div style="display:flex; align-items:center; gap:6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span><?php echo date('d F Y'); ?></span>
        </div>
        <span>•</span>
        <div style="display:flex; align-items:center; gap:6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span><?php echo htmlspecialchars($_SESSION['admin_user']['nama']); ?></span>
        </div>
        <a href="../index.html" class="btn btn-ghost btn-sm" style="margin-left:10px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <span>Portal</span>
        </a>
        <a href="logout.php" class="btn btn-red btn-sm" onclick="return confirm('Keluar dari panel admin?')" style="margin-left:6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Keluar</span>
        </a>
      </div>
    </header>

    <div class="container">

      <!-- ==================== TAB 1: LIVE MONITORING ==================== -->
      <section id="tab-live" class="tab-panel active">
        
        <!-- Stats Overview -->
        <div class="stats">
          <div class="stat gold">
            <div>
              <div class="num"><?php echo $totalStaf; ?></div>
              <div class="lbl">Total Pegawai & Guru</div>
            </div>
            <div class="stat-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="stat green">
            <div>
              <div class="num"><?php echo $statHadir; ?></div>
              <div class="lbl">Hadir Tepat Waktu</div>
            </div>
            <div class="stat-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>
          <div class="stat amber">
            <div>
              <div class="num"><?php echo $statTerlambat; ?></div>
              <div class="lbl">Terlambat Masuk</div>
            </div>
            <div class="stat-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
          </div>
          <div class="stat cyan">
            <div>
              <div class="num"><?php echo $statIzin; ?></div>
              <div class="lbl">Izin / Sakit</div>
            </div>
            <div class="stat-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
          </div>
          <div class="stat blue">
            <div>
              <div class="num"><?php echo $statBelum; ?></div>
              <div class="lbl">Belum Hadir</div>
            </div>
            <div class="stat-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
          </div>
        </div>

        <!-- Live Feed Table -->
        <div class="card">
          <div class="card-header">
            <h3>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
              <span>Daftar Kehadiran Hari Ini (<?php echo date('d-m-Y'); ?>)</span>
            </h3>
            <button class="btn btn-ghost btn-sm" onclick="location.reload()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
              <span>Perbarui</span>
            </button>
          </div>
          <div class="card-body" style="padding:0; overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Pegawai / Guru</th>
                  <th>Peran & Jabatan</th>
                  <th>Absen Masuk</th>
                  <th>Absen Pulang</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($listPresensiToday)): ?>
                  <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted)">Belum ada data presensi hari ini</td></tr>
                <?php else: ?>
                  <?php foreach ($listPresensiToday as $row): ?>
                    <?php 
                      $hasMasuk  = !empty($row['jam_masuk']);
                      $hasPulang = !empty($row['jam_pulang']);
                      $isHadir   = $row['status'] === 'hadir';
                      $isIzin    = $row['status'] === 'izin';
                      $isSakit   = $row['status'] === 'sakit';
                      
                      $statusBadge = '<span class="badge belum"><span style="width:6px;height:6px;border-radius:50%;background:#94a3b8"></span>Belum Hadir</span>';
                      if ($isIzin) {
                        $statusBadge = '<span class="badge izin"><span style="width:6px;height:6px;border-radius:50%;background:#38bdf8"></span>Izin</span>';
                      } elseif ($isSakit) {
                        $statusBadge = '<span class="badge sakit"><span style="width:6px;height:6px;border-radius:50%;background:#f43f5e"></span>Sakit</span>';
                      } elseif ($hasMasuk) {
                        $statusBadge = $isHadir 
                          ? '<span class="badge hadir"><span style="width:6px;height:6px;border-radius:50%;background:#10b981"></span>Tepat Waktu</span>'
                          : '<span class="badge terlambat"><span style="width:6px;height:6px;border-radius:50%;background:#f59e0b"></span>Terlambat</span>';
                      }
                      
                      $roleBadge = $row['role'] === 'guru'
                        ? '<span class="badge role-guru">Guru</span>'
                        : '<span class="badge role-pegawai">Pegawai</span>';

                      $jarakMsk = isset($row['jarak_masuk']) ? $row['jarak_masuk'] : 0;
                      $jarakPlg = isset($row['jarak_pulang']) ? $row['jarak_pulang'] : 0;
                      $jabatanTxt = !empty($row['jabatan']) ? $row['jabatan'] : '–';
                    ?>
                    <tr>
                      <td>
                        <div style="font-weight:700; color:#fff"><?php echo htmlspecialchars($row['nama']); ?></div>
                        <div style="font-size:0.75rem; color:var(--muted)">NIP: <?php echo htmlspecialchars($row['nip']); ?></div>
                      </td>
                      <td>
                        <?php echo $roleBadge; ?>
                        <div style="font-size:0.78rem; color:var(--muted); margin-top:4px;"><?php echo htmlspecialchars($jabatanTxt); ?></div>
                      </td>
                      <td>
                        <?php if ($isIzin || $isSakit): ?>
                          <div style="background:rgba(255,255,255,0.03); border:1px dashed var(--border-subtle); border-radius:8px; padding:6px 10px; max-width:280px;">
                            <div style="display:flex; align-items:center; gap:6px; font-weight:700; font-size:0.76rem; color:<?php echo $isIzin ? '#38bdf8' : '#f43f5e'; ?>;">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                              <span><?php echo $isIzin ? 'Keterangan Izin' : 'Keterangan Sakit'; ?> (<?php echo substr($row['jam_masuk'],0,5); ?> WIB)</span>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text); margin-top:3px; word-break:break-word;">
                              Alasan: "<?php echo htmlspecialchars(!empty($row['keterangan']) ? $row['keterangan'] : 'Tanpa catatan alasan'); ?>"
                            </div>
                          </div>
                        <?php elseif ($hasMasuk): ?>
                          <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($row['foto_masuk'])): ?>
                              <img class="selfie-thumb" src="../<?php echo htmlspecialchars($row['foto_masuk']); ?>" 
                                   alt="Foto Masuk" 
                                   onclick="bukaPopupFoto('../<?php echo htmlspecialchars($row['foto_masuk']); ?>', '<?php echo addslashes($row['nama']); ?>', 'Absen Masuk: <?php echo substr($row['jam_masuk'],0,5); ?> WIB', 'Jarak: <?php echo $jarakMsk; ?> meter')" />
                            <?php else: ?>
                              <div class="selfie-thumb-none">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                              </div>
                            <?php endif; ?>
                            <div>
                              <div style="font-weight:700; color:#fff"><?php echo substr($row['jam_masuk'], 0, 5); ?> WIB</div>
                              <div style="font-size:0.72rem; color:var(--muted)"><?php echo $jarakMsk; ?>m dari sekolah</div>
                            </div>
                          </div>
                        <?php else: ?>
                          <span style="color:var(--muted)">–</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($hasPulang): ?>
                          <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($row['foto_pulang'])): ?>
                              <img class="selfie-thumb" src="../<?php echo htmlspecialchars($row['foto_pulang']); ?>" 
                                   alt="Foto Pulang" 
                                   onclick="bukaPopupFoto('../<?php echo htmlspecialchars($row['foto_pulang']); ?>', '<?php echo addslashes($row['nama']); ?>', 'Absen Pulang: <?php echo substr($row['jam_pulang'],0,5); ?> WIB', 'Jarak: <?php echo $jarakPlg; ?> meter')" />
                            <?php else: ?>
                              <div class="selfie-thumb-none">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V6a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v14"/><path d="M2 20h20"/></svg>
                              </div>
                            <?php endif; ?>
                            <div>
                              <div style="font-weight:700; color:#fff"><?php echo substr($row['jam_pulang'], 0, 5); ?> WIB</div>
                              <div style="font-size:0.72rem; color:var(--muted)"><?php echo $jarakPlg; ?>m dari sekolah</div>
                            </div>
                          </div>
                        <?php else: ?>
                          <span style="color:var(--muted)">–</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo $statusBadge; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </section>

      <!-- ==================== TAB 2: DATA PEGAWAI & GURU ==================== -->
      <section id="tab-pegawai" class="tab-panel">
        <div class="card">
          <div class="card-header">
            <h3>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <span>Manajemen Akun Guru & Pegawai</span>
            </h3>
            <button class="btn btn-primary btn-sm" onclick="bukaModalPegawai()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>Tambah Pegawai Baru</span>
            </button>
          </div>
          <div class="card-body" style="padding:0; overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>NIP</th>
                  <th>Nama Lengkap</th>
                  <th>Peran</th>
                  <th>Jabatan / Mata Pelajaran</th>
                  <th>No. WhatsApp</th>
                  <th style="text-align:right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($listPegawai)): ?>
                  <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted)">Belum ada data pegawai</td></tr>
                <?php else: ?>
                  <?php foreach ($listPegawai as $peg): ?>
                    <tr>
                      <td style="font-weight:700; color:var(--accent)"><?php echo htmlspecialchars($peg['nip']); ?></td>
                      <td style="font-weight:600; color:#fff"><?php echo htmlspecialchars($peg['nama']); ?></td>
                      <td>
                        <span class="badge <?php echo $peg['role'] === 'guru' ? 'role-guru' : 'role-pegawai'; ?>">
                          <?php echo strtoupper($peg['role']); ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($peg['jabatan'] ?: '–'); ?></td>
                      <td style="color:var(--muted)"><?php echo htmlspecialchars($peg['no_hp'] ?: '–'); ?></td>
                      <td style="text-align:right">
                        <button class="btn btn-ghost btn-sm" onclick='editPegawai(<?php echo json_encode($peg); ?>)'>Edit</button>
                        <button class="btn btn-red btn-sm" onclick="hapusPegawai(<?php echo $peg['id']; ?>, '<?php echo addslashes($peg['nama']); ?>')">Hapus</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ==================== TAB 3: REKAPITULASI BULANAN ==================== -->
      <section id="tab-rekap" class="tab-panel">
        <div class="card">
          <div class="card-header">
            <h3>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              <span>Laporan Kehadiran Bulanan Pegawai</span>
            </h3>
            <div style="display:flex; gap:10px;">
              <select id="filter-bulan" class="form-group" style="margin:0; width:auto; padding:6px 12px; background:var(--navy); color:#fff; border:1px solid var(--border)">
                <?php for($m=1; $m<=12; $m++): ?>
                  <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                    Bulan <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                  </option>
                <?php endfor; ?>
              </select>
              <button class="btn btn-primary btn-sm" onclick="muatRekapBulanan()">Tampilkan</button>
              <button class="btn btn-ghost btn-sm" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak</span>
              </button>
            </div>
          </div>
          <div class="card-body" style="padding:0; overflow-x:auto;">
            <div id="rekap-table-wrap">
              <div style="padding:30px; text-align:center; color:var(--muted)">Tekan tombol "Tampilkan" untuk memuat rekapitulasi kehadiran</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ==================== TAB 4: PENGATURAN SEKOLAH & GEOFENCING ==================== -->
      <section id="tab-settings" class="tab-panel">
        <div class="card" style="max-width:700px;">
          <div class="card-header">
            <h3>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
              <span>Pengaturan Lokasi Geofencing & Jam Kerja</span>
            </h3>
          </div>
          <div class="card-body">
            <form id="form-settings" onsubmit="event.preventDefault(); simpanPengaturan();">
              <div class="form-group">
                <label>Nama Sekolah / Lembaga</label>
                <input type="text" id="cfg-nama-sekolah" value="<?php echo htmlspecialchars(isset($settings['nama_sekolah']) ? $settings['nama_sekolah'] : 'SMK Negeri 1 Nusantara'); ?>" required />
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label>Latitude Sekolah (GPS)</label>
                  <input type="text" id="cfg-lat" value="<?php echo htmlspecialchars(isset($settings['kampus_lat']) ? $settings['kampus_lat'] : '-6.208800'); ?>" required />
                </div>
                <div class="form-group">
                  <label>Longitude Sekolah (GPS)</label>
                  <input type="text" id="cfg-lng" value="<?php echo htmlspecialchars(isset($settings['kampus_lng']) ? $settings['kampus_lng'] : '106.845600'); ?>" required />
                </div>
              </div>

              <div class="form-group">
                <label>Radius Toleransi Presensi (Meter)</label>
                <input type="number" id="cfg-radius" value="<?php echo htmlspecialchars(isset($settings['max_radius']) ? $settings['max_radius'] : '150'); ?>" min="20" max="2000" required />
                <small style="color:var(--muted); font-size:0.75rem;">Jarak maksimal guru/pegawai diizinkan melakukan presensi dari titik pusat sekolah.</small>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label>Batas Jam Masuk (Jam:Menit WIB)</label>
                  <div style="display:flex; gap:8px;">
                    <input type="number" id="cfg-batas-jam" value="<?php echo htmlspecialchars(isset($settings['batas_jam']) ? $settings['batas_jam'] : '7'); ?>" min="0" max="23" style="width:50%" />
                    <input type="number" id="cfg-batas-menit" value="<?php echo htmlspecialchars(isset($settings['batas_menit']) ? $settings['batas_menit'] : '15'); ?>" min="0" max="59" style="width:50%" />
                  </div>
                  <small style="color:var(--muted); font-size:0.75rem;">Presensi lewat dari jam ini otomatis dihitung <b>Terlambat</b>.</small>
                </div>

                <div class="form-group">
                  <label>Jam Mulai Absen Pulang</label>
                  <input type="time" id="cfg-jam-pulang" value="<?php echo htmlspecialchars(isset($settings['jam_pulang_mulai']) ? $settings['jam_pulang_mulai'] : '15:00'); ?>" />
                  <small style="color:var(--muted); font-size:0.75rem;">Jam minimal tombol Absen Pulang diizinkan.</small>
                </div>
              </div>

              <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary" id="btn-save-cfg">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                  <span>Simpan Pengaturan</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </section>

    </div><!-- /container -->

  </div><!-- /main -->

  <!-- ==================== MODAL: FORM PEGAWAI ==================== -->
  <div id="modal-pegawai" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modal-pegawai-title">Tambah Pegawai / Guru Baru</h3>
        <button class="btn-close-modal" onclick="tutupModalPegawai()" aria-label="Tutup">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <form id="form-pegawai" onsubmit="event.preventDefault(); submitFormPegawai();">
          <input type="hidden" id="pegawai-id" value="" />
          <input type="hidden" id="pegawai-action" value="create" />

          <div class="form-group">
            <label>NIP / Nomor Induk Pegawai</label>
            <input type="text" id="pegawai-nip" required />
          </div>

          <div class="form-group">
            <label>Nama Lengkap (beserta gelar)</label>
            <input type="text" id="pegawai-nama" required />
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>Peran / Role</label>
              <select id="pegawai-role">
                <option value="guru">Guru / Pendidik</option>
                <option value="pegawai">Staff / Tenaga Kependidikan</option>
              </select>
            </div>
            <div class="form-group">
              <label>Jabatan / Mata Pelajaran</label>
              <input type="text" id="pegawai-jabatan" placeholder="Cth: Guru Fisika / TU" />
            </div>
          </div>

          <div class="form-group">
            <label>Nomor WhatsApp / HP</label>
            <input type="text" id="pegawai-hp" placeholder="08xxxxxxxxxx" />
          </div>

          <div class="form-group">
            <label>Kata Sandi Akun</label>
            <input type="password" id="pegawai-pass" placeholder="Biarkan kosong jika tidak ingin mengubah password" />
            <small style="color:var(--muted); font-size:0.72rem;">Default untuk pegawai baru: <b>guru123</b></small>
          </div>

          <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button type="button" class="btn btn-ghost" onclick="tutupModalPegawai()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ==================== MODAL: POPUP FOTO SELFIE ==================== -->
  <div id="modal-preview-foto" class="modal-overlay" onclick="tutupPopupFoto()">
    <div class="modal" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h3 id="popup-foto-title">Bukti Foto Presensi</h3>
        <button class="btn-close-modal" onclick="tutupPopupFoto()" aria-label="Tutup">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <img id="preview-foto-img" src="" alt="Bukti Foto Selfie" />
        <div class="preview-info-box">
          <div style="font-weight:700; color:var(--accent); font-size:0.95rem" id="popup-foto-nama">–</div>
          <div id="popup-foto-waktu" style="color:#fff; margin-top:2px;">–</div>
          <div id="popup-foto-jarak" style="color:var(--muted); font-size:0.78rem">–</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Tab switching
    function switchTab(tabId, el) {
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
      
      document.getElementById(tabId).classList.add('active');
      if (el) el.classList.add('active');

      const titles = {
        'tab-live': 'Live Feed Presensi Hari Ini',
        'tab-pegawai': 'Data Pegawai & Guru',
        'tab-rekap': 'Rekapitulasi Presensi Bulanan',
        'tab-settings': 'Pengaturan Sekolah & Geofencing'
      };
      const titleSpan = document.querySelector('#topbar-title span');
      if (titleSpan) titleSpan.textContent = titles[tabId] || 'Admin Management';
    }

    // Modal Foto PopUp
    function bukaPopupFoto(src, nama, waktu, jarak) {
      document.getElementById('preview-foto-img').src = src;
      document.getElementById('popup-foto-nama').textContent = nama;
      document.getElementById('popup-foto-waktu').textContent = waktu;
      document.getElementById('popup-foto-jarak').textContent = jarak;
      document.getElementById('modal-preview-foto').classList.add('show');
    }
    function tutupPopupFoto() {
      document.getElementById('modal-preview-foto').classList.remove('show');
    }

    // Modal Pegawai
    function bukaModalPegawai() {
      document.getElementById('pegawai-id').value = '';
      document.getElementById('pegawai-action').value = 'create';
      document.getElementById('modal-pegawai-title').textContent = 'Tambah Pegawai / Guru Baru';
      document.getElementById('form-pegawai').reset();
      document.getElementById('modal-pegawai').classList.add('show');
    }

    function editPegawai(data) {
      document.getElementById('pegawai-id').value = data.id;
      document.getElementById('pegawai-action').value = 'update';
      document.getElementById('modal-pegawai-title').textContent = 'Edit Data: ' + data.nama;
      document.getElementById('pegawai-nip').value = data.nip;
      document.getElementById('pegawai-nama').value = data.nama;
      document.getElementById('pegawai-role').value = data.role;
      document.getElementById('pegawai-jabatan').value = data.jabatan || '';
      document.getElementById('pegawai-hp').value = data.no_hp || '';
      document.getElementById('pegawai-pass').value = '';
      document.getElementById('modal-pegawai').classList.add('show');
    }

    function tutupModalPegawai() {
      document.getElementById('modal-pegawai').classList.remove('show');
    }

    // Submit Pegawai
    async function submitFormPegawai() {
      const payload = {
        action:   document.getElementById('pegawai-action').value,
        id:       document.getElementById('pegawai-id').value,
        nip:      document.getElementById('pegawai-nip').value.trim(),
        nama:     document.getElementById('pegawai-nama').value.trim(),
        role:     document.getElementById('pegawai-role').value,
        jabatan:  document.getElementById('pegawai-jabatan').value.trim(),
        no_hp:    document.getElementById('pegawai-hp').value.trim(),
        password: document.getElementById('pegawai-pass').value.trim()
      };

      try {
        const res = await fetch('../api/guru.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
          alert('Berhasil: ' + json.message);
          location.reload();
        } else {
          alert('Gagal: ' + json.message);
        }
      } catch (e) {
        alert('Kesalahan jaringan: ' + e.message);
      }
    }

    async function hapusPegawai(id, nama) {
      if (!confirm('Yakin ingin menghapus akun pegawai ' + nama + '?')) return;
      try {
        const res = await fetch('../api/guru.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id: id })
        });
        const json = await res.json();
        if (json.status === 'success') {
          alert('Pegawai berhasil dihapus');
          location.reload();
        } else {
          alert('Gagal: ' + json.message);
        }
      } catch (e) {
        alert('Kesalahan: ' + e.message);
      }
    }

    // Simpan Pengaturan Sekolah
    async function simpanPengaturan() {
      const btn = document.getElementById('btn-save-cfg');
      btn.disabled = true;
      btn.textContent = 'Menyimpan…';

      const payload = {
        nama_sekolah:     document.getElementById('cfg-nama-sekolah').value.trim(),
        kampus_lat:       document.getElementById('cfg-lat').value.trim(),
        kampus_lng:       document.getElementById('cfg-lng').value.trim(),
        max_radius:       document.getElementById('cfg-radius').value.trim(),
        batas_jam:        document.getElementById('cfg-batas-jam').value.trim(),
        batas_menit:      document.getElementById('cfg-batas-menit').value.trim(),
        jam_pulang_mulai: document.getElementById('cfg-jam-pulang').value.trim()
      };

      try {
        const res = await fetch('../api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
          alert('Pengaturan sekolah & GPS berhasil disimpan!');
        } else {
          alert('Gagal: ' + json.message);
        }
      } catch (e) {
        alert('Kesalahan: ' + e.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = `
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span>Simpan Pengaturan</span>
        `;
      }
    }

    // Rekap Bulanan
    async function muatRekapBulanan() {
      const bln = document.getElementById('filter-bulan').value;
      const wrap = document.getElementById('rekap-table-wrap');
      wrap.innerHTML = '<div style="padding:30px; text-align:center; color:var(--muted)">Memuat data rekapitulasi…</div>';

      try {
        const res = await fetch(`../api/rekap.php?type=bulanan&bulan=${bln}`);
        const json = await res.json();
        if (json.status === 'success' && json.data && json.data.data) {
          const rows = json.data.data;
          if (rows.length === 0) {
            wrap.innerHTML = '<div style="padding:30px; text-align:center; color:var(--muted)">Tidak ada data presensi pada bulan ini.</div>';
            return;
          }

          let html = `
            <table>
              <thead>
                <tr>
                  <th>NIP</th>
                  <th>Nama Pegawai</th>
                  <th>Peran</th>
                  <th>Hadir Tepat Waktu</th>
                  <th>Terlambat</th>
                  <th>Izin / Sakit</th>
                  <th>Total Hari</th>
                </tr>
              </thead>
              <tbody>
          `;

          rows.forEach(r => {
            const izinSakit = (parseInt(r.total_izin) || 0) + (parseInt(r.total_sakit) || 0);
            html += `
              <tr>
                <td style="color:var(--accent); font-weight:700">${r.nip}</td>
                <td style="color:#fff; font-weight:600">${r.nama}</td>
                <td><span class="badge ${r.role==='guru'?'role-guru':'role-pegawai'}">${r.role.toUpperCase()}</span></td>
                <td style="color:var(--green); font-weight:700">${r.total_hadir || 0} hari</td>
                <td style="color:var(--yellow); font-weight:700">${r.total_terlambat || 0} hari</td>
                <td style="color:#38bdf8; font-weight:700">${izinSakit} hari</td>
                <td style="font-weight:800; color:#fff">${r.total_presensi || 0} hari</td>
              </tr>
            `;
          });

          html += `</tbody></table>`;
          wrap.innerHTML = html;
        }
      } catch (e) {
        wrap.innerHTML = `<div style="padding:30px; text-align:center; color:var(--red)">Gagal memuat rekap: ${e.message}</div>`;
      }
    }
  </script>
</body>
</html>
