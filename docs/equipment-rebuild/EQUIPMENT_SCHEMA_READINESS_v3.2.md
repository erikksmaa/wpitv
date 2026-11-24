# Equipment Schema Readiness v3.2

## Status berlaku — Phase 6C.4L.1E, 23 November 2026

**APPROVED sebagai baseline desain** berdasarkan EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md: paket teknis v3.2 setelah koreksi R1, lost→retired dan reason policy Technical7.1. Label/kalimat proposal, rekomendasi atau menunggu approval dari Phase1D/R1 yang masih dikutip untuk detail desain dalam scope tersebut kini dibaca APPROVED; catatan historis bukan keputusan pending baru. Ini tidak mengesahkan bukti lingkungan, hasil tes, provisioning, grant runtime atau cutover. Bukti yang belum tersedia tetap **BLOCKED**; Phase6C.4L.2 belum diotorisasi. Strategi histori, loan legacy aktif, identitas target, manifest operasional, backup/restore dan cutover tetap memerlukan keputusan/bukti tersendiri. Record1E menjadi otoritas status terbaru, record1C tetap historis.

Phase1D • Documentation only • Implementation NOT AUTHORIZED.

**Status sekarang: BLOCKED untuk READY FOR SCHEMA IMPLEMENTATION**, bukan penolakan terhadap review technical proposal. Baseline teknis disetujui pada Phase1E: TECHNICAL BASELINE APPROVED. Persetujuan ini hanya menutup G-01; gate eksekusi/bukti lain tidak otomatis lulus. Tidak menjalankan SQL, koneksi DB, provisioning, migration atau runtime permission changes.

## 1. Audit konfigurasi read-only (tanpa bootstrap/koneksi)

Dibaca hanya bagian relevan: config/database.php, config/filesystems.php, app/Models/User.php, Role.php, AppServiceProvider.php, app/Support/TestingDatabaseGuard.php, tests/Support/ReleaseConcurrencyGuard.php dan dokumen v3.1/approval1C. Environment file diparse hanya untuk APP_ENV/DB_CONNECTION/host/port/database/username/disk, tidak menyalin password atau APP_KEY.

- `.env` menunjuk mysql loopback:3306, database wpitv dengan akun root; aplikasi local. Ini sumber existing, **bukan** calon target tes Equipment.
- `.env.testing` saat inspeksi menunjuk mysql loopback:3306, database wpitv_testing dan akun runner lama. Tidak diubah dan tidak dipakai. Guard kode saat ini justru mem-pin wpitv_rebuild_testing, jadi konfigurasi file dan guard belum selaras untuk Equipment. Jangan memperbaikinya dengan menunjuk tests ke DB sumber.
- ReleaseConcurrencyGuard mem-pin schema baseline24 tabel, host/port/account serta fingerprint; tidak menerima Equipment baru. TestingDatabaseGuard juga mem-pin nama clone lama. Audit statis ini tidak memverifikasi grants, UUID, DB row counts atau server type.
- Local disk root storage/app/private. Tanpa private runtime clone override, media tes dapat menyentuh storage existing; jangan memakai disk tersebut untuk Equipment clone.
- User/Role model dan Gate provider yang ditinjau role-based; belum ada bukti permission resolver/store khusus. PROPOSED integrasi readonly permission catalog+mapping terpusat tanpa tabel domain baru harus disetujui/diuji, bukan menganggap isSuperAdmin setara permission.

## 2. Rencana isolasi (belum dibuat; identitas operasional tetap menunggu validasi/otorisasi)

| Komponen | Proposal | Bukti/approval yang dibutuhkan |
| --- | --- | --- |
| Database | wpitv_equipment_v32_testing | Nama exact disetujui; bukan wpitv/wp itv_testing/clonebaseline |
| Instance | MySQL8.0.30 terpisah, loopback port3317 calon, UUID/datadir tersendiri | Port bebas, binary version, UUID, resolved datadir dan process ownership belum diverifikasi |
| Provisioner | eq_v32_provisioner pada instance target saja, DDL/CREATE/ALTER/INDEX/REFERENCES sesuai scope DB | Tidak global admin/FILE/SUPER/GRANT ke source; akun setup instance terpisah dari aplikasi |
| Runner | eq_v32_runner | Hanya DML yang diperlukan; audit INSERT/SELECT tanpa UPDATE/DELETE; tidak DDL atau akses source |
| Cleanup test | eq_v32_fixture_cleaner | Clone-only exact owned IDs+incoming-FK checks/journal; tidak dipakai aplikasi atau dioperasikan sekarang |
| Private runtime | storage/app/testing/equipment-v32/runtime-<pid>-<random>/ | Storage/views/session/cache/log/media/temp sendiri, resolved path allowlist, bukan public symlink |
| Data seed | Schema core otoritatif + role names + synthetic fixtures | Tidak menyalin user/borrower/WhatsApp/media/ledger privat ke Git/fixture |
| users.id | Expected INT UNSIGNED | BLOCKED actual DDL proof pada authoritative baseline dan target; model bukan bukti unsigned |
| Engine/session | InnoDB, utf8mb4, strict SQL, FK/UNIQUE checks1, UTC, REPEATABLE READ, no persistent PDO | Dibuktikan pada koneksi provisioning/runner setelah mendapat izin |

## 3. Schema allowlist proposal

Target **35 tables** = core+Live/Monthly baseline24 + Equipment11. users existing core, bukan stub baru. migrations repository memuat sembilan migration baseline feature setelah provisioning otoritatif dan migration Equipment baru yang nanti disetujui; jumlah migration Equipment belum ditetapkan/dibuat.

Baseline24 (source-code allowlist, belum diperiksa ke DB saat ini):

live_stream_events, live_streams, migrations, monthly_activity_attachments, monthly_activity_events, monthly_activity_items, monthly_activity_reports, monthly_activity_versions, ms_banner, ms_news, ms_program, ms_video, roles, tr_perliputan, tr_usercounter, tvclub_generations, tvclub_group_members, tvclub_groups, tvclub_members, tvclub_organization_members, tvclub_organization_positions, users, wpitv_agendas, wpitv_organization_positions.

Equipment11:

equipment_categories, equipment_models, equipment_units, equipment_model_photos, equipment_unit_photos, equipment_loans, equipment_returns, equipment_loan_units, equipment_return_photos, equipment_operation_keys, equipment_audit_log.

Tidak ada inventory_legacy, approval table, Social tambahan, stock aggregate atau report table. Manifest expected table names/types/engines/PK/FK/index/CHECK/generated/schema hash ditandatangani/disetujui terpisah; current guard baseline tidak diedit. Guard Equipment baru/terparameter yang diotorisasi harus fail closed pada instance UUID, DB/account/host/port dan storage; dua PDO independen, no outer transaction untuk race, source access denied, parallel runner ditolak. Provisioner/runner tidak boleh memakai fallback koneksi baca/tulis sumber.

## 4. Backup dan restore proposal

Gunakan schema baseline resmi yang cocok dengan source, hash/manifest diverifikasi; jangan menyusun core schema dari Eloquent. Backup database sumber/media/ledger terbaru jika proses historis/cutover nanti diperlukan, di lokasi privat dengan kontrol akses; bukan repository. Restore drill pada instance terisolasi harus mencocokkan DDL/hash/rows/FK/media tanpa orphan sebelum memakai backup untuk recovery. Provision clone awal dapat memakai schema-only core + synthetic data tanpa data privat. Hentikan jika source/target identity, manifest atau referensi media salah. Recovery dari DDL partial memerlukan rencana langkah terakhir tercommit; jangan menganggap MySQLDDL rollback atomik. Tidak restore ke sumber pada fase ini.

## 5. Gate READY FOR SCHEMA IMPLEMENTATION

| Gate | Sekarang | Bukti yang diperlukan |
| --- | --- | --- |
| G-01 approval teknis v3.2 | APPROVED DESIGN — Phase1E | Persetujuan eksplisit paket v3.2/R1, lost→retired, reason policy; lihat EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md. Bukan izin eksekusi |
| G-02 scope fase berikutnya | BLOCKED | Instruksi baru mengizinkan pembuatan migration/provisioning/DDL di clone yang disebut spesifik |
| G-03 instance/account/storage | BLOCKED | Resolved identity, UUID/version/port/datadir/storage, deny source access, DDL-only scope vs DML runner dan cleanup role terpisah |
| G-04 authoritative baseline/users type | BLOCKED | Baseline artifact+hash/core schema compatibility; users.id INT UNSIGNED dibuktikan tanpa tebakan |
| G-05 DBML/constraint review | Static proposal checked; parser BLOCKED | Parser full bila tersedia tanpa dependency tambahan; semua PK/FK/index/check/outcome tuple diterjemahkan sesuai manual |
| G-06 restore/fingerprint plan | BLOCKED bukti operasional; rencana tetap desain | Operator menyetujui backup/restore procedure, expected allowlist35, signer dan source/target separation |
| G-07 permission integration | BLOCKED untuk backend, proposal ada | Bukti resolver/config mapping bisa fail closed tanpa role bypass; tidak butuh tabel approval atau perubahan runtime sekarang |
| G-08 MySQL constraint proof | BLOCKED, acceptance fase schema | Setelah G-01–06 dan izin fase baru, DDL candidate + negatif FK/CHECK/generated/NULL/row-lock diuji pada clone, termasuk return_id CHECK+composite FK |
| G-09 regression/rollback acceptance | BLOCKED bukti tes; rencana disetujui sebagai desain | Schema phase lulus, no source mutation, Live/Monthly schema/hash terjaga, rollback/restore drill. Backend/UI izin terpisah |

READY FOR SCHEMA IMPLEMENTATION mensyaratkan approval+scope+target/schema-baseline/preflight plan G-01–06 yang relevan; tidak dapat dinyatakan hari ini. G-08–09 adalah acceptance untuk menyatakan schema implementation berhasil setelah diizinkan, bukan alasan mengeksekusi DDL sekarang untuk 'membuka gate'. Permission backend G-07 juga tidak diimplementasikan oleh izin schema semata.

## 6. Cutover tetap terpisah

Collision equipment_loans/equipment_return_photos di source tidak diatasi rename/drop/reset. Full migration versus read-only archive, kewajiban loan aktif, target users/core consistency, freeze writer, mapping timestamp→DATE, media hash dan approval recovery/cutover mengikuti rencana v3.1 dan harus disetujui tersendiri. Tidak membuka dua writer untuk aset/kewajiban yang sama. Tidak ada izin reset data.

## 7. Evidence limits

APPROVED adalah keputusan pengguna, bukan hasil tes. Pemeriksaan sekarang statis dokumen/config saja, tanpa koneksiDB. Tidak mengklaim 35tables telah dibuat, users target sudah cocok, grants sudah aman, parser tersedia, MySQLDDL diterima, backup terkini sudah direstore atau regresi telah diulang. Password/APP_KEY, SQL dump, media dan ledger tidak dimasukkan dokumen. Laporan validasi statis dan hash historis ada pada context v3.2.
