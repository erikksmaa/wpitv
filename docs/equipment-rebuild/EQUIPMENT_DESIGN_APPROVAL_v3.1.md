# Equipment v3.1 — Design Approval Record

Phase **6C.4L.1C** • 23 November 2026 • **DOCUMENTATION ONLY**

**Verdict: DESIGN BASELINE APPROVED**, sebatas kontrak yang dinyatakan approved di bawah. Ini catatan keputusan pengguna dalam permintaan Phase 6C.4L.1C, bukan persetujuan menyeluruh atas semua rekomendasi teknis Phase 1B.

Branch diperiksa: `rebuild/wpitv-clean`; HEAD `e3b0289531b3dd7dc632f803c85c314eada1880e`. Dokumen desain dari Phase 1B masih untracked; tidak ada commit atau push pada fase ini.

## 1. Sumber dan kedudukan keputusan

Dibaca: WPiTV_Equipment_PRD_v3.1.md, WPiTV_Equipment_ERD_v3.1.dbml, WPiTV_Equipment_REBUILD_CONTEXT_v3.1.md. Catatan ini menjadi sumber status persetujuan terbaru untuk butir yang dicantumkan; v3 dan ringkasan rekonsiliasi 1B tetap referensi historis.

- **Approved**: keputusan eksplisit pengguna pada Phase 1C dan baseline yang diminta dipertahankan. Tidak menyiratkan izin eksekusi.
- **Recommended**: rincian teknis proposal 1B yang layak ditinjau, tetapi tidak otomatis ikut disetujui oleh approval baseline.
- **Pending**: keputusan/otorisasi konkret yang belum ditetapkan atau masih memerlukan verifikasi dan instruksi tersendiri.

Label `agreed` di PRD/context sebelumnya ekuivalen dengan kesepakatan kontrak sesuai sumbernya. Pernyataan umum lama bahwa seluruh asumsi menunggu approval kini dibaca bersama daftar approved di catatan ini; hanya butir yang benar-benar disebut di sini yang dinaikkan statusnya. Perubahan otorisasi 1C mengesampingkan aturan role-only/Super-Admin-only untuk void pada versi sebelumnya. Tidak mengubah kontrak bisnis non-otorisasi yang masih berlaku.

## 2. Approved — baseline domain

| ID | Keputusan disetujui | Konsekuensi desain |
| --- | --- | --- |
| A-01 | 11 tabel Equipment, nol database VIEW | Tidak menambah tabel approval; users hanya external stub |
| A-02 | Kategori → barang induk/model → unit fisik | Identitas model dan unit terpisah; bukan grouping runtime legacy |
| A-03 | Stok berasal dari unit dan transaksi | Tanpa stok agregat mutable pada model |
| A-04 | Tenggat DATE, akhir hari WIB | Timestamp UTC; definisi overdue/late konsisten dengan kalender WIB |
| A-05 | Minimal satu foto retur per unit dan partial return | Receipt dan loan-unit terhubung tepat; unit lain tetap on_loan |
| A-06 | Perpanjangan wajib alasan | Snapshot resolusi lama tidak ditulis ulang |
| A-07 | Snapshot historis | Histori identitas/kategori/model/hasil transaksi tidak mengikuti perubahan label master |
| A-08 | Optimistic locking dan idempotency atomik | Stale write ditolak; bisnis/audit/key satu transaksi, tanpa committed pending |
| A-09 | Progress derived | active / partially_resolved / closed / voided; overdue flag terpisah; tidak ada status header tersimpan |
| A-10 | Audit append-only | Actor, target, alasan, waktu dan perubahan dapat ditelusuri; tidak menghapus histori |
| A-11 | Laporan peminjaman terpisah dari Monthly WPiTV | Tidak mengganti workflow/schema Laporan Bulanan atau Live Streaming |
| A-12 | Otorisasi Equipment langsung berbasis permission | Policy server-side mengecek permission aksi; role name bukan bukti hak atau bypass |

Sebelas tabel tetap: equipment_categories, equipment_models, equipment_units, equipment_model_photos, equipment_unit_photos, equipment_loans, equipment_loan_units, equipment_returns, equipment_return_photos, equipment_operation_keys, equipment_audit_log. Tidak ada tabel/domain ke-12, tabel approval, atau permission runtime yang dibuat oleh pekerjaan ini.

## 3. Approved — otorisasi langsung empat aksi sensitif

**Kontrak bersama**: pengguna wajib mempunyai permission yang sesuai; alasan nonblank wajib; otorisasi resource dan lifecycle/version divalidasi; validasi dan locking dilakukan di dalam transaksi bersama mutasi dan audit append-only. Permission valid tidak melewati aturan bisnis, larangan saat dipinjam, atau syarat snapshot/idempotency. Status akun/policy/IDOR tetap diperiksa. Replay tidak menambah audit/mutasi dan tetap memerlukan otorisasi akses hasil.

**Tidak ada approval workflow, approval queue, approver, atau tabel approval tambahan**. Aksi langsung dijalankan oleh pelaku yang berpermission setelah validasi. Istilah approval desain/schema/cutover pada catatan ini adalah gate proyek, bukan alur approval operasional Equipment.

| Aksi | Permission yang harus dimiliki (nama runtime belum ditetapkan) | Prasyarat dan hasil approved |
| --- | --- | --- |
| Retire | Permission retire | Tolak jika unit sedang dipinjam/on_loan. Terminal, tanpa hard delete; alasan, audit dan transaksi wajib |
| Recovery / found | Permission recovery | Hanya unit operational_status=lost. Pindah ke maintenance, tidak langsung available atau active; hasil pemeriksaan terpisah sebelum loanable. Histori outcome lost tidak diubah |
| Loss | Permission loss sesuai scope resource | Alasan wajib, audit, validasi dan lock dalam transaksi. Kehilangan unit on_loan hanya melalui workflow resolusi khusus; outcome loan-unit dan operational_status unit diselesaikan atomik, bukan lewat edit stok bebas |
| Void | Permission khusus void | Hanya transaksi salah input yang barangnya belum benar-benar diserahkan. Attestasi non-handover dan alasan wajib; seluruh unit masih on_loan dan belum ada retur/loss. Void all-or-nothing, tidak menghapus header/detail/audit atau histori lainnya |

**Batas role**: Super Admin maupun Admin Production tidak otomatis mempunyai empat hak ini hanya karena namanya. Catatan ini tidak memberikan grant kepada Admin News/TVClub atau role/user mana pun. Pemetaan permission ke role/user existing ditetapkan terpisah. Tidak membuat role baru, seeder grant, guard middleware, policy runtime atau permission store pada fase dokumentasi.

`Recovery` di tabel ini berarti pemulihan unit hilang, bukan restore/rollback database. Recovery database tetap memerlukan prosedur dan persetujuan tersendiri.

## 4. Recommended — belum otomatis disahkan

Rekomendasi teknis Phase 1B berikut tetap berstatus recommended; harus dipilih/dirinci sebelum pekerjaan schema yang bergantung padanya:

1. FK RESTRICT snapshot kategori/model independen, indeks filter historis serta aturan immutability yang diuraikan PRD 12.5. Konsep snapshot approved; bentuk fisik FK/indeks belum otomatis approved.
2. Registry operation/result allowlist, canonical payload dan envelope replay stabil, serta CHECK tuple hasil idempotency. Kontrak atomiknya approved; detail representasi tetap perlu review.
3. Kandidat lock order lintas header/kategori/model/unit/loan-unit, retry policy dan daftar race untuk dibuktikan pada clone.
4. Detail grain KPI, label cohort/event, DISTINCT counting dan current-as_of read snapshot; bukan klaim rekonstruksi historical-as-of.
5. Rincian closed_* pada found, batas angka/revision dan seluruh constraint lokal dari notes DBML untuk diterjemahkan menjadi DDL nanti.
6. Pemisahan FK actor nullable dengan payload audit append-only; SET NULL saat akun dihapus versus tuntutan strict byte-immutable audit harus diputuskan, bukan dianggap otomatis selesai.

Tidak mengubah keputusan permission-based menjadi role-only dan tidak mengusulkan approval workflow sebagai pengganti detail permission yang belum ditentukan.

## 5. Pending — keputusan dan otorisasi terpisah

| ID | Hal yang belum ditetapkan | Harus selesai sebelum |
| --- | --- | --- |
| P-01 | Nama permission, granularitas loss bebas/on-loan dan pemetaan grant ke role/user existing | Policy/authorization implementation; bukan grant runtime sekarang |
| P-02 | Mekanisme integrasi permission dengan auth/RBAC baseline yang tersedia, tanpa role-only fallback atau bypass | Implementasi otorisasi; jangan mengasumsikan store permission sudah tersedia |
| P-03 | Pengesahan rekomendasi FK/index/CHECK/operation-result/lock-order dan kebijakan audit actor | Penyusunan/penerapan schema yang bergantung keputusan tersebut |
| P-04 | Kebijakan MIME/size/dimensi/retensi foto, checksum dan lifecycle staging/orphan cleanup; code collision/kapasitas nomor | Kontrak rinci media/kode dan constraint terkait |
| P-05 | Clone khusus Equipment, identitas instance/database, provisioning account, DML-only runner dan manifest/hash schema baru | DDL atau tes mutatif apa pun |
| P-06 | Scope Phase 6C.4L.2 dan izin eksplisit untuk pekerjaan schema pada clone terpilih | Membuat migration atau menjalankan DDL; belum diberikan dalam Phase 1C |
| P-07 | Migrasi histori penuh versus arsip read-only, pemetaan transaksi lama aktif dan konversi tenggat legacy | Cutover; tidak otomatis menghalangi pencatatan baseline desain |
| P-08 | Topologi source/target/users FK, writer freeze, media mapping, backup/restore drill dan rollback setelah write baru | Approval cutover/recovery terpisah |
| P-09 | Reset/destruksi data atau penghapusan histori/media bila pernah diusulkan | Persetujuan terpisah dengan daftar dampak; tidak termasuk approval ini |

Keputusan P-01/P-02 tidak membuat kebijakan aksi kembali pending: aturan izin langsung sudah final; yang pending adalah penamaan, assignment dan integrasinya. P-07–P-09 bukan alasan untuk menghapus histori demi memperoleh schema clean.

## 6. Gate fase schema berikutnya

Tidak ada gate eksekusi yang otomatis terbuka karena dokumen ini dibuat.

| Gate | Bukti / syarat | Status Phase 1C |
| --- | --- | --- |
| S-01 — sumber desain | PRD/context otorisasi selaras dengan approval record; DBML 11 tabel tanpa approval table, users stub eksternal | Review dokumentasi dilakukan; tidak ada perubahan DBML |
| S-02 — keputusan fisik | Putuskan P-03 dan kebijakan P-04 yang memengaruhi kolom/constraint/index; catat keputusan yang belum final | Pending |
| S-03 — izin dan identitas clone | Otorisasi scope Phase 6C.4L.2, target clean tanpa legacy, kredensial terpisah provisioning/DML-only dan pemisahan storage | Pending; tidak ada koneksi atau SQL fase ini |
| S-04 — keselamatan/pemulihan | Backup otoritatif dan media/ledger bila relevan, verifikasi hash, restore drill pada target terisolasi, preflight source/target | Rencana gate; belum dieksekusi ulang |
| S-05 — manifest guard | Allowlist core+11 tabel, users.id actual type, FK/index/name collision, signature dan akun diuji; guard 24 tabel baseline tidak dilemahkan | Pending; butuh approval manifest baru |
| S-06 — spesifikasi schema | Parser DBML penuh bila tersedia; urutan FK, unsigned, generated UNIQUE, CHECK/nullability, index dan plan restore/rollback DDL diperiksa | Static review sebelumnya saja; belum parser/DDL/MySQL tested |
| S-07 — constraint/race test plan | Negative FK/photo/outcome/empty-batch/pending-token/void tests, rollback dan concurrency; otorisasi empat aksi tidak dilewati | Spesifikasi acceptance, belum tes runtime |
| S-08 — peralihan fase | Persetujuan eksplisit pengguna atas pekerjaan schema setelah syarat relevan lulus | Belum ada; berhenti setelah dokumentasi |

Jika Phase 2 nanti diotorisasi, hanya clone yang disetujui boleh menerima schema. Izin tersebut tetap bukan izin migration/reset/cutover pada wpitv atau wpitv_testing. Backend/UI, grant permission runtime, produksi dan deployment mempunyai scope/gate berikutnya.

## 7. Konsistensi dengan DBML

DBML v3.1 tidak diubah: sebelas domain tables, users stub, nol VIEW/tabel approval. Notes audit/server-side policy tidak membatasi void ke role tertentu, sehingga policy permission-based tidak memerlukan kolom approver, approval status, queue atau tabel baru. Retired terminal, found→maintenance, loss outcome dan void outcome tetap konsisten dengan kolom/status yang ada.

Semua FK/UNIQUE/generated/CHECK notes dan snapshot proposals dipertahankan. DBML tidak dapat membuktikan hak akses, alasan nonblank lintas event, transaksi yang benar, larangan perubahan saat on_loan, foto minimal satu, atau append-only enforcement secara otomatis. Itu memerlukan service/policy/constraint tests pada fase yang kelak disetujui. Tidak ada klaim DDL/MySQL telah diuji.

## 8. Acceptance otorisasi untuk fase implementasi — belum dijalankan

- Akun tanpa permission retire/recovery/loss/void ditolak, termasuk akun berlabel Super Admin; tidak ada perubahan bisnis/audit sukses/key sukses akibat denial.
- Akun berpermission sesuai lolos hanya bila lifecycle/resource/version valid, alasan terisi dan syarat transaksi terpenuhi; tidak menunggu approver.
- Retire unit on_loan ditolak; retire bebas terminal dan tetap mempunyai histori.
- Recovery selain status lost ditolak; lost→maintenance tidak membuat unit loanable, tidak mengubah lost outcome lama.
- Loss saat dipinjam atomik pada unit dan loan-unit; fault injection membatalkan seluruh batch dan audit/key suksesnya.
- Void tanpa permission khusus, tanpa attestasi non-handover, sesudah handover nyata, atau setelah unit returned/lost ditolak; void sukses tidak hard-delete histori dan tidak mencampur outcome.
- Replay authorized menghasilkan referensi hasil lama tanpa audit/mutasi baru; revokasi permission/status akun tetap diperiksa; hasil privat tidak bocor.
- Tidak ada UI approve/reject, approval queue, tabel approval atau actor approver yang dibutuhkan untuk menjalankan empat aksi.

## 9. Perubahan dan bukti verifikasi Phase 1C

Dibuat: EQUIPMENT_DESIGN_APPROVAL_v3.1.md.
Diperbarui hanya bagian otorisasi terkait dalam PRD/context v3.1: matriks role-only diganti kontrak permission-based, aturan dan AC void/Super Admin diperbarui, AC akses tanpa permission ditegaskan, pending permission/found dipisahkan antara kebijakan approved dan detail assignment. Rincian schema, laporan, histori dan idempotency di luar otorisasi tidak dirancang ulang.

DBML, sumber v3 dan ringkasan rekonsiliasi 1B tetap tidak diubah. Status ringkasan 1B adalah catatan historis sebelum keputusan baru, bukan policy void terbaru. Bukti SHA-256 DBML dipertahankan sebelum/sesudah perubahan. Pemeriksaan dokumen memastikan tidak tersisa aturan void khusus role Super Admin pada PRD/context aktif, tidak ada tabel approval baru, serta tepat 11 tabel domain di DBML. Tidak menjalankan tes aplikasi, parser DBML baru, DDL, SQL, database inspection, migration, grant permission, commit/push atau deployment.

**DESIGN BASELINE APPROVED**; penamaan/grant permission dan keputusan teknis pending tidak diputuskan diam-diam. Fase schema berikutnya memerlukan penyelesaian gate relevan dan instruksi baru. Berhenti pada catatan persetujuan ini.
