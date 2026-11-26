-- Gabungan kebutuhan DDL WPiTV untuk Modul Media Sosial & Deskripsi Live Streaming Manual (MySQL 8).
-- Siap diimpor melalui phpMyAdmin pada database target baru yang belum menerapkan perubahan ini.
-- Catatan: Jangan jalankan ulang pada database yang sudah memiliki tabel/kolom di bawah ini.

-- 1. Media Sosial: dukungan platform YouTube & tautan opsional pada publikasi
ALTER TABLE tvclub_social_accounts
  DROP CHECK social_accounts_check_1,
  ADD CONSTRAINT social_accounts_check_1 CHECK (platform IN ('instagram','tiktok','facebook','youtube'));

ALTER TABLE tvclub_social_posts
  MODIFY url VARCHAR(500) NULL,
  MODIFY url_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;

-- 2. Media Sosial: tabel laporan bulanan
CREATE TABLE IF NOT EXISTS tvclub_social_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_start DATE NOT NULL UNIQUE,
  status ENUM('draft','submitted','revision','approved') NOT NULL DEFAULT 'draft',
  author_id INT UNSIGNED NOT NULL,
  draft JSON NOT NULL,
  version_id BIGINT UNSIGNED NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY (author_id) REFERENCES users(id),
  FOREIGN KEY (version_id) REFERENCES tvclub_social_report_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Live Streaming: kolom deskripsi untuk siaran manual
ALTER TABLE live_streams
  ADD COLUMN manual_description TEXT NULL AFTER manual_title;
