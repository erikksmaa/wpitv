# Konteks Proyek WPiTV

Snapshot: **25 November 2026**, `rebuild/wpitv-clean`. Baca bersama [status](DEVELOPMENT_STATUS.md), [arsitektur](ARCHITECTURE.md), dan [keputusan](DECISIONS.md). Implemented tidak otomatis ready for use.

## Latar belakang, tujuan dan lingkup

WPiTV adalah media digital kampus Widya Pratama di Pekalongan. Modernisasi mempertahankan data website lama sambil membangun portal publik dan operasional admin dengan Laravel/Livewire. Penyebutan STMIK pada materi legacy tidak boleh diganti dengan fakta sejarah/visi baru tanpa sumber resmi; identitas dokumen Social saat ini memakai Institut Widya Pratama.

Tujuan utama: publik menemukan tayangan/berita/kru/jadwal dan mengajukan peliputan; pengelola mengelola produksi, aset, transaksi peminjaman dan pertanggungjawaban bulanan dengan akses terpisah. Data existing, privasi, relasi dan histori harus dipertahankan.

Rebuild 21 November memisahkan Live Streaming dan Monthly dari pekerjaan Equipment lama yang diarsipkan. Branch sekarang telah memiliki Equipment baru serta perubahan lokal Social. Pernyataan lama “Equipment belum dimulai” sudah tidak mewakili working tree.

Lingkup aktif mencakup seluruh modul di bawah. Banner retired dari aplikasi; tabel dan gambar historis tetap ada. Tidak ada portal login peminjam, mesin streaming sendiri, sinkronisasi API media sosial, atau deployment production yang dibuktikan oleh handover ini.

## Aktor dan hak akses

Nama role merupakan kontrak literal dari `User`/`Role`: **Super Admin**, **Admin Production**, **Admin News**, **TVClub**. Semua akses admin mensyaratkan akun aktif dan sesi valid. Role lain tidak otomatis mendapat akses admin.

| Modul/kemampuan | Super Admin | Admin Production | Admin News | TVClub |
| --- | --- | --- | --- | --- |
| Dashboard | Lihat sesuai role | Lihat sesuai role | Lihat sesuai role | Lihat sesuai role |
| Program; generasi/anggota/kelompok/organisasi TVClub | Kelola | Kelola | Tidak | Kelola |
| Tayangan | Kelola termasuk hapus | Kelola termasuk hapus | Lihat/tambah/edit | Tidak |
| Berita | Kelola | Tidak | Kelola | Tidak |
| Peliputan, Agenda, organisasi WPiTV | Kelola | Kelola | Tidak | Tidak |
| Live Streaming | Kelola lifecycle/arsip | Kelola lifecycle/arsip | Tidak | Tidak |
| User, Role, statistik pengunjung | Kelola; statistik lihat | Tidak | Tidak | Tidak |
| Inventory, pinjam/retur, laporan Equipment | Seluruh permission | Operasi biasa; tanpa retire/void | Tidak | Tidak |
| Aktivitas Bulanan | Lihat semua/review; bukan penulis divisi | Tulis/kirim Production | Tulis/kirim News | Tidak |
| Media Sosial | Berdasarkan penugasan eksplisit | Berdasarkan penugasan eksplisit | Berdasarkan penugasan eksplisit | Berdasarkan penugasan eksplisit |

“Kelola” tetap tunduk proteksi dependensi, lifecycle, data diri sendiri dan Super Admin terakhir. Tidak ada bypass universal Super Admin untuk Equipment/Social. Social memakai daftar manager/reviewer pada konfigurasi, bukan role; pemeriksa harus berbeda dari pengaju. Peminjam mahasiswa/dosen/staf adalah snapshot transaksi, bukan role login baru. Pengunjung umum mengakses portal tanpa autentikasi admin.

## Modul publik dan alurnya

| Modul | Route utama | Alur bisnis |
| --- | --- | --- |
| Beranda | `/` | Membaca konten database, highlight Live, tayangan terbaru, pilihan video institusi, dan counter agregat. Video profil/PMB dikonfigurasi terpisah dari katalog tayangan. |
| Tentang WPiTV | `/tentang` | Mengenal identitas, visi/misi yang tersedia, struktur organisasi dan kru; tidak mengarang sejarah atau profil. |
| Program | `/program`, `/program/{program:slug}` | Memilih program aktif, melihat deskripsi, tayangan dan tim produksi berdasarkan relasi aktual. |
| Tayangan | `/tayangan`, `/tayangan/{video}` | Mencari/memfilter tayangan, membuka player YouTube, konteks program dan kru terkait. |
| Berita | `/berita`, `/berita/{news:slug}` | Menelusuri berita dan membaca isi yang disanitasi beserta penulis/gambar. |
| TVClub | `/tv-club` | Overview komunitas dan pintu masuk generasi, kru dan tim produksi. |
| Generasi TVClub | `/tv-club/generasi`, `/tv-club/generasi/{generation}` | Arsip generasi, anggota, struktur dan konteks tim historis. |
| Kru TVClub | `/tv-club/anggota`, `/tv-club/anggota/{member}` | Cari/filter kru, lihat portofolio penugasan dan role pivot; NIM tetap privat. |
| Tim produksi | `/tv-club/tim-produksi` | Menghubungkan kelompok dengan program, generasi, anggota dan peran. |
| Layanan peliputan | `/peliputan`, `/peliputan/ajukan`, `/peliputan/berhasil/{code}`, `/peliputan/status` | Mengisi acara/kontak → memperoleh kode dan receipt sesi → memeriksa status dengan kode + nomor yang cocok. Rate limit dan masking melindungi lookup; WhatsApp berupa tautan bila dikonfigurasi. |
| Agenda | `/agenda` | Kalender bulanan dan kegiatan mendatang yang lolos scope publik; internal/draft tidak boleh bocor. |
| Live Streaming | `/live`, `/live/{liveStream}` | Menemukan siaran terjadwal/berlangsung/arsip yang publik; pemutar YouTube mengikuti status operator, bukan polling API otomatis. |
| Pencarian dan counter | Komponen global | Mencari konten publik dan menghitung trafik agregat; tidak menampilkan IP pengunjung. |

## Modul admin dan alurnya

Semua path berikut berawalan `/admin`. Form create/edit dan endpoint dokumen ada pada `routes/web.php`; inventaris teknis di ARCHITECTURE.

| Modul | Path | Alur bisnis utama |
| --- | --- | --- |
| Dashboard | `/`, `/dashboard` | Menampilkan KPI, aksi dan pekerjaan sesuai role; Equipment menambah ringkasan stok bagi pemegang permission laporan. |
| Program | `/programs` | Tambah/edit program, slug/gambar/status → tampil publik saat aktif; hapus ditolak bila dependensi masih ada. |
| Tayangan | `/videos` | Input YouTube + program/tanggal → normalisasi ID → publikasi katalog. Admin News tidak boleh menghapus. |
| Berita | `/news` | Tulis TinyMCE → sanitasi HTML + optimasi gambar → simpan dan tampil pada portal. |
| Peliputan | `/peliputan` | Tinjau permohonan → approved/in_progress/completed atau rejected → catatan/status; buat Agenda dari permohonan bila diperlukan. |
| Agenda | `/agenda` | Buat manual/dari peliputan → kaitkan program/kelompok → atur tanggal, status dan visibility. Perubahan agenda terkait Live melewati lifecycle terkoordinasi. |
| Live Streaming | `/live-streams` | Buat dari Agenda atau manual → konfigurasi YouTube/jadwal → scheduled/live/ended/cancelled sesuai aturan → kaitkan tayangan arsip saat ended. Audit dan versi menjaga perubahan bersamaan. |
| TVClub | `/tvclub/generations`, `/tvclub/members`, `/tvclub/groups`, `/tvclub/generations/{generation}/organization` | Kelola generasi → anggota → kelompok/program dan assignment role → struktur posisi/anggota per generasi. Penghapusan tunduk dependensi. |
| Organisasi WPiTV | `/organisasi-wpitv` | Susun pohon jabatan dan penanggung jawab dari user; struktur organisasi bukan assignment permission. |
| Statistik | `/visitor-statistics` | Super Admin memilih periode dan membaca agregat trafik/chart, tanpa mengekspos IP. |
| User dan Role | `/users`, `/roles` | Kelola akun aktif, password hash dan role; lindungi diri sendiri, role inti dan Super Admin terakhir. |
| Inventory | `/inventory/categories`, `/inventory/equipment`, `/inventory/activity` | Kategori → model + stok awal → unit berkode aset → tambah stok/kondisi/serial/maintenance/lost/found/retire → foto dan audit privat. Model bukan unit fisik. |
| Peminjaman | `/equipment-loans`, `/equipment-loans/create`, `/equipment-loans/{loanId}` | Operator merekam identitas dan kebutuhan → memilih unit eligible lintas halaman → checkout atomik → nomor transaksi dan snapshot. Maksimal 50 unit/20 model. |
| Pengembalian/pengecualian | Detail peminjaman | Retur parsial/penuh per unit dengan kondisi dan foto; maksimal 10 unit/40 foto per batch, 1–8 foto per unit. Rusak/terlambat wajib catatan. Perpanjang tenggat dengan alasan; loss mengunci fakta historis; void khusus salah input sebelum serah terima dengan attestasi. |
| Dashboard/laporan Equipment | `/equipment-reports/inventory`, `/equipment-reports/loans` | Ringkasan stok di Inventory/dashboard; filter laporan inventaris atau cohort checkout/retur → terapkan filter → detail atau CSV berizin. Tidak ada tabel laporan agregat terpisah. |
| Laporan Aktivitas Bulanan | `/monthly-reports`, `/monthly-reports/{reportId}` | Penulis divisi membuat draft per bulan → item manual/kandidat sumber + lampiran → submit snapshot → Super Admin revisi/setujui → CSV/cetak dan riwayat versi. Laporan nihil memerlukan alasan. |
| Media Sosial | `/social/accounts`, `/social/posts` | Manager ditugaskan mencatat akun dan publikasi manual, platform/jenis/tanggal/metrik observasi → arsipkan bila perlu; tidak hard delete histori atau mengambil data API. |
| Laporan bulanan Media Sosial | `/social/reports` | Pilih bulan WIB → isi followers/reach/impressions/engagement rate dan analisis → hitung jenis publikasi aktual → submit → reviewer berbeda meminta revisi/approve → snapshot resmi → preview/print/XLSX sesuai template WPiTV. Saat handover masih pending SQL dan reviewer. |

Aktivitas Bulanan dan laporan Media Sosial adalah dua workflow berbeda: yang pertama per divisi dan periode; yang kedua ringkasan empat platform per periode dan template resmi. Jangan menyatukan tabel atau approval keduanya tanpa keputusan baru.

## Membaca bukti lama

[Checklist progres lama](PROJECT_PROGRESS_CHECKLIST.md) berisi bukti publik/admin dan QA terdahulu, bukan jaminan working tree terbaru. [Rebuild](WPITV_ARCHIVE_AND_REBUILD.md) menjelaskan baseline Live/Monthly. Laporan Inventory/Loans pada `docs/equipment-rebuild/` menjelaskan implementasi 23–24 November. Bila bertentangan, gunakan metadata aktual + kode saat ini, lalu catat perubahan keputusan dan keterbatasan buktinya.
