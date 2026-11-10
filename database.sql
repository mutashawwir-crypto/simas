-- =====================================================
-- SIMAS - Sistem Informasi Manajemen Masjid
-- Import file ini lewat phpMyAdmin (tab Import)
-- =====================================================

CREATE DATABASE IF NOT EXISTS db_simas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_simas;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS jadwal;
DROP TABLE IF EXISTS kas;
DROP TABLE IF EXISTS petugas;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------
-- Tabel users
-- -----------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','bendahara','jamaah') NOT NULL DEFAULT 'jamaah',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabel kas
-- -----------------------------------------------------
CREATE TABLE kas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tanggal DATE NOT NULL,
  jenis ENUM('masuk','keluar') NOT NULL,
  kategori VARCHAR(50) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tanggal (tanggal),
  KEY idx_jenis (jenis),
  CONSTRAINT fk_kas_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabel petugas
-- -----------------------------------------------------
CREATE TABLE petugas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(100) NOT NULL,
  jabatan ENUM('Imam','Muazin','Marbot','Khatib','Pengurus') NOT NULL,
  telepon VARCHAR(20) DEFAULT NULL,
  alamat VARCHAR(255) DEFAULT NULL,
  status ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jabatan (jabatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabel jadwal
-- -----------------------------------------------------
CREATE TABLE jadwal (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tanggal DATE NOT NULL,
  waktu ENUM('Subuh','Dzuhur','Ashar','Maghrib','Isya','Jumat') NOT NULL,
  imam_id INT UNSIGNED DEFAULT NULL,
  muazin_id INT UNSIGNED DEFAULT NULL,
  khatib_id INT UNSIGNED DEFAULT NULL,
  tema VARCHAR(150) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tanggal_waktu (tanggal, waktu),
  CONSTRAINT fk_jadwal_imam FOREIGN KEY (imam_id) REFERENCES petugas (id) ON DELETE SET NULL,
  CONSTRAINT fk_jadwal_muazin FOREIGN KEY (muazin_id) REFERENCES petugas (id) ON DELETE SET NULL,
  CONSTRAINT fk_jadwal_khatib FOREIGN KEY (khatib_id) REFERENCES petugas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATA DUMMY
-- Password: admin / admin123, bendahara / bendahara123, jamaah / jamaah123
-- =====================================================

INSERT INTO users (nama, username, password, role) VALUES
('Administrator Masjid', 'admin',     '$2y$10$6IQGGXlP4Z8i0hoeLIJM9u6wptzFGz48wH2JxkWD/lhkkYZ7Hg/hC', 'admin'),
('Bapak Syafruddin',     'bendahara', '$2y$10$fIGj97HbxiLhftVqC/ok1eF4g5YtBYb0seDraYtyw3ox3RkHsC3mW', 'bendahara'),
('Jamaah Masjid',        'jamaah',    '$2y$10$fZC3HdNNaQs1KnqOvtl30.JXmmwZjqfWKZBs746Yd0mJ5fl4DAzVO', 'jamaah');

INSERT INTO petugas (nama, jabatan, telepon, alamat, status) VALUES
('Ust. Abdul Rahman',      'Imam',     '081234567801', 'Jl. Pattimura No. 12',       'Aktif'),
('H. Muhammad Yusuf',      'Imam',     '081234567802', 'Jl. Sultan Thaha No. 5',     'Aktif'),
('Ahmad Fauzi',            'Muazin',   '081234567803', 'Jl. Kenanga No. 8',          'Aktif'),
('Rizal Hakim',            'Muazin',   '081234567804', 'Jl. Melati No. 21',          'Aktif'),
('Pak Sugeng',             'Marbot',   '081234567805', 'Kompleks Masjid, Rumah Jaga', 'Aktif'),
('Bang Udin',              'Marbot',   '081234567806', 'Jl. Mawar No. 3',            'Aktif'),
('Ust. Dr. Hasan Basri',   'Khatib',   '081234567807', 'Jl. Gajah Mada No. 17',      'Aktif'),
('Ust. Ali Mustofa',       'Khatib',   '081234567808', 'Jl. Diponegoro No. 40',      'Aktif'),
('H. Darmawan',            'Pengurus', '081234567809', 'Jl. Merdeka No. 1',          'Aktif'),
('Bapak Syafruddin',       'Pengurus', '081234567810', 'Jl. Cendana No. 9',          'Aktif');

INSERT INTO kas (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES
(DATE_SUB(CURDATE(), INTERVAL 75 DAY), 'masuk',  'Infaq Jumat',    'Kotak infaq Jumat pekan 1',          1850000, 2),
(DATE_SUB(CURDATE(), INTERVAL 68 DAY), 'masuk',  'Donasi',         'Donasi hamba Allah untuk operasional', 5000000, 2),
(DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'keluar', 'Listrik & Air',  'Tagihan listrik dan air bulanan',     1250000, 2),
(DATE_SUB(CURDATE(), INTERVAL 54 DAY), 'keluar', 'Honor Petugas',  'Honor marbot dan muazin',             2500000, 2),
(DATE_SUB(CURDATE(), INTERVAL 47 DAY), 'masuk',  'Infaq Jumat',    'Kotak infaq Jumat pekan 2',          2100000, 2),
(DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'masuk',  'Zakat',          'Penerimaan zakat mal',               3500000, 2),
(DATE_SUB(CURDATE(), INTERVAL 33 DAY), 'keluar', 'Perawatan',      'Perbaikan sound system',               950000, 2),
(DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'masuk',  'Sedekah',        'Sedekah jamaah pengajian',            1200000, 2),
(DATE_SUB(CURDATE(), INTERVAL 21 DAY), 'keluar', 'Kebersihan',     'Pembelian alat kebersihan dan pewangi', 450000, 2),
(DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'masuk',  'Infaq Jumat',    'Kotak infaq Jumat pekan 3',          2350000, 2),
(DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'keluar', 'Listrik & Air',  'Tagihan listrik dan air bulanan',     1300000, 2),
(DATE_SUB(CURDATE(), INTERVAL 9 DAY),  'keluar', 'Honor Petugas',  'Honor marbot dan muazin',             2500000, 2),
(DATE_SUB(CURDATE(), INTERVAL 6 DAY),  'masuk',  'Wakaf',          'Wakaf karpet dari jamaah',            4000000, 2),
(DATE_SUB(CURDATE(), INTERVAL 3 DAY),  'masuk',  'Infaq Jumat',    'Kotak infaq Jumat pekan ini',        2250000, 2),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY),  'keluar', 'Kegiatan',       'Konsumsi pengajian rutin',             600000, 2);

-- Jadwal: hari ini dan besok (5 waktu sholat) + 4 Jumat ke depan
INSERT INTO jadwal (tanggal, waktu, imam_id, muazin_id, khatib_id, tema) VALUES
(CURDATE(), 'Subuh',   1, 3, NULL, NULL),
(CURDATE(), 'Dzuhur',  2, 4, NULL, NULL),
(CURDATE(), 'Ashar',   1, 3, NULL, NULL),
(CURDATE(), 'Maghrib', 2, 4, NULL, NULL),
(CURDATE(), 'Isya',    1, 3, NULL, NULL),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Subuh',   2, 4, NULL, NULL),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Dzuhur',  1, 3, NULL, NULL),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Ashar',   2, 4, NULL, NULL),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Maghrib', 1, 3, NULL, NULL),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Isya',    2, 4, NULL, NULL),
(DATE_ADD(CURDATE(), INTERVAL ((4 - WEEKDAY(CURDATE()) + 7) % 7) DAY),      'Jumat', 1, 3, 7, 'Menjaga Keikhlasan dalam Beramal'),
(DATE_ADD(CURDATE(), INTERVAL ((4 - WEEKDAY(CURDATE()) + 7) % 7 + 7) DAY),  'Jumat', 2, 4, 8, 'Keutamaan Bersedekah'),
(DATE_ADD(CURDATE(), INTERVAL ((4 - WEEKDAY(CURDATE()) + 7) % 7 + 14) DAY), 'Jumat', 1, 3, 7, 'Adab Bertetangga dalam Islam'),
(DATE_ADD(CURDATE(), INTERVAL ((4 - WEEKDAY(CURDATE()) + 7) % 7 + 21) DAY), 'Jumat', 2, 4, 8, 'Meneladani Akhlak Rasulullah');
