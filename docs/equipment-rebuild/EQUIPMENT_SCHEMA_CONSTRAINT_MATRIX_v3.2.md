# Equipment Schema Constraint Matrix v3.2

## Status berlaku — Phase 6C.4L.1E, 23 November 2026

**APPROVED sebagai baseline desain** berdasarkan EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md: paket teknis v3.2 setelah koreksi R1, lost→retired dan reason policy Technical7.1. Label/kalimat proposal, rekomendasi atau menunggu approval dari Phase1D/R1 yang masih dikutip untuk detail desain dalam scope tersebut kini dibaca APPROVED; catatan historis bukan keputusan pending baru. Ini tidak mengesahkan bukti lingkungan, hasil tes, provisioning, grant runtime atau cutover. Bukti yang belum tersedia tetap **BLOCKED**; Phase6C.4L.2 belum diotorisasi. Strategi histori, loan legacy aktif, identitas target, manifest operasional, backup/restore dan cutover tetap memerlukan keputusan/bukti tersendiri. Record1E menjadi otoritas status terbaru, record1C tetap historis.

**APPROVED physical design baseline Phase1E**, 11 domain tables + external users stub, nol VIEW. Prinsip baseline APPROVED menurut record1C; detail matrix disetujui sebagai desain; belum diuji MySQL. Baca Technical Contract v3.2 dan readiness untuk BLOCKED evidence. Bukan SQL/migration dan tidak dieksekusi.

## 1. Semua kolom: tipe, NULL dan default

NN=NOT NULL (PK selalu NN); NULL=nullable. Semua bigint/int/smallint domain di bawah UNSIGNED. DATETIME(6) wajib UTC server dan tidak punya default waktu. VARCHAR/TEXT utf8mb4_unicode_ci kecuali token/code/hash/operation/actor_key/result_type/event_type dan outcome/status/enum vocabulary memakai ASCII binary-safe collation pada desain SQL. Metadata foto filename asli tidak disimpan. Nullable normal default NULL; generated column tidak memiliki input default; `none` berarti nilai wajib disuplai server. unsigned adalah kontrak SQL, tidak diekspresikan native oleh DBML bigint/int shorthand.

| Table | Column | SQL target type | Nullability | Default / generation | Key |
| --- | --- | --- | --- | --- | --- |
| users | id | int UNSIGNED | NN | none | PK |
| equipment_categories | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_categories | name | varchar(100) | NN | none | UNIQUE |
| equipment_categories | description | varchar(1000) | NULL | NULL | — |
| equipment_categories | sort_order | smallint UNSIGNED | NN | 0 | — |
| equipment_categories | is_active | boolean | NN | true | — |
| equipment_categories | version | int UNSIGNED | NN | 1 (successful-update counter; NOT AUTO_INCREMENT) | — |
| equipment_categories | created_at | datetime(6) | NN | none | — |
| equipment_categories | updated_at | datetime(6) | NN | none | — |
| equipment_models | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_models | category_id | bigint UNSIGNED | NN | none | — |
| equipment_models | model_code | varchar(40) | NN | none | UNIQUE |
| equipment_models | name | varchar(150) | NN | none | — |
| equipment_models | brand | varchar(100) | NULL | NULL | — |
| equipment_models | model_name | varchar(100) | NULL | NULL | — |
| equipment_models | description | text | NULL | NULL | — |
| equipment_models | is_active | boolean | NN | true | — |
| equipment_models | next_unit_no | int UNSIGNED | NN | 1 | — |
| equipment_models | version | int UNSIGNED | NN | 1 | — |
| equipment_models | created_at | datetime(6) | NN | none | — |
| equipment_models | updated_at | datetime(6) | NN | none | — |
| equipment_units | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_units | model_id | bigint UNSIGNED | NN | none | — |
| equipment_units | unit_no | int UNSIGNED | NN | none | — |
| equipment_units | asset_code | varchar(60) | NN | none | UNIQUE |
| equipment_units | serial_number | varchar(100) | NULL | NULL | — |
| equipment_units | physical_condition | varchar(20) | NN | 'good' | — |
| equipment_units | operational_status | varchar(20) | NN | 'active' | — |
| equipment_units | status_changed_at | datetime(6) | NN | none | — |
| equipment_units | closed_at | datetime(6) | NULL | NULL | — |
| equipment_units | closed_reason | varchar(1000) | NULL | NULL | — |
| equipment_units | notes | text | NULL | NULL | — |
| equipment_units | version | int UNSIGNED | NN | 1 | — |
| equipment_units | created_at | datetime(6) | NN | none | — |
| equipment_units | updated_at | datetime(6) | NN | none | — |
| equipment_model_photos | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_model_photos | model_id | bigint UNSIGNED | NN | none | — |
| equipment_model_photos | revision | int UNSIGNED | NN | none | — |
| equipment_model_photos | storage_key | varchar(255) | NN | none | UNIQUE |
| equipment_model_photos | mime | varchar(40) | NN | none | — |
| equipment_model_photos | byte_size | bigint UNSIGNED | NN | none | — |
| equipment_model_photos | sha256 | char(64) | NN | none | — |
| equipment_model_photos | uploaded_by | int UNSIGNED | NULL | NULL | — |
| equipment_model_photos | created_at | datetime(6) | NN | none | — |
| equipment_unit_photos | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_unit_photos | unit_id | bigint UNSIGNED | NN | none | — |
| equipment_unit_photos | photo_type | varchar(24) | NN | none | — |
| equipment_unit_photos | storage_key | varchar(255) | NN | none | UNIQUE |
| equipment_unit_photos | mime | varchar(40) | NN | none | — |
| equipment_unit_photos | byte_size | bigint UNSIGNED | NN | none | — |
| equipment_unit_photos | sha256 | char(64) | NN | none | — |
| equipment_unit_photos | uploaded_by | int UNSIGNED | NULL | NULL | — |
| equipment_unit_photos | captured_at | datetime(6) | NULL | NULL | — |
| equipment_unit_photos | created_at | datetime(6) | NN | none | — |
| equipment_loans | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_loans | loan_code | varchar(40) | NN | none | UNIQUE |
| equipment_loans | borrower_type | varchar(20) | NN | none | — |
| equipment_loans | borrower_name | varchar(150) | NN | none | — |
| equipment_loans | identity_number | varchar(40) | NN | none | — (nonunique borrower search index only) |
| equipment_loans | organization | varchar(150) | NN | none | — |
| equipment_loans | whatsapp_number | varchar(20) | NN | none | — |
| equipment_loans | purpose | text | NN | none | — |
| equipment_loans | borrowed_at | datetime(6) | NN | none | — |
| equipment_loans | original_due_date | date | NN | none | — |
| equipment_loans | due_date | date | NN | none | — |
| equipment_loans | recorded_by | int UNSIGNED | NULL | NULL | — |
| equipment_loans | recorded_by_name | varchar(100) | NN | none | — |
| equipment_loans | notes | text | NULL | NULL | — |
| equipment_loans | version | int UNSIGNED | NN | 1 | — |
| equipment_loans | created_at | datetime(6) | NN | none | — |
| equipment_loans | updated_at | datetime(6) | NN | none | — |
| equipment_returns | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_returns | loan_id | bigint UNSIGNED | NN | none | — |
| equipment_returns | receipt_code | varchar(50) | NN | none | UNIQUE |
| equipment_returns | returned_at | datetime(6) | NN | none | — |
| equipment_returns | received_by | int UNSIGNED | NULL | NULL | — |
| equipment_returns | received_by_name | varchar(100) | NN | none | — |
| equipment_returns | notes | text | NULL | NULL | — |
| equipment_returns | created_at | datetime(6) | NN | none | — |
| equipment_loan_units | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_loan_units | loan_id | bigint UNSIGNED | NN | none | — |
| equipment_loan_units | unit_id | bigint UNSIGNED | NN | none | — |
| equipment_loan_units | category_id_at_checkout | bigint UNSIGNED | NN | none | — |
| equipment_loan_units | model_id_at_checkout | bigint UNSIGNED | NN | none | — |
| equipment_loan_units | unit_snapshot | json | NN | none | — |
| equipment_loan_units | condition_out | varchar(20) | NN | none | — |
| equipment_loan_units | checkout_notes | text | NULL | NULL | — |
| equipment_loan_units | outcome | varchar(20) | NN | 'on_loan' | — |
| equipment_loan_units | return_id | bigint UNSIGNED | NULL | NULL | — |
| equipment_loan_units | condition_in | varchar(20) | NULL | NULL | — |
| equipment_loan_units | due_date_at_resolution | date | NULL | NULL | — |
| equipment_loan_units | is_late | boolean | NULL | NULL | — |
| equipment_loan_units | resolution_at | datetime(6) | NULL | NULL | — |
| equipment_loan_units | resolution_notes | text | NULL | NULL | — |
| equipment_loan_units | open_unit_id | bigint UNSIGNED | NULL | STORED IF(outcome=on_loan,unit_id,NULL); no input default | — |
| equipment_loan_units | created_at | datetime(6) | NN | none | — |
| equipment_loan_units | updated_at | datetime(6) | NN | none | — |
| equipment_return_photos | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_return_photos | return_id | bigint UNSIGNED | NN | none | — |
| equipment_return_photos | loan_unit_id | bigint UNSIGNED | NN | none | — |
| equipment_return_photos | storage_key | varchar(255) | NN | none | UNIQUE |
| equipment_return_photos | mime | varchar(40) | NN | none | — |
| equipment_return_photos | byte_size | bigint UNSIGNED | NN | none | — |
| equipment_return_photos | sha256 | char(64) | NN | none | — |
| equipment_return_photos | created_at | datetime(6) | NN | none | — |
| equipment_operation_keys | token | char(36) | NN | none | PK |
| equipment_operation_keys | operation | varchar(32) | NN | none | — |
| equipment_operation_keys | actor_key | varchar(64) | NN | none | — |
| equipment_operation_keys | payload_hash | char(64) | NN | none | — |
| equipment_operation_keys | result_type | varchar(32) | NULL | NULL | — |
| equipment_operation_keys | result_id | bigint UNSIGNED | NULL | NULL | — |
| equipment_operation_keys | created_at | datetime(6) | NN | none | — |
| equipment_operation_keys | completed_at | datetime(6) | NULL | NULL | — |
| equipment_audit_log | id | bigint UNSIGNED | NN | AUTO_INCREMENT (no explicit default) | PK |
| equipment_audit_log | subject_type | varchar(24) | NN | none | — |
| equipment_audit_log | subject_id | bigint UNSIGNED | NN | none | — |
| equipment_audit_log | event_type | varchar(60) | NN | none | — |
| equipment_audit_log | loan_id | bigint UNSIGNED | NULL | NULL | — |
| equipment_audit_log | actor_id | int UNSIGNED | NULL | NULL | — |
| equipment_audit_log | actor_name | varchar(100) | NN | none | — |
| equipment_audit_log | reason | varchar(1000) | NN | none | — |
| equipment_audit_log | before_data | json | NULL | NULL | — |
| equipment_audit_log | after_data | json | NULL | NULL | — |
| equipment_audit_log | created_at | datetime(6) | NN | none | — |

users.id adalah external stub expected INT UNSIGNED PK: BLOCKED actual authoritative type/version proof pada calon clone, bukan perintah membuat users. Tipe FK tidak boleh disesuaikan berdasarkan Eloquent default semata. Field actor nullable hanya untuk importer histori disetujui; runtime semua aktor wajib valid.

## 2. Semua FK, arah dan delete/update semantics

Seluruh relasi di bawah PROPOSED RESTRICT/noncascading; actor FKs berubah dari SET NULL v3.1. Update identity ditolak, bukan cascade. Untuk kolom yang ikut CHECK, rencana SQL memakai FK default noncascading tanpa explicit ON DELETE/ON UPDATE; DBML `[delete: restrict]` menyatakan semantic intent, bukan instruksi mengeksekusi exporter verbatim. Jangan menggabungkan cascading/SET NULL action dengan CHECK pada kolom yang sama. Proof acceptance khusus return_id/composite FK tetap BLOCKED sebelum DDL clone yang diotorisasi.

| FK name (PROPOSED) | Child columns | Parent columns | Delete/update policy | Integrity |
| --- | --- | --- | --- | --- |
| fk_eq_models_category_id | equipment_models.category_id | equipment_categories.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_units_model_id | equipment_units.model_id | equipment_models.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_mphoto_model_id | equipment_model_photos.model_id | equipment_models.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_mphoto_uploaded_by | equipment_model_photos.uploaded_by | users.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_uphoto_unit_id | equipment_unit_photos.unit_id | equipment_units.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_uphoto_uploaded_by | equipment_unit_photos.uploaded_by | users.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_loans_recorded_by | equipment_loans.recorded_by | users.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_returns_loan_id | equipment_returns.loan_id | equipment_loans.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_returns_received_by | equipment_returns.received_by | users.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_lunits_loan_id | equipment_loan_units.loan_id | equipment_loans.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_lunits_unit_id | equipment_loan_units.unit_id | equipment_units.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_lunits_return_id_loan_id | equipment_loan_units.(return_id, loan_id) | equipment_returns.(id, loan_id) | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_rphoto_return_id | equipment_return_photos.return_id | equipment_returns.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_rphoto_loan_unit_id_return_id | equipment_return_photos.(loan_unit_id, return_id) | equipment_loan_units.(id, return_id) | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_audit_loan_id | equipment_audit_log.loan_id | equipment_loans.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_audit_actor_id | equipment_audit_log.actor_id | users.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_lunits_category_id_snapshot | equipment_loan_units.category_id_at_checkout | equipment_categories.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |
| fk_eq_lunits_model_id_snapshot | equipment_loan_units.model_id_at_checkout | equipment_models.id | RESTRICT / identity immutable | Parent PK/explicit UNIQUE; types+order match |

Composite receipt FK memastikan receipt milik loan yang sama. Composite photo FK memastikan loan-unit menunjuk receipt yang sama; direct return FK tetap sebagai explicit parent scope. Nullable return_id pada on_loan/lost/voided membuat composite FK tidak aktif untuk row tersebut; outcome nullability tidak dapat disimpulkan dari FK. Snapshot category/model FK independen; hierarchy hanya cocok SAAT checkout melalui locked service check.

## 3. Semua UNIQUE dan secondary index

PK id setiap domain table kecuali operation_keys.token. Single-column UNIQUE: categories.name, models.model_code, units.asset_code, loans.loan_code, returns.receipt_code, dan storage_key di masing-masing tiga tabel foto. Kode/storage key immutable. Token PK unik global, tidak unique per actor. Cross-photo-table storage_key ownership diperiksa service; tidak menambah tabel media. Seluruh composite/secondary indexes eksplisit DBML:

| Table | Columns | Kind / name |
| --- | --- | --- |
| equipment_categories | is_active, sort_order | INDEX idx_eq_categories_order |
| equipment_models | category_id, is_active | INDEX idx_eq_models_category_active |
| equipment_models | name | INDEX idx_eq_models_name |
| equipment_units | model_id, unit_no | UNIQUE uq_eq_units_model_number |
| equipment_units | model_id, operational_status, physical_condition, id | INDEX idx_eq_units_stock |
| equipment_units | model_id, physical_condition | INDEX idx_eq_units_model_condition |
| equipment_model_photos | uploaded_by | INDEX idx_eq_model_photos_actor |
| equipment_model_photos | model_id, revision | UNIQUE uq_eq_model_photos_revision |
| equipment_unit_photos | uploaded_by | INDEX idx_eq_unit_photos_actor |
| equipment_unit_photos | unit_id, created_at | INDEX idx_eq_unit_photos_history |
| equipment_loans | recorded_by | INDEX idx_eq_loans_actor |
| equipment_loans | borrower_type, identity_number | INDEX idx_eq_loans_borrower |
| equipment_loans | due_date, id | INDEX idx_eq_loans_due_date |
| equipment_loans | borrowed_at, id | INDEX idx_eq_loans_borrowed_at |
| equipment_returns | received_by | INDEX idx_eq_returns_actor |
| equipment_returns | returned_at, id | INDEX idx_eq_returns_time |
| equipment_returns | id, loan_id | UNIQUE uq_eq_returns_id_loan |
| equipment_returns | loan_id, returned_at | INDEX idx_eq_returns_loan_time |
| equipment_loan_units | outcome, resolution_at, loan_id | INDEX idx_eq_loan_units_resolution |
| equipment_loan_units | category_id_at_checkout, model_id_at_checkout, loan_id | INDEX idx_eq_loan_units_checkout_category |
| equipment_loan_units | model_id_at_checkout, loan_id | INDEX idx_eq_loan_units_checkout_model |
| equipment_loan_units | loan_id, unit_id | UNIQUE uq_eq_loan_units_loan_unit |
| equipment_loan_units | open_unit_id | UNIQUE uq_eq_loan_units_active_unit |
| equipment_loan_units | id, return_id | UNIQUE uq_eq_loan_units_id_return |
| equipment_loan_units | return_id, loan_id | INDEX idx_eq_loan_units_return_loan |
| equipment_loan_units | loan_id, outcome | INDEX idx_eq_loan_units_loan_outcome |
| equipment_loan_units | unit_id, outcome | INDEX idx_eq_loan_units_unit_outcome |
| equipment_return_photos | return_id | INDEX idx_eq_return_photos_return |
| equipment_return_photos | loan_unit_id, return_id | INDEX idx_eq_return_photos_unit_return |
| equipment_operation_keys | operation, created_at | INDEX idx_eq_opkeys_operation_time |
| equipment_audit_log | actor_id, created_at | INDEX idx_eq_audit_actor |
| equipment_audit_log | subject_type, subject_id, created_at | INDEX idx_eq_audit_subject |
| equipment_audit_log | loan_id, created_at | INDEX idx_eq_audit_loan |

Index purpose: units stock composite mengawali model_id/status/condition untuk availability; open_unit_id UNIQUE membela double checkout; loan_id+outcome membaca progres; returns time+id dan outcome+resolution_at+loan_id mendukung period event; dua indeks snapshot mendukung kategori/model histori; borrowed_at/due_date+id mendukung cohort/order; actor/loan/subject audit indexes menjaga FK/history. Prefix index tidak dipakai untuk FK/unique codes. Evaluate EXPLAIN di clone setelah izin; tidak mengklaim index optimal atau query telah diuji sekarang.

## 4. CHECK predicates (logical specification, bukan DDL)

Seluruh constraint ENFORCED pada calon MySQL 8.0.30; nama eqc_<table-short>_<rule> unik per schema <=64 chars. Tidak ada NOT ENFORCED. CHECK tidak menolak UNKNOWN, maka semua branch nullable memakai IS NULL/IS NOT NULL eksplisit. Jangan cek auto-increment id, waktu NOW(), current user/role, SELECT ke parent, aggregate/minimum child count atau nilai sesi dalam CHECK.

| ID | Table/columns | Predicate lokal yang diusulkan |
| --- | --- | --- |
| C-01 | categories/models is_active | is_active hanya 0 atau1 (NN); categories.sort_order 0..65535 |
| C-02 | categories/models/units/loans version | version 1..4294967295, NN |
| C-03 | categories.name; models.name; borrower/actor snapshot names; receipt/loan code | CHAR_LENGTH(TRIM(value))>0 sesuai field NN; string length dari type; normalisasi/control validation service |
| C-04 | models.next_unit_no | 1..1000000; reservation range/monotonicity service |
| C-05 | units.unit_no | 1..999999; model sequence relation service |
| C-06 | units.physical_condition; loan_units.condition_out/condition_in | good/minor_damage/major_damage; condition_in NULL hanya sesuai C-11; checkout baru condition_out good di service |
| C-07 | units.operational_status+closed_* | enum active/maintenance/lost/retired; lost/retired: closed_at NONNULL dan closed_reason NONNULL/TRIM nonblank; active/maintenance: keduanya NULL |
| C-08 | loans.borrower_type/dates | student/lecturer/staff; due_date>=original_due_date; DATE(borrowed_at) UTC bukan tanggal WIB, perbandingan WIB borrower-time ditangani service |
| C-09 | all photo metadata | mime image/jpeg,image/png,image/webp; byte_size 1..5000000; sha256 exactly64 lowercase hex; model photo revision>=1; unit photo_type reference/inspection/damage |
| C-10 | loan_units.outcome | on_loan/returned/lost/voided, NN |
| C-11 | loan_units resolution tuple | Truth table berikut; is_late jika nonnull hanya0/1; explicit IS NOT NULL pada seluruh field wajib |
| C-12 | operation_keys | UUID canonical lowercase bentuk8-4-4-4-12; operation/result_type allowlist Technical registry; actor_key users:<positive decimal ID>; payload_hash exactly64 lowercasehex |
| C-13 | operation_keys result tuple | result_type/result_id/completed_at semuaNULL OR semuanyaNONNULL; result_id>0 bila nonnull; completed_at>=created_at bila lengkap |
| C-14 | audit_log subject/event/reason | subject_type category/model/unit/loan/loan_unit/return; subject_id>0 (bukan auto PK); event_type registry; reason/actor_name nonblank; keberadaan subject service |
| C-15 | status_changed_at/closed_at; timestamps | closed_at jika ada >=created_at; status_changed_at>=created_at; updated_at>=created_at di tabel mutable; tidak membandingkan dengan NOW() |

**Outcome truth table C-11** (return_id FK compatibility proof tetap gate; jangan diam-diam menghapus aturan atau FK):

| outcome | return_id | condition_in | due_date_at_resolution | is_late | resolution_at | resolution_notes |
| --- | --- | --- | --- | --- | --- | --- |
| on_loan | NULL | NULL | NULL | NULL | NULL | NULL |
| returned | NONNULL | valid condition | NONNULL | 0/1 NONNULL | NULL | NULL atau teks; service wajib notes bila rusak/late (Technical7.1 approved) |
| lost | NULL | NULL | NONNULL | 0/1 NONNULL | NONNULL | NONNULL/nonblank |
| voided | NULL | NULL | NULL | NULL | NONNULL | NONNULL/nonblank |

Tanggal return ada HANYA pada equipment_returns.returned_at. Validasi is_late formula perlu parent timestamp/tenggat dan WIB conversion, sehingga dibekukan service, bukan cross-table CHECK. Loss/void event_at>=borrowed_at juga service. Operation result_type enum CHECK harus mengizinkan NULL ketika pending; C-13 mencegah tuple setengah lengkap namun bukan COMMIT pending. Foto count/cek decoded bytes bukan CHECK metadata belaka.

## 5. Lifecycle truth table dan invariant service

| State/action | Allowed result | Conditions / status |
| --- | --- | --- |
| active→maintenance | maintenance | PROPOSED alasan+permission, bebas on_loan |
| maintenance→active | active | PROPOSED kondisi good+inspection, bebas |
| active/maintenance→lost | lost | PROPOSED bebas; on_loan hanya loan.loss atomik |
| lost→found | maintenance | APPROVED permission recovery+reason, hasil maintenance dan loan lost immutable; PROPOSED detail clear closed_* current + audit before/after |
| active/maintenance→retired | retired | APPROVED permission+reason, bebas on_loan, terminal/no hard delete; detail source-state PROPOSED |
| lost→retired | retired | APPROVED Phase1E R1: verifikasi tidak ada on_loan; permission retire+alasan eksplisit; bukan found; outcome lost immutable, audit before/after closed_* |
| retired→anything | ditolak | APPROVED terminal/no hard delete |
| management edit saat on_loan | ditolak | APPROVED kondisi/status/retire; transaksi return/loss bukan management edit |
| returned damaged | tidak eligible | PROPOSED operational tidak otomatis maintenance; condition_in immutable |

Service-only: model/category eligibility dan snapshot match saat checkout, monotonic numbering/revision/version, maximum request quantity, atomic all-unit checkout/return/void, tidak ada loan/receipt kosong, minimal satu decoded photo per returned unit, no duplicate uploads across entities, no mixed void outcomes, no invalid unit/allocation drift, terminal transitions, historical snapshot/audit immutability, authorized replay, pending-key anti-commit, file durability dan orphan leases. Semua memerlukan future transaction/fault/race tests, tidak dapat dijanjikan oleh SQL/DBML sendiri.

Progress untuk N>=1: semuaon_loan=active; campuran on_loan+returned/lost tanpa void=partially_resolved; semua returned/lost=closed; semuavoided=voided; empty/mixedvoid=integrity error. Overdue terpisah dan current read snapshot. Jangan membuat kolom status/progress pada loans atau kolom borrowed pada units.

## 6. MySQL compatibility notes / bukti yang belum ada

[Manual CHECK MySQL8.0](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html) membatasi CHECK ke ekspresi lokal deterministik; auto-increment, subquery dan referential-action interaction harus dihindari. NULL menghasilkan UNKNOWN yang dapat lolos, sehingga cabang NULL harus eksplisit. [Manual FK](https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html) mendasari exact key order/types/indexes. [Generated columns](https://dev.mysql.com/doc/refman/8.0/en/create-table-generated-columns.html) mendasari STORED expression/unique; generated key tidak digunakan sebagai FK target.

Rencana physical translation menggunakan default noncascading FK, tidak menambahkan explicit referential actions pada CHECK-participating columns. DBML annotation restrictive adalah semantic proposal, bukan exporter-ready DDL. **BLOCKED evidence**: penerimaan tepat kombinasi composite FK return_id dan C-11 di MySQL8.0.30 belum diuji; harus dibuktikan di clone yang diotorisasi, bukan membuat asumsi kompatibilitas. Jika DDL generator memilih explicit ON DELETE/UPDATE atau server menolak kandidat, hentikan dan review sebelum mengubah enforcement. Tidak melonggarkan service invariant/constraint diam-diam. Keputusan PROPOSED belum merupakan schema executable.


## 7. Nama key single-column (PROPOSED)

PRIMARY untuk seluruh PK. UNIQUE single: uq_eq_categories_name(name), uq_eq_models_code(model_code), uq_eq_units_asset(asset_code), uq_eq_loans_code(loan_code), uq_eq_returns_code(receipt_code), uq_eq_model_photo_storage(storage_key), uq_eq_unit_photo_storage(storage_key), uq_eq_return_photo_storage(storage_key). Composite unique/index names persis pada tabel bagian3/DBML. Seluruh 18 FK names telah ditetapkan pada bagian2, unik <=64 bytes; jangan bergantung nama auto-generated untuk assertion. Physical name equivalence terhadap DBML harus dipertahankan walau generated SQL library menamai berbeda; DBML annotation bukan DDL source yang dieksekusi.


## 8. Kelengkapan CHECK dan daftar nama per tabel (PROPOSED)

C-03 nonblank fields tepatnya: categories.name; models.name/model_code; loans.loan_code/borrower_name/identity_number/organization/whatsapp_number/purpose/recorded_by_name; returns.receipt_code/received_by_name. C-14 audit actor_name/reason nonblank. Optional notes/description/serial dinormalisasi NULL jika kosong dan dibatasi service, tidak diwajibkan CHECK nonblank.

C-16 loans: original_due_date <= due_date (DATE vs DATE, tanpa konversi zona pada CHECK). original_due_date >= tanggal WIB borrowed_at divalidasi service.
C-17 loan_units: resolution_at IS NULL OR resolution_at>=created_at; updated_at>=created_at. Parent borrowed_at/receipt time tetap service-only.
C-18 loans.borrower_type tepat student/lecturer/staff (external tidak diterima v1).
C-19 format model_code dan asset_code uppercase ASCII alnum+dash serta panjang varchar; format penuh/prefix model dan suffix-unit_no cocok melalui service, tidak cross-table CHECK. loan_code = EQ-L-<26 char uppercase ULID>, receipt_code = EQ-R-<26 char uppercase ULID> PROPOSED; UNIQUE dan maksimal3 fresh-transaction retries pada collision server-issued random code, same operation token; no COUNT.

Nama CHECK unik ditetapkan `eqc_<short-table>_<cNN>` untuk setiap pasangan berikut, kecuali ID C11/C13 juga mencakup nullability tuple seperti truth table. Setiap nama <64 karakter, bukan CHECK terpisah pada auto PK. `photo C09` menerapkan hanya subset field yang ada (revision model_photo; photo_type unit_photo; semua mime/size/hash). C15 menerapkan hanya tabel dengan status/timestamp mutable terkait.

| Short table | Exact CHECK IDs / names |
| --- | --- |
| categories | eqc_categories_c01, eqc_categories_c02, eqc_categories_c03 |
| models | eqc_models_c01, eqc_models_c02, eqc_models_c03, eqc_models_c04, eqc_models_c19 |
| units | eqc_units_c02, eqc_units_c05, eqc_units_c06, eqc_units_c07, eqc_units_c15, eqc_units_c19 |
| model_photos | eqc_model_photos_c09 |
| unit_photos | eqc_unit_photos_c09 |
| loans | eqc_loans_c02, eqc_loans_c03, eqc_loans_c16, eqc_loans_c18, eqc_loans_c19 |
| returns | eqc_returns_c03, eqc_returns_c19 |
| loan_units | eqc_loan_units_c06, eqc_loan_units_c10, eqc_loan_units_c11, eqc_loan_units_c17 |
| return_photos | eqc_return_photos_c09 |
| operation_keys | eqc_operation_keys_c12, eqc_operation_keys_c13 |
| audit_log | eqc_audit_log_c14 |

SHA-256 lexical validation termasuk C09/C12 memakai case-sensitive ASCII hex pattern; hash equality terhadap bytes server adalah service. C12 operation/result allowlist mengikuti table registry, NULL result_type hanya pending C13. Tidak memasukkan PK atau FK actor ke CHECK. C11 return_id FK interaction tetap compatibility proof BLOCKED sebagaimana bagian6; tidak menambahkan cascade/SET NULL actions atau mengklaim telah diuji.


## R1 — klarifikasi terbatas, status keputusan tidak dinaikkan

Schema correction: equipment_categories.version = INT UNSIGNED NOT NULL DEFAULT1, tanpa AUTO_INCREMENT/UNIQUE; increment hanya update sukses, tidak replay/rollback. models/units/loans.version sama (INT UNSIGNED NOT NULL DEFAULT1), bukan AUTO_INCREMENT. models.next_unit_no = INT UNSIGNED NOT NULL DEFAULT1, reservation counter transaksional, bukan AUTO_INCREMENT atau quantity stok. Hanya sepuluh PK domain id memakai AUTO_INCREMENT; PK operation_keys.token UUID bukan auto, users stub PK bukan kolom yang dibuat ulang. Loans.identity_number VARCHAR40 NOT NULL TIDAK UNIQUE, juga tidak mempunyai composite UNIQUE dengan borrower_type; idx_eq_loans_borrower(borrower_type,identity_number) nonunique tetap. Peminjam sama boleh mempunyai banyak loan, tanpa borrower master table.

Aturan retire dari lost APPROVED secara eksplisit pada Phase1E: di bawah lock verifikasi tidak ada loan-unit on_loan. Jika status lost tetapi ada on_loan, anggap integrity failure dan tolak, bukan memaksa void/return/loss historis. Permission retire dan alasan eksplisit wajib; closed_at/reason current diganti menjadi kejadian retire server, nilai kehilangan sebelumnya disimpan audit before. Semua loan-unit outcome lost, due snapshot/is_late/resolution_at/notes historis tetap immutable. Tidak perlu found palsu dahulu. Found hanya lost→maintenance; retired terminal, sehingga barang yang ditemukan setelah retire tidak otomatis boleh direaktivasi. No hard delete berlaku pada semua jalur.

C14 reason audit nonblank TIDAK berarti setiap form membutuhkan field alasan manual. Proposal R1 Technical7.1 membedakan alasan eksplisit sensitif/admin dan alasan audit server-generated untuk create/checkout/first optional photo. Predicate C14 tetap nonblank; audit reason server tidak mengganti loan.purpose, identity atau checkout snapshots. Permission-action/result/event registry, seluruh FK/snapshot/composite keys dan result tuple tetap.

Media caps berlaku serentak: 1..8 foto tiap returned loan-unit; <=10unit; <=40foto; setiap file input DAN hasil normalisasi<=5000000 bytes; SUM(input bytes)<=100000000 DAN SUM(final bytes)<=100000000, dihitung terpisah (bukan satu gabungan input+output). Semua batas harus lulus, tidak boleh menukar kelebihan per-unit dengan sisa kuota batch. Dimensi/staging/history tetap.
