# Equipment Design Contract Reconciliation v3.1

Phase 6C.4L.1B • 23 November 2026 • DESIGN ONLY

**Verdict: READY FOR DESIGN APPROVAL.** Lima konflik sekarang mempunyai kontrak tertulis dan proposal yang dapat disetujui. Ini bukan approval desain itu sendiri dan bukan izin implementasi/DDL/cutover. Recommended dan needs approval harus diputuskan sebelum pekerjaan dependennya.

## Identitas sumber dan perubahan scope

Branch aktual rebuild/wpitv-clean; HEAD e3b0289531b3dd7dc632f803c85c314eada1880e; source bersih sebelum dokumentasi. Tiga v3 diambil dari lampiran/Downloads yang telah direview, diverifikasi header dan hash lalu disalin byte-identical. Tidak membaca runtime Equipment archive sebagai fondasi baru. Tidak menghubungi database atau menjalankan SQL/test aplikasi.

| Sumber asli yang disimpan | SHA-256 |
| --- | --- |
| WPiTV_Equipment_PRD_v3.md | fb081559921cfb9562187be73c338faeaebd08cdbf41c3fe8d6547561bb34f0b |
| WPiTV_Equipment_ERD_v3.dbml | d9ce0c431da3ed7a4d38c1dfb3b2d52dfffd6e736fdb13b88000395448a7ef30 |
| WPiTV_Equipment_REBUILD_CONTEXT.md | 6c319dd5f236199c565015727df4f65562b1dd632bda18c1c14b48c160675804 |

Ketiga sumber sebelumnya belum ada di docs/equipment-rebuild. Sekarang tiga v3 dipertahankan, tiga artefak v3.1 dibuat dan dokumen reconciliation ini ditambahkan. Tidak ada source aplikasi, migration, runtime guard, data, ledger atau foto diubah. Tidak ada commit/push/deploy.

## Ringkasan delta dari v3

| Konflik | Kontrak v3.1 | Status |
| --- | --- | --- |
| Nama tabel legacy bertabrakan | Nama final tetap di clone clean; mapping privat, alternatif histori A/B, pinjaman aktif, backup+restore drill, target/routing dan recovery gate terdokumentasi | agreed untuk batasan; strategi akhir needs approval |
| Concurrency kategori/unit | categories.version nonnull default 1; stale reject; management condition/status/retire dilarang saat on_loan; return/loss khusus | agreed; lock order teknis recommended |
| Idempotency belum lengkap | Pending hanya uncommitted; hasil/bisnis/audit/key satu commit; rollback tidak menyisakan key; replay immutable envelope; deterministic UNIQUE recovery | agreed; registry/envelope/CHECK tuple recommended |
| Histori/filter/KPI ambigu | Default borrowed; basis returned/lost eksplisit; WIB→UTC; no backdating; snapshot ID/label; void terpisah; DISTINCT grain; contoh hitungan dan current-as_of scope | agreed; FK/indeks snapshot dan detail KPI recommended |
| Progress tidak seragam | active / partially_resolved / closed / voided, derived only; empty/mixed-void integrity failure; overdue terpisah | agreed |

Kolom baru hanya equipment_categories.version, equipment_loan_units.category_id_at_checkout dan equipment_loan_units.model_id_at_checkout. Dua FK snapshot RESTRICT independen menjaga keberadaan ID; indeks kategori+model+loan dan model+loan mendukung filter. Tidak ada FK yang menuntut category snapshot tetap sama dengan kategori model CURRENT. Immutability dan kesesuaian pada saat checkout tetap invariant service.

Tetap 11 domain tables + 1 external users stub, 124 kolom total termasuk stub (123 domain), nol VIEW dan nol tabel agregat/pivot tambahan. Dua FK snapshot membuat jumlah deklarasi Ref menjadi 18. Tabel operation_keys mempertahankan delapan kolom, tanpa status/response-cache baru. Tidak ada penambahan tabel struktur baru.

## Matriks aturan bisnis → kontrak data

Label agreed/recommended/needs approval mempunyai arti yang sama di ketiga dokumen. Seluruh persyaratan warisan v3 yang belum dikunci tetap recommended sampai pengesahan baseline.

| Area / aturan | Kolom / relasi / query | Penegakan yang dirancang | Status |
| --- | --- | --- | --- |
| Tipe dan identitas | domain IDs BIGINT UNSIGNED; user FKs INT UNSIGNED; users stub | Migration nanti harus menerjemahkan unsigned notes; tidak membuat users | agreed |
| Nama dan jumlah tabel | 11 equipment_*; nol VIEW/stock/report table | Schema allowlist clone khusus; target collision bukan rename sumber | agreed |
| Kategori stale update | categories.version INT UNSIGNED NOT NULL DEFAULT 1 | expected_version + transaksi, increment sukses sekali, rollback/replay tidak increment | agreed |
| Metadata current vs histori | models.category_id, units.model_id, loan_units.snapshot IDs+JSON | FK snapshot independen; isi server dalam locked checkout; label frozen | snapshot kebutuhan agreed; kolom/FK/index recommended |
| Unit lifecycle | units.physical_condition, operational_status, closed_at/reason, version | Management ditolak ketika on_loan; loss/return atomik; found audit, retired terminal | larangan mutasi agreed; closed-field detail recommended |
| Outcome CHECK/nullability | loan_units.outcome, return_id, condition_in, due_date_at_resolution, is_late, resolution_at/notes | Truth table PRD 12.6; nonblank loss/void notes; timestamp returned hanya pada returns | agreed |
| Double checkout | generated STORED open_unit_id UNIQUE, loan_id+unit_id UNIQUE | Nilai generated dari outcome, lock/revalidate; browser tak menginput generated | agreed |
| Receipt milik loan | (loan_units.return_id,loan_id) → returns(id,loan_id) UNIQUE | Composite FK + selected IDs scoped ke locked loan | agreed |
| Foto per unit/receipt | return_photos.return_id NOT NULL; (loan_unit_id,return_id) → loan_units(id,return_id) UNIQUE | FK tepat; >=1 valid photo/returned unit dan nonempty batch wajib service sebelum COMMIT | agreed |
| Idempotency | token PK; actor_key,operation,payload_hash; result_type/id/completed_at | Immutable identity, satu transaksi, fresh recovery read, auth replay; tuple semua null/lengkap | agreed; tuple CHECK/envelope registry recommended |
| Progress | agregat loan_units.outcome, N>=1 | active/partially_resolved/closed/voided; campuran void invalid; no header status | agreed |
| DATE/late | loan.due_date/original_due_date; resolved snapshot; returns.returned_at/resolution_at | Waktu server UTC, no backdate; batas hari WIB dan snapshot late immutable | agreed |
| Laporan | borrowed_at default; returned/lost event range; snapshot category/model filter | Rentang half-open UTC; DISTINCT grain; current inventory relasi current; current snapshot bukan historical replay | basis/time/history agreed; KPI/read-snapshot detail recommended |
| RBAC/security/media | existing users/roles; actor refs; private photo storage_key/mime/size/hash | Policy tiap aksi+IDOR+CSRF; validasi upload server; Super Admin void; News/TVClub ditolak | v3 baseline recommended; granular policy/foto needs approval |
| Audit dan histori/cutover | audit_log subject logical + optional loan FK, actor_name/JSON; mapping operator eksternal | Append-only payload; FK SET NULL actor nuance PRD 12.9; mapping/restore/media/active-loan verification | protection agreed; topology/A/B/reset needs approval |

Tidak ada CHECK lokal yang menjamin kategori/model aktif saat checkout, minimum satu foto/loan-unit, loan tidak kosong, semua void satu loan, immutability seluruh field, committed-pending prohibition, atau matching JSON snapshot. PRD/DBML secara eksplisit menempatkan invariant lintas baris/temporal tersebut pada service, authorization dan clone tests; bukan klaim integritas otomatis dari schema gambar.

## Verifikasi yang benar-benar dilakukan

1. Identitas header dan SHA-256 tiga sumber; salinan v3 dibanding ulang byte-per-byte: cocok.
2. Static structural assertions lokal terhadap DBML: 12 Table blocks, 11 domain/1 stub; 124 field; 18 Ref; semua endpoint tabel/kolom ada, aritas FK cocok, tipe dasar cocok, arah child→parent benar; semua target FK adalah PK/UNIQUE yang dideklarasikan; SET NULL hanya menunjuk child nullable. Dua composite FK kritis dan dua FK snapshot dicek.
3. categories.version nonnull, dua snapshot IDs nonnull, generated open_unit_id UNIQUE, tidak ada header status/progress, delimiter braces/brackets seimbang setelah komentar/string disisihkan.
4. Pemeriksaan silang definisi progress, waktu WIB/UTC, outcome nullability, result tuple, photo minimum, snapshot history, RBAC/audit dan cutover; AC-19–33 menambah skenario untuk lima blocker dan integritas terkait. Contoh A/B di PRD menyebut grain KPI secara eksplisit.
5. Ketersediaan parser: require.resolve @dbml/core dan @dbml/cli gagal pada environment yang ada. Tidak menginstal dependency baru atau mengubah package/lock. Karena itu **tidak mengklaim full DBML syntax/parser validation**; pemeriksaan delimiter/Ref adalah validator struktural terbatas, bukan parser grammar DBML.
6. Tidak membuat/menjalankan DDL, migration, SQL, koneksi DB, tes Laravel/MySQL, browser UAT, atau regresi Live/Monthly. Angka DB/tes lama di context diberi label historis, bukan diverifikasi ulang. git diff --check dan scope akhir diperiksa terpisah.

## Keputusan yang tetap membutuhkan approval

- Pengesahan desain clone v3.1 beserta snapshot FK/index, registry/envelope, KPI grain dan scope as_of, lock order serta seluruh rekomendasi warisan v3.
- Policy granular per aksi termasuk found/recovery; kebijakan ukuran/MIME/dimensi/retensi foto; kapasitas dan collision numbering/code; actor FK SET NULL versus strict byte-immutable audit.
- Identitas clone khusus/akun provisioning dan DML-only runner/schema approval/restore drill; guard 24 tabel existing tidak boleh dilemahkan.
- Fase terpisah: full migration versus read-only archive, loan lama aktif, konversi tenggat/history, target/core/user-FK routing topology, cutover/recovery dan reset/destruksi bila pernah diusulkan. Tidak diputuskan pada fase dokumentasi.
- Full DBML parser dan pengujian DDL MySQL/constraint masih harus dilakukan di fase schema yang mendapat approval; rancangan tidak dinyatakan executable.

Kelima blocker sudah berubah menjadi kontrak/desain proposal yang eksplisit, bukan patch runtime. Tidak ada kebutuhan tabel kedua belas. **READY FOR DESIGN APPROVAL**, bukan READY FOR IMPLEMENTATION/CUTOVER. Berhenti setelah dokumentasi.
