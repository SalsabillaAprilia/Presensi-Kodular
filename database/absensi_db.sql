-- ============================================
-- Smart Attendance - Sistem Presensi Pegawai & Guru
-- Database Setup
-- ============================================

-- Drop tabel lama jika ada
DROP TABLE IF EXISTS absensi;
DROP TABLE IF EXISTS presensi;
DROP TABLE IF EXISTS jadwal;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS mata_pelajaran;
DROP TABLE IF EXISTS kelas;
DROP TABLE IF EXISTS settings;

-- ============================================
-- 1. Tabel Users (Admin, Guru, Pegawai)
-- Catatan: Pendaftaran akun hanya melalui Admin
-- ============================================
CREATE TABLE users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nip          VARCHAR(30)  NOT NULL UNIQUE,
  nama         VARCHAR(100) NOT NULL,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('admin','guru','pegawai') NOT NULL DEFAULT 'guru',
  jabatan      VARCHAR(100) NULL,
  no_hp        VARCHAR(20)  NULL,
  foto_profil  VARCHAR(255) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. Tabel Absensi (Presensi Harian Berbasis GPS + Selfie)
-- Mendukung Alur Bertahap: Absen Masuk & Absen Pulang
-- ============================================
CREATE TABLE absensi (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  tanggal      DATE NOT NULL,
  -- Data Absen Masuk
  jam_masuk    TIME NULL,
  foto_masuk   VARCHAR(255) NULL,
  lat_masuk    DECIMAL(10, 8) NULL,
  lng_masuk    DECIMAL(11, 8) NULL,
  jarak_masuk  INT NULL COMMENT 'Jarak dalam meter ke titik sekolah saat masuk',
  -- Data Absen Pulang (Hanya diisi setelah absen masuk)
  jam_pulang   TIME NULL,
  foto_pulang  VARCHAR(255) NULL,
  lat_pulang   DECIMAL(10, 8) NULL,
  lng_pulang   DECIMAL(11, 8) NULL,
  jarak_pulang INT NULL COMMENT 'Jarak dalam meter ke titik sekolah saat pulang',
  -- Status & Validasi
  status       ENUM('hadir','terlambat','izin','sakit','alpha') DEFAULT 'hadir',
  keterangan   TEXT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_tanggal (user_id, tanggal),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. Tabel Settings (Konfigurasi Sekolah, GPS & Jam)
-- ============================================
CREATE TABLE settings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(50)  NOT NULL UNIQUE,
  setting_val VARCHAR(255) NOT NULL,
  label       VARCHAR(100) NOT NULL,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- DATA AWAL (SEED DATA)
-- ============================================

-- Konfigurasi Sekolah & Geofencing
INSERT INTO settings (setting_key, setting_val, label) VALUES
('nama_sekolah',      'SMK Negeri 1 Nusantara', 'Nama Sekolah / Instansi'),
('kampus_lat',        '-6.208800',              'Latitude Lokasi Sekolah'),
('kampus_lng',        '106.845600',             'Longitude Lokasi Sekolah'),
('max_radius',        '150',                    'Radius Maksimal Toleransi (Meter)'),
('batas_jam',         '7',                      'Batas Jam Masuk (Jam)'),
('batas_menit',       '15',                     'Batas Jam Masuk (Menit)'),
('jam_pulang_mulai',  '15:00',                  'Jam Mulai Diizinkan Absen Pulang');

-- Akun Default: Admin Tata Usaha (password: admin123)
INSERT INTO users (nip, nama, password, role, jabatan, no_hp) VALUES
('admin', 'Administrator TU', MD5('admin123'), 'admin', 'Kepala Tata Usaha', '081234567890');

-- Akun Default: Guru (password: guru123)
INSERT INTO users (nip, nama, password, role, jabatan, no_hp) VALUES
('198501001', 'Budi Hartono, M.Pd',    MD5('guru123'), 'guru', 'Guru Matematika',     '081234567891'),
('198702002', 'Siti Aminah, S.Pd',      MD5('guru123'), 'guru', 'Guru B. Indonesia',   '081234567892'),
('199003003', 'Agus Setiawan, S.Kom',   MD5('guru123'), 'guru', 'Guru RPL / IT',      '081234567893');

-- Akun Default: Tenaga Kependidikan / Pegawai (password: pegawai123)
INSERT INTO users (nip, nama, password, role, jabatan, no_hp) VALUES
('199204004', 'Rina Wulandari, S.E',   MD5('pegawai123'), 'pegawai', 'Staff Administrasi', '081234567894'),
('199505005', 'Dedi Kurniawan',        MD5('pegawai123'), 'pegawai', 'Teknisi Lab & Sarpras', '081234567895');
