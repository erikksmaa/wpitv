-- WPiTV manual MySQL 8 schema change. Do not run through Laravel migrations.
-- The backup table is temporary rollback protection and must be removed after verification.

SELECT COUNT(*) AS historical_email_rows
FROM tr_perliputan
WHERE email IS NOT NULL AND TRIM(email) <> '';

CREATE TABLE tr_perliputan_email_retirement_backup LIKE tr_perliputan;

INSERT INTO tr_perliputan_email_retirement_backup
SELECT * FROM tr_perliputan;

ALTER TABLE tr_perliputan
DROP COLUMN email;

SHOW COLUMNS FROM tr_perliputan;

DROP TABLE tr_perliputan_email_retirement_backup;
