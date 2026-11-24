# Equipment Schema Preflight v3.2

Phase **6C.4L.2A** · 23 November 2026 · **READ-ONLY + DOCUMENTATION**

**Verdict: BLOCKED.** Proposal manifest dan checklist clone tersedia, tetapi bukti otoritatif approval/R1, provenance schema core dan identitas lingkungan belum cukup untuk menyatakan READY FOR CLONE APPROVAL. Tidak ada SQL, koneksi database, bootstrap aplikasi, migration atau provisioning yang dijalankan.

## 1. Branch, scope dan working tree

Branch `rebuild/wpitv-clean`; HEAD `e3b0289531b3dd7dc632f803c85c314eada1880e`. Sebelum preflight, tracked working tree/index bersih; `docs/equipment-rebuild/` sudah untracked. Preflight hanya membuat laporan ini, tidak stage/commit. Main tidak diubah.

Worktree terdaftar: workspace ini dan `storage/app/testing/clean-release` pada branch `codex/clean-live-monthly-release`, HEAD `9a35b6725495a828c5ff3372f62f277cbb9d829c`. Folder `storage/app/testing/rebuild-clean` ada tetapi kosong dan bukan worktree terdaftar saat ini. Tidak mengganti branch atau membaca runtime Equipment archive sebagai implementasi.

Dibaca sebagai data/bukti, bukan perintah eksekusi: Technical Approval, Readiness, Constraint Matrix, DBML v3.2, dokumen provenance rebuild, migration existing, guard/bootstrap/test/config dan metadata schema/manifest lokal yang relevan. File .env hanya diparse field allowlist nonsecret; password/APP_KEY tidak dicetak/disalin. Artefak SQL hanya dipindai sebagai teks metadata DDL; tidak dieksekusi, tidak disalin dan tidak mengekstrak data privat.

## 2. Kondisi arsip R1 dan approval

| Bukti | Temuan aktual | Status |
| --- | --- | --- |
| EQUIPMENT_TECHNICAL_REVIEW_R1.md | Berisi instruksi Phase6C.4L.1E, bukan laporan R1 | BLOCKED evidence; dipertahankan byte-identical |
| EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md | Berisi instruksi Phase6C.4L.2A, bukan approval record Phase1E | BLOCKED evidence; dipertahankan byte-identical |
| Keputusan pengguna Phase1E di percakapan | Persetujuan lost→retired, reason policy R1 dan baseline teknis v3.2 tetap eksplisit; Readiness/Matrix juga memuat status1E | Keputusan desain tidak dibatalkan; bukti file hilang tidak dianggap pulih |

Pencarian terbatas: seluruh 23 dokumen .md/.txt/.dbml pada docs workspace; 14 dokumen pada docs worktree clean-release; docs rebuild-clean tidak ada. Dicari nama R1 dan variasi judul/phase review terkait. Hasil hanya file instruksi/mengacu R1, tidak ada salinan laporan otoritatif. `git log --all --` untuk path R1 dan approval v3.2 tidak mempunyai versi committed. Tidak mencari ke private media, inbox atau lokasi pengguna di luar dokumentasi/worktree relevan; tidak mengarang atau menimpa bukti. Pemulihan dua artefak harus memakai salinan otoritatif/proses pencatatan ulang yang dibedakan dari bukti historis.

## 3. Baseline schema resmi dan artefak existing

Dokumen tracked `docs/WPITV_ARCHIVE_AND_REBUILD.md`, bagian Official schema and database safety, menetapkan sumber resmi: private backup `equipment-main-6c4j4-20261121-103924-a4cec7/wpitv.sql`, SHA-256 `688f19b0061dd993839f6d87c3cf84e88e36787df93d009cede2dc69094cfe2b`. Prosedur terdokumentasi mengambil hanya 17 core CREATE TABLE, lalu sembilan migration Live/Monthly, menghasilkan 24 tabel. Pernyataan tes/DB pada dokumen tersebut adalah bukti historis, bukan pemeriksaan DB ulang Phase2A.

Repository sekarang memiliki tepat sembilan migration: tujuh create Live/Monthly dan dua alter manual Live. Tidak ada migration create users/core atau Equipment. Karena itu migration repository saja tidak dapat membuat baseline lengkap.

Artefak lokal ditemukan: `storage/app/testing/wpitv-schema.sql`, 18.476 byte, SHA-256 `93c2a7134a1ce1b900921793000b37694f0bfc0dcbccd9df79678c250ce2af6c`. Pemindaian teks menemukan tepat 17 core CREATE TABLE dan tidak ada statement INSERT/REPLACE. Nama 17 tabel cocok dengan core subset guard24. Artefak tidak tracked; bukan full backup dengan hash688f dan belum ada rantai ekstraksi/attestation yang membuktikan derivasi dari backup resmi. Perbedaan hash artefak schema-only vs full dump bukan bukti korupsi, tetapi juga tidak membuktikan provenance.

Pencarian nama artefak di storage/app dan storage worktree terkait (dibatasi metadata, mengecualikan dependencies/runtime/media) tidak menemukan full backup resmi tersebut. Ditemukan juga versi schema pada `archive-pre-peliputan-email-retirement`, yang bukan baseline pilihan otomatis. Tidak memilih versi berdasarkan nama saja. **BLOCKED:** ketersediaan/verifikasi full backup resmi atau pengesahan schema-only artifact dengan provenance dan manifest/hash. Tidak membaca ulang seluruh dump privat atau menghubungi database untuk melengkapi bukti.

## 4. users.id dan kecocokan FK statis

Artefak schema-only menyatakan users.id `INT UNSIGNED NOT NULL AUTO_INCREMENT`, PRIMARY KEY(id). Ini bukti isi artefak lokal, bukan schema aktual source/target atau bukti artefak resmi. Migration existing Live/Monthly memakai unsignedInteger untuk reference users, sejalan secara statis; model Eloquent tidak digunakan untuk menebak tipe.

Lima reference Equipment ke users: model_photos.uploaded_by, unit_photos.uploaded_by, loans.recorded_by, returns.received_by dan audit_log.actor_id. Matrix menetapkan semuanya INT UNSIGNED nullable; DBML users stub INT UNSIGNED. Dengan users.id artefak, width/signedness dan parent PK kompatibel secara desain. FK actor RESTRICT adalah desain approved; nullable hanya import terverifikasi, runtime actor wajib. Tiga belas Ref lainnya domain BIGINT UNSIGNED dan composite order mengikuti Matrix/DBML; total18 Ref.

**Pending/BLOCKED:** authoritative parent type/engine/index/core hash, instance topology dan exact FK enforcement pada target harus dibuktikan setelah koneksi/clone diizinkan. Tidak membuat users dari stub, tidak mengganti tipe ke BIGINT berdasarkan default foreignId, tidak menyesuaikan source atau Live/Monthly FK.

## 5. Proposal manifest35 — belum executable/approved fingerprint

Perhitungan statis: 17 core + 7 feature-created =24, tepat sama dengan fixed allowlist ReleaseConcurrencyGuard; +11 Equipment =35 unique table names. users dan migrations dihitung sekali. Dua migration alter tidak menambah tabel. Tidak ada VIEW/tabel approval/borrower master/stock aggregate/report baru.

| No | Expected table | Provenance desain |
| --- | --- | --- |
| 1 | equipment_audit_log | Equipment DBML v3.2 (11) |
| 2 | equipment_categories | Equipment DBML v3.2 (11) |
| 3 | equipment_loan_units | Equipment DBML v3.2 (11) |
| 4 | equipment_loans | Equipment DBML v3.2 (11) |
| 5 | equipment_model_photos | Equipment DBML v3.2 (11) |
| 6 | equipment_models | Equipment DBML v3.2 (11) |
| 7 | equipment_operation_keys | Equipment DBML v3.2 (11) |
| 8 | equipment_return_photos | Equipment DBML v3.2 (11) |
| 9 | equipment_returns | Equipment DBML v3.2 (11) |
| 10 | equipment_unit_photos | Equipment DBML v3.2 (11) |
| 11 | equipment_units | Equipment DBML v3.2 (11) |
| 12 | live_stream_events | Live/Monthly migrations (7) |
| 13 | live_streams | Live/Monthly migrations (7) |
| 14 | migrations | Core artifact (17) |
| 15 | monthly_activity_attachments | Live/Monthly migrations (7) |
| 16 | monthly_activity_events | Live/Monthly migrations (7) |
| 17 | monthly_activity_items | Live/Monthly migrations (7) |
| 18 | monthly_activity_reports | Live/Monthly migrations (7) |
| 19 | monthly_activity_versions | Live/Monthly migrations (7) |
| 20 | ms_banner | Core artifact (17) |
| 21 | ms_news | Core artifact (17) |
| 22 | ms_program | Core artifact (17) |
| 23 | ms_video | Core artifact (17) |
| 24 | roles | Core artifact (17) |
| 25 | tr_perliputan | Core artifact (17) |
| 26 | tr_usercounter | Core artifact (17) |
| 27 | tvclub_generations | Core artifact (17) |
| 28 | tvclub_group_members | Core artifact (17) |
| 29 | tvclub_groups | Core artifact (17) |
| 30 | tvclub_members | Core artifact (17) |
| 31 | tvclub_organization_members | Core artifact (17) |
| 32 | tvclub_organization_positions | Core artifact (17) |
| 33 | users | Core artifact (17) |
| 34 | wpitv_agendas | Core artifact (17) |
| 35 | wpitv_organization_positions | Core artifact (17) |

Manifest ini daftar nama calon, bukan sertifikat 35 tabel telah ada. Proposal engine seluruhnya InnoDB (guard24 menuntutnya; Equipment contract memilihnya); actual engine/core charset/collation masih pending. Field manifest final wajib: artifact/core hash, migration hashes, exact tables/types/engines, setiap column/type/unsigned/null/default/generated, PK/UNIQUE/index order, FK/action/name, CHECK/enforcement, collation, DBML/contract revision, format fingerprint dan signer. Expected schema hash target **belum tersedia**, tidak diisi dari hash clone lama.

## 6. Audit guard/testing/config read-only

| Komponen | Bukti statis saat preflight | Risiko / gate |
| --- | --- | --- |
| .env | local, mysql 127.0.0.1:3306, wpitv, root, disk local | Source existing; bukan Equipment test target |
| .env.testing | testing, mysql127.0.0.1:3306, wpitv_testing, wpitv_test_runner, isolated=true, local | Nama/env isolated tidak membuktikan safety; tidak dipakai untuk tes |
| approved.json workspace | Database wpitv_testing, runner lama, 43 tabel termasuk legacy Equipment/Social | Bukan manifest24/35; tidak direuse atau diedit |
| TestingDatabaseGuard | Hanya testing+uncached config dan database wpitv_rebuild_testing; menolak source | Tidak kompatibel dengan .env.testing atau proposed Equipment clone; jangan melonggarkan guard |
| ReleaseConcurrencyGuard | Fixed24, wpitv_rebuild_testing, wpitv_rebuild_runner, 127.0.0.1:3306, exact grants/hash/session/pair | Manifest43 dan Equipment35 ditolak; juga identity worktree release-blocker tidak cocok |
| TestEnvironment / phpunit bootstrap | tests/bootstrap.php memanggil initialize; membaca .env.testing+approved.json, membuat runtime dan override proses; require bootstrap pada Feature/Concurrency | Jangan jalankan phpunit/artisan sebagai inspeksi read-only; bootstrap sendiri dapat menulis runtime/membuka koneksi pada fase berikutnya |
| ReleaseConcurrencyTestCase | Opt-in true, forbidden transaction/refresh traits, independent PDO pair, schema/grants/session inspection | Tidak dijalankan; guard checks bukan bukti runtime sudah lolos |
| Grant contract lama | ReleaseConcurrencyGuard mengharapkan DB-wide SELECT/INSERT/UPDATE/DELETE dan menolak table grants | Tidak cocok dengan audit append-only per-table Equipment; future Equipment guard harus didesain/diotorisasi terpisah, bukan reuse manifest saja |
| config/database.php | mysql strict=true, prefix kosong, env-driven DB_URL/socket, engine null; tidak menetapkan timezone/isolation pada default mysql | Setting runtime UTC/RR/engine/persistent/init-command harus dibuktikan nanti, tidak diasumsikan default memenuhi kontrak |
| config/filesystems.php | local storage/app/private, public storage/app/public+link storage | Equipment clone wajib resolved private runtime sendiri; jangan memakai media existing/public link |

Worktree clean-release mempunyai private manifest24 untuk `wpitv_release_blocker_testing` dengan hash sendiri; ini metadata lingkungan lama, bukan persetujuan target Equipment atau alasan menyalin credentials. Tidak menjalankan source guard untuk menguji denial dan tidak membuat guard baru.

## 7. Collision legacy Equipment

Manifest43 workspace mencatat legacy equipment_loans dan equipment_return_photos: dua nama bertabrakan langsung dengan 11 tabel baru. Legacy dependents juga mencakup equipment_loan_items, equipment_return_items, equipment_return_photo_items, equipment_return_receipts, equipment_audit_events dan inventory_*; schema bernama sama belum tentu kontrak sama. Keberadaan aktual pada source belum diperiksa DB, maka collision aktual bukan klaim introspeksi baru.

Jangan Schema::hasTable lalu skip, CREATE IF NOT EXISTS, ALTER legacy diam-diam, rename/drop/reset, import full legacy dump, reuse approval43 atau menggunakan writer/archive compatibility. Clone kosong terisolasi dengan core allowlist saja menghindari benturan awal. Full migration/read-only archive, active loan mapping, media/history dan writer/cutover tetap keputusan terpisah. Auth/RBAC, Mazer, Live/Monthly serta modul existing dipertahankan.

## 8. Checklist calon provisioning/backup/restore/fingerprint

Semua checkbox di bawah **belum dijalankan** dan tidak memberi izin eksekusi.

- [ ] Pulihkan bukti laporan R1/approval record dari sumber otoritatif; cocokkan revision/hash dengan keputusan pengguna. Jangan menimpa file instruksi sebagai bukti historis.
- [ ] Sahkan scope fase baru, target exact `wpitv_equipment_v32_testing`, instance terpisah dan kandidat port3317 setelah verifikasi; fail jika target existing/unknown/source. Tetapkan binary/version/UUID/datadir/process owner dan resolved storage allowlist; semua belum diverifikasi.
- [ ] Sediakan artifact baseline resmi+hash/provenance. Putuskan schema-only core tanpa rows; pin sembilan migration hashes dan dependency order. Jangan seed data privat; hanya canonical role labels dan fixtures sintetis.
- [ ] Setujui exact manifest35, constraint names/signature, users type dan baseline Live/Monthly preservation; full DBML parser/translation plan. Stop/review jika parser atau MySQL menolak C11/composite FK.
- [ ] Tetapkan setup/provisioner target-only versus DML runner versus exact-owned-ID cleaner. Runner tidak DDL/global/admin/source; audit SELECT/INSERT saja, key completion terkontrol, no runtime DELETE audit/key/domain. Verifikasi grants/source denial hanya setelah izin.
- [ ] Siapkan private runtime storage/views/cache/session/log/temp/media tersendiri dan test key baru; jangan mengubah .env/.env.testing source atau menyalin secret ke laporan/Git. Tentukan guard Equipment terpisah yang fail closed; implementasinya belum diizinkan.
- [ ] Setujui backup/restore procedure, pemilik, lokasi privat/hash/retensi; existing backup bukan izin dump/restore baru. Schema-only clone awal tidak perlu borrower/user/media privat. Bila data recovery/cutover kelak dibutuhkan, fresh DB+media+ledger backup dan restore drill harus diotorisasi terpisah.
- [ ] Rencana DDL dependency: core17 topological FK, Live/Monthly9 sesuai dependency, Equipment categories→models→units/photos/loans→returns→loan_units→return_photos, operation_keys/audit sesuai refs. Urutan final seluruh FK diverifikasi dari manifest, bukan menebak dari daftar nama sorted. Jangan FOREIGN_KEY_CHECKS=0 atau ALTER source.
- [ ] Setelah diizinkan saja: periksa session InnoDB/strict/FK+UNIQUE=1/UTC/RR, exact identity/grants, fingerprint canonical SHOW CREATE (normalize line endings dan table AUTO_INCREMENT counter saja, bukan column declaration), two independent PDO/no outer transaction dan deny parallel runners. Hash old24/43 tidak boleh dipakai untuk target35.
- [ ] Simpan before/after fingerprints core+Live/Monthly dan exact migration rows/hash. Buat completion journal untuk partial DDL; jangan mengandalkan transaction rollback MySQLDDL. Restore drill hanya target terisolasi dengan izin, tanpa DROP source.

## 9. Negative tests yang direncanakan — tidak dieksekusi

| Kelompok | Kasus / expected rejection |
| --- | --- |
| Identity/guard | Wrong DB/host/port/account/UUID/storage/hash/table count, source fallback, URL/socket/read-write override, cached config, missing opt-in, unsafe grants/parallel/outer transaction ditolak sebelum mutation |
| DDL/FK | Signed/width mismatch, missing PK/index, wrong snapshot IDs, receipt milik loan lain, photo loan-unit/receipt salah; actor RESTRICT dan incoming FK; tidak drop constraint untuk meloloskan DDL |
| CHECK/generated | Outcome branches NULL/UNKNOWN, result triple parsial, enum/range/version/counter/photo bytes/hash invalid; generated open_unit_id UNIQUE menolak dua allocation on_loan; C11 return_id+composite FK acceptance pada actual MySQL8.0.30 |
| Atomicity/concurrency | Pending token tidak boleh committed, stale version/replay tidak increment, same-token/different payload conflict, rollback fault audit/result/photo, last-unit double checkout, return-vs-loss, whole-batch deadlock/timeout rollback |
| Lifecycle/reason | Retire on_loan/lost+on_loan ditolak, retired terminal, found hanya maintenance, history lost immutable; manual reason sensitif/admin wajib, checkout purpose, conditional notes/photo sesuai R1, no role bypass |
| Media | Zero/nine photos per returned unit, >40 photos/>10unit, >5.000.000 per input/final, >100.000.000 input atau final, MIME/checksum/path/scoping/race sweeper; satu invalid menggagalkan batch |
| Regression/restore | Core/Live/Monthly schema fingerprints unchanged, KPI join foto tidak menggandakan grain, UTC/WIB boundary, partial return/progress/void; restore/rollback dan no source/private media mutation |

DDL acceptance terpisah dari service/backend tests. Race/media/permission/report tests baru dijalankan dalam scope implementasi yang diotorisasi; tidak mungkin dibuktikan oleh preflight statis.

## 10. Register kesiapan

| Gate | Hasil2A |
| --- | --- |
| Desain teknis pengguna1E | Disetujui di percakapan dan reflected dokumen; file approval record sekarang salah isi, evidence BLOCKED |
| R1 archive evidence | BLOCKED; laporan otoritatif tidak ditemukan pada scope pencarian |
| Baseline | Jalur/provenance resmi teridentifikasi; artifact17 dan sembilan migrations tersedia; attestation core artifact/full backup resmi BLOCKED |
| users/FK | Static local-artifact type cocok; actual source/target type/version/engine/enforcement PENDING/BLOCKED koneksi terotorisasi |
| Manifest35 | Proposal nama lengkap terverifikasi set aritmetik; actual schema hash, signer dan physical acceptance BLOCKED |
| Guard/clone | Mismatch env43 vs guard24 dan grants/runtime contract teridentifikasi; target35, identity, storage, safety evidence dan izin provisioning BLOCKED |
| MySQL/tests/restore | BLOCKED; tidak dijalankan, tidak diklaim lulus |

Preflight dokumentasi selesai. **Provisioning clone belum siap diajukan sebagai READY FOR CLONE APPROVAL** sampai blocker bukti/provenance/target dan rencana guard terkait diselesaikan. Izin membaca metadata DB pun tidak diberikan sekarang; langkah dependen tetap menunggu otorisasi tersendiri. Tidak ada migration/DDL/provisioning/SQL/grant/source/config/guard/reset/cutover/commit/push/deploy atau perubahan wpitv/wpitv_testing.

## 11. Evidence fingerprints

Hash file dokumen aktual (bukan hash laporan/approval yang semestinya):

| Dokumen | SHA-256 |
| --- | --- |
| EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md | f6809d804ccff79bad95f0ace9c16bdd24e200b30c099881cb5540fbc4c8762b |
| EQUIPMENT_TECHNICAL_REVIEW_R1.md | 4c3768bc1f1e4fe759cb78406a5f9afc758918a4de91b44bfab19dd6fbda19a3 |
| EQUIPMENT_SCHEMA_READINESS_v3.2.md | 45ebbddb0eceb40abb7f3ac4271f9182971c67dc738c856aa97b0cd6bf5d3137 |
| EQUIPMENT_SCHEMA_CONSTRAINT_MATRIX_v3.2.md | a7f49a385b2e6f6df226c5dad7fa6191aef3f6f4567fcfe9235817f88f80cf1a |
| WPiTV_Equipment_ERD_v3.2.dbml | e8d88bd2e2ced2c1ed004eb428d5d3696a55a9ce2cc68b75ef08cded0af7ed3a |

Pin sembilan migration existing, hanya untuk manifest proposal:

| Migration | SHA-256 |
| --- | --- |
| 2026_11_09_000001_create_live_streams_table.php | 471efd2da26d0e7ecbdf64680570c3de570c084b59b0a840a86bdb8ab0306db2 |
| 2026_11_09_000002_create_live_stream_events_table.php | c321ab00f7f4722c1be852e1664e2c3c7eb620474f97c71a842535e9a3a40f81 |
| 2026_11_11_000001_create_monthly_activity_reports_table.php | 8ae675fe5defd1192d4f3a4d54b46394c949d9a5e5c63cba5bac15cd3fb28396 |
| 2026_11_11_000002_create_monthly_activity_items_table.php | 6f110ce4d0718c47ef966be111bc9fa86cd243d790fa53ee42b048ab7c234471 |
| 2026_11_11_000003_create_monthly_activity_attachments_table.php | 0d6e39e6eb191510dee64dd24846a02bdd453d58ef5e36073b5970051322bba1 |
| 2026_11_11_000004_create_monthly_activity_versions_table.php | d60cd9fcc09dd0eeb9ef261a48ff9b66fc0d6a89f9931744e650de70013136d6 |
| 2026_11_11_000005_create_monthly_activity_events_table.php | 8b8bfa08963d505e3e754beffeaec36251ffda87979afd352c0cf87ad10011db |
| 2026_11_18_000001_support_manual_live_streams.php | 1a3f365eacbab45f4554d149a183bbc499db5db18a2c996ddc3d206787d625b2 |
| 2026_11_18_000002_allow_manual_live_audit_event.php | cd8fac0a7d9f2dadf7cca1229bf5fd302bc0bd7d71d46692b92aae74eed46927 |

Seluruh input yang dibaca untuk pin/safety diperiksa byte-identical sebelum/sesudah penulisan laporan. Hash adalah bukti integritas file lokal saat2A, bukan jaminan provenance/approval atau hasil server.
