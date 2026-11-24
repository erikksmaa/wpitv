CONTEXT HANDOFF — WPiTV Equipment Full Rebuild v3
Tujuan dokumen: tempel ke Codex sebagai konteks pekerjaan baru. Dokumen ini bukan instruksi untuk menjalankan migration atau menghapus histori.
A. Posisi Git dan status proyek
- Repository: C:/laragon/www/projects/wpitv.
- Branch kerja: rebuild/wpitv-clean. Pemeriksaan terakhir dari Codex: HEAD e3b0289531b3dd7dc632f803c85c314eada1880e, local/remote sinkron, working tree bersih. Periksa ulang keadaan aktual sebelum bekerja.
- main: stabil 9a35b672, jangan disentuh.
- archive/failed-equipment-20261121: snapshot implementasi lama, HEAD 2b005082 pada pemeriksaan terakhir. Referensi sejarah saja, jangan cherry-pick campuran.
- Worktree kandidat lama storage/app/testing/clean-release masih mempunyai perubahan lokal. Jangan membersihkan atau memakainya sebagai baseline tanpa persetujuan.
- Live Streaming dan Laporan Bulanan telah terintegrasi pada rebuild; baseline pengujian pernah lulus 311 tes PHP / 2.530 assertions, 7 JS, composer audit 0. Ini baseline historis, bukan bukti perubahan baru.
- Phase 6C.4L masih PENDING. User meminta spesifikasi, belum menyetujui implementasi.
B. Database dan perlindungan data
- Database existing wpitv, wpitv_testing: menyimpan Equipment lama, histori pinjaman aktif, retur, media, dan ledger. Jangan jalankan DDL/DML/destructive cleanup di sini.
- Clone wpitv_rebuild_testing pernah divalidasi untuk baseline Core + Live/Monthly (24 tabel, sembilan migration, 37 FK dalam laporan sebelumnya). Jangan berasumsi hitungan masih sama: inspeksi read-only.
- Untuk uji rebuild Equipment, gunakan clone terisolasi yang secara khusus disetujui, akun provisioning DDL terbatas dan runner DML-only. Jangan gunakan DB asli.
- Jangan migrate:fresh, migrate:reset, db:wipe, TRUNCATE, DROP, restore paksa, atau hapus media/ledger pada sumber aktif.
- Skema baru adalah pengganti runtime Equipment, bukan rewrite Live Streaming atau Monthly.
C. Artefak yang HARUS menjadi sumber rancangan
1. WPiTV_Equipment_PRD_v3.md — kebutuhan fitur, alur, aturan status, penerimaan.
2. WPiTV_Equipment_ERD_v3.dbml — 11 tabel target, atribut dan FK.
3. WPiTV_Equipment_REBUILD_CONTEXT.md — batasan Git, keselamatan, fase.
Jika artefak belum ada di workspace, minta pengguna menaruhnya di docs/equipment-rebuild/ atau tempel isi dari lampiran. Jangan mengarang isi file dan jangan mengganti dengan schema lama.
D. Kontrak domain yang tidak boleh menyimpang
- Category → model (barang induk) → unit fisik. Satu model = satu card katalog, satu unit = satu asset code.
- Stok berasal dari equipment_units dan equipment_loan_units, tidak ada mutable stock counter atau DB VIEW.
- Unit operational_status: active, maintenance, lost, retired; kondisi fisik good, minor_damage, major_damage.
- on_loan hanya di equipment_loan_units.outcome, bukan kolom status unit.
- Eligible = model aktif, unit active+good, tidak ada outcome on_loan. Kategori nonaktif membatasi pilihan pendaftaran baru, bukan memblokir histori.
- Kode model contoh SON-A6400, unit SON-A6400-001, immutable, unik, nomor tidak boleh reuse, alokasi concurrency-safe.
- Peminjam v1: mahasiswa/dosen/staf; snapshot borrower_type/name/identity_number/organization/WhatsApp pada loan. External belum aktif.
- Tenggat menggunakan DATE lokal WIB (due_date, original_due_date), borrowed/returned UTC DATETIME(6). Lewat 00:00 WIB hari berikutnya = overdue.
- Checkout POS satu header + satu loan_unit per unit fisik, auto/manual allocation, lock & revalidate at commit, idempotency.
- Partial return harus didukung. Setiap unit returned wajib minimal satu foto privat, ditautkan ke return dan loan_unit yang cocok.
- Outcome unit: on_loan, returned, lost, voided. Progress loan dihitung dari outcomes, bukan status header duplikat.
- Perpanjangan dengan alasan mempertahankan original_due_date, snapshot due_date_at_resolution/is_late pada unit resolved.
- Lost outcome historis immutable; found mengembalikan unit ke maintenance untuk pemeriksaan; tidak menulis ulang outcome.
- Void hanya Super Admin untuk koreksi salah pencatatan, seluruh unit belum resolved, dengan attestasi belum ada handover fisik, disertai audit.
- Retire tanpa hard delete, final, dengan alasan, tidak saat unit on_loan.
- Laporan peminjaman tanpa tabel agregat, berbeda dari Laporan Bulanan WPiTV.
- Semua aksi mutatif audit append-only, policy/RBAC, IDOR, foto privat, file staging dan cleanup gagal.
E. 11 tabel Equipment baru
1. equipment_categories
2. equipment_models
3. equipment_units
4. equipment_model_photos
5. equipment_unit_photos
6. equipment_loans
7. equipment_loan_units
8. equipment_returns
9. equipment_return_photos
10. equipment_operation_keys
11. equipment_audit_log
users pada DBML hanya stub referensi eksternal, tidak membuat tabel baru. Tidak ada table inventory_unit_members, return_items duplikat, generic photo pivot, DB VIEW, atau borrowing report snapshot.
F. Relasi kritis
- FK models.category_id → categories.id, units.model_id → models.id, unit/code indexes.
- UNIQUE generated STORED loan_units.open_unit_id untuk outcome on_loan (double checkout prevention).
- Composite FK (loan_units.return_id, loan_id) → returns(id, loan_id) untuk receipt yang benar.
- Composite FK (return_photos.loan_unit_id, return_id) → loan_units(id, return_id) untuk bukti yang benar.
- CHECK outcome returned/lost/voided wajib lengkap; ketika returned, timestamp kanonis di returns.returned_at, bukan duplikasi waktu pada loan_units.
- Kewajiban foto >=1 per returned loan_unit tidak bisa ditegakkan oleh FK sederhana: Service melakukan pengecekan sebelum commit, test rollback.
- Audit subject polymorphic bersifat referensi logis; jangan hard-delete entitas Equipment; verifikasi integritas lewat service dan tes.
G. Roadmap kerja (jangan dilompati)
Fase	Scope	Gate
6C.4L.1	Review PRD+DBML, audit dependency existing read-only, buat contract dan keputusan review	DESIGN REVIEW ONLY
6C.4L.2	Finalisasi rancangan, siapkan Laravel migration untuk CLONE yang disetujui, constraint tests, rollback/restore plan	SCHEMA READY
6C.4L.3	Bangun backend baru: model, policy, domain services, stock, POS, return, photos, audit, laporan query	BACKEND TESTS PASS
6C.4L.4	Bangun UI Mazer/Livewire: kategori, katalog, detail, stok, POS, retur, laporan	UI REVIEW
6C.4L.5	Full regression, concurrency, security, browser UAT, histori/cutover plan	READY FOR CUTOVER APPROVAL


Instruksi pekerjaan pertama untuk Codex: hanya lakukan Phase 6C.4L.1 sebagai audit read-only + review file di atas. Jangan implementasi DB/backend/UI dulu; jangan mengeksekusi migration; jangan commit/push/deploy. Bila ada konflik PRD vs DBML, catat dengan rekomendasi sebelum coding.
H. Definition of Done akhir (bukan target untuk fase audit)
- 11 tabel Equipment baru teruji di clone otoritatif dengan FK/UNIQUE/CHECK dan tanpa VIEW.
- Tidak ada runtime Equipment lama atau compatibility layer yang ikut terbawa.
- Semua flow kategori→stok→checkout→return→report berhasil, termasuk case concurrency, idempotency, partial return, per-unit proof, void/lost/extension, dates WIB.
- RBAC dan media privat lulus security tests.
- Tidak ada orphan/double-count, laporan CSV & cetak sesuai service tunggal.
- Live Streaming dan Laporan Bulanan lulus regresi; main, database existing, ledger, dan media tetap utuh sampai cutover disetujui.
I. Format laporan kerja Codex
Maksimal 150 kata: hasil pemeriksaan branch/DB, konflik spesifikasi, file yang dibaca/ditulis, tes yang benar-benar dijalankan, masalah tersisa, dan verdict READY FOR DESIGN APPROVAL, PARTIAL, atau BLOCKED. Jangan klaim tes atau migration yang belum dijalankan.