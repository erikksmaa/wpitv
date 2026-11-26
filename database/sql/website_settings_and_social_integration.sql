-- Pengaturan Website & Integrasi Akun Media Sosial Publik WPiTV (MySQL 8)
-- Siap diimpor melalui phpMyAdmin pada database target yang belum menerapkan perubahan ini.
-- Seluruh perubahan bersifat aditif dan aman bagi data historis yang sudah ada.
-- Catatan: Jangan jalankan ulang statement ALTER TABLE apabila kolom show_on_website / display_order sudah tersedia.

-- 1. Tabel pengaturan website umum (mendukung pengembangan key pengaturan lainnya)
CREATE TABLE IF NOT EXISTS `wpitv_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` TEXT COLLATE utf8mb4_unicode_ci NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wpitv_settings_key_unique` (`setting_key`),
  KEY `wpitv_settings_updated_by_idx` (`updated_by`),
  CONSTRAINT `wpitv_settings_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Riwayat audit perubahan pengaturan website (append-only)
CREATE TABLE IF NOT EXISTS `wpitv_setting_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` INT UNSIGNED NULL,
  `actor_name_snapshot` VARCHAR(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_value` TEXT COLLATE utf8mb4_unicode_ci NULL,
  `after_value` TEXT COLLATE utf8mb4_unicode_ci NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `wpitv_setting_events_key_created_idx` (`setting_key`, `created_at`),
  KEY `wpitv_setting_events_actor_idx` (`actor_id`),
  CONSTRAINT `wpitv_setting_events_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tambahan visibilitas publik & urutan tampil pada akun Media Sosial existing
ALTER TABLE `tvclub_social_accounts`
  ADD COLUMN `show_on_website` TINYINT(1) NOT NULL DEFAULT 0 AFTER `profile_url`,
  ADD COLUMN `display_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `show_on_website`;

CREATE INDEX `tvclub_social_accounts_public_idx`
  ON `tvclub_social_accounts` (`archived_at`, `show_on_website`, `display_order`, `id`);
