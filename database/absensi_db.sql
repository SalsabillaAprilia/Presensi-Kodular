-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: 29 Okt 2026 pada 14.53
-- Versi Server: 10.1.8-MariaDB
-- PHP Version: 5.6.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `lat_masuk` decimal(10,8) DEFAULT NULL,
  `lng_masuk` decimal(11,8) DEFAULT NULL,
  `jarak_masuk` int(11) DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `foto_pulang` varchar(255) DEFAULT NULL,
  `lat_pulang` decimal(10,8) DEFAULT NULL,
  `lng_pulang` decimal(11,8) DEFAULT NULL,
  `jarak_pulang` int(11) DEFAULT NULL,
  `status` enum('hadir','terlambat','izin','sakit','alpha') DEFAULT 'hadir',
  `keterangan` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `user_id`, `tanggal`, `jam_masuk`, `foto_masuk`, `lat_masuk`, `lng_masuk`, `jarak_masuk`, `jam_pulang`, `foto_pulang`, `lat_pulang`, `lng_pulang`, `jarak_pulang`, `status`, `keterangan`, `created_at`) VALUES
(1, 2, '2026-10-25', '23:03:47', 'uploads/selfie_2_masuk_1792944227.jpg', '-6.53870455', '106.77789968', 10, '23:04:28', 'uploads/selfie_2_pulang_1792944268.jpg', '-6.53870455', '106.77789968', 10, 'terlambat', NULL, '2026-10-25 16:03:47'),
(2, 3, '2026-10-26', '00:55:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'izin', 'Menghadiri undangan pelatihan kurikulum dinas pendidikan', '2026-10-25 17:55:25'),
(3, 2, '2026-10-26', '00:58:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sakit', 'sakit gigi', '2026-10-25 17:58:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal`
--

CREATE TABLE `jadwal` (
  `id` int(11) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `mapel_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `jadwal`
--

INSERT INTO `jadwal` (`id`, `kelas_id`, `mapel_id`, `guru_id`, `hari`, `jam_mulai`, `jam_selesai`) VALUES
(1, 1, 1, 2, 'Senin', '07:00:00', '08:30:00'),
(2, 1, 2, 3, 'Senin', '08:30:00', '10:00:00'),
(3, 1, 3, 4, 'Senin', '10:15:00', '11:45:00'),
(4, 1, 4, 5, 'Selasa', '07:00:00', '08:30:00'),
(5, 1, 5, 6, 'Selasa', '08:30:00', '10:00:00'),
(6, 1, 6, 4, 'Selasa', '10:15:00', '11:45:00'),
(7, 2, 4, 5, 'Senin', '07:00:00', '08:30:00'),
(8, 2, 1, 2, 'Senin', '08:30:00', '10:00:00'),
(9, 2, 2, 3, 'Senin', '10:15:00', '11:45:00'),
(10, 3, 1, 2, 'Senin', '07:00:00', '08:30:00'),
(11, 3, 4, 5, 'Senin', '08:30:00', '10:00:00'),
(12, 3, 5, 6, 'Senin', '10:15:00', '11:45:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL,
  `nama_kelas` varchar(20) NOT NULL,
  `tingkat` enum('X','XI','XII') NOT NULL,
  `tahun_ajaran` varchar(10) NOT NULL DEFAULT '2024/2025',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `tingkat`, `tahun_ajaran`, `created_at`) VALUES
(1, 'X-A', 'X', '2024/2025', '2026-10-20 16:16:19'),
(2, 'X-B', 'X', '2024/2025', '2026-10-20 16:16:19'),
(3, 'XI-IPA-1', 'XI', '2024/2025', '2026-10-20 16:16:19'),
(4, 'XI-IPA-2', 'XI', '2024/2025', '2026-10-20 16:16:19'),
(5, 'XII-IPA-1', 'XII', '2024/2025', '2026-10-20 16:16:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_pelajaran`
--

CREATE TABLE `mata_pelajaran` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kode` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `mata_pelajaran`
--

INSERT INTO `mata_pelajaran` (`id`, `nama`, `kode`) VALUES
(1, 'Matematika', 'MTK'),
(2, 'Bahasa Indonesia', 'BIN'),
(3, 'Bahasa Inggris', 'BIG'),
(4, 'Fisika', 'FIS'),
(5, 'Kimia', 'KIM'),
(6, 'Biologi', 'BIO'),
(7, 'Sejarah', 'SEJ'),
(8, 'Pendidikan Agama', 'PAI');

-- --------------------------------------------------------

--
-- Struktur dari tabel `presensi`
--

CREATE TABLE `presensi` (
  `id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `status` enum('hadir','izin','sakit','alpha') DEFAULT 'hadir',
  `keterangan` text,
  `dicatat_oleh` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `presensi`
--

INSERT INTO `presensi` (`id`, `jadwal_id`, `siswa_id`, `tanggal`, `status`, `keterangan`, `dicatat_oleh`, `created_at`) VALUES
(1, 1, 7, '2026-10-21', 'hadir', 'Tepat waktu', 2, '2026-10-20 17:05:28'),
(2, 1, 8, '2026-10-21', 'hadir', 'Tepat waktu', 2, '2026-10-20 17:05:28'),
(3, 1, 9, '2026-10-21', 'izin', 'Surat dari orang tua', 2, '2026-10-20 17:05:28'),
(4, 1, 10, '2026-10-21', 'sakit', 'Demam', 2, '2026-10-20 17:05:28'),
(5, 1, 11, '2026-10-21', 'hadir', 'Tepat waktu', 2, '2026-10-20 17:05:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_val` varchar(255) NOT NULL,
  `label` varchar(100) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_val`, `label`, `updated_at`) VALUES
(1, 'nama_sekolah', 'SMA Negeri Aja', 'Nama Sekolah', '2026-10-25 16:02:24'),
(2, 'tahun_ajaran', '2024/2025', 'Tahun Ajaran', '2026-10-20 16:16:19'),
(3, 'semester', 'Ganjil', 'Semester', '2026-10-20 16:16:19'),
(4, 'kepala_sekolah', 'Dr. H. Supriyadi, M.Pd', 'Kepala Sekolah', '2026-10-20 16:16:19'),
(5, 'kampus_lat', '-6.538791726929058', 'Latitude Lokasi Sekolah', '2026-10-25 16:02:24'),
(6, 'kampus_lng', '106.7778704458676', 'Longitude Lokasi Sekolah', '2026-10-25 16:02:24'),
(7, 'max_radius', '150', 'Radius Maksimal Toleransi (Meter)', '2026-10-25 15:34:56'),
(8, 'batas_jam', '7', 'Batas Jam Masuk (Jam)', '2026-10-25 15:34:56'),
(9, 'batas_menit', '15', 'Batas Jam Masuk (Menit)', '2026-10-25 15:34:56'),
(10, 'jam_pulang_mulai', '15:00', 'Jam Mulai Diizinkan Absen Pulang', '2026-10-25 15:34:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `nomor_induk` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guru','pegawai','siswa') DEFAULT 'guru',
  `jabatan` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `kelas_id` int(11) DEFAULT NULL,
  `no_hp_ortu` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nip`, `nama`, `nomor_induk`, `password`, `role`, `jabatan`, `no_hp`, `foto_profil`, `kelas_id`, `no_hp_ortu`, `created_at`) VALUES
(1, 'admin', 'Administrator', 'admin', '0192023a7bbd73250516f069df18b500', 'admin', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:16:19'),
(2, '198501001', 'Pak Budi Hartono', '198501001', '9310f83135f238b04af729fec041cca8', 'guru', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:16:19'),
(3, '198702002', 'Bu Siti Aminah', '198702002', '9310f83135f238b04af729fec041cca8', 'guru', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:16:19'),
(4, '199003003', 'Pak Agus Setiawan', '199003003', '9310f83135f238b04af729fec041cca8', 'guru', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:16:19'),
(5, '198804004', 'Bu Rina Wulandari', '198804004', '9310f83135f238b04af729fec041cca8', 'guru', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:16:19'),
(6, '199105005', 'Pak Dedi Kurniawan', '199105005', '9310f83135f238b04af729fec041cca8', 'guru', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:16:19'),
(7, '10001', 'Ahmad Fauzi', '10001', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 1, '081234567001', '2026-10-20 16:16:19'),
(8, '10002', 'Siti Rahayu', '10002', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 1, '081234567002', '2026-10-20 16:16:19'),
(9, '10003', 'Budi Santoso', '10003', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 1, '081234567003', '2026-10-20 16:16:19'),
(10, '10004', 'Dewi Lestari', '10004', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 1, '081234567004', '2026-10-20 16:16:19'),
(11, '10005', 'Rizky Pratama', '10005', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 1, '081234567005', '2026-10-20 16:16:19'),
(12, '10006', 'Andi Wijaya', '10006', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 2, '081234567006', '2026-10-20 16:16:19'),
(13, '10007', 'Putri Handayani', '10007', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 2, '081234567007', '2026-10-20 16:16:19'),
(14, '10008', 'Fajar Nugroho', '10008', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 2, '081234567008', '2026-10-20 16:16:19'),
(15, '10009', 'Lina Marlina', '10009', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 2, '081234567009', '2026-10-20 16:16:19'),
(16, '10010', 'Hendra Gunawan', '10010', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 2, '081234567010', '2026-10-20 16:16:19'),
(17, '11001', 'Yoga Aditya', '11001', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 3, '081234567011', '2026-10-20 16:16:19'),
(18, '11002', 'Nadia Putri', '11002', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 3, '081234567012', '2026-10-20 16:16:19'),
(19, '11003', 'Rafi Ananda', '11003', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 3, '081234567013', '2026-10-20 16:16:19'),
(20, '11004', 'Dina Safitri', '11004', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 3, '081234567014', '2026-10-20 16:16:19'),
(21, '11005', 'Bayu Firmansyah', '11005', '3afa0d81296a4f17d477ec823261b1ec', 'siswa', NULL, NULL, NULL, 3, '081234567015', '2026-10-20 16:16:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_tanggal` (`user_id`,`tanggal`);

--
-- Indexes for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `mapel_id` (`mapel_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_presensi` (`jadwal_id`,`siswa_id`,`tanggal`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `dicatat_oleh` (`dicatat_oleh`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_induk` (`nomor_induk`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
--
-- AUTO_INCREMENT for table `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- AUTO_INCREMENT for table `presensi`
--
ALTER TABLE `presensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `presensi_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `presensi_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `presensi_ibfk_3` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
