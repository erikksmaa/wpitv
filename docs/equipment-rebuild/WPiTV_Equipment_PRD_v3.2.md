# WPiTV Equipment PRD v3.2

## Status berlaku — Phase 6C.4L.1E, 23 November 2026

**APPROVED sebagai baseline desain** berdasarkan EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md: paket teknis v3.2 setelah koreksi R1, lost→retired dan reason policy Technical7.1. Label/kalimat proposal, rekomendasi atau menunggu approval dari Phase1D/R1 yang masih dikutip untuk detail desain dalam scope tersebut kini dibaca APPROVED; catatan historis bukan keputusan pending baru. Ini tidak mengesahkan bukti lingkungan, hasil tes, provisioning, grant runtime atau cutover. Bukti yang belum tersedia tetap **BLOCKED**; Phase6C.4L.2 belum diotorisasi. Strategi histori, loan legacy aktif, identitas target, manifest operasional, backup/restore dan cutover tetap memerlukan keputusan/bukti tersendiri. Record1E menjadi otoritas status terbaru, record1C tetap historis.

Phase6C.4L.1D • 23 November 2026 • DOCUMENTATION ONLY • Implementation NOT AUTHORIZED.

## 1. Sumber, status dan precedence

Sumber historis yang tidak diubah: PRD/ERD/context v3 dan v3.1, reconciliation1B dan EQUIPMENT_DESIGN_APPROVAL_v3.1.md. Catatan1C dan approval1E adalah bukti keputusan **APPROVED** pengguna; Phase1E mengesahkan paket teknis v3.2 setelah R1 sebagai baseline desain. **PROPOSED** = calon keputusan teknis final; **BLOCKED** = data, bukti atau otorisasi yang belum ada.

v3.2 menggunakan EQUIPMENT_TECHNICAL_CONTRACT_v3.2.md, EQUIPMENT_SCHEMA_CONSTRAINT_MATRIX_v3.2.md, EQUIPMENT_PERMISSION_MATRIX_v3.2.md, EQUIPMENT_SCHEMA_READINESS_v3.2.md dan WPiTV_Equipment_ERD_v3.2.dbml sebagai paket normatif. v3.1 tetap referensi kebutuhan/AC historis; jika detail teknis berbeda, delta teknis v3.2/R1 disetujui sebagai desain pada Phase1E; label PROPOSED sebelumnya bersifat historis. Runtime belum diotorisasi.

## 2. Tujuan dan baseline APPROVED

Petugas mengelola kategori, model/barang induk, unit fisik, stok derived, POS tanpa harga, partial/full return dengan bukti foto setiap unit, serta laporan peminjaman. Satu model=satu card katalog; satu unit=satu asset unik. Tetap 11 tabel Equipment, nol VIEW/counter stok mutable/tabel laporan agregat. Tidak ada approval workflow/queue/tabel approval. users existing hanya stub relasi.

Tenggat DATE sampai akhir WIB; timestamp UTC; extension beralasan, late snapshot immutable. Model/category/unit berbeda dari outcome transaksi. Progress active/partially_resolved/closed/voided derived, overdue terpisah. Snapshot historis, optimistic locking, idempotency atomik dan audit append-only wajib. Laporan Equipment tidak menggantikan Monthly WPiTV. Retire terminal/no hard-delete/ditolak saat dipinjam; recovery lost→maintenance tidak langsung tersedia; loss/void memakai permission, alasan dan transaksi/audit. Void hanya salah input sebelum handover nyata, permission khusus dan attestasi, tanpa menghapus histori.

Live/Monthly, auth/RBAC, Mazer/theme, Agenda/Program/Video, News/TVClub dan modul existing dipertahankan. Equipment archive tidak menjadi runtime/compatibility. Tidak termasuk pembayaran, denda, portal peminjam, external borrower, reservasi, redesign modul lain, atau reset/migrasi legacy tanpa approval terpisah.

## 3. Alur dan kontrak teknis APPROVED DESIGN

| Alur/layar | Kontrak v3.2 yang diajukan | Rujukan |
| --- | --- | --- |
| Kategori | Nama normalized unik; version INT UNSIGNED NOT NULL DEFAULT1 (bukan AUTO_INCREMENT), expected_version; toggle tidak menulis histori lama | Technical3; Matrix C01–03 |
| Create model | Nama/merek/model/kategori aktif, server code, initial1..100 unit atomik, condition dan cover opsional di bawah form | Technical5–6 |
| Katalog/detail | Satu card/model; foto valid latest revision; ringkasan7 stock groups; unit/history/notes current | Technical5,8 |
| Unit/stok | Add1..100 rows, lock monotonic counter; edit condition/status dilarang on_loan, maintenance/found/retire sesuai truth table | Technical3,5; Matrix5 |
| POS | student/lecturer/staff snapshot, organization/identity/WhatsApp/purpose, server borrowed_at, due DATE WIB; auto/manual allocation<=50unit/20model, review lalu atomic revalidation | Technical3–5; MatrixC18 |
| Extension | Header lock dan expected_version; due maju, original tetap; resolved snapshot tidak diubah | Technical3,8 |
| Return | <=10unit/receipt, condition_in dan 1..8 foto/unit; timestamp server, no backdate; partial supported; rollback seluruh batch bila invalid | Technical4,6; MatrixC11 |
| Loss | unit bebas terpisah dari loan.loss; loan-unit outcome lost dan unit status lost atomik, snapshot late | Technical3–5; Permission matrix |
| Recovery/retire/void | Permission direct + reason, no approver; lost→retired mengikuti APPROVED syarat R1 (bukan found); resource/lifecycle/version/attestasi di server | Permission matrix; Approval1C |
| Reports/CSV/print | Borrowed default, returned/lost event basis, historical IDs/labels; DISTINCT grains; default PII redacted; single service | Technical8 |

Responsive Mazer dua kolom desktop/satu mobile, Light/Dark existing, label/error/loading/empty/keyboard states. Tidak ada harga/cart persisten. Fitur baru tidak dibangun pada fase ini.

## 4. Schema APPROVED DESIGN

Tabel: equipment_categories, equipment_models, equipment_units, equipment_model_photos, equipment_unit_photos, equipment_loans, equipment_loan_units, equipment_returns, equipment_return_photos, equipment_operation_keys, equipment_audit_log. categories/models/units/loans.version INT UNSIGNED NOT NULL DEFAULT 1, bukan AUTO_INCREMENT, increment hanya update sukses (create=1; replay/no-op/rollback tidak increment); next_unit_no INT UNSIGNED NOT NULL DEFAULT 1, counter transaksional bukan AUTO_INCREMENT. Hanya PK id domain memakai AUTO_INCREMENT. loans.identity_number VARCHAR40 NOT NULL tidak UNIQUE; pencarian (borrower_type,identity_number) nonunique, peminjam sama boleh banyak loan tanpa borrower master. Daftar lengkap124 kolom termasuk users stub, default/nullability/PK/FK/index/UNIQUE/CHECK terdapat di Matrix; DBML memperlihatkan hubungan logis, bukan DDL executable.

Delta fisik terhadap v3.1: tidak menambah tabel/kolom; menambah indeks child-FK/actor/time/report/stock, menamai key/constraint, memilih FK actor RESTRICT alih-alih SET NULL untuk menghindari mutasi audit saat delete user, membekukan batas nomor dan media, serta operation result type model_photo/unit_photo. category.version dan snapshot category/model checkout yang sudah ada tetap. Immutability/eligibility/minimum foto/committed-pending tidak dijanjikan oleh CHECK saja.

PK/FK types unsigned harus cocok dengan schema core otoritatif. Generated open_unit_id STORED+UNIQUE dan composite receipt/photo FK tetap. Default noncascading SQL FK tanpa explicit actions dipilih untuk menghindari interaction CHECK/referential actions; compatibility proof MySQL8.0.30 tetap BLOCKED sampai clone work diotorisasi. Tidak mengeksekusi DBML exporter output.

## 5. Otorisasi, media dan audit

**APPROVED** direct permission authorization, alasan eksplisit empat aksi sensitif dan extension, audit+locking transaksi, no role-name bypass, no operational approval. **PROPOSED** catalog/map role eksplisit di Permission Matrix: SA/AP default biasa; retire/void direkomendasikan SA grant saja; AN/TC default N, semua role tetap wajib permission. Tidak memberi runtime grant. Existing code yang dibaca belum menyediakan bukti permission integration; resolver/map/grants harus disetujui dan diuji; tidak membuat tabel approval/permission domain.

**PROPOSED** JPEG/PNG/WebP, input/output<=5,000,000 bytes, 64..4096px/sisi, full decode/re-encode/hash output, private staging dan cleanup lease24jam; evidence committed historis tak dihapus otomatis. Batas serentak: <=8foto/returned unit, <=10unit/batch, <=40foto/batch; tiap input/final file<=5000000 byte; total input<=100000000 DAN total final<=100000000 byte terpisah. Sisa kuota batch tidak menaikkan batas unit/file. Download scope ada di Technical6. No public URL. Audit registry explicit, subject logical+optional FKloan, actor snapshot server; akun dinonaktifkan bukan dihapus jika direferensikan. DML/grant split runtime versus fixture cleanup ada di Technical7.

## 6. Reporting dan expected output

**PROPOSED** satu read snapshot current+as_of server; basis borrowed cohort vs returned/lost events, WIB→half-open UTC. Progress loan dari semua outcome unit, bukan subset report filter; unit KPI hanya subset berlabel. Snapshot ID/label saat checkout tidak mengikuti master rename/reclassification. Void records dilaporkan terpisah dari successful checkout; current inventory selalu sumber current, bukan historis-as-of yang dibuat-buat.

Expected fixture A3unit successful dan B2unit void pada cohort25Nov: record_loans2, voided_loans1, successful_checkout_loans1/units3, returned2/lost1/on_loan0/voided_units2, late_resolved2, damaged_on_return1; overdue0 setelah closed. Basis returned28Nov memilih1unit late damaged, basis lost28Nov memilih1unit late. Multi-photo join tidak mengubah angka. Rincian tanggal/extension/receipt grain dan CSV/print sanitization di Technical8.

## 7. Acceptance dan verification plan

AC-01–33 v3.1 dipertahankan sebagai baseline spesifikasi dengan penggantian otorisasi1C; N-01–18 Technical9 menambah constraint/lock/token/media/security/report negative cases. Wajib di clone kelak: double allocation duaPDO tepat1commit, overlapping return/loss, same-token races, stale, timeout whole rollback, photo min/scoping/limits, snapshot history, no mixed void, numbering exhaustion/reuse, actor RESTRICT, grants append-only dan file orphan races. Negative DDL terhadap null/check/FK/generated harus dibuktikan di MySQL8.0.30 setelah izin. Live/Monthly dan existing non-Equipment wajib regresi. Tidak ada test runtime atau DB yang dijalankan di Phase1D.

## 8. Clone/cutover dan approval

Schema Readiness merinci proposal database wpitv_equipment_v32_testing pada instance terpisah, allowlist35 (core+Live/Monthly24 + Equipment11), provisioner versus DML-only runner, cleanup account dan storage isolated. Seluruh nama/port/UUID/grants bersifat PROPOSED atau BLOCKED evidence. `.env`/`.env.testing` saat ini menunjuk source existing, bukan target Equipment; tidak dipakai/diubah. Guard24 baseline tidak dilemahkan.

Cutover histori penuh versus arsip read-only, loan aktif, users/core topology, konversi tenggat, backup/restore, writer freeze dan recovery tetap approval terpisah. Tidak rename/drop/reset source untuk menghindari collision nama. Approval dokumen teknis tidak mengizinkan migration/provisioning/DDL/cutover atau backend/UI. Verdict paket: **TECHNICAL BASELINE APPROVED**, sementara **READY FOR SCHEMA IMPLEMENTATION belum terpenuhi**. Berhenti setelah dokumentasi.


## R1 — klarifikasi reason dan retire dari lost

APPROVED Phase1E reason-policy detail Technical7.1 membedakan mandatory manual reason pada aksi sensitif/extension dan administrative updates, dari server-generated audit reason untuk create biasa/checkout dengan purpose mandatory. Audit.reason tetap nonblank. First photo/reference dan retur normal mengikuti detail conditional R1, bukan menghapus audit atau requirement sensitif approved. Semua18 operation dan permission map tetap; tidak ada policy runtime yang dibuat.

APPROVED Phase1E lost→retired hanya dengan permission retire, alasan eksplisit, transaksi/locks dan bukti tidak ada on_loan; jika drift lost+on_loan, tolak/investigasi. Nilai closed_* current berubah ke event retire dengan old/new pada audit; histori outcome lost/resolution/late immutable. No hard delete, terminal, found hanya lost→maintenance. Tidak perlu found palsu sebelum retire administratif aset masih hilang. Detail source-state ini disetujui eksplisit oleh pengguna pada Phase1E.
