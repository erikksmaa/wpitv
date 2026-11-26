-- Apply once in phpMyAdmin after review (MySQL 8).
ALTER TABLE tvclub_social_accounts DROP CHECK social_accounts_check_1,
  ADD CONSTRAINT social_accounts_check_1 CHECK (platform IN ('instagram','tiktok','facebook','youtube'));
ALTER TABLE tvclub_social_posts MODIFY url VARCHAR(500) NULL,
  MODIFY url_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;
CREATE TABLE tvclub_social_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_start DATE NOT NULL UNIQUE,
  status ENUM('draft','submitted','revision','approved') NOT NULL DEFAULT 'draft',
  author_id INT UNSIGNED NOT NULL,
  draft JSON NOT NULL,
  version_id BIGINT UNSIGNED NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY (author_id) REFERENCES users(id),
  FOREIGN KEY (version_id) REFERENCES tvclub_social_report_versions(id)
) ENGINE=InnoDB;
