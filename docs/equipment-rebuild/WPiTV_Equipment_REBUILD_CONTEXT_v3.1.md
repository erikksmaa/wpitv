# WPiTV Equipment Rebuild Context v3.1

Phase 6C.4L.1B — DESIGN ONLY — 23 November 2026.

## Status dan otoritas

**agreed**: scope sekarang hanya dokumentasi rekonsiliasi PRD/DBML/context. Tidak membuat migration, menjalankan SQL, mengubah DB/source aplikasi, implementasi backend/UI, menghapus histori, commit/push/deploy. Instruksi pengguna aktif lebih tinggi daripada teks handoff. Persetujuan kontrak bukan izin eksekusi.

**agreed**: sumber v3 asli dipertahankan byte-identical sebagai WPiTV_Equipment_PRD_v3.md, WPiTV_Equipment_ERD_v3.dbml, WPiTV_Equipment_REBUILD_CONTEXT.md. v3.1 memakai WPiTV_Equipment_PRD_v3.1.md (khususnya bab 12), WPiTV_Equipment_ERD_v3.1.dbml, dan dokumen ini; matriks/changelog di WPiTV_Equipment_DESIGN_RECONCILIATION_v3.1.md. Jangan mengambil schema lama sebagai pengganti.

Label setiap aturan: **agreed** = diminta eksplisit pengguna; **recommended** = proposal teknis untuk design approval; **needs approval** = belum ada persetujuan keputusan/aksi. Aturan v3 warisan yang tidak dikunci oleh pengguna tetap recommended sampai approval desain. Semua rincian berlaku sebagaimana PRD 12; tidak ada approval implisit dari dokumen.

## Git dan aplikasi existing

Baseline pemeriksaan sesi: branch rebuild/wpitv-clean, HEAD e3b0289531b3dd7dc632f803c85c314eada1880e. main stabil; archive hanya referensi/cadangan, bukan runtime/compatibility. Kandidat worktree lama tidak disentuh. Hasil tes terdahulu 311/2.530 dan audit 0 merupakan bukti historis, tidak dijalankan ulang pada fase ini. Recheck status sebelum pekerjaan berikutnya.

**agreed**: Live Streaming, Monthly Reports (bukan borrowing reports), auth, users/roles/RBAC, Mazer/theme, News/TVClub/modul lain dipertahankan. Dependency yang direview: AdminNavigation, AdminDashboardMetrics, policy existing, local storage privat, CsvCell, UTC casts dan guard clone. Tidak menambahkan domain Equipment melalui service lama.

## Kontrak inti

**agreed**: 11 tabel domain: equipment_categories, equipment_models, equipment_units, equipment_model_photos, equipment_unit_photos, equipment_loans, equipment_loan_units, equipment_returns, equipment_return_photos, equipment_operation_keys, equipment_audit_log. users external stub tidak dibuat. Nol VIEW, tanpa counter stok mutable/stock report table/pivot grouping/return_items duplikat/lokasi penyimpanan. Category→model→unit; outcome on_loan hanya pada loan_units. Nama final berlaku di clone clean tanpa legacy.

**agreed**: operational active/maintenance/lost/retired terpisah dari kondisi good/minor_damage/major_damage dan outcome on_loan/returned/lost/voided. Eligibility model aktif, unit active+good, tanpa on_loan. Stok 7 kelompok eksklusif; lost/retired dengan on_loan adalah integrity failure, bukan ditutupi prioritas angka.

**agreed**: progress active (semua on_loan), partially_resolved (ada returned/lost dan masih on_loan), closed (semua returned/lost), voided (semua voided); N>=1. Semua campuran voided, loan kosong atau outcome tak dikenal invalid. Tidak menyimpan status header. Overdue flag terpisah hanya untuk on_loan dan as_of_date WIB > due_date.

**agreed**: DATE tenggat kalender WIB, seluruh DATETIME(6) UTC; loan.borrowed_at dicatat server. returns.returned_at kanonis returned, resolution_at kanonis lost/voided; tidak backdating return/loss v1. Snapshot due_date_at_resolution/is_late untuk returned/lost dibekukan; extension hanya memengaruhi unit belum resolved. Import histori bukan transaksi backdated v1 dan membutuhkan approval/mapping terpisah.

**agreed**: foto privat minimal satu per returned unit, return_id+loan_unit_id nonnull, composite FK menjaga unit/receipt/loan yang benar. Minimum child count, no empty batch, audit/file staging dan rollback dijamin service dan acceptance test, bukan FK biasa. Tidak ada foto receipt umum. Loss menyelesaikan loan_unit secara historis; found menuju maintenance tanpa mengubah outcome lost. Retire terminal dan ditolak saat on_loan; recovery hanya lost→maintenance, tidak langsung tersedia. Retire/recovery/loss/void wajib permission sesuai, alasan, audit append-only serta validasi/locking dalam transaksi. Void memerlukan permission khusus, seluruhnya unresolved dan alasan+attestasi non-handover; tidak menghapus histori. Empat aksi langsung tanpa approval workflow/queue atau tabel approval tambahan (approved Phase 6C.4L.1C).

## Lima rekonsiliasi

1. **agreed**: mulai di clone; jangan DDL/DML/drop/mass rename/reset wpitv atau wpitv_testing. **needs approval**: A migrasi histori penuh atau B arsip read-only, mapping loan aktif, topologi koneksi target, backup/restore drill dan approval cutover/recovery. Tidak membuka dua writer/kewajiban untuk unit sama. PRD 12.2 merinci A/B dan loan aktif; nama tabel DBML tidak diganti untuk menghindari collision dengan patch legacy.
2. **agreed**: category.version INT UNSIGNED nonnull default 1, expected_version; stale ditolak; increment satu per update sukses. Unit management dilarang mengubah condition/status/retire selama on_loan; return/loss workflow khusus. Version tidak menggantikan row lock/transaksi. **recommended/needs approval**: lock order lintas kategori/model/unit/loan dan race validation di PRD 12.3.
3. **agreed**: token/operation/actor/hash immutable, pending hanya uncommitted, bisnis+audit+completed result committed bersama. Rollback tidak meninggalkan pending. Replay identik tanpa audit/mutasi tambahan; beda actor/operation/hash ditolak. Tuple completed_at/result_type/result_id lengkap bersamaan; deterministic unique-conflict recovery setelah rollback pada fresh transaction. **recommended**: registry/envelope stabil di PRD 12.4 dan CHECK tuple; committed-pending prohibition tetap unit-of-work invariant.
4. **agreed**: default cohort borrowed_at, basis returned/lost eksplisit; UTC range dari tanggal WIB; historis kategori/model/label dari checkout, inventory dari master current. Voided dipisahkan dari sukses operasional. **recommended**: category_id_at_checkout + model_id_at_checkout BIGINT UNSIGNED nonnull, FK RESTRICT independen, dua indeks; unit_snapshot menyimpan ID/label/kode/kondisi. Tidak mengikat snapshot-category pada kategori current model. Grain KPI dan contoh A/B, read-snapshot versus historical-as-of dijelaskan PRD 12.5.
5. **agreed**: PRD/DBML/context/UI/report/AC memakai progress active/partially_resolved/closed/voided; label UI bukan enum baru. CHECK outcome lengkap pada PRD 12.6; AC-19–33 menambah pengujian untuk lima blocker.

## Database dan fase berikutnya

**agreed**: wpitv/wpitv_testing (nama kedua: wpitv_testing), media privat dan ledger v2 tetap utuh. Tidak membuka koneksi SQL pada fase ini. Hitungan clone 24 tabel/9 migration/37 FK adalah laporan historis, bukan verifikasi ulang sekarang.

**needs approval**: clone khusus Equipment yang kosong dari legacy, kredensial provisioning/runner DML-only, schema signature baru dan restore plan sebelum schema work. ReleaseConcurrencyGuard existing mem-pin baseline 24 tabel; jangan melemahkan guard untuk menerima 11 tabel tambahan atau menimpa approval lama. Review gate dahulu; lalu Phase 6C.4L.2 hanya setelah scope dan izin baru. Fase 3 backend, fase 4 UI, fase 5 regression/UAT dan approval cutover terpisah. Jangan melompati fase.

**needs approval**: rekomendasi teknis v3.1, nama/granularitas permission dan pemetaan grant (otorisasi langsung retire/recovery/loss/void sudah approved), kebijakan foto/retensi dan collision/kapasitas code; pilihan histori/cutover/reset terpisah dari approval desain clone. Tidak ada tabel baru di luar 11.

## Batas klaim verifikasi

Static cross-document review dapat memeriksa nama/tipe/nullability/Ref/index dan kontrak. DBML hanya mendeskripsikan generated/CHECK dalam notes; parser DBML tidak menguji MySQL, dan bahkan DDL valid belum membuktikan service invariant. Dokumentasikan parser yang benar-benar tersedia dan semua test yang benar-benar dilakukan. Akhiri setelah dokumentasi; tidak ada commit atau implementasi otomatis.


## Pembaruan otorisasi Phase 6C.4L.1C

**Approved**: otorisasi Equipment berbasis permission langsung; role name saja tidak cukup dan tidak memberi bypass otomatis. Tidak membuat permission/role runtime sekarang. Retire/recovery/loss/void membutuhkan permission aksi yang sesuai, alasan wajib, audit append-only dan validasi/row locking dalam transaksi; tidak ada workflow approval, queue atau tabel approval. Retire terminal/no hard delete dan ditolak saat dipinjam. Recovery hanya lost→maintenance, tidak langsung loanable dan tidak menulis ulang outcome lost. Loss pinjaman melalui workflow resolusi atomik khusus. Void hanya salah input sebelum handover nyata, permission khusus, seluruh unit masih on_loan, tanpa menghapus histori. Guard lifecycle, optimistic locking, idempotency dan otorisasi replay tetap berlaku.

Catatan otoritatif terbaru untuk status persetujuan adalah EQUIPMENT_DESIGN_APPROVAL_v3.1.md. Baseline yang secara eksplisit disetujui di sana berstatus approved; istilah recommended/needs approval warisan 1B tetap berlaku bagi rincian teknis yang tidak tercakup. Nama permission/grant per role-user tetap pending; jangan menghidupkan kembali aturan role-only void. Persetujuan desain ini tidak mengizinkan DDL, migration, reset, cutover atau implementasi; gate fase schema di approval record wajib dipenuhi dan memerlukan instruksi terpisah. Approval gate proyek bukan approval operasional empat aksi Equipment.
