PRODUCT REQUIREMENTS DOCUMENT (PRD)
Equipment Management WPiTV | Full Rebuild v3.0
Status: Baseline rancangan untuk review, bukan persetujuan menjalankan migration.
Tanggal: 23 November 2026
Proyek: WPiTV Modernization, branch rebuild/wpitv-clean
Stack: Laravel 12, Livewire 4, Blade, Mazer, Bootstrap 5, MySQL 8.0.30
Artefak terkait: WPiTV_Equipment_ERD_v3.dbml, WPiTV_Equipment_REBUILD_CONTEXT.md.
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
Aktor	Kategori / Barang / Unit	Checkout dan Retur	Laporan Equipment
Super Admin	Kelola penuh, termasuk void dan retire	Kelola dan audit	Lihat / ekspor
Admin Production	Buat/edit, stok dan perawatan sesuai policy	Checkout, retur, perpanjangan, lapor hilang	Lihat / ekspor
Admin News	Tidak berwenang	Tidak berwenang	Tidak berwenang
TVClub	Tidak berwenang	Tidak berwenang	Tidak berwenang


Peran di atas menggunakan RBAC existing, bukan membuat tabel role baru. Penetapan izin per aksi harus diuji berdasarkan policy, bukan hanya menu yang terlihat.

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
Petugas mengisi nama, deskripsi/batas cakupan, urutan, dan status aktif. Nama unik secara normalisasi yang disepakati aplikasi (trim dan case-insensitive sesuai collation). Kategori yang memiliki histori tidak dihapus fisik. Kategori nonaktif tidak boleh dipilih untuk barang baru, tetapi barang lama tetap tampil.
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
- Status operasional tidak dapat diubah melalui manajemen stok selama unit on_loan; kasus hilang saat dipinjam menggunakan alur penyelesaian kehilangan khusus.
Status operasional ≠ sedang dipinjam. Tidak ada kolom on_loan pada equipment_units dan tidak ada kolom stok mutable pada equipment_models.
4.4 Peminjaman POS
Petugas mengisi tipe peminjam, nama, identitas, prodi/unit organisasi, WhatsApp, keperluan, waktu pinjam tercatat otomatis, dan tanggal tenggat WIB. Petugas memilih beberapa barang serta quantity. Alokasi unit otomatis default, pilihan manual opsional.

Pratinjau unit hanya indikatif. Saat final submit, service melakukan transaksi database, mengunci baris model/unit dalam urutan deterministik, mengecek stok ulang, mengalokasikan N unit, membuat satu loan header, N baris loan_units, audit, dan idempotency record. Kegagalan satu unit membuat seluruh checkout rollback. Unique index pada generated open_unit_id merupakan penjaga terakhir terhadap double-loan.

Tidak ada harga, pembayaran, maupun tabel cart persisten. Barang yang sudah diserahkan harus diselesaikan melalui retur atau kehilangan, bukan void biasa.
4.5 Perpanjangan tenggat
Hanya jika masih ada unit on_loan, oleh petugas berwenang dan wajib alasan. original_due_date tetap. due_date hanya dapat bergeser ke tanggal yang lebih akhir dari tenggat saat ini; perubahan tercatat sebagai audit loan.extended, memuat nilai sebelum/sesudah. Perpanjangan tidak mengubah snapshot keterlambatan unit yang sudah selesai sebelumnya.
4.6 Pengembalian per unit (foto wajib)
Petugas memilih pinjaman, memilih satu atau beberapa unit yang masih on_loan, memilih kondisi balik per unit, melampirkan minimal satu foto privat untuk SETIAP unit, dan mengisi catatan bila diperlukan. Satu tindakan menghasilkan satu equipment_returns dan memperbarui loan_units terpilih menjadi returned, mengisi return_id, kondisi, due_date_at_resolution, dan is_late.

Foto disimpan di equipment_return_photos dengan return_id serta loan_unit_id keduanya NOT NULL. Foto umum tanpa unit dilarang. Database composite FK memastikan foto hanya menunjuk unit yang dikembalikan pada receipt tersebut. Aplikasi memastikan jumlah foto minimal satu sebelum COMMIT. Retur gagal tidak boleh meninggalkan receipt parsial. File privat dan DB tidak berbagi transaksi atomik; gunakan staging, verifikasi checksum, cleanup gagal, dan rekonsiliasi orphan.

Unit minor_damage/`major_damage` tidak otomatis masuk maintenance, tetapi langsung tidak layak dipinjam. Petugas memulai perawatan sebagai aksi terpisah. Jika masih ada unit on_loan, peminjaman belum selesai.
4.7 Unit hilang dan ditemukan
Saat unit hilang dalam pinjaman, petugas berwenang mengubah outcome unit menjadi lost dan status operasional unit menjadi lost dalam satu transaksi, dengan tanggal dan alasan. Unit tersebut tidak lagi dihitung on_loan, tetapi histori lost tidak boleh diubah kemudian. Jika ditemukan, unit pindah dari lost ke maintenance lalu hasil pemeriksaan menentukan kembali aktif atau retired. Event unit.found menyimpan riwayat pemulihan; histori outcome pinjaman tetap lost.
4.8 Pembatalan salah input (void)
voided hanya tersedia untuk koreksi administratif oleh Super Admin dengan pernyataan eksplisit bahwa barang belum benar-benar diserahkan. Seluruh unit harus masih on_loan, belum ada retur atau kehilangan. Seluruh loan_units berubah menjadi voided dalam satu transaksi, disertai alasan dan audit. Header tidak menyimpan status duplikat; tampilan pinjaman menjadi Voided jika seluruh unit voided. Jika serah-terima benar-benar terjadi, gunakan retur atau loss. Sistem tidak dapat membuktikan serah-terima fisik secara otomatis; kontrol ini bergantung pada otorisasi dan audit.
4.9 Laporan peminjaman
Laporan Equipment terpisah dari Laporan Bulanan WPiTV. Filter periode (WIB), peminjam, kategori, model, status progres, keterlambatan, dan outcome unit. Tampilkan total loan, unit dipinjam, kembali, on_loan, lost, voided, keterlambatan, kerusakan saat retur, dan rincian receipt. Ekspor CSV dan halaman print memakai service yang sama, dengan mitigasi CSV formula injection.
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
- Active: seluruh unit masih on_loan.
- Partially resolved: ada unit returned atau lost, masih ada unit on_loan.
- Closed: tidak ada unit on_loan, ada sekurangnya satu returned atau lost, dan tidak ada voided.
- Voided: seluruh unit ber-outcome voided.
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
AC-12	Void hanya Super Admin, belum ada retur/loss, dengan alasan dan attestasi non-handover.
AC-13	Jumlah 7 kelompok stok eksklusif selalu sama dengan total unit.
AC-14	Laporan dengan filter 00:30 WIB masuk hari WIB yang benar.
AC-15	Route dan permission Equipment tidak membuka akses ke Admin News/TVClub.
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