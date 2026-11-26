# Checklist Progres Modernisasi WPiTV

> **Catatan handover, 25 November 2026:** checklist berikut dipertahankan sebagai bukti historis, bukan status working tree terkini. Tracking lintas modul kini berada di [DEVELOPMENT_STATUS.md](DEVELOPMENT_STATUS.md) dan prioritas/acceptance criteria di [ROADMAP.md](ROADMAP.md). Jangan menjalankan full suite, migration, commit atau deployment hanya karena direkomendasikan checklist lama; ikuti scope tugas dan [AGENTS.md](../AGENTS.md).

Terakhir diperbarui: 1 September 2026

Dokumen ini menjadi pusat tracking pekerjaan modernisasi portal publik dan admin WPiTV.

## Legenda

- [x] Selesai dan sudah diverifikasi.
- [ ] Belum dikerjakan atau masih memerlukan tindak lanjut.
- [~] Sudah diterapkan, tetapi masih membutuhkan validasi menyeluruh atau keputusan pemilik konten.

## Ringkasan Status

- [x] Struktur aplikasi publik dan admin sudah dibangun dengan Laravel, Livewire, dan Vite.
- [x] Pengujian penuh setelah retirement Banner lulus: 816 tes dan 2.946 assertion, database `wpitv_testing`.
- [x] Laravel Pint, Blade cache, Vite production build, dan `git diff --check` terakhir lulus.
- [~] Perubahan proyek masih tersebar dalam banyak file modified/untracked dan belum memiliki checkpoint Git khusus modernisasi.
- [~] Pengujian fungsional otomatis sudah kuat, tetapi audit visual manual setiap halaman dan setiap role belum selesai.
- [ ] Belum dilakukan deployment production dan pemeriksaan pascadeploy.

## 1. Fondasi Aplikasi dan Keamanan

- [x] Autentikasi admin menggunakan username dan password.
- [x] User nonaktif tidak dapat login.
- [x] Proteksi route admin untuk guest dan user tanpa izin.
- [x] Role Super Admin, Admin Produksi, dan Admin Berita tersedia.
- [x] Policy akses tersedia untuk program, video, berita, peliputan, TV Club, user, dan role. Banner sudah retired dari aplikasi.
- [x] Pengujian akses langsung URL dan mutasi Livewire tersedia.
- [~] Audit manual menu dan tombol berdasarkan ketiga role pada seluruh halaman admin.
- [ ] Buat checkpoint Git/tag sebelum perubahan besar berikutnya.
- [ ] Review konfigurasi `.env` production, session, cache, queue, mail, dan logging.

## 2. Modernisasi UI Admin

- [x] Tema Mazer telah dipoles melalui `resources/css/admin.css`.
- [x] Font global admin diselaraskan.
- [x] Warna utama, warna terang, badge, tombol, form, tabel, dan card telah dimodifikasi.
- [x] Field filter menggunakan surface yang lebih terang dan mudah dibaca.
- [x] Spacing, padding container, margin, dan gap card telah dipadatkan.
- [x] Variasi gradient digunakan pada card informasi tertentu.
- [x] Dashboard menggunakan logo WPiTV tanpa card putih pada sidebar.
- [x] Favicon admin menggunakan `public/favicon.png`.
- [x] Ringkasan periode dan card statistik pengunjung telah dipoles.
- [x] Bahasa Indonesia digunakan pada mayoritas judul, subtitle, filter, tabel, status, dan aksi admin.
- [~] Audit konsistensi ukuran badge di seluruh modul, bukan hanya Statistik Pengunjung.
- [~] Audit kontras teks pada seluruh kombinasi card/status, termasuk dark mode bila digunakan.
- [~] Audit kepadatan teks dan hapus informasi teknis/database yang tidak berguna di setiap halaman.
- [ ] QA visual manual halaman admin pada desktop 1440 px, laptop 1280 px, tablet, dan mobile.
- [ ] Audit aksesibilitas keyboard, focus state, label form, dan kontras WCAG.

## 3. Dashboard dan Statistik Pengunjung

- [x] KPI dashboard bersumber dari database.
- [x] Statistik tayangan, pengunjung, trafik harian, dan periode tersedia.
- [x] Filter hari ini, 7 hari, 14 hari, 30 hari, bulan ini, dan rentang khusus tersedia.
- [x] Agregasi trafik harian dan kalkulasi pengunjung online diuji.
- [x] Alamat IP pengunjung tidak ditampilkan pada halaman statistik publik/admin.
- [x] Library chart hanya dimuat pada halaman yang memerlukannya.
- [x] Tampilan ringkasan periode telah disederhanakan.
- [~] Uji interaksi chart secara manual untuk seluruh pilihan periode dan rentang kosong.
- [~] Validasi keterbacaan chart ketika hanya ada satu titik atau satu nilai.
- [ ] Tambahkan empty/error state visual yang konsisten bila query statistik gagal.

## 4. Modul Admin

### Banner — RETIRED / REMOVED FROM APPLICATION

- [x] Query publik, route/CRUD admin, sidebar, KPI/action dashboard, model, dan policy Banner dihapus.
- [x] Tes regresi memastikan tidak ada query Banner, menu/action, atau route aktif untuk semua role.
- [x] Tabel `ms_banner`, baris historis, dan file gambar lama dipertahankan; bukan penghapusan database.

### Program

- [x] Daftar, pencarian, filter status, tambah, edit, dan hapus tersedia.
- [x] Penghapusan diblokir jika program masih mempunyai video.
- [x] Slug otomatis dan validasi slug tersedia.
- [ ] QA visual data sangat panjang, cover rusak, dan program tanpa video.

### Tayangan/Video

- [x] Daftar, pencarian, filter program, tambah, edit, dan hapus tersedia.
- [x] URL YouTube watch, short, embed, dan ID langsung dapat diproses.
- [x] Fallback thumbnail dan relasi program nullable ditangani.
- [ ] QA manual embed YouTube lambat, gagal dimuat, atau diblokir browser.

### Berita

- [x] Daftar, pencarian, filter, tambah, edit, dan hapus tersedia.
- [x] Tombol tambah dan tombol aksi tersedia sesuai policy role.
- [x] TinyMCE lokal digunakan untuk isi berita.
- [x] Upload dan penggantian gambar membersihkan file lama.
- [~] Uji TinyMCE untuk heading, list, link, paste, sanitasi HTML, dan konten panjang.
- [ ] QA mobile halaman form berita dan toolbar TinyMCE.

### Peliputan

- [x] Daftar, pencarian, filter status/tanggal, sorting, pagination, dan CRUD tersedia.
- [x] Quick update status tersedia.
- [x] Modal detail permohonan telah dibuat lebih modern.
- [x] Modal perubahan status menggunakan pola modal admin modern.
- [~] Audit apakah seluruh modal tersisa sudah memakai pola `admin-modal` yang sama.
- [ ] QA visual rincian permohonan dengan deskripsi, alamat, nomor telepon, dan nama yang sangat panjang.

### TV Club

- [x] Manajemen generasi tersedia.
- [x] Manajemen anggota tersedia.
- [x] Manajemen kelompok dan assignment anggota tersedia.
- [x] Dependensi mencegah penghapusan data yang masih digunakan.
- [x] Hak akses berdasarkan role diuji.
- [ ] QA visual foto kosong/rusak, nama panjang, kelompok tanpa anggota, dan banyak anggota.

### User dan Role

- [x] CRUD user dan role tersedia.
- [x] Filter, pencarian, sorting, status, dan assignment role tersedia.
- [x] Password di-hash dan password kosong saat edit tidak menimpa password lama.
- [x] User aktif tidak dapat menghapus dirinya sendiri.
- [x] Super Admin terakhir tidak dapat dinonaktifkan/dihapus sembarangan.
- [ ] Audit UX penggantian password dan penjelasan permission untuk administrator nonteknis.

## 5. SweetAlert2 dan Feedback Aksi

- [x] SweetAlert2 original dipasang melalui dependency NPM.
- [x] Toast mixin tersedia untuk feedback sukses.
- [x] Konfirmasi tersedia untuk insert, update, delete, dan logout.
- [x] Helper global `WPiAlert` tersedia pada admin.
- [x] Pesan dibuat ringkas, misalnya login berhasil dan data berhasil ditambah/diubah/dihapus.
- [x] Alert login berhasil ditangani melalui flash session.
- [~] Uji manual toast dan konfirmasi pada setiap modul admin setelah navigasi Livewire.
- [~] Pastikan tidak ada form penting yang terlewat dari konfirmasi.
- [ ] Uji fallback ketika JavaScript gagal dimuat agar submit penting tetap aman.

## 6. Portal Publik

- [x] Homepage menggunakan konten database.
- [x] Homepage menampilkan Public Visitor Counter berbasis `tr_usercounter` untuk Hari ini, Kemarin, Bulan ini, Tahun ini, dan Online; agregat di-cache 60 detik tanpa mengekspos IP.
- [x] Halaman daftar dan detail Program tersedia.
- [x] Halaman daftar dan detail Tayangan tersedia.
- [x] Halaman daftar dan detail Berita tersedia.
- [x] Portal TV Club, generasi, dan profil anggota tersedia.
- [x] `/tv-club` telah diringkas menjadi overview mini portal tanpa direktori, filter, dan pagination yang menumpuk.
- [x] Arsip generasi, direktori kru, dan kelompok produksi dipisahkan ke route publik khusus.
- [x] Navigasi TV Club memakai dropdown desktop dan accordion mobile dengan active state serta kontrol keyboard.
- [x] Breadcrumb detail generasi dan anggota terhubung kembali ke indeks yang sesuai.
- [x] Enam route TV Club lolos QA browser pada 1440, 1280, 1024, 768, 430, dan 390 px tanpa overflow horizontal.
- [x] Portal TV Club telah diverifikasi pada light mode dan dark mode.
- [x] Landing TV Club dipoles dengan konsep editorial “Di Balik Layar WPiTV” yang menonjolkan manusia, produksi, dan komunitas.
- [x] Hero landing menampilkan kru aktual berbasis database dengan fallback inisial karena aset foto kru belum tersedia.
- [x] Preview kru dibatasi enam anggota aktif dari generasi aktif, memakai eager loading, jumlah penugasan aktual, dan tautan profil.
- [x] Kartu generasi aktif, gateway portal, culture strip, dan closing CTA telah dipoles tanpa mengubah IA maupun subpage.
- [x] Indeks Generasi dipoles menjadi active feature dan arsip timeline semantik yang menonjolkan waktu, kontinuitas, dan komunitas.
- [x] Detail Generasi kini memiliki hero profil, snapshot aktual, kartu anggota, kelompok produksi, relasi program, dan navigasi tahun sebelumnya/berikutnya.
- [x] Anggota dan kelompok tidak aktif tetap ditampilkan sebagai konteks historis tanpa mengekspos NIM atau data privat.
- [x] Indeks serta detail Generasi lolos QA browser pada 1440, 1280, 1024, 768, 430, dan 390 px dalam light/dark mode tanpa overflow.
- [x] Direktori kru dipoles menjadi people-first crew directory dengan hero ringkas, pencarian nama, filter generasi, ringkasan hasil Indonesia, pagination lokal, dan kartu portrait 4:5.
- [x] Kartu kru memakai jumlah penugasan aktual dari `withCount('groups')`, status publik yang subtil, fallback inisial penuh, dan tidak menampilkan NIM.
- [x] Detail anggota dipoles menjadi production portfolio dengan hero identitas, snapshot, penugasan dan role pivot aktual, program terdeduplikasi, konteks generasi, serta maksimal empat kru terkait.
- [x] Kru tanpa penugasan, role kosong, foto kosong, dan anggota historis memiliki fallback yang aman tanpa bio, social media, skills, atau metrik rekaan.
- [x] Direktori dan detail kru lolos QA browser pada 1440, 1280, 1024, 768, 430, dan 390 px dalam light/dark mode tanpa overflow horizontal.
- [x] Regresi tautan profil pada Detail Program dan Detail Generasi, Landing TV Club, Tim Produksi, serta Detail Video telah diverifikasi.
- [x] Kelompok Produksi dipoles menjadi pengalaman organisasi produksi yang menghubungkan identitas program, tim, generasi, dan kru dalam kartu editorial dua kolom.
- [x] Query Kelompok Produksi eager-load generasi, program, dan anggota dengan kolom publik minimum serta `withCount('members')` tanpa N+1.
- [x] Preview kru menampilkan maksimal empat anggota di desktop atau tiga di mobile, indikator `+N`, fallback inisial, role pivot aktual pada label aksesibel, dan tautan profil.
- [x] Tim historis, tim tanpa kru, deskripsi kosong, serta relasi program/generasi yang hilang memiliki fallback aman tanpa mengekspos NIM.
- [x] Kelompok Produksi lolos QA browser pada 1440, 1280, 1024, 768, 430, dan 390 px dalam light/dark mode tanpa overflow horizontal.
- [x] Regresi Landing, Generasi, Crew Directory, Member Detail, Program Detail, dan selector stabil Video Detail telah diverifikasi setelah polish Kelompok Produksi.
- [x] Alur pengajuan dan pengecekan status peliputan tersedia.
- [x] Seluruh hero publik menggunakan bahasa visual biru khas WPiTV.
- [x] Favicon publik/guest menggunakan `public/favicon.png`.
- [x] Navigasi desktop, mobile, footer, dan internal route terhubung.
- [x] Hero homepage memakai Featured Video Selector untuk tayangan terbaru, profil kampus, dan informasi PMB.
- [x] Satu player YouTube berganti secara client-side tanpa reload dan tanpa autoplay saat halaman dibuka.
- [x] Poster/fallback tetap tersedia sebelum player siap.
- [x] Detail Program menampilkan “Tim di Balik Program” dari relasi kelompok, generasi, anggota, dan role pivot aktual.
- [x] Kelompok produksi kosong disembunyikan; satu kelompok tampil langsung dan multi-generasi memakai selector aksesibel.
- [x] Profil kru terhubung ke portal TV Club tanpa menampilkan NIM atau data privat.
- [x] Deskripsi Program kosong tidak lagi menampilkan placeholder sistem dan jumlah tayangan memakai data aktual.
- [~] Uji pemutaran manual pada Firefox, Safari, Android, dan iOS; Chrome desktop sudah diverifikasi.
- [~] Audit seluruh halaman publik untuk konsistensi hero, breadcrumb, tombol, dan dark mode.
- [ ] Audit SEO lanjutan: canonical URL, Open Graph, Twitter Card, sitemap, dan robots.txt.

## 7. Halaman Tentang WPiTV

- [x] Route `/tentang` dan komponen Livewire tersedia.
- [x] Hero brand storytelling dengan visual tayangan aktual tersedia.
- [x] Bagian pengenalan, brand statement, visi-misi, pilar kerja, statistik, workflow, kru, ekosistem, dan CTA tersedia.
- [x] Statistik memakai data database yang sebenarnya.
- [x] Preview kru dibatasi maksimal enam anggota aktif.
- [x] NIM dan data privat anggota tidak ditampilkan.
- [x] Empty state dan avatar inisial tersedia.
- [x] Light/dark mode dan CSS responsif tersedia.
- [x] Metadata title dan description khusus tersedia.
- [x] Pengujian khusus lulus: 10 test dan 44 assertion.
- [x] QA browser desktop tidak menemukan gambar rusak, overflow horizontal, atau error konsol.
- [~] Visi masih memakai redaksi legacy “STMIK Widya Pratama” dengan penanda transparan.
- [ ] Konfirmasi visi resmi terbaru Institut Widya Pratama.
- [ ] Konfirmasi misi resmi terbaru WPiTV.
- [ ] Konfirmasi tahun berdiri dan tonggak sejarah WPiTV.
- [ ] Konfirmasi nama/jabatan pengelola atau Kepala UPT saat ini.
- [ ] Setelah data resmi diterima, tambahkan bagian sejarah/pengelola tanpa membuat informasi rekaan.

## 8. Performa

- [x] Query dashboard dan halaman publik utama diuji agar tidak mengalami N+1 yang tidak terkendali.
- [x] Chart hanya dimuat pada halaman yang membutuhkan.
- [x] Data halaman Tentang menggunakan cache singkat di lingkungan non-testing.
- [x] Iframe video hero dipersiapkan melalui poster terlebih dahulu.
- [x] Production asset sudah dapat dikompilasi dengan Vite.
- [~] Keluhan website terasa berat belum ditutup dengan pengukuran Lighthouse terbaru.
- [ ] Catat baseline Lighthouse desktop/mobile untuk Homepage, Tentang, detail Video, dan Dashboard.
- [ ] Audit ukuran gambar, format WebP/AVIF, lazy loading, dan responsive `srcset`.
- [ ] Audit bundle JavaScript/CSS dan hapus library/selector yang tidak terpakai.
- [ ] Aktifkan dan ukur cache production, compression Brotli/Gzip, serta browser cache headers.
- [ ] Ukur Core Web Vitals: LCP, CLS, INP, dan TTFB.

## 9. Pengujian dan Quality Gate

- [x] Test autentikasi tersedia.
- [x] Test policy dan access control tersedia.
- [x] Test seluruh modul admin utama tersedia.
- [x] Test seluruh alur publik utama tersedia.
- [x] Full test terakhir: 459 test dan 1.360 assertion lulus.
- [x] Public regression terakhir: 162 test dan 469 assertion lulus.
- [x] `TVClubPortalTest`: 45 test dan 154 assertion lulus.
- [x] Laravel Pint terakhir lulus.
- [x] Vite production build terakhir lulus.
- [x] Blade view cache terakhir lulus.
- [x] `git diff --check` terakhir lulus.
- [ ] Jalankan ulang seluruh quality gate setiap sebelum commit/release.
- [ ] Tambahkan browser E2E otomatis untuk SweetAlert2, chart period, TinyMCE, dan modal.
- [ ] Tambahkan visual regression screenshot untuk halaman kritis.

## 10. Release dan Deployment

- [x] Dokumen deployment dan checklist release dasar tersedia.
- [ ] Review semua perubahan modified, deleted, dan untracked sebelum commit.
- [ ] Pastikan penghapusan route/view auth lama memang disengaja.
- [ ] Buat commit terpisah per domain: fondasi, admin, publik, alert/editor, Tentang, dan test.
- [ ] Siapkan backup database dan storage.
- [ ] Jalankan migration/seeder hanya setelah backup dan review.
- [ ] Jalankan build production final.
- [ ] Deploy ke staging terlebih dahulu.
- [ ] Jalankan smoke test staging untuk guest dan semua role admin.
- [ ] Deploy production setelah approval.
- [ ] Jalankan post-deployment check, log monitoring, dan rollback verification.

## Workflow Berikutnya yang Direkomendasikan

1. [ ] Buat checkpoint Git yang aman dan inventaris perubahan saat ini.
2. [ ] Tutup QA visual admin per modul dan per role.
3. [ ] Tutup QA interaksi SweetAlert2, chart, TinyMCE, serta seluruh modal.
4. [ ] Lakukan audit performa berbasis Lighthouse dan Network waterfall.
5. [ ] Minta serta masukkan data resmi visi, misi, sejarah, dan pengelola WPiTV.
6. [ ] Jalankan quality gate lengkap setelah perbaikan.
7. [ ] Deploy ke staging dan lakukan user acceptance test.
8. [ ] Buat release candidate, backup, lalu deploy production.

## Definition of Done

Sebuah item dianggap selesai hanya jika:

- Implementasi sudah ada di kode.
- Test otomatis yang relevan lulus.
- Tampilan dan interaksi utama sudah diperiksa secara manual.
- Tidak mengekspos data privat atau melanggar policy role.
- Tidak menimbulkan error console, overflow, atau regresi pada halaman terkait.
- Perubahan sudah tercatat dalam commit/checkpoint yang dapat dipulihkan.
