# WPiTV Equipment Rebuild Context v3.2

Phase6C.4L.1D • DOCUMENTATION ONLY • Implementation NOT AUTHORIZED.

## Status yang tidak boleh dinaikkan otomatis

**APPROVED** = keputusan pengguna pada record1C: baseline 11 tabel/nol VIEW/category→model→unit/stock derived/DATE WIB/foto per unit/partial return/extension reason/snapshot/optimistic locking/atomic keys/derived progress/append-only audit/permission direct. Retire terminal/bebas on_loan/no hard delete; found lost→maintenance; loss berpermission/beralasan/transaction; void khusus salah input sebelum handover, permission dan attestasi, no history deletion. Tidak ada approval queue/workflow/table.

**PROPOSED** = seluruh detail freeze v3.2: fields/defaults/keys/checks/indices/actor RESTRICT, registry/canonical hashing/envelope/retry/lock order, model+asset numbering capacity, media caps/staging/sweeper/download, permission catalog/role mapping/grants, KPI grains/read snapshot, calon clone/accounts/storage/guards. Tidak menjadi APPROVED karena diminta 'finalisasi'. **BLOCKED** = missing proof seperti actual instance UUID/type/grants, permission integration, parser dan DDL tests/authorization. Gate proyek bukan operational approver.

## Artefak aktif dan sejarah

Baca PRD v3.2 dan ERD v3.2 bersama EQUIPMENT_TECHNICAL_CONTRACT_v3.2.md, EQUIPMENT_SCHEMA_CONSTRAINT_MATRIX_v3.2.md, EQUIPMENT_PERMISSION_MATRIX_v3.2.md dan EQUIPMENT_SCHEMA_READINESS_v3.2.md. Matriks adalah authority detail physical/default/null/constraints yang tidak seluruhnya native DBML. Catatan1C tetap authority approval. Semua v3/v3.1 dipertahankan byte-identical pada fase1D; jangan menulis ulang sejarah atau menaikkan rekomendasi lama menjadi approved.

Branch rebuild/wpitv-clean, HEAD e3b0289531b3dd7dc632f803c85c314eada1880e pada preflight. Jangan main/archive/candidateworktree. Tidak baca seluruh archive Equipment. Live/Monthly/auth/RBAC/Mazer dan modul lain tetap. Hasil311tests/2530assertions/audit0 sebelumnya historis, bukan tes fase ini.

## Kontrak PROPOSED ringkas

11 tabel domain bernama persis di ERD, users external stub expected INT UNSIGNED; 124 kolom total termasuk stub. Tidak menambah kolom/tabel dari v3.1. Snapshot FKs category/model independen RESTRICT; indexes historis/stock/actor/time explicit. Actor FK berubah SET NULL→RESTRICT (proposal) dan akun disable, bukan harddelete; audit payload dan row tidak ditulis ulang. Generated open_unit_id untuk outcome on_loan STORED UNIQUE; composite return→loan dan photo→loan-unit/receipt tetap. CHECK lokal hanya deterministic/own-row, tanpa AUTO_INCREMENT/NOW/subquery/cascade. FK/CHECK return_id acceptance proof BLOCKED; jangan menganggap DBML adalah testedDDL.

Lock order: token→existing loan header→categories ascending→models ascending→units ascending→loan_units ascending→new receipt/photo/audit/result. Master writer tidak mengambil loan-header belakangan; references berubah memicu rollback/restart. Maksimal3 whole-transaction attempts untuk deadlock/timeout; stale409 tanpa autoupgrade version. Key/result+audit+business commit bersama; pending hanya uncommitted. Same-token contender fresh recovery read setelah rollback, authorized immutable envelope replay. Tidak response cache atau tabel baru.

model_code brand3-model20 uppercase ASCII, collision suffix -M000001..M999999; UNIQUE bukan COUNT; official at commit. asset_code model_code-unit_no minimum3digits, unit_no1..999999, next1..1000000 sentinel. Initial/add1..100, lock range; nomor committed tidak reuse. stock7groups eksklusif, drift invalid bukan ditutup prioritas. Managementcondition/status/retire dilarang on_loan; loss khusus, foundmaintenance, retiredterminal.

Media JPG/PNG/WebP, 5MB decimal=5000000bytes input/output; 64..4096px/sisi, decode/reencode+SHA256 final. Return1..8foto/unit, <=10unit,<=40file,<=100000000bytes/batch. Private staging/object durability+DB metadata atomik hanya sisi DB; lease+reference recheck24jam untuk orphan takterpakai, tidak auto-delete evidence referenced. Controller auth permission+parent scope, no publicURL. Model cover revision monotonic/latestvalid, all revisions retained.

Permission resolver eksplisit, no isSuperAdmin bypass. Role map PROPOSED di matrix, bukan grant: regular SA/AP, retire/void SA-only default; AN/TC no default; semua hak dari permission set. Nama/status actor dan action+resource scope divalidasi, reason audit tiap mutasi. Belum ada bukti resolver/store di baseline, jangan implementasi workaround role-only. Registry event dan operation sama dengan Technical4/7, photo result mengacu photoID, batch stock/loss parentID+audit list.

Report default borrowed_at WIB cohort, returned/lost basis event; historical snapshotfilter vs current inventory. DISTINCT unit/loan/receipt, progress dari semua loan-unit, bukan subset. Void tidak sukses; activeoverdue vs frozenresolvedlate; no backdating. as_of=current DB read snapshot, bukan historical balance; expected fixture A/B dalam PRD/Technical. CSV+print same service, sanitize/escape dan redacted PII default.

## Environment dan keselamatan

Config read-only tanpa bootstrap: .env→wpitv, .env.testing→wpitv_testing pada port3306; tidak dipakai/diubah. Kode guard mem-pin clonebaseline wpitv_rebuild_testing+24tables. Usulan Equipment clone wpitv_equipment_v32_testing pada instance MySQL8.0.30 terpisah (port3317 calon belum diverifikasi) dengan provisioner eq_v32_provisioner, DML runner eq_v32_runner dan cleanup account terpisah. Allowlist35tables dan isolatedstorage/schemahash/UUID approval baru wajib; jangan melemahkan currentguard.

users.id type actual, instance/port/UUID/grants, baseline official hash/restore drill dan permission integration = BLOCKED evidence. Mapping legacy/loanaktif/topologi users/core/backup/restore/cutover/reset tetap persetujuan lain. Source wpitv/wpitv_testing/media/ledger tidak disentuh (nama kedua: wpitv_testing). Tidak SQL/DDL/migration/provisioning/koneksiDB/runtimefeature/commit/push/deploy fase ini.

## Verification dan next gate

Static validation: tabel/kolom/Ref types+direction+arity/UNIQUE/FK-support indices, enum/outcome/lifecycle/key-result/photo limits, operation-permission/event registry, expected report examples dan historical source hashes. Parser @dbml/core/@dbml/cli tidak tersedia, tidak menambah dependency. Tidak mengklaim full DBML grammar/MySQL tests. Field CHECK/indices hanya proposal; N01–18 adalah spesifikasi future tests, bukan tests aplikasi.

READY FOR TECHNICAL APPROVAL dapat berarti paket proposal konsisten untuk ditandatangani, tidak membuka schema gate. READY FOR SCHEMA IMPLEMENTATION membutuhkan approvalteknis, scope baru, identityclone/authoritativebaseline/restore/guard plan sesuai G01–06; G08–09 DDLnegative/regression adalah acceptance fase berikutnya setelah diotorisasi. Berhenti setelah dokumentasi.


## Hasil static review Phase1D (bukan runtime tests)

Pemeriksaan lokal lulus: 12 Table blocks (11 Equipment+1 users stub), 124 columns termasuk stub, 18 Ref, arah/arity/type cocok, semua parent target PK/UNIQUE dan semua child FK mempunyai leading index yang sesuai. Generated open_unit_id memiliki UNIQUE dan expression note; header loans tanpa status/progress. Semua124field tercakup matrix; 18FK names unik <=64chars; 18operation registry cocok dengan permission-action matrix. Delimiter braces/brackets seimbang setelah comments/strings dikeluarkan. Ini validator struktural terbatas, bukan grammar parser atau MySQLDDL acceptance.

Perhitungan fixture laporan A/B direkonsiliasi: 5 loan-units = 2returned+1lost+2voided; successful units3; 2record loans = 1successful+1void; late2 dan damaged1. Scope returned28Nov/lost28Nov masing-masing1unit; progressloan dihitung dari semuaunits. Tidak ada SQL/query dieksekusi untuk angka ini.

@dbml/core/@dbml/cli tidak tersedia di node_modules environment; tidak menginstal dependency. Dokumen v3/v3.1 dan approval1C tidak ditulis ulang. Catatan parser/CHECK-FK/grant/userstype/cloneproof tetap BLOCKED untuk implementasi sampai fase baru diotorisasi. READY FOR TECHNICAL APPROVAL adalah kesiapan paket proposal untuk ditinjau, bukan READY FOR SCHEMA IMPLEMENTATION.


### Fingerprint sumber historis (read-only pada fase ini)

| File | SHA-256 |
| --- | --- |
| EQUIPMENT_DESIGN_APPROVAL_v3.1.md | 5cfe79c11029f2eb01a3a91978b8cf96b79f9dd758ba273cc4ce1a7c9bd65fca |
| WPiTV_Equipment_DESIGN_RECONCILIATION_v3.1.md | 67a2dddb1f1aae3ea09cfba40204d580249f1ee7d782072fdc6986cf48ddb477 |
| WPiTV_Equipment_ERD_v3.1.dbml | c75d67d09cb2df882a528a69941dcec58e27312fd73057b6ba449241a81f038f |
| WPiTV_Equipment_ERD_v3.dbml | d9ce0c431da3ed7a4d38c1dfb3b2d52dfffd6e736fdb13b88000395448a7ef30 |
| WPiTV_Equipment_PRD_v3.1.md | 904671b097529b975a2b3a64a75333945a3cd86d1eb3feb934d550986a091d82 |
| WPiTV_Equipment_PRD_v3.md | fb081559921cfb9562187be73c338faeaebd08cdbf41c3fe8d6547561bb34f0b |
| WPiTV_Equipment_REBUILD_CONTEXT.md | 6c319dd5f236199c565015727df4f65562b1dd632bda18c1c14b48c160675804 |
| WPiTV_Equipment_REBUILD_CONTEXT_v3.1.md | 001772a6de06e429af7de12df20153f111b38464e2c09896c6f527c22c553b80 |
