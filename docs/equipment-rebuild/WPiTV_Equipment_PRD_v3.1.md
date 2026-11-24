PRODUCT REQUIREMENTS DOCUMENT (PRD)
Equipment Management WPiTV | Full Rebuild v3.1
Status: Baseline rancangan untuk review, bukan persetujuan menjalankan migration.
Tanggal: 23 November 2026
Proyek: WPiTV Modernization, branch rebuild/wpitv-clean
Stack: Laravel 12, Livewire 4, Blade, Mazer, Bootstrap 5, MySQL 8.0.30
Artefak terkait: WPiTV_Equipment_ERD_v3.1.dbml, WPiTV_Equipment_REBUILD_CONTEXT_v3.1.md.
1. Ringkasan produk
WPiTV membutuhkan satu modul untuk mencatat jenis alat, identitas unit fisik, stok yang dapat dipinjam, transaksi peminjaman, retur sebagian atau penuh, kondisi barang, bukti foto, dan laporan. Implementasi Equipment lama diarsipkan dan tidak digunakan sebagai fondasi runtime baru. Live Streaming dan Laporan Bulanan yang sudah ada pada branch rebuild harus tetap berfungsi.

Hasil yang dituju: Petugas membuat kategori, membuat satu barang induk berikut stok awal, meminjamkan alat melalui POS tanpa harga, memproses retur per unit dengan foto wajib, dan meninjau laporan yang konsisten dengan transaksi asli.
1.1 Tujuan terukur
1. Setiap unit fisik memiliki ID dan kode aset yang stabil serta unik.
2. Stok tersedia dihitung dari unit dan transaksi aktif, tanpa counter stok manual.
3. Satu unit tidak dapat masuk ke dua pinjaman on_loan secara bersamaan.
4. Retur parsial tidak menutup peminjaman yang masih memiliki unit on_loan.
5. Setiap unit yang berstatus returned memiliki sedikitnya satu foto privat yang terkait dengan receipt yang benar.
6. Laporan dan dashboard menggunakan definisi status serta batas waktu WIB yang sama.
7. Perubahan Equipment tidak memengaruhi Live Streaming, Laporan Bulanan, News, TVClub, atau pengguna/role.
1.2 Tidak termasuk ruang lingkup
- Harga sewa, pembayaran, denda finansial, atau pengelolaan deposit.
- Persetujuan daring peminjam, portal self-service peminjam, reservasi jadwal, atau peminjaman pihak eksternal pada rilis pertama.
- Penggantian tabel atau workflow Live Streaming dan Laporan Bulanan.
- Migrasi otomatis atau penghapusan database Equipment lama tanpa persetujuan tersendiri.
- Database VIEW, tabel penyimpanan laporan agregat, dan stok tersimpan pada tabel barang.
2. Aktor dan hak akses

**Approved — Phase 6C.4L.1C**: Equipment menggunakan otorisasi langsung berbasis permission per aksi melalui pemeriksaan server-side. Role existing tetap menjadi konteks pengelolaan akses, bukan pengganti permission atau bypass otomatis. Tidak membuat role/permission runtime pada fase dokumentasi ini. Matriks role v3 sebelumnya bukan lagi sumber hak final; nama permission dan pemetaan grant ke role/user existing dicatat pending pada EQUIPMENT_DESIGN_APPROVAL_v3.1.md. Tidak ada hak baru yang diberikan secara implisit kepada Super Admin, Admin Production, Admin News atau TVClub oleh catatan ini. Policy, IDOR, status akun dan scope resource tetap diperiksa.

### 2.1 Otorisasi langsung retire, recovery, loss dan void

**Approved**: pengguna wajib mempunyai permission yang sesuai dengan aksi. Alasan nonblank wajib; audit append-only mencatat actor, target, alasan, waktu dan perubahan. Validasi resource/lifecycle/version dan row locking dilakukan dalam transaksi bersama mutasi dan audit. Permission valid tidak melewati invariant bisnis. Aksi dijalankan langsung setelah validasi, tanpa approval workflow, approval queue, approver kedua atau tabel approval tambahan. Kontrak tetap 11 tabel Equipment. Pemeriksaan ulang hak akun/permission berlaku juga sebelum hasil replay privat diberikan; replay identik tidak menambah audit mutasi sukses kedua.

| Aksi | Syarat permission dan validasi approved |
| --- | --- |
| Retire | Permission retire; alasan wajib; ditolak bila unit on_loan; terminal; histori dipertahankan, tanpa hard delete |
| Recovery / found | Permission recovery; alasan wajib; sumber operational_status=lost saja; target maintenance; tidak langsung active/available; outcome lost historis tidak diubah |
| Loss | Permission loss; alasan wajib; loss saat dipinjam melalui workflow resolusi khusus yang mengubah outcome loan-unit dan operational_status unit secara atomik; bukan edit stok bebas |
| Void | Permission khusus void; alasan dan attestasi bahwa transaksi salah input serta barang belum diserahkan; seluruh loan-unit masih on_loan, belum retur/loss; all-or-nothing; tidak menghapus histori |

Nama permission, granularitas loss unit bebas versus loss pinjaman, dan grant per role/user adalah kontrak integrasi pending, bukan alasan menambah approval workflow. Approval desain/schema/cutover adalah gate proyek terpisah, bukan approval petugas untuk empat aksi di atas. Batas scoped approval desain dan gate schema berikutnya tercatat pada EQUIPMENT_DESIGN_APPROVAL_v3.1.md.

Jenis peminjam: student, lecturer, staff. Tipe external dapat dipertimbangkan kemudian, tetapi tidak boleh dipilih saat rilis pertama. Peminjam tidak wajib memiliki akun WPiTV.
3. Definisi domain dan konvensi
Istilah	Makna final
Kategori	Kelompok fungsional alat; tanpa stok.
Barang induk / model	Satu jenis alat yang muncul sebagai satu kartu katalog.
Unit fisik	Satu alat individual dengan ID dan label aset unik.
Kondisi fisik	good, minor_damage, major_damage.
Status operasional	active, maintenance, lost, retired.
Sedang dipinjam	Ada baris equipment_loan_units.outcome = on_loan. Bukan kolom pada unit.
Tersedia	Model aktif, unit aktif, kondisi good, tidak sedang dipinjam.
Loan	Satu transaksi serah-terima dari petugas kepada satu peminjam.
Loan unit	Satu unit fisik pada satu transaksi. Tidak ada quantity agregat di tabel ini.
Return / receipt	Satu batch serah-terima kembali, dapat mencakup sebagian unit loan.
Audit	Event immutable dengan actor, waktu UTC, objek, perubahan, dan alasan.


Waktu: DATETIME(6) disimpan dalam UTC. DATE untuk tenggat disimpan sebagai tanggal kalender WIB (Asia/Jakarta); konversi dilakukan di Laravel. Pinjaman jatuh tempo pada akhir tanggal due_date WIB dan mulai terlambat ketika tanggal WIB hari ini lebih besar daripada due_date.
4. Alur operasional utama
4.1 Buat kategori
Petugas mengisi nama, deskripsi/batas cakupan, urutan, dan status aktif. Kategori mempunyai version INT UNSIGNED NOT NULL DEFAULT 1; update wajib expected_version cocok dan menaikkan version tepat sekali setelah update berhasil. Nama unik secara normalisasi yang disepakati aplikasi (trim dan case-insensitive sesuai collation). Kategori yang memiliki histori tidak dihapus fisik. Kategori nonaktif tidak boleh dipilih untuk barang baru, tetapi barang lama tetap tampil.
4.2 Buat barang dan stok awal
Form berurutan: nama, merek, model, kategori, kode otomatis, jumlah stok awal, kondisi awal, aktif/nonaktif, foto katalog, catatan. Kode ditampilkan sebagai pratinjau; nilai resmi hanya diterbitkan pada saat penyimpanan server.

Satu transaksi database membuat barang dan N unit fisik (N minimal 1). Jika pembuatan unit gagal, pembuatan barang ikut rollback. Foto katalog opsional dan diletakkan di bawah form, dengan preview dan verifikasi privat. Berkas berada di area staging sampai dapat dipastikan aman; kegagalan penyimpanan harus dibersihkan/reconciled. Barang tidak dapat dibuat sebagai entri separuh selesai.

Kode: model_code misalnya SON-A6400; asset_code unit SON-A6400-001, SON-A6400-002. Kode unik, immutable. Jika slug berbenturan, server memakai mekanisme suffix unik yang stabil, bukan COUNT(*). next_unit_no dilock, hanya naik, tidak pernah reuse, termasuk setelah retire. Nama/merek/model yang diedit tidak mengubah kode.
4.3 Manajemen unit dan stok
- Tambah stok: membuat N baris equipment_units, bukan mengubah angka counter stok.
- Periksa kondisi: ubah physical_condition dengan alasan dan audit.
- Perawatan: active → maintenance → active setelah diperiksa dan dinyatakan good.
- Hilang/ditemukan: unit hilang masuk lost; ketika ditemukan masuk maintenance untuk pemeriksaan sebelum active.
- Retire: status akhir retired, wajib alasan; tidak boleh saat masih dipinjam.
- Tidak ada hard delete unit pada rilis pertama, termasuk saat salah input. Unit salah input di-retire dan nomor aset tidak boleh dipakai ulang.
- Kondisi fisik, status operasional, dan retire tidak dapat diubah melalui manajemen stok selama unit on_loan; kasus hilang saat dipinjam menggunakan alur penyelesaian kehilangan khusus.
Status operasional ≠ sedang dipinjam. Tidak ada kolom on_loan pada equipment_units dan tidak ada kolom stok mutable pada equipment_models.
4.4 Peminjaman POS
Petugas mengisi tipe peminjam, nama, identitas, prodi/unit organisasi, WhatsApp, keperluan, waktu pinjam tercatat otomatis, dan tanggal tenggat WIB. Petugas memilih beberapa barang serta quantity. Alokasi unit otomatis default, pilihan manual opsional.

Pratinjau unit hanya indikatif. Saat final submit, service melakukan transaksi database, mengunci baris model/unit dalam urutan deterministik, mengecek stok ulang, mengalokasikan N unit, membuat satu loan header, N baris loan_units, audit, dan idempotency record. Kegagalan satu unit membuat seluruh checkout rollback. Unique index pada generated open_unit_id merupakan penjaga terakhir terhadap double-loan.

Tidak ada harga, pembayaran, maupun tabel cart persisten. Barang yang sudah diserahkan harus diselesaikan melalui retur atau kehilangan, bukan void biasa.
4.5 Perpanjangan tenggat
Hanya jika masih ada unit on_loan, oleh petugas berwenang dan wajib alasan. original_due_date tetap. due_date hanya dapat bergeser ke tanggal yang lebih akhir dari tenggat saat ini; perubahan tercatat sebagai audit loan.extended, memuat nilai sebelum/sesudah. Perpanjangan tidak mengubah snapshot keterlambatan unit yang sudah selesai sebelumnya.
4.6 Pengembalian per unit (foto wajib)
Petugas memilih pinjaman, memilih satu atau beberapa unit yang masih on_loan, memilih kondisi balik per unit, melampirkan minimal satu foto privat untuk SETIAP unit, dan mengisi catatan bila diperlukan. Satu tindakan menghasilkan satu equipment_returns dan memperbarui loan_units terpilih menjadi returned, mengisi return_id, kondisi, due_date_at_resolution, dan is_late.

returned_at dan resolution_at berasal dari waktu server tepercaya; backdating retur/loss ditolak pada v1. Foto disimpan di equipment_return_photos dengan return_id serta loan_unit_id keduanya NOT NULL. Foto umum tanpa unit dilarang. Database composite FK memastikan foto hanya menunjuk unit yang dikembalikan pada receipt tersebut. Aplikasi memastikan jumlah foto minimal satu sebelum COMMIT. Retur gagal tidak boleh meninggalkan receipt parsial. File privat dan DB tidak berbagi transaksi atomik; gunakan staging, verifikasi checksum, cleanup gagal, dan rekonsiliasi orphan.

Unit minor_damage/`major_damage` tidak otomatis masuk maintenance, tetapi langsung tidak layak dipinjam. Petugas memulai perawatan sebagai aksi terpisah. Jika masih ada unit on_loan, peminjaman belum selesai.
4.7 Unit hilang dan ditemukan
Saat unit hilang dalam pinjaman, petugas berwenang mengubah outcome unit menjadi lost dan status operasional unit menjadi lost dalam satu transaksi, dengan waktu server tepercaya dan alasan; tanggal/waktu kejadian tidak dapat di-backdate oleh browser. Unit tersebut tidak lagi dihitung on_loan, tetapi histori lost tidak boleh diubah kemudian. Jika ditemukan, unit pindah dari lost ke maintenance lalu hasil pemeriksaan menentukan kembali aktif atau retired. Event unit.found menyimpan riwayat pemulihan; histori outcome pinjaman tetap lost.
4.8 Pembatalan salah input (void)
voided hanya tersedia untuk koreksi administratif oleh pengguna dengan permission khusus void dan pernyataan eksplisit bahwa barang belum benar-benar diserahkan. Seluruh unit harus masih on_loan, belum ada retur atau kehilangan. Seluruh loan_units berubah menjadi voided dalam satu transaksi, disertai alasan dan audit. Header tidak menyimpan status duplikat; tampilan pinjaman menjadi voided jika seluruh unit voided. Jika serah-terima benar-benar terjadi, gunakan retur atau loss. Sistem tidak dapat membuktikan serah-terima fisik secara otomatis; kontrol ini bergantung pada otorisasi dan audit.
4.9 Laporan peminjaman
Laporan Equipment terpisah dari Laporan Bulanan WPiTV. Default periode memilih borrowed_at menurut tanggal WIB; basis alternatif return memilih equipment_returns.returned_at, dan basis loss memilih equipment_loan_units.resolution_at dengan outcome lost. Filter peminjam, kategori/model historis saat checkout, progres, keterlambatan, dan outcome unit tersedia. Filter retur dan loss tidak diam-diam digabung sebagai AND; basis periode harus eksplisit. Tampilkan total loan, unit dipinjam, kembali, on_loan, lost, voided, keterlambatan, kerusakan saat retur, dan rincian receipt. Ekspor CSV dan halaman print memakai service yang sama, dengan mitigasi CSV formula injection.
5. Definisi status dan rumus stok
5.1 Kelayakan meminjam
Unit eligible jika model is_active = true, unit operational_status = active, kondisi good, dan tidak ada loan_unit ber-outcome on_loan. Kategori nonaktif tidak otomatis mengunci barang existing; mengubah status model aktif menjadi nonaktif saat memiliki loan aktif diblokir dalam versi pertama.
5.2 Ringkasan stok eksklusif
Urutan	Kelompok	Ketentuan
1	Retired	Unit operational_status = retired
2	Lost	Unit operational_status = lost
3	On loan	Ada loan_unit outcome = on_loan
4	Maintenance	Unit operational_status = maintenance
5	Damaged	Unit active tetapi physical_condition bukan good
6	Model inactive	Unit baik dan active, tetapi barang induk nonaktif
7	Available	Seluruh syarat eligible terpenuhi


Invarian: total_units = retired + lost + on_loan + maintenance + damaged + model_inactive + available. Kondisi fisik dapat menjadi dimensi laporan tambahan tanpa ditambahkan lagi ke total eksklusif. Jika unit lost/retired masih punya pinjaman on_loan, sistem wajib menandai integritas gagal, bukan menutupinya dengan prioritas statistik.
5.3 Status progres pinjaman
- active: seluruh unit masih on_loan.
- partially_resolved: ada unit returned atau lost, masih ada unit on_loan.
- closed: tidak ada unit on_loan, ada sekurangnya satu returned atau lost, dan tidak ada voided.
- voided: seluruh unit ber-outcome voided.
- Overdue: atribut waktu terpisah, bernilai benar untuk loan dengan unit on_loan dan tanggal laporan WIB melebihi due_date.
Semua unit dalam suatu loan wajib mengikuti aturan pembatalan all-or-nothing, sehingga outcome voided tidak bercampur dengan returned/`lost`. Tidak ada kolom status pada header yang berpotensi tidak sinkron.
5.4 Keterlambatan historis
- Loan terbuka: overdue dihitung terhadap tanggal WIB saat laporan dibuat (as_of_date).
- Unit returned: is_late dibekukan berdasarkan tanggal WIB returns.returned_at dibandingkan loan_units.due_date_at_resolution.
- Unit lost: is_late dibekukan berdasarkan tanggal WIB unit dinyatakan hilang dibanding snapshot tenggat pada saatnya.
- Unit voided: tidak dihitung sebagai pengembalian maupun kehilangan dan is_late kosong.
- Perpanjangan setelah retur sebagian tidak mengubah is_late unit yang sudah selesai.
6. Kontrak data inti (11 tabel)
Tipe BIGINT UNSIGNED digunakan untuk ID Equipment, INT UNSIGNED untuk FK ke users.id yang sudah ada. Semuanya berindeks sesuai kebutuhan relasi dan filter. Seluruh DATETIME(6) menggunakan UTC. Lihat ERD DBML untuk atribut lengkap, indeks, dan relasi.
Tabel	Sumber kebenaran	Relasi utama
equipment_categories	Nama, deskripsi, urutan, keaktifan kategori	1:N models
equipment_models	Identitas barang, model_code, next_unit_no, status	N:1 categories; 1:N units/photos
equipment_units	Identitas unit, asset_code, kondisi dan status operasional	N:1 models; 1:N loan_units/unit_photos
equipment_model_photos	Foto katalog per revisi, penyimpanan privat	N:1 models
equipment_unit_photos	Foto referensi/inspeksi/kerusakan unit (opsional)	N:1 units
equipment_loans	Identitas peminjam, tujuan, borrowed_at, DATE tenggat	1:N loan_units/returns
equipment_loan_units	Satu unit dipinjam, outcome dan kondisi ketika kembali	N:1 loans/units; return_id opsional
equipment_returns	Satu batch retur, receipt_code dan timestamp UTC	N:1 loans; 1:N loan_units
equipment_return_photos	Foto wajib per unit retur	N:1 returns dan N:1 loan_units
equipment_operation_keys	Idempotency token, actor dan hash payload	logical operation result
equipment_audit_log	Audit append-only dengan actor snapshot dan JSON diff	subject_type/id + optional FK loan


6.1 Constraint penting
1. UNIQUE(model_code), UNIQUE(asset_code), UNIQUE(model_id, unit_no).
2. open_unit_id adalah generated STORED yang bernilai unit_id hanya untuk outcome on_loan, dan diberi UNIQUE.
3. UNIQUE(loan_id, unit_id) menghindari satu unit tercatat dua kali dalam satu loan.
4. Composite FK (equipment_loan_units.return_id, loan_id) → (equipment_returns.id, loan_id) memastikan return milik loan yang sama.
5. Composite FK (equipment_return_photos.loan_unit_id, return_id) → (equipment_loan_units.id, return_id) memastikan foto milik unit pada batch retur yang sama.
6. CHECK outcome harus menetapkan kolom yang wajib dan yang harus NULL untuk on_loan, returned, lost, voided.
7. CHECK unit lost/retired harus mempunyai closed_at dan closed_reason.
8. UNIQUE(token) menjamin replay idempotency; actor, operasi, dan payload hash harus diverifikasi server.
9. Invarian minimal satu foto per returned unit tidak bisa dijaga hanya dengan FK biasa. Wajib diverifikasi di service dalam transaksi dan diuji.
10. Audit subject_type + subject_id adalah referensi logis, bukan FK polimorfik otomatis. Karena tidak ada hard delete pada entitas Equipment, data audit dapat diverifikasi melalui service dan audit integrity test.
6.2 Keputusan desain yang disengaja
- Tidak ada tabel pivot inventory_unit_members.
- Tidak ada tabel return items terpisah karena outcome/condition/return_id dicatat pada loan_units.
- Tidak ada foto umum per receipt; setiap foto harus menunjuk loan unit spesifik.
- Tidak ada kolom lokasi penyimpanan pada schema clean.
- Tidak ada VIEW; stock, progress dan report dihasilkan dari service/query scope terpusat.
- Tidak ada status on_loan pada unit fisik, tidak ada kolom stok agregat mutable.
- Tidak ada hard delete unit, model atau transaksi selama rilis pertama.
7. Layar dan UX
Layar	Komponen minimum	Validasi penting
Kategori	Tabel, cari/filter, form, aktif/nonaktif	Nama unik, kategori referensial aman
Katalog	Satu kartu per model, foto, kode, ringkasan stok, filter	Jangan tampilkan satu kartu per unit
Form barang	Urutan form disepakati, stok awal N, foto bawah dengan preview	N >= 1; kode final di server
Detail barang	Header, stok, tab daftar unit, kondisi, audit, foto	Tidak ada mutasi stok agregat
Kelola unit	Tambah unit, periksa, maintenance, lost/found, retire	Tolak perubahan selama on_loan kecuali loss resolution
POS checkout	Identitas, pilih model/qty, auto/manual allocation, review	Revalidate/lock saat submit, no oversell
Detail pinjaman	Identitas dan tenggat, unit, progress, perpanjangan	Status dihitung, due_date WIB
Form retur	Pilih unit, kondisi per unit, foto per unit, receipt	Minimal 1 foto per unit, partial allowed
Laporan	KPI, filter, tabel, CSV dan print	Period WIB, no double count


Tampilan mengikuti Mazer dan Light/Dark Theme branch rebuild. Form responsif dua kolom pada desktop dan satu kolom pada mobile. Tidak perlu style berlebihan; prioritaskan alur kerja petugas.
8. Non-functional requirements
- Keamanan: server-side policy, RBAC, IDOR, CSRF, prepared queries, private media authorization, MIME/size/hash validation, CSV formula injection prevention, no public photo storage.
- Konsistensi: transaksi DB atomik untuk checkout/return/bulk create; generated UNIQUE untuk active loan; deadlock retry terbatas, canonical lock ordering, optimistic version untuk perubahan admin.
- Idempotency: satu token + actor + payload yang sama menghasilkan respons transaksi yang sama; token sama dengan payload/actor berbeda ditolak.
- Audit: setiap mutasi penting memiliki event append-only, actor snapshot, waktu UTC, alasan, before/after; tidak mengubah event lama menjadi generic updated.
- Kinerja: index untuk model/status/unit/outcome/loan/due_date; hindari N+1 dan agregasi berat berulang pada katalog; pagination.
- Storage: staging foto, checksum, validasi authorization, cleanup orphan berkala; dokumentasi pemulihan dan backup.
- Usability: error di field, empty/loading state, navigasi konsisten, keyboard friendly, responsive, light/dark.
- Testing: clone-only mutative tests, race dua koneksi, rollback sengaja, snapshot historis, restore drill, browser smoke dan regresi non-Equipment.
9. Acceptance criteria dan skenario QA
ID	Kriteria lulus
AC-01	Buat model dengan 5 unit gagal di unit ke-3 → tak ada model/unit yang committed.
AC-02	Dua petugas meminjam unit terakhir → tepat satu checkout berhasil.
AC-03	Klik ganda token dan payload sama → satu transaksi; token sama dengan payload beda → ditolak.
AC-04	Pinjam 3 unit, retur 1 + foto → 2 unit tetap on_loan.
AC-05	Retur tanpa foto untuk satu unit → seluruh batch ditolak, tanpa receipt parsial.
AC-06	Foto unit A tidak boleh dikaitkan ke return milik loan/unit B.
AC-07	Unit kembali minor_damage → tidak tersedia, dan tidak otomatis maintenance.
AC-08	Tenggat 25 Nov WIB, kembali 26 Nov 00:01 WIB → late.
AC-09	Retur 1 unit sebelum perpanjangan; perpanjang; retur unit lain → is_late lama tetap.
AC-10	Lost saat pinjam, lalu found → hasil pinjaman tetap lost; unit masuk maintenance.
AC-11	Retire unit sedang on_loan ditolak; retire unit bebas tercatat audit; tidak dihapus fisik.
AC-12	Void wajib permission khusus, belum ada retur/loss, dengan alasan dan attestasi non-handover; tanpa approval workflow, tidak menghapus histori.
AC-13	Jumlah 7 kelompok stok eksklusif selalu sama dengan total unit.
AC-14	Laporan dengan filter 00:30 WIB masuk hari WIB yang benar.
AC-15	Route/aksi Equipment menolak akun tanpa permission yang sesuai, termasuk percobaan Admin News/TVClub; nama role bukan bypass. Grant per role/user ditetapkan terpisah, bukan diberikan oleh dokumen ini.
AC-16	Live Streaming dan Laporan Bulanan tetap lulus regresi, tidak berubah di schema baru.
AC-17	Tidak ada Equipment runtime lama, VIEW, tabel stock counter, atau table laporan snapshot baru.
AC-18	Perubahan dijalankan pada clone yang diotorisasi, bukan wpitv atau wpitv_testing.


10. Strategi Full Rebuild dan histori
Baseline pengembangan: branch rebuild/wpitv-clean (Live Streaming dan Laporan Bulanan sudah terintegrasi). Branch main stabil; branch archive hanya referensi kode lama. Clone yang pernah diverifikasi wpitv_rebuild_testing berisi core + Live/Monthly, bukan Equipment clean.

Implementasi baru: rancang dan bangun 11 tabel pada database clone yang terisolasi, migrasi Laravel baru yang hanya berlaku pada clone, domain service baru tanpa compatibility runtime Equipment lama, UI baru, dan suite independen. Verifikasi konsistensi sebelum membahas cutover.

Histori lama: data Equipment di wpitv/`wpitv_testing` beserta foto,  pinjaman aktif, audit, dan ledger tidak boleh diubah saat tahap desain/rebuild. Pilihan cutover harus dibahas terpisah: (A) migrasi histori terverifikasi dengan mapping dan audit, atau (B) arsip read-only dengan awal transaksi baru. Tidak boleh menghapus database lama hanya demi schema clean. Backup terbaru, media, dan restore drill adalah prasyarat mutasi.

Release gate: audit desain → persetujuan schema → clone provisioning → backend → UI → regression/UAT → data cutover dengan approval terpisah. Tidak ada commit/push/deploy otomatis.
11. Keputusan yang terkunci dan asumsi untuk konfirmasi
Dipilih pengguna: (1) tenggat DATE sampai akhir WIB, (2) wajib foto minimal satu per setiap unit returned.

Baseline rancangan yang masih bisa direview sebelum implementasi: (1) peminjam mahasiswa/dosen/staf, eksternal ditunda; (2) kerusakan ringan tidak eligible; (3) void administratif terbatas sebelum serah-terima sesungguhnya; (4) retire tanpa hard delete; (5) tidak ada VIEW; (6) 11 tabel, termasuk foto unit terpisah; (7) foto katalog opsional, stok awal N minimal satu; (8) perpanjangan hanya maju dan dengan alasan.

Semua asumsi tersebut harus disahkan pada gate final desain. Berkas DBML adalah representasi logis, bukan migration SQL executable, dan belum tervalidasi pada server MySQL.

# 12. Kontrak rekonsiliasi v3.1 — Phase 6C.4L.1B

Status dokumen: DESIGN ONLY, calon persetujuan desain. Bab 1–11 tetap menjadi dasar v3; bab 12 merinci koreksi/kontrak v3.1. Tidak memberikan izin migration, DDL/DML, implementasi, reset, cutover, commit/push/deploy.

## 12.1 Label keputusan

- **agreed**: dinyatakan eksplisit oleh pengguna pada instruksi v3.1 (termasuk DATE WIB/foto per unit dari v3). Ini persetujuan kontrak rancangan, bukan persetujuan eksekusi.
- **recommended**: pilihan teknis yang diajukan untuk disahkan pada design gate; bukan keputusan pengguna yang diasumsikan.
- **needs approval**: keputusan lingkungan, kebijakan atau cutover yang belum diotorisasi. Jika belum disahkan, implementasi bagian terkait ditahan.
- Seluruh persyaratan warisan v3 yang tidak disebut eksplisit dalam instruksi rekonsiliasi berstatus **recommended** sampai baseline v3.1 disetujui. Dua ketentuan terkunci DATE akhir WIB dan minimal satu foto per unit returned tetap **agreed**. Label ini berlaku untuk setiap ketentuan warisan, bukan klaim semua asumsi sudah disetujui.

## 12.2 Cutover dan perlindungan histori

**agreed**: nama final 11 tabel tetap untuk clone bersih tanpa Equipment legacy. equipment_loans dan equipment_return_photos bertabrakan dengan legacy pada sumber, sehingga migration clone tidak boleh diterapkan begitu saja pada wpitv/wpitv_testing (nama kedua: wpitv_testing). Tidak ada mass RENAME TABLE, DROP, migration sumber, penghapusan media/histori/ledger, atau reset data pada fase ini.

**recommended — mapping manifest**: ledger mapping privat, berversi dan immutable, mencatat source instance/database/table/PK, target database/table/PK, kode lama/baru, snapshot asli dan hasil transformasi, hash baris/media, alasan transformasi, status resolved/unmatched. Nomor ID lintas database tidak dianggap identik. Tidak menambah tabel runtime ke 11 tabel target; manifest migrasi adalah artefak operator eksternal. Mapping loan lama/loan-unit/retur/foto/audit wajib ditelusuri dua arah; ketidakcocokan memblokir cutover. Histori lama dengan timestamp yang sah dipertahankan melalui importer yang disetujui; larangan backdating v1 berlaku pada transaksi operasional baru, bukan izin menulis ulang timestamp histori.

**needs approval — alternatif A, migrasi penuh**: sumber dibekukan; ekspor histori dengan private media; transformasi deterministik pada clone; pertahankan snapshot dan kode dengan kebijakan konflik eksplisit. Loan lama yang masih aktif dipetakan beserta unit fisik, borrower snapshot, due-date dan bukti kewajibannya, lalu diselesaikan hanya melalui runtime baru setelah switch. Tenggat legacy bertipe timestamp tidak otomatis dibulatkan ke DATE WIB; aturan konversi dan perubahan implikasi terlambat harus disetujui per mapping. Histori loss legacy yang belum dianggap selesai juga tidak otomatis diubah menjadi resolved tanpa rekonsiliasi.

**needs approval — alternatif B, arsip read-only**: transaksi selesai tetap di arsip berpolicy/authorized-media terpisah tanpa compatibility runtime baru. Untuk loan lama aktif: pilih (B1) selesaikan seluruhnya melalui sistem lama dalam window transisi terjadwal, lalu freeze dan baru aktifkan runtime baru; atau (B2) lakukan transfer kewajiban aktif yang diverifikasi dengan manifest dan identitas transaksi lintas arsip, tanpa mencatat checkout sukses dua kali. B2 membutuhkan desain/mapping dan persetujuan sendiri. Dilarang membuka transaksi baru untuk unit yang masih mempunyai kewajiban lama, menutupnya dengan void palsu, atau menganggap kewajiban hilang karena memilih arsip. Tidak boleh ada dua writer terhadap unit/kewajiban yang sama.

**recommended — urutan operasional untuk persetujuan terpisah**:
1. Inventarisasi ulang source/target, writer, pinjaman aktif, seluruh FK, ledger dan media; rekam baseline/fingerprint aktual, jangan memakai hitungan historis sebagai fakta baru.
2. Hentikan writer saat snapshot final; backup seluruh database dan media privat + ledger, hash/manifest; restore ke instance terisolasi dan verifikasi schema, jumlah, hash dan FK sebelum cutover.
3. Uji import dua kali pada clone; nol unmatched/orphan, semua snapshot/media/kewajiban aktif cocok, tanpa perubahan sumber. Uji otorisasi histori dan media arsip.
4. Sajikan rencana A/B, mapping termasuk unit aktif, target topologi/database routing, namespace konflik, downtime, acceptance dan recovery kepada pengguna. **needs approval**: approval cutover, provisioning/clone khusus, lokasi/retensi backup/arsip, dan keputusan reset/destruksi semuanya terpisah.
5. Switch hanya setelah approval; route/read/write memakai satu arsitektur. Nama sama dipisahkan oleh database target/core yang telah disiapkan dan diverifikasi, bukan mass rename sumber. Apakah mengganti koneksi aplikasi penuh atau koneksi domain Equipment khusus harus diputuskan setelah audit FK users/otorisasi dan konsistensi core; jangan menduplikasi users tanpa desain.
6. Tahan writer bila verifikasi gagal. Recovery sebelum write baru dapat membatalkan routing; setelah write baru harus merekonsiliasi delta sebelum rollback agar transaksi tidak hilang. Restore ke sumber hanya dengan approval recovery terpisah; DDL MySQL tidak diasumsikan dapat dirollback atomik. Sumber dan backup tetap disimpan.

## 12.3 Locking kategori dan unit

**agreed**: equipment_categories.version INT UNSIGNED NOT NULL DEFAULT 1. Setiap update/aktif-nonaktif membawa expected_version; versi salah ditolak tanpa perubahan atau audit sukses. Update berhasil menaikkan version satu kali dalam transaksi. Perubahan kategori tidak mengubah ID/kode unit atau snapshot historis. Replay bukan update baru dan tidak menaikkan versi.

**agreed**: manajemen stok tidak boleh mengubah physical_condition, operational_status atau retire ketika terdapat loan_unit.outcome=on_loan. Return dan loss adalah pengecualian melalui workflow khusus yang atomik, bukan lewat form pengelolaan unit. expected_version tidak menggantikan lock model/unit/header/detail yang relevan serta revalidasi eligibility. Kondisi checkout dan snapshot tetap immutable.

**recommended**: version pada kategori/model/unit/loan wajib >=1. Update status/kondisi yang berhasil menaikkan versi pemiliknya; create/rollback tidak menghasilkan versi tambahan. Operasi foto model mengikuti lock model ketika mengalokasikan revision. Hanya stale write yang ditolak; rename label tidak merombak code.

**recommended — lock ordering calon**: klaim token operasi unik terlebih dahulu; lock loan header bila operasi pada loan existing, lalu categories yang relevan (urut ID), models (urut ID), units (urut ID), loan_units (urut ID). Selesaikan seluruh validasi sebelum mutasi; jangan kemudian mengambil lock pada kelas baris yang lebih awal. Operasi master tidak mengunci loan header; pemeriksaan kewajiban dilakukan setelah lock model/unit yang juga dihormati checkout. Ini memerlukan pengujian dua koneksi untuk deactivate-vs-checkout, condition-vs-checkout, return-vs-loss, extension-vs-return, create-category-vs-model dan numbering. **needs approval**: finalisasi urutan setelah evaluasi query nyata pada clone; tidak menjalankan query/DDL sekarang.

## 12.4 Idempotency: satu sumber hasil operasi

**agreed**: equipment_operation_keys tetap berisi token, operation, actor_key, payload_hash, result_type, result_id, created_at, completed_at; tanpa kolom status redundan. token UUID unik dengan collation binary-safe. actor/operation/hash immutable. Canonical payload menyertakan format/schema request, ID, jumlah, expected_version, alasan/attestasi, pilihan unit serta checksum manifest upload yang telah diverifikasi server. Timestamp server/result ID yang belum ada tidak dimasukkan sebagai input hash. Urutkan set unit, normalisasi string/null/telepon/tanggal dengan aturan tetap; perbedaan urutan set yang setara bukan payload berbeda.

**agreed**: insert token pending dan seluruh mutasi bisnis/audit berada dalam SATU transaksi database; pending hanya row uncommitted yang dimiliki transaksi tersebut. Jangan commit token sebelum operasi bisnis atau menyimpan status pending permanen sebagai antrean. Gagal, deadlock retry atau rollback tidak meninggalkan row token committed. completed_at/result_type/result_id sukses diisi bersamaan dan committed dengan hasil; completed_at >= created_at. Minimal DB CHECK yang direkomendasikan: ketiganya semua NULL atau semuanya NOT NULL; CHECK ini tidak dapat mencegah COMMIT row pending, sehingga service/unit-of-work dan fault-injection test wajib menegakkannya. DBML bukan DDL executable.

**agreed**: replay token dengan actor+operation+payload_hash sama mengembalikan hasil lama tanpa audit baru, bisnis baru atau kenaikan version. Actor berbeda, operation berbeda atau hash berbeda ditolak tanpa membocorkan hasil pemilik token. Otorisasi dan status akun diperiksa ulang sebelum memberi hasil replay. Replay diperiksa sebelum stale-version/lifecycle validation terhadap state terkini karena expected_version pada request asli sudah pernah berhasil.

**agreed**: race token sama diserialisasi UNIQUE token; pemenang melakukan satu commit. Request lain menunggu keputusan pemenang. Setelah duplicate-key, lakukan recovery read pada transaksi/snapshot baru di luar seluruh transaksi gagal, periksa actor+operation+hash, lalu replay atau conflict. Bila pemenang rollback, contender boleh memperoleh token dan mencoba operasi. Lock timeout/deadlock retry terbatas, tidak membuat token baru; kegagalan sementara memberi retryable error dengan token sama, bukan sukses palsu. File staging milik contender tidak boleh menghapus file yang sudah direferensikan pemenang.

**recommended — registry operasi dan hasil**:

| operation (<=32 chars) | result_type / result_id | Scope hasil |
| --- | --- | --- |
| category.create, category.update | category / category.id | kategori yang dibuat/diupdate |
| model.create, model.update, stock.add | model / model.id | model dan batch stok melalui audit terkait |
| unit.condition, unit.maintenance_start, unit.maintenance_finish, unit.lost, unit.found, unit.retire | unit / unit.id | unit terkait |
| loan.checkout, loan.extend, loan.void, loan.loss | loan / loan.id | transaksi/batch resolusi terkait |
| return.create | return / return.id | receipt batch |
| model.photo_add | model / model.id | riwayat foto pada model |
| unit.photo_add | unit / unit.id | riwayat foto pada unit |

**recommended**: operasi batch loss mengacu loan; stock.add mengacu model, tidak berpura-pura mempunyai satu ID unit. result_type/id adalah referensi logis allowlisted yang diperiksa service, bukan FK generik. Seluruh result entities tidak di-hard-delete. Jika jawaban perlu rincian batch, audit menyimpan daftar ID dan token operasi yang sama dalam JSON event, bukan tabel hasil baru. result fields pada token bukan snapshot state mutable.

**recommended — respons sukses/replay stabil** (contoh, bukan ID aktual):
`{"token":"<uuid>","operation":"return.create","result":{"type":"return","id":501},"completed_at":"2026-11-23T03:00:00.000000Z"}`
Request pertama dan replay mengembalikan envelope identik tersebut. Detail receipt dapat diambil melalui akses berpolicy; jangan memasukkan version/progress/label master terkini ke envelope replay lalu menyebutnya hasil lama. Error validasi tidak menghasilkan completed key. Tidak ada response body cache atau status tambahan yang diperlukan dalam 11 tabel.

## 12.5 Reporting historis dan timestamp tepercaya

**agreed**: period_basis=`borrowed` default: pilih cohort loan dari borrowed_at; basis=`returned`: pilih loan_units outcome returned melalui returns.returned_at; basis=`lost`: pilih loan_units outcome lost melalui resolution_at. Opsi returned/lost adalah pilihan basis periode tambahan yang eksplisit. Apabila kelak perlu menggabungkan beberapa rentang kejadian sekaligus, harus mendefinisikan scope terpisah; jangan menerapkan AND ambigu pada semua timestamp.

**agreed**: kategori/model histori memakai checkout, inventaris aktif memakai relasi saat ini. **recommended**: tambahkan category_id_at_checkout dan model_id_at_checkout BIGINT UNSIGNED NOT NULL pada loan_units dengan FK RESTRICT ke categories/models yang tidak boleh hard-delete, serta indeks (category_id_at_checkout, model_id_at_checkout, loan_id) dan (model_id_at_checkout, loan_id). Tipe sama dengan ID target. ID snapshot immutable dan diisi server bersama unit_snapshot; tidak ada input snapshot browser. Tidak memakai composite FK snapshot-category ke category_id model terkini: perpindahan kategori master tidak boleh membuat histori invalid. Nilai snapshot harus cocok dengan unit→model→category yang sudah dilock SAAT checkout. **recommended**: model_id unit tidak direparent pada v1; transfer fisik/model menjadi workflow terpisah bila dibutuhkan.

**agreed**: unit_snapshot menyimpan snapshot schema version, unit_id/asset_code/serial, model_id/model_code/name/brand/model_name, category_id/category_name, dan kondisi checkout. ID pada JSON konsisten dengan dua kolom snapshot dan unit_id; label laporan historis tidak diambil dari master terkini. FK ID snapshot menjaga keberadaan entitas, bukan label historis. Nomor identitas peminjam/WhatsApp tetap snapshot loan dan bukan user FK.

**agreed**: tidak ada backdating return/loss pada v1. Waktu kejadian dibuat dari jam server tepercaya setelah lock diperoleh dan sebelum penulisan; satu timestamp per batch. returns.returned_at kanonis untuk returned; resolution_at kanonis untuk lost/voided dan NULL untuk returned. Tidak mempercayai timestamp browser, EXIF, captured_at atau created_at sebagai waktu resolusi. Guard waktu minimal borrowed_at <= event_at; jika jam server melanggar urutan, tolak dan investigasi, bukan memalsukan timestamp. is_late dan due_date_at_resolution pada returned/lost dibekukan; extension tidak menulis ulang baris resolved. Koreksi historis masa depan memerlukan workflow/approval terpisah.

**agreed**: input periode tanggal WIB bersifat inklusif; ubah batas menjadi [awal 00:00 WIB, hari setelah akhir 00:00 WIB) lalu konversi ke UTC. Contoh 25 November 2026 adalah `[2026-11-24 17:00:00Z, 2026-11-25 17:00:00Z)`. Filter timestamp memakai rentang UTC, bukan DATE(timestamp UTC). due_date adalah DATE WIB; overdue hanya bila ada outcome on_loan dan as_of_date WIB > due_date. is_late event dihitung dari tanggal WIB event dibanding due_date_at_resolution; pada batas tepat 00:00 hari sesudahnya sudah late. as_of timestamp berasal server tepercaya dan diturunkan menjadi as_of_date WIB.

**recommended — grain KPI**: `audit_record_loans` adalah semua header yang masuk scope (termasuk void), bukan jumlah event audit_log; `voided_loans` subset seluruh unit voided; `successful_checkout_loans` = header valid nonvoided; `successful_checkout_units` = unit nonvoided. Receipt count = DISTINCT return_id, loan count = DISTINCT loan_id; jangan menggandakan keduanya akibat join foto. Outcome counts memakai DISTINCT loan_unit.id dan memisahkan on_loan/returned/lost/voided. `damaged_on_return` menghitung condition_in != good hanya pada returned; kondisi unit current tidak menulis ulang nilai ini. Untuk basis returned/lost, header/loan KPI memakai DISTINCT loan yang mempunyai unit kejadian cocok dan unit KPI hanya pada kejadian cocok; beri label 'loan dengan retur' atau 'loan dengan kehilangan', bukan mengklaim checkout terjadi dalam periode itu. Ringkasan cohort penuh, bila ditampilkan sebagai detail, diberi label terpisah dari KPI scope.

**recommended — waktu laporan v1**: as_of_date menentukan overdue pinjaman yang MASIH terbuka pada snapshot baca laporan saat itu. Outcome/progress lain memakai snapshot DB current; filter periode lama bukan rekonstruksi saldo historis 'as of'. UI v1 tidak menawarkan pilihan historical-as-of yang berpura-pura dapat direkonstruksi hanya dari state current. Untuk laporan konsisten, ambil satu read snapshot/as_of untuk seluruh KPI, CSV dan print menggunakan query service yang sama, termasuk filter authorization dan mitigasi CSV formula injection dari helper existing yang sudah direview. Rekonstruksi historical-as-of memerlukan desain event timeline dan approval terpisah.

Contoh **recommended**, fixture spesifikasi bukan data produksi:
- Loan A checkout 25 Nov WIB berisi 3 unit, due 25 Nov: unit 1 returned 25 Nov 23:59 good; due_date diperpanjang menjadi 27 Nov; unit 2 returned 28 Nov 00:01 minor_damage; unit 3 lost 28 Nov 00:02. Snapshot unit 1 tetap due 25 Nov/is_late=false; unit 2/3 due 27 Nov/is_late=true. Progress A closed; overdue=false karena tidak ada on_loan, meski dua resolusi late.
- Loan B checkout 25 Nov berisi 2 unit, seluruhnya voided sesuai attestasi. Cohort borrowed 25 Nov (setelah kejadian di atas) menghasilkan audit_record_loans=2, voided_loans=1, successful_checkout_loans=1, successful_checkout_units=3, returned=2, lost=1, on_loan=0, voided_units=2, damaged_on_return=1, late_resolved_units=2. Outcome counts seluruh record = 5; tiga unit sukses tidak ditambah voided menjadi stok atau sukses checkout.
- Basis returned 28 Nov memilih hanya A unit 2: loan dengan retur=1, returned=1, damaged_on_return=1, late_resolved_units=1; unit 1 dan unit 3 tidak dimasukkan ke KPI returned. Basis lost 28 Nov memilih hanya A unit 3.
- Rename atau pindah kategori master sesudah checkout tidak mengubah label/ID filter historis A; inventaris aktif menampilkan kategori current.

## 12.6 Outcome, progress dan CHECK

**agreed**: istilah mesin satu-satunya `active`, `partially_resolved`, `closed`, `voided`; label UI Indonesia dapat menerjemahkannya tanpa enum/status tersimpan baru. Tidak ada equipment_loans.status atau progress column. Overdue flag terpisah.

| Kondisi agregat (N>=1) | progress agreed |
| --- | --- |
| on_loan=N | active |
| 0<on_loan<N, ada returned/lost, voided=0 | partially_resolved |
| on_loan=0, returned+lost=N, voided=0 | closed |
| voided=N | voided |
| N=0; voided bercampur outcome lain; outcome tidak dikenal | invalid integrity, bukan status bisnis kelima |

**agreed**: void adalah all-or-nothing seluruh unit masih on_loan, belum ada returned/lost, pengguna berpermission khusus void, alasan dan attestasi non-handover tersimpan pada audit. Larangan mencampur voided berlaku JUGA dengan on_loan. Row CHECK tidak dapat menjamin aturan lintas baris ini; lock header dan validasi keseluruhan loan wajib. Loan kosong/receipt kosong ditolak service, bukan ditampilkan active/closed.

**agreed — row nullability**:
- on_loan: return_id, condition_in, due_date_at_resolution, is_late, resolution_at, resolution_notes semuanya NULL.
- returned: return_id, condition_in, due_date_at_resolution, is_late wajib; resolution_at NULL; resolution_notes opsional. event_at berasal returns.returned_at.
- lost: return_id/condition_in NULL; due_date_at_resolution/is_late/resolution_at wajib; resolution_notes wajib nonblank.
- voided: return_id/condition_in/due_date_at_resolution/is_late NULL; resolution_at wajib dan resolution_notes nonblank.

**agreed**: per-unit evidence tidak dapat diganti foto receipt umum. Composite FK return→loan dan foto→loan_unit/receipt tetap. Minimal satu foto valid, nonempty receipt, nonempty loan, unit eligibility, no on_loan ketika lost/retired dan append-only audit adalah invariant service/cross-row; jangan menyatakan telah dijamin DBML/row CHECK.

**recommended**: ENUM/CHECK untuk condition/outcome/operational/photo_type/borrower_type; closed_at/closed_reason wajib nonblank untuk lost/retired dan keduanya NULL untuk active/maintenance. Saat found, clear closed_* pada unit current dalam transaksi dan simpan nilai sebelumnya di audit unit.found; loan outcome lost tidak berubah. Retired terminal, tidak melalui found/active lagi. model_code/asset_code/ID snapshot immutable; numbering monoton under lock; hash/MIME/size/revision divalidasi server sesuai kebijakan foto yang perlu disahkan.

## 12.7 Acceptance specifications tambahan (belum dieksekusi)

Semua skenario di bawah berstatus **agreed sebagai cakupan verifikasi yang diminta**; nilai teknis berlabel recommended tetap menunggu approval desain. Tidak ada test runtime/DDL dijalankan pada fase dokumentasi.

| ID | Given / When | Expected |
| --- | --- | --- |
| AC-19 | Clone clean dan source legacy nama sama | Hanya clone target diotorisasi; target source/wrong identity ditolak sebelum DDL; sumber tidak berubah |
| AC-20 | Mapping histori A/B dan loan aktif | Nol unit dengan writer/kewajiban ganda, mapping/foto/ID/late-conversion cocok; unmatched blokir switch; approval recovery wajib |
| AC-21 | Dua editor kategori version 4 | Satu update sukses ke 5; stale lain ditolak, nol audit/versi tambahan; replay sukses tetap versi 5 |
| AC-22 | Unit on_loan vs edit condition/status/retire | Semua management edit ditolak; return/loss workflow saja boleh menyelesaikan di bawah lock; race checkout vs edit tidak menghasilkan unit ineligible terpinjam |
| AC-23 | Fault setelah pending token/audit/foto metadata | Rollback seluruh bisnis/audit/key; tidak ada committed pending; staging tidak merusak foto pemenang |
| AC-24 | Dua proses token+actor+operation+payload sama; token konflik | Tepat satu hasil/audit; respons replay stabil; actor/operation/hash berbeda ditolak tanpa disclosure |
| AC-25 | original result menjadi closed/renamed setelah commit | Replay mengembalikan referensi hasil/envelope lama, bukan mutasi baru atau label/version current yang dianggap snapshot |
| AC-26 | Checkout lalu rename/pindah kategori model | ID snapshot dan label lama tetap pada filter/CSV/print historis; current inventory memakai master current |
| AC-27 | Event 25 Nov 00:30 WIB dan filter 25 Nov | Masuk lewat UTC 24 Nov 17:30; batas akhir eksklusif dan DATE WIB/late tepat pada pergantian hari |
| AC-28 | Request retur/loss membawa waktu lampau/future | Timestamp browser tidak dipakai dan backdate override ditolak; service memakai satu waktu server batch; original snapshot tidak direwrite |
| AC-29 | Contoh A/B pada 12.5 dan join dua foto per returned unit | KPI sesuai contoh, tidak double-count unit/loan/receipt; void bukan checkout sukses; basis tanggal berbeda diberi label tepat |
| AC-30 | Empty loan atau campuran void+returned/lost/on_loan | Integrity failure, bukan closed/active; void seluruh loan atomik dan policy/attestasi wajib |
| AC-31 | Return photo unit dari receipt/loan berbeda atau satu unit tanpa foto | FK/scope menolak cross receipt; missing photo membatalkan seluruh batch, termasuk token dan audit |
| AC-32 | Desain users/category snapshot FK/type + operasi pending sukses | Tipe unsigned sesuai; users stub tidak dibuat; tuple op key semua NULL atau lengkap; committed pending diuji gagal di unit-of-work |
| AC-33 | Found unit lost, lalu maintenance→active good | closed_* current dibersihkan melalui audit; outcome loan lama tetap lost; total 7 kelompok stock konsisten tanpa double count |

## 12.8 Keputusan sebelum implementasi

**needs approval**: pengesahan baseline v3.1 dan semua rekomendasi teknis (FK/indeks snapshot, registry/envelope idempotency, lock order, KPI grain/read snapshot), nama/granularitas permission dan pemetaan grant ke role/user existing (kebijakan langsung found/recovery sudah approved; hak perubahan kategori perlu dipetakan), batas/MIME/dimensi/retensi foto dan aturan orphan cleanup, slug collision & kapasitas nomor, identitas clone/provisioning/runner/schema signature dan restore drill. Jalur kategori→model→unit, 11 tabel dan nol VIEW tetap; tidak ada kebutuhan tabel tambahan.

**needs approval, fase terpisah**: strategi A/B dan loan lama aktif, topologi routing/database, konversi tenggat/snapshot legacy, cutover/recovery, reset atau penghapusan data. Kebutuhan ini tidak menghalangi review desain clone, tetapi menahan rollout ke database existing. Guard test existing mem-pin 24 tabel; schema manifest untuk clone Equipment kelak harus diotorisasi baru tanpa melemahkan guard atau mengganti manifest baseline Live/Monthly secara diam-diam.


### 12.9 Audit actor dan invariant append-only

**recommended**: payload audit (event_type, subject, reason, before/after, actor_name dan waktu) append-only. FK actor_id/uploader/receiver ke users memakai SET NULL agar penghapusan akun historis tidak menghapus bukti; actor_name snapshot tetap. Pen-null-an FK oleh referential action bukan izin mengubah isi event melalui service. **needs approval**: jika kebijakan mengharuskan seluruh byte baris audit immutable bahkan saat user dihapus, pilih RESTRICT user deletion/soft-delete pada rancangan tersendiri; jangan mengaku SET NULL menjamin byte-level immutability. Uji actor-name preservation, otorisasi current dan provenance audit setelah account deletion di clone kelak.
