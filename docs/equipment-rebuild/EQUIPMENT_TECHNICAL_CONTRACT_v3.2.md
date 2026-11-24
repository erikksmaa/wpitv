# Equipment Technical Contract v3.2

## Status berlaku — Phase 6C.4L.1E, 23 November 2026

**APPROVED sebagai baseline desain** berdasarkan EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md: paket teknis v3.2 setelah koreksi R1, lost→retired dan reason policy Technical7.1. Label/kalimat proposal, rekomendasi atau menunggu approval dari Phase1D/R1 yang masih dikutip untuk detail desain dalam scope tersebut kini dibaca APPROVED; catatan historis bukan keputusan pending baru. Ini tidak mengesahkan bukti lingkungan, hasil tes, provisioning, grant runtime atau cutover. Bukti yang belum tersedia tetap **BLOCKED**; Phase6C.4L.2 belum diotorisasi. Strategi histori, loan legacy aktif, identitas target, manifest operasional, backup/restore dan cutover tetap memerlukan keputusan/bukti tersendiri. Record1E menjadi otoritas status terbaru, record1C tetap historis.

Phase 6C.4L.1D • 23 November 2026 • Documentation only. **Implementation NOT AUTHORIZED.**

## 1. Status dan batas freeze

**APPROVED** baseline1C yang tetap berlaku: 11 tabel/nol VIEW, category→model→unit, stok derived, DATE tenggat akhir WIB, foto per returned unit, partial return, extension beralasan, snapshot, optimistic locking, atomic idempotency, progress derived, audit append-only dan permission-based actions tanpa approval workflow/queue/table. Retire terminal tanpa hard delete dan ditolak saat dipinjam; recovery hanya lost→maintenance; void hanya salah input sebelum handover dengan permission khusus, alasan dan attestasi.

**APPROVED DESIGN**: pilihan teknis bab2–9 dan matriks schema/permission setelah R1 sesuai keputusan pengguna Phase1E. Persetujuan mencakup lost→retired dan reason policy R1, bukan bukti pelaksanaan. **BLOCKED**: bukti lingkungan, integrasi dan pengujian yang belum tersedia. Readiness/cutover mengikuti batas record1E. Tidak ada izin SQL, DDL, migration, provisioning, runtime feature/grant, commit/push/deploy, reset atau cutover.

## 2. Kontrak storage dan schema (APPROVED DESIGN)

Tetap 11 tabel domain dari DBML; users external stub bukan tabel baru. InnoDB, utf8mb4_unicode_ci untuk label, ascii_bin untuk token/code/hash/operation/actor_key/result_type/event_type; canonical category name trim/NFC dengan uniqueness case-insensitive sesuai collation (accent-insensitive harus diterima saat technical approval). Semua domain PK BIGINT UNSIGNED auto increment kecuali operation_keys.token CHAR(36) PK; users FK INT UNSIGNED mengikuti baseline yang masih perlu bukti DDL otoritatif. Semua DATETIME(6) UTC, tanpa default CURRENT_TIMESTAMP; service memasok satu waktu server per event/batch. Semua DATE tenggat adalah tanggal WIB, bukan UTC date. PK/identity/code immutable; tidak membuat CHECK pada PK AUTO_INCREMENT.

Matriks constraint memuat SEMUA kolom/default/nullability, PK/FK/UNIQUE/index dan CHECK predicate. R1: categories.version INT UNSIGNED NOT NULL DEFAULT1, bukan AUTO_INCREMENT; models/units/loans.version dan next_unit_no juga bukan AUTO_INCREMENT. Hanya PK id domain yang auto-increment. equipment_loans.identity_number VARCHAR40 NOT NULL tidak UNIQUE; indeks (borrower_type,identity_number) nonunique, peminjam sama boleh banyak loan; tanpa borrower master table. Default yang tidak disebut adalah tidak ada default eksplisit; kolom nullable default NULL; integer version/next_unit_no default 1, booleans active default true, physical_condition good, operational_status active, outcome on_loan, sort_order 0. Default tidak menggantikan validasi create. Jangan gunakan INSERT IGNORE/REPLACE, FOREIGN_KEY_CHECKS=0 atau bulk writer bypass untuk domain.

Snapshot category_id_at_checkout/model_id_at_checkout BIGINT UNSIGNED NOT NULL, FK RESTRICT independen dan indexed. Snapshot category tidak diikat ke kategori model CURRENT, sehingga reclassification master tidak merusak histori. units.model_id tidak direparent v1. Generated open_unit_id bertipe BIGINT UNSIGNED STORED, ekspresi logis `IF(outcome = 'on_loan', unit_id, NULL)`, UNIQUE; tidak diterima dari browser atau ditulis ORM. NULL berulang membolehkan allocation historis, nilai nonnull unik mencegah dua on_loan. Generated column tidak menjadi target FK.

Actor FK dipilih **PROPOSED RESTRICT untuk semua lima referensi users**, menggantikan SET NULL v3.1: nonaktifkan akun melalui users.status, bukan hard-delete pengguna yang direferensikan. actor_name/received_by_name/recorded_by_name tetap snapshot immutable. Actor/uploader ID nullable hanya untuk import histori yang sudah diverifikasi, bukan mengizinkan aksi runtime tanpa aktor. Ini menjaga row audit tetap immutable saat akun tidak aktif. Tidak mengubah kebijakan user management runtime existing pada fase ini; dampak penolakan delete harus disetujui dan diuji regresi dahulu.

## 3. Locking, versions dan kegagalan (APPROVED DESIGN)

Semua mutasi memakai unit-of-work sendiri pada satu connection; tidak ada commit tersembunyi di helper. Urutan global: (0) reotorisasi akun+permission dan claim token; (1) loan header existing jika relevan; (2) categories ascending ID; (3) models ascending ID; (4) units ascending ID; (5) loan_units ascending ID; (6) append receipt/photo/audit/result. Lock eksplisit per ID, bukan mengandalkan urutan optimizer. Checkout loan header baru tidak bersaing pada header existing. Untuk model reclassification, lock kedua kategori urut lalu model dan revalidasi old category; jika referensi berubah sejak pre-read, rollback/restart, jangan mengambil lock kategori belakangan. Master action tidak mengambil loan header setelah unit lock; cek on_loan di bawah model/unit lock yang dihormati semua allocation/resolution writers. Return/loss/void/extension lock header dahulu; receipt dan due-date snapshot dibentuk setelah validasi. Batch lintas loan tidak didukung v1.

expected_version wajib untuk update kategori/model/unit/loan yang sesuai; tidak menggantikan lock. Setiap pemilik record yang benar-benar berubah increment tepat satu kali per operasi; create version=1, replay/no-op/rollback tidak increment. Update no-op ditolak sebagai validation error agar tidak menerbitkan audit/update versi palsu. Stock.add mengubah model counter/version, bukan semua unit lama. Checkout increment versi selected units, return/loss/void increment header dan unit yang berubah, detail outcome immutable sesudah resolved. Counter/foto revision memakai lock model. Category toggle/model deactivate di-lock dan dicek ulang; deactivate model dengan on_loan ditolak.

Deadlock MySQL 1213/SQLSTATE 40001: rollback seluruh transaction dan maksimal tiga attempts dengan jitter 50–150 ms, token+canonical request sama. Lock timeout 1205: explicitly rollback seluruh transaction, tidak mengandalkan statement rollback; maksimal tiga attempts dengan same-token. Jika habis, 409 RETRYABLE_CONFLICT, state refresh diperlukan, token sama dapat retry. Stale version: 409 STALE_VERSION, tanpa auto-merge/retry ke expected_version baru. Validation 422, token conflict 409, permission 403, resource mismatch 404, inactive/unauthenticated 403/401. Kegagalan audit/result/metadata menggagalkan bisnis. Unique asset/code collision ditangani dalam algoritme nomor, bukan dijadikan generic sukses.

## 4. Idempotency dan operation registry (APPROVED DESIGN)

Token global UUID kanonis lowercase, CHAR(36) ascii_bin PK; tidak di-scope actor untuk melewati konflik. actor_key=`users:<id>` dengan decimal ID kanonis, bukan nama/role. operation exact allowlist, payload_hash lowercase SHA-256. actor+operation+hash immutable. Canonicalization request version 1: UTF-8 valid, NFC, trim, CRLF→LF, optional empty→NULL, integers tipe angka, boolean tipe boolean; tanggal strict YYYY-MM-DD; ID/selected-unit sets disortir numerik dan duplicate ditolak; urutan list naratif tetap. WhatsApp dinormalisasi E.164 digit dengan country code (tanpa +); identitas institusi string tidak dipaksa numerik. Unknown fields ditolak. Hash JSON UTF-8 dengan key order fixed, no whitespace, escaping Unicode/slash tetap; expected_version, alasan/attestasi, quantity/manual selections dan checksum manifest media server-verified masuk hash. Server timestamp, allocated automatic unit IDs, staged random storage keys, actor mutable labels dan result tidak ikut hash. Manual selection dan automatic request adalah payload berbeda. Jangan mempercayai hash/storage_key dari browser.

Insert key pending uncommitted lalu lock/revalidate/mutasi/audit; isi result_type/result_id/completed_at bersama sebelum commit. Result triple wajib semuanya NULL atau semuanya nonnull; completed_at>=created_at. PROPOSED service unit-of-work tidak menyediakan commit tanpa complete; fault-injection harus membuktikan committed pending tak ada. CHECK hanya mengizinkan pending row selama transaction, tidak dapat menolak COMMIT pending. Tidak ada status/cache table baru, token selesai tidak dihapus/expired otomatis. Logical result type allowlist = category/model/unit/loan/return/model_photo/unit_photo; target harus ada dan tidak hard-delete. Audit JSON menyimpan operation token/correlation dan batch ID list untuk stock/loss, tanpa menambah kolom/table.

Replay dibaca setelah autentikasi+permission/scope current; revoked user/permission ditolak meski token pernah sukses. Cocok actor+operation+hash mengembalikan envelope immutable `{token, operation, result:{type,id}, completed_at}` yang sama; mutable resource detail dibaca terpisah. Replay terjadi sebelum stale/current lifecycle validation. Different actor/operation/hash ditolak 409 tanpa disclosure. Duplicate PK contender menunggu; setelah rollback failed transaction lakukan fresh read di transaction baru (bukan snapshot lama) dan replay/conflict. Pemenang rollback membuat key hilang sehingga contender boleh mencoba claim lagi; timeout tidak boleh mengarang hasil. Result tuple NULL yang ditemukan committed berarti integrity alarm dan 503, bukan diambil alih/expired diam-diam.

| operation | required permission | result_type | event utama |
| --- | --- | --- | --- |
| category.create | equipment.category.create | category | category.created |
| category.update | equipment.category.update | category | category.updated |
| model.create | equipment.model.create | model | model.created |
| model.update | equipment.model.update | model | model.updated |
| stock.add | equipment.stock.add | model | stock.added |
| unit.condition | equipment.unit.condition | unit | unit.condition_changed |
| unit.maintenance_start | equipment.unit.maintenance | unit | unit.maintenance_started |
| unit.maintenance_finish | equipment.unit.maintenance | unit | unit.maintenance_finished |
| unit.lost | equipment.unit.loss | unit | unit.lost |
| unit.found | equipment.unit.recover | unit | unit.found |
| unit.retire | equipment.unit.retire | unit | unit.retired |
| loan.checkout | equipment.loan.checkout | loan | loan.checked_out |
| loan.extend | equipment.loan.extend | loan | loan.extended |
| loan.void | equipment.loan.void | loan | loan.voided |
| loan.loss | equipment.loan.loss | loan | loan.loss_recorded |
| return.create | equipment.loan.return | return | return.created |
| model.photo_add | equipment.model.photo.add | model_photo | model.photo_added |
| unit.photo_add | equipment.unit.photo.add | unit_photo | unit.photo_added |

Result ID photo_add v3.2 menunjuk ID foto baru (lebih presisi daripada parent ID proposal v3.1). stock.add tetap result model; loan.loss result loan dan audit mencatat seluruh selected loan-unit IDs. Atomic create_model dapat memuat stok awal dan optional cover dalam audit model.created tunggal beserta rincian, tanpa menduplikasi token untuk substep. Operation registry bukan permission grant runtime.

## 5. Kode unit, kapasitas dan stok (APPROVED DESIGN)

model_code panjang <=40 ASCII uppercase. Base = normalized brand first 3 alnum (fallback INV) + '-' + normalized model_name first 20 alnum (fallback normalized name, lalu UNIT). Contoh SON-A6400. Alnum normalisasi transliterasi ASCII, uppercase, hapus nonalnum; hasil final tidak ditentukan oleh COUNT. Collision: base lebih dulu, lalu -M000001 sampai -M999999; alokasi kandidat memakai UNIQUE(model_code) dan bounded retry 10 kandidat per request; kandidat terpakai dilewati berdasarkan existing code, tidak menafsirkan jumlah rows sebagai nomor. Exhaustion/attempt cap = validation/retryable error eksplisit, bukan menimpa model. Code resmi hanya diterbitkan saat transaction commit; preview tidak menjanjikan reservasi.

asset_code=`<model_code>-<unit_no decimal minimum 3 digit>`; SON-A6400-001; nomor 1000 tetap 1000, tidak truncate/wrap. unit_no 1..999999; next_unit_no 1..1000000 (sentinel exhausted). DB integer UNSIGNED dengan CHECK batas bisnis; next_unit_no awal 1, create N unit memakai 1..N dan menjadi N+1. Lock model, reserve range contiguous, validasi batas sebelum insert N; UNIQUE(model_id,unit_no) dan asset_code global. Tidak pernah menurunkan counter atau reuse nomor unit committed setelah retire/loss, koreksi input, rename, maupun recovery. Nomor yang belum pernah committed boleh terpakai setelah rollback; tidak ada janji gapless/sequence mandiri di luar transaksi. Tidak hard-delete model/unit sehingga code committed tetap terjaga. Model_code suffix 32 chars maksimum dengan rumus ini; asset maksimum 39, aman dalam varchar 40/60.

Stok awal 1..100 unit per request; tambah stok 1..100. Semua initial units berasal satu model, condition dipilih, operational active (model boleh nonaktif); tidak ada initial lost/retired/on_loan. Kondisi damaged tidak loanable. Penambahan stok untuk model existing tidak memerlukan kategori current aktif; kategori nonaktif hanya mencegah registrasi/reclassification ke kategori tersebut. Model nonaktif boleh ditambah stok tetapi tetap tak eligible. Checkout <=50 unit, <=20 model; return/loss <=10 unit per batch; loan tidak kosong.

Lifecycle: active→maintenance dengan alasan; maintenance→active hanya good setelah inspection note; active/maintenance→lost hanya unit bebas lewat unit.lost, sedangkan on_loan hanya loan.loss; lost→maintenance hanya unit.found, bukan active; active/maintenance→retired jika bebas dan alasan; lost→retired hanya menurut APPROVED syarat R1 berikut; retired terminal. Tidak ada condition/status/retire management edit ketika on_loan. Kondisi dapat diperiksa good/minor_damage/major_damage saat bebas dengan alasan, tanpa otomatis mengganti operational status. Return damaged tetap operational active (atau status current sah) namun tidak eligible; perawatan aksi terpisah. Found clear closed_* current dan audit menyimpan before, outcome pinjaman lost tetap. Tujuh kelompok stok eksklusif: retired,lost,on_loan,maintenance,damaged,model_inactive,available; jangan menghitung kondisi dua kali. Audit integrity gagal bila lost/retired/maintenance masih mempunyai on_loan; jangan menyamarkannya dengan prioritas angka.

**APPROVED Phase1E — retire unit lost**: permission retire+alasan eksplisit, row locks dan recheck bahwa tidak ada on_loan. Status lost+on_loan adalah integrity failure: tolak dan investigasi, jangan memperbaiki histori dengan void/retur palsu. Retire langsung dari lost berarti penutupan administratif aset hilang, bukan pernyataan barang sudah ditemukan. Simpan old/new closed_at+closed_reason di audit; current closed_* menjadi waktu server dan alasan retire. Semua histori outcome lost, resolution_at, due_date_at_resolution/is_late serta catatan loan-unit tetap. Tidak perlu found dahulu; found selalu lost→maintenance, dan retired tetap terminal meski barang kemudian ditemukan. Ini merinci proposal jalur v3.2 sebelumnya, bukan mengubah approved terminal/no-delete/no-on-loan secara diam-diam.

Loan/receipt codes PROPOSED = EQ-L-/EQ-R- diikuti uppercase ULID26; UNIQUE dengan maksimal3 transaction retries same-token pada collision server-issued, sebagaimana matrix C19.

## 6. Media privat (APPROVED DESIGN)

Format input JPG/JPEG, PNG, WebP saja; **5 MB = 5,000,000 bytes**, bukan 5 MiB. Dimensi setelah orientasi 64..4096 px per sisi, area <=16,777,216 px. Validasi uploaded-file validity, ukuran asli, signature/MIME finfo, full decode, dimensional cap dan extension; reject SVG/GIF/animated images, decompression/pixel excess, malformed/polyglot yang tidak lolos decode/re-encode. Normalize orientation, strip EXIF/metadata dan re-encode; output wajib tetap format allowlist, size/dimension cap. sha256 dan byte_size metadata dihitung terhadap BYTES FINAL yang disimpan; original upload tidak menjadi evidence immutable sebelum normalisasi.

Model cover opsional saat create; satu cover upload per mutation, revision integer monoton di bawah model lock, latest valid committed revision menjadi current cover; semua revision historis tetap privat dan immutable, tidak diganti in-place. Unit reference/inspection/damage optional <=8 foto/action. Return minimal1 dan maksimal8 foto PER loan-unit; maksimal10 unit PER batch dan maksimal40 foto TOTAL batch. Input tiap file <=5,000,000 bytes DAN output final tiap file <=5,000,000 bytes; SUM(input bytes)<=100,000,000 DAN SUM(final bytes)<=100,000,000, dua total terpisah, bukan satu kuota gabungan input+output. Seluruh batas berlaku bersamaan: kuota batch tidak menggantikan batas per-unit/per-file. Contoh 10unit×8foto=80 ditolak walau semua file kecil; 10unit×4foto=40 sah hanya bila kedua total byte juga lulus. Limit total tidak menaikkan limit per-file. Reject seluruh batch bila satu foto/unit kurang/tidak valid; foto umum receipt dilarang.

Staging: authorize upload dahulu, server-owned random key dan binding actor+request+checksum; browser tak dapat memilih storage_key/table. Validasi dan private staging tanpa DB commit. Dalam transaksi ulang scope dan checksum/existence, pastikan final private object sudah durable dengan key baru sebelum row metadata+audit+token commit; filesystem tidak dianggap ikut rollback DB. On commit object immutable. On rollback/retry jangan langsung menghapus media yang mungkin dimiliki winning retry: reconciliation wajib mengecek semua tiga tabel foto committed, aktif upload lease dan token outcome. Pending upload lease memakai metadata manifest staging privat di filesystem, bukan tabel domain tambahan. PROPOSED staged/unreferenced grace 24 jam; sweeper mengambil lease eksklusif dan recheck reference di bawah koordinasi uploader sebelum delete. Objek yang direferensikan histori TIDAK dihapus otomatis, tidak ada retention purge evidence. Reconciliation/recovery operator dijurnal; orphan cleanup bukan domain hard-delete.

Download/inline melalui controller authorized, resource scoping model/unit/loan/receipt/loan-unit dan composite FK; permission photo.view + resource view yang relevan. Tidak ada public URL/symlink, signed public bearer URL atau raw path dari browser. Storage key random dan canonical path tetap di private clone/app root; reject traversal. Respond Content-Type allowlist, nosniff, private/no-store, disposition attachment atau inline aman; nama file server-generated. Revoked akun ditolak. Race duplicate token tidak memindahkan kepemilikan staging atau menghapus foto pemenang. Upload/image pipeline dan file-access tests diperlukan nanti, belum dilakukan.

## 7. Permission dan audit (APPROVED DESIGN)

Lihat EQUIPMENT_PERMISSION_MATRIX_v3.2.md. Tidak memakai isSuperAdmin/global Gate-before sebagai bypass. Static catalog dan mapping eksplisit terpusat di layer permission existing yang kelak disetujui adalah proposal v1 tanpa tabel permission domain baru; hasil resolver adalah set permission, policy mengecek permission+resource+akun aktif. Role hanya input mapping administratif, bukan kondisi akses yang langsung true. Unknown/missing mapping fail closed. Per-user custom grants/revocation store tidak ditambahkan tanpa desain/approval. Baseline yang dibaca belum menunjukkan permission store, sehingga bukti integrasi resolver/guard tetap BLOCKED untuk implementasi; tidak membuat seeder/config/policy runtime sekarang.

Audit event registry utama sama dengan operation table. Untuk batch loss tambahkan event unit.lost dan loan-unit resolution facts dalam audit yang sama; stock.add memuat daftar unit dibuat. reason audit nonblank <=1000 untuk semua mutasi penting; sumber manual versus server-generated mengikuti proposal R1 bab7.1, bukan kewajiban field alasan manual universal; actor_name snapshot diambil server. subject_type allowlist category/model/unit/loan/loan_unit/return; foto ditautkan via parent subject dan JSON photo ID, tidak mengubah subject enum. event_type catalog case-sensitive <=60, actor_name max100 harus tervalidasi terhadap users/name baseline sebelum implementasi. Audit payload read-only setelah commit; application runner tidak boleh menjalankan UPDATE/DELETE pada audit_log. Audit retention indefinite sampai prosedur legal/operator terpisah disetujui. FK RESTRICT menjaga actor deletion tidak menulis ulang audit; akun nonaktif tetap disimpan.

DML-only runner proposal mendapat SELECT/INSERT pada audit_log dan operation_keys, UPDATE operation_keys hanya melalui unit-of-work completion (kolom result saja bila grants kolom dipilih), tidak DELETE kedua tabel. Tabel lain SELECT/INSERT/UPDATE secukupnya, tanpa DELETE domain. Test fixture cleanup memakai akun cleanup clone-only terpisah, terbatas target, exact ownership/journal dan invariant; jangan memberikan hak cleanup pada runtime lalu mengaku immutable secara grant. Detail grant/provisioning tetap proposal, belum dijalankan.

### 7.1 Reason policy R1 — APPROVED Phase1E

APPROVED tetap: unit.retire, unit.found/recovery, unit.lost dan loan.loss serta loan.void memerlukan alasan eksplisit pengguna berpermission; loan.extend juga tetap wajib alasan sesuai baseline. Tidak boleh mensubstitusi alasan sensitif dengan label otomatis, purpose checkout, atau string default tersembunyi. Void tetap memerlukan attestasi non-handover terpisah.

APPROVED R1 menggantikan proposal lama bahwa create biasa selalu wajib alasan manual. Audit.reason selalu nonblank/server-written, tetapi input alasan bergantung aksi:

| Kelompok | Operation | Reason input / audit |
| --- | --- | --- |
| Sensitif / extension | unit.retire, unit.found, unit.lost, loan.loss, loan.void, loan.extend | Alasan eksplisit nonblank mandatory; audit dari alasan valid itu, actor+timestamp server |
| Administrative perubahan existing | category.update, model.update, stock.add, unit.condition, unit.maintenance_start, unit.maintenance_finish | APPROVED alasan eksplisit wajib; before/after melengkapi alasan, bukan penggantinya |
| Create normal | category.create, model.create | APPROVED tidak perlu extra manual reason; audit reason server-generated canonical event (mis. model.created: pendaftaran model+stok awal), input/snapshot/quantity/actor sebagai metadata |
| Checkout biasa | loan.checkout | purpose nonblank tetap mandatory; APPROVED tidak perlu field reason manual kedua; audit reason canonical loan.checked_out, purpose disimpan lengkap pada loan, bukan dibocorkan sebagai event label |
| Foto tambahan/revisi existing | model.photo_add, unit.photo_add | APPROVED alasan manual untuk revisi cover atau inspection/damage; first cover/reference boleh reason otomatis + metadata. Unit photo_type reference pertama memakai provenance server; foto berikut/revisi memerlukan alasan eksplisit |
| Retur normal | return.create | APPROVED receipt/event reason otomatis untuk good, tepat waktu; kondisi rusak atau late mewajibkan catatan per selected loan-unit dan ringkasan audit. Detail ini approved Phase1E; no backdating tetap |

reason <=1000, whitespace-only ditolak; audit.before/after bukan duplikasi reason dan bukan pengganti manual pada aksi wajib. Jangan memotong alasan diam-diam. Semua authorizations/lifecycle/row locks, event names, operation allowlist, result types dan atomic token tetap. Dalam canonical payload, reason dimasukkan hanya bila input kebijakan meminta/mengizinkannya; server-generated reason TIDAK ditambahkan sebagai input hash. purpose dan conditional per-unit notes tetap input hash. Replay menggunakan request canonical version/policy yang sama; jika kebijakan baru kelak diberlakukan untuk API existing, version canonicalization harus berubah dan old completed keys tetap immutable. Tidak ada runtime schema/permission change sekarang.

Permission Matrix v3.2 kini membedakan alasan manual, purpose dan audit reason otomatis; itu bukan assertion bahwa setiap create/checkout meminta teks manual tambahan. Proposal mapping permission tidak diubah. PRD v3.2 mengikuti bab ini; v3/v3.1 dan approval1C tetap historis tidak diubah. Keputusan menerima reason otomatis, conditional photo/return notes dan granularitas admin disetujui Phase1E, tanpa melonggarkan requirement sensitif yang sudah approved.

## 8. Laporan (APPROVED DESIGN)

Satu read snapshot/as_of server UTC untuk KPI/detail/CSV/print. Default period_basis=borrowed memilih cohort header berdasarkan borrowed_at; basis returned memilih loan-unit returned melalui receipt.returned_at; basis lost memilih loan-unit lost melalui resolution_at. Periode tanggal WIB inklusif → [awal UTC, besok setelah akhir UTC) eksklusif. 25 Nov WIB = [24 Nov 17:00Z,25 Nov 17:00Z). DATE due dibanding tanggal WIB; bukan DATE(timestamp UTC). Backdate override return/loss ditolak; due_date_at_resolution/is_late frozen.

Historical category/model filters memakai immutable checkout IDs dan labels JSON; current inventory memakai relations terkini. Cohort total header = DISTINCT loan.id termasuk void; successful_checkout_loans/units mengecualikan seluruh void; event basis memberi label loans_with_returns/loans_with_loss, bukan checkout-in-period. Unit metrics = DISTINCT loan_unit.id sesuai scope; receipts = DISTINCT return.id; foto tidak pernah menambah unit/loan/receipt. current progress dihitung atas SEMUA unit pada loan, bukan subset filter (subset tidak boleh membuat loan terlihat closed). unit outcome counts boleh subset dengan label selected_units. active overdue = ada current on_loan dan todayWIB>due_date; historical late = is_late resolved snapshot, bukan progress overdue. Void bukan return/loss/damaged atau sukses checkout; void counter terpisah. Current inventory total=7 kelompok; operational_units mengecualikan retired bila ditampilkan, dengan nama eksplisit.

Contoh expected output: loan A checkout 25 Nov, 3 unit, due25; 1 kembali25Nov23:59 good, extend ke27; 1 kembali28Nov00:01 minor_damage; 1 lost28Nov00:02. Loan B checkout25Nov 2 unit seluruhnya void. Cohort25Nov current setelah semua event: record_loans=2, voided_loans=1, successful_checkout_loans=1, successful_checkout_units=3, returned=2,lost=1,on_loan=0,voided_units=2,late_resolved=2,damaged_on_return=1; progress A closed, B voided; overdue loans=0. Returned basis28Nov: selected loan-units=1, loans_with_returns=1,late=1,damaged=1,receipts=1. Lost basis28Nov: selected loan-units=1,loans_with_loss=1,late=1. Dua foto per return unit tidak mengubah hitungan. inventory current found unit tetap maintenance meskipun outcome historis lost.

Current DB snapshot + periode lama bukan rekonstruksi historical-as-of: UI v1 tidak menyediakan historical balance selector. Keluaran CSV dan print berasal service/DTO/filter+scope yang sama; CSV semua sel untrusted melewati CsvCell (termasuk prefix whitespace/BOM/control dan invalid UTF-8), RFC quoting/newline konsisten, print HTML escape semua teks, no raw HTML/file URL. Borrower identity/WhatsApp hanya ditampilkan bagi permission borrower.view/export dengan explicit inclusion; default export redacted. Raw PII tidak dicatat di access logs atau error.

## 9. Invariant service dan negative test specifications

Belum ada tes runtime dieksekusi. Matriks constraint mencantumkan batas SQL; skenario berikut menjadi gate schema/backend, bukan bukti lulus:

| ID | Skenario negatif / race | Expected |
| --- | --- | --- |
| N-01 | PK/FK unsigned beda; receipt loan salah; photo receipt/unit salah | DDL/type gate atau FK menolak; no partial commit |
| N-02 | Nullable CHECK menerima UNKNOWN | Explicit null/nonblank branches menolak incomplete outcome/result tuple |
| N-03 | Dua checkout unit terakhir; auto/manual overlap | Satu commit, satu conflict, generated unique tetap benar |
| N-04 | Same token sama actor/payload di dua koneksi | Satu bisnis+audit/key; envelope sama, winner file tetap ada |
| N-05 | Token beda actor/operation/hash; revoked account replay | 409/403; tidak membocorkan result |
| N-06 | Fault sebelum/selepas audit atau result completion | Seluruh rollback; tidak ada committed pending/empty header/receipt |
| N-07 | Deadlock/timeout pada unit ke-N | Whole rollback, bounded attempts, tidak reuse token baru atau sebagian batch |
| N-08 | Stale category/model/unit/header | 409 tanpa write/audit sukses atau auto-merge |
| N-09 | Deactivate/edit/retire versus checkout atau return-vs-loss | Lock order konsisten, tidak ada unit ineligible on_loan/double resolve |
| N-10 | Counter nomor999999 plus tambah2, model slug collisions | Overflow ditolak atomik; no wrap/reuse/COUNT |
| N-11 | Returned unit tanpa foto, photo >limit, fake MIME/hash/path/EXIF | Batch ditolak; all valid objects scoped privat; tidak hapus winner |
| N-12 | Found active/retired, retire borrowed, void post-handover/partial resolved | Ditolak meski punya permission; histori tidak dihapus |
| N-13 | Inventaris di-rename/reclassify, report join multi-photo, void cohort | Historical IDs/labels dan expected KPI tetap benar |
| N-14 | Event 00:00/00:30 WIB, extension sesudah partial, browser backdate | UTC range/late snapshots tepat dan immutable |
| N-15 | Permission absent pada Super Admin; IDOR receipt/file | Deny, bukan role bypass; file bukan public |
| N-16 | Hard-delete user referenced; UPDATE/DELETE audit by runtime | FK/grants menolak; disable user tidak merusak audit |
| N-17 | Orphan sweep bersamaan commit/replay | Lease+recheck mencegah delete evidence terpakai |
| N-18 | Wrong source DB/UUID/storage/schema approval | Guard fail sebelum mutation; wpitv/wpitv_testing tidak disentuh |

AC-01–33 v3.1 tetap berlaku sejauh tidak diganti keputusan otorisasi 1C. N-series menambah detail, tidak menghapus assertion/skenario lama. Semua proof mutatif hanya di clone setelah approval implementasi.

## 10. Keputusan approval dan evidensi

APPROVED baseline1C dan paket teknis v3.2/R1 tercatat pada record1E (schema, actor RESTRICT, registry, default permission mapping, numbering, media/KPI/lock dan desain pemisahan grants). Grant aktual tidak dibuat atau diotorisasi. BLOCKED lingkungan/SQL/parser dicatat di readiness; tidak mengisi port/UUID/type aktual dengan tebakan. Cutover legacy nama bertabrakan, mapping active loans, backup/restore dan pilihan full migration/read-only archive tetap keputusan terpisah. Tidak mengubah 11 tabel untuk menyembunyikan collision.
