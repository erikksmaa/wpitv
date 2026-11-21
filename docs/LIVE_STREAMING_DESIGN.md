# WPiTV — Live Streaming Architecture & Design

Tanggal audit: 9 November 2026. Fase: **3A, audit dan desain saja**.

Status terbaru: kedua migration Live telah diterapkan secara additive pada MySQL lokal `wpitv` dan `wpitv_testing` (24 tabel), setelah backup dan verifikasi isolasi. Integrasi Live/Agenda dan browser QA admin telah dijalankan; hasil, perbaikan, serta batas verifikasi tercatat di [LIVE_STREAMING_VERIFICATION.md](LIVE_STREAMING_VERIFICATION.md). Bagian 1–13 mempertahankan hasil masing-masing fase saat dikerjakan; keterangan pengujian tertunda di bagian tersebut adalah catatan historis, bukan status terbaru. Frontend Live publik tersedia pada Phase 3D; hasil verifikasi terbaru ada di bagian Phase 3D pada dokumen verifikasi.

## 1. Lingkup dan bukti audit

Audit meninjau source Program, Tayangan/Video, Agenda, routes, policy, Livewire, Blade, navigasi, dashboard, serta konfigurasi keamanan. Metadata database terkait diverifikasi melalui koneksi read-only: struktur `ms_program`, `ms_video`, `wpitv_agendas`, tipe PK `users.id`, dan penggunaan Agenda `type=live`. Tidak menjalankan migration, seeder, test suite, atau perubahan data. Modul inventaris tidak diaudit ulang. Baseline 22 tabel mengikuti kondisi proyek; pemeriksaan database fase ini dibatasi pada objek yang relevan dengan Live.

### 1.1 Kondisi aktual

| Area | Temuan source dan skema aktual | Implikasi |
| --- | --- | --- |
| Program | `Program` memakai `ms_program`; memiliki relasi videos dan agendas, scope aktif, slug unik. Nama maksimal 150, slug 180, status boolean. | Gunakan Program existing melalui Agenda; tidak membuat katalog Program kedua. |
| Tayangan | `Video` memakai `ms_video`, relasi Program dan pengunggah; menyimpan judul, `youtube_id`, deskripsi, `air_date`, dan tag. Helper watch/embed/thumbnail sudah tersedia. | Gunakan sebagai arsip editorial dan halaman menonton rekaman. Bukan sumber status on-air. |
| Identitas YouTube | `ms_video.youtube_id` berupa `VARCHAR(50)` dengan collation case-insensitive, tanpa unique constraint. Parser menerima ID 11 karakter dan beberapa URL, tetapi tidak `/live/{id}`; pencarian parameter `v` belum memvalidasi hostname secara ketat. | Jangan memakai parser tersebut tanpa pengamanan untuk Live. Jangan mengasumsikan arsip existing bebas duplikasi atau pencarian ID case-insensitive aman. |
| Agenda | `WpitvAgenda` memakai `wpitv_agendas`, memiliki Program opsional, tanggal, jam mulai/selesai, lokasi, deskripsi, status, visibility, dan pembuat. Jenis `live` sudah tersedia. | Jadwal dan identitas acara tetap bersumber dari Agenda. |
| Makna Agenda live | Saat ini hanya label klasifikasi. Status Agenda adalah `draft`, `scheduled`, `completed`, `cancelled`; tidak ada status on-air, ID YouTube, atau relasi arsip. Pada pemeriksaan read-only tidak ditemukan record `type=live`. | Tidak perlu mengonversi data historis menjadi Live atau menebak status dari jenis Agenda. Tetap tangani Agenda live tanpa konfigurasi streaming. |
| Publik Agenda | `publicVisible()` hanya `visibility=public` dan `status=scheduled`. `upcoming()` memakai tanggal, belum membandingkan jam. Kalender serta beranda menampilkan jadwal tersebut. | Jadwal lewat tidak membuktikan siaran berakhir. Query arsip Live tidak dapat menggunakan scope Agenda scheduled. |
| Publik Tayangan | Daftar, detail, serta daftar video Program menampilkan Video tanpa status draft/publikasi. | Membuat Video untuk siaran internal atau belum siap akan langsung memublikasikannya. Jangan membuat Video otomatis saat membuat Live. |
| Player | Iframe inline ada di detail Tayangan, preview admin, dan beranda; belum ada komponen Blade player bersama. Beranda memakai poster sebelum memuat player. | Gunakan pola tampilan existing; komponen player aman dapat dirancang saat implementasi tanpa merombak player legacy. |
| Beranda | Payload beranda memakai cache `public.homepage.v5` selama 60 detik. Hero berasal dari Video terbaru, bukan status siaran. | Widget Live harus membaca status tersendiri; Video terbaru bukan indikator sedang live. |
| Dashboard/navigasi | `AdminDashboardMetrics` memisahkan query menurut role. `AdminNavigation` memakai Gate untuk menu. Blade Mazer, pagination, dan SweetAlert2 tersedia. | Ikuti pola tersebut dan lakukan otorisasi sebelum membangun query Live. |
| Keamanan | CSP mengizinkan frame YouTube dan youtube-nocookie; `script-src` dan `connect-src` hanya self. Header referrer memakai `strict-origin-when-cross-origin`. CSP default konfigurasi report-only; mode deployment aktual tidak diverifikasi di fase ini. | Iframe biasa cukup untuk MVP. IFrame JavaScript API memerlukan tinjauan CSP tersendiri. |

Seluruh PK legacy yang relevan (`ms_program.id`, `ms_video.id`, `wpitv_agendas.id`, `users.id`) adalah **INT UNSIGNED**. FK existing Video/Agenda ke Program menggunakan `ON DELETE SET NULL`. Jadwal Agenda berupa `DATE` + `TIME`; timestamps legacy berupa `TIMESTAMP` dengan presisi detik, bukan mikrodetik. Tidak ada kolom legacy yang diusulkan berubah.

### 1.2 Titik source yang ditinjau

- Model: `app/Models/Program.php`, `Video.php`, `WpitvAgenda.php`, dan helper role `User.php`.
- Policy: `app/Policies/ProgramPolicy.php`, `VideoPolicy.php`, `WpitvAgendaPolicy.php`; acuan otorisasi `docs/RBAC_ACCESS_MATRIX.md`.
- CRUD: `app/Livewire/Admin/Programs/{Index,Form}.php`, `Admin/Videos/{Index,Form}.php`, `Admin/Agenda/{Index,Form}.php`, beserta Blade pasangannya.
- Publik: `app/Livewire/Public/Home.php`, `Public/Programs/Show.php`, `Public/Videos/{Index,Show}.php`, `Public/Agenda/Index.php`, serta `resources/views/layouts/public.blade.php`.
- Integrasi: `routes/web.php`, `app/Support/AdminNavigation.php`, `app/Services/AdminDashboardMetrics.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/AddSecurityHeaders.php`, `config/security.php`.

Routes existing: `/program`, `/program/{program:slug}`, `/tayangan`, `/tayangan/{video}`, `/agenda`; admin `/admin/programs`, `/admin/videos`, `/admin/agenda` beserta form masing-masing. Belum ada route atau modul Live khusus. Tidak ada halaman detail Agenda publik existing.

## 2. Keputusan arsitektur

Rekomendasi: **satu tabel pendamping Live dan satu tabel riwayat perubahan**. Tidak menambahkan status streaming ke semua Video atau mengubah enum/status Agenda.

| Tanggung jawab | Sumber kebenaran |
| --- | --- |
| Judul acara, deskripsi publik, Program opsional, lokasi, tanggal/jam rencana, visibility | Agenda existing |
| Video YouTube untuk siaran, status operasional manual, waktu penandaan, versi perubahan | `live_streams` baru |
| Siapa mengubah konfigurasi/status dan alasan perubahan | `live_stream_events` baru |
| Judul/deskripsi editorial rekaman, tanggal tayang, pengunggah, halaman arsip | Video/Tayangan existing |
| Kondisi siaran aktual di platform | YouTube; **belum diverifikasi aplikasi dalam MVP** |

Agenda saja tidak cukup untuk menyimpan identitas YouTube, status on-air, kaitan arsip, dan kontrol versi. Video saja juga tidak cukup karena tidak mempunyai jadwal/status publikasi dan memiliki RBAC berbeda. Tabel pendamping menghindari perubahan 22 tabel existing. Tabel event kecil menjaga penelusuran status manual tanpa memakai tabel audit inventaris atau membangun platform audit umum. Jika diterapkan kelak, jumlah tabel menjadi **24**, bukan kondisi database saat audit.

Relasi: Program `1 → banyak` Agenda; Agenda `1 → 0/1` LiveStream; LiveStream `1 → banyak` LiveStreamEvent; LiveStream `0/1 → 0/1` Video arsip. Tidak ada `program_id`, judul, deskripsi, atau jadwal kedua pada LiveStream. Agenda tanpa LiveStream tetap berfungsi seperti sekarang.

Tidak ada server streaming, ingest key, upload rekaman, reservasi penonton, akun penonton, chat internal, atau kewajiban YouTube Data API. Untuk MVP beberapa acara boleh ditandai live bersamaan; halaman publik menampilkan pilihan, bukan memilih satu siaran secara tersembunyi.

## 3. Rancangan database additive

Seluruh struktur berikut merupakan spesifikasi untuk fase berikutnya, bukan SQL yang dijalankan pada audit ini. Engine InnoDB; teks umum utf8mb4_unicode_ci. Waktu pada tabel baru disimpan UTC sebagai `DATETIME(6)` dari server aplikasi, ditampilkan Asia/Jakarta. Gunakan pola `UtcDateTime`/`HasUtcTimestamps` existing untuk kolom baru setelah verifikasi unit; jangan mengubah timezone global atau menafsirkan ulang timestamp legacy.

### 3.1 `live_streams`

| Kolom | Tipe dan aturan |
| --- | --- |
| `id` | BIGINT UNSIGNED, PK, auto increment. |
| `agenda_id` | **INT UNSIGNED NOT NULL**, unique; FK `wpitv_agendas.id`, DELETE RESTRICT, UPDATE RESTRICT. Ikatan Agenda tidak dapat dipindah setelah dibuat. |
| `youtube_id` | CHAR(11), ASCII dengan collation `ascii_bin`, nullable, unique. NULL berarti URL belum tersedia. ID bersifat case-sensitive. |
| `status` | VARCHAR(16) NOT NULL default `scheduled`; CHECK hanya scheduled/live/ended/cancelled. Ini status manual, bukan hasil pengecekan YouTube. |
| `archive_video_id` | **INT UNSIGNED**, nullable, unique; FK `ms_video.id`, DELETE SET NULL, UPDATE RESTRICT. |
| `version` | INT UNSIGNED NOT NULL default 1, CHECK >= 1; versi perubahan Live dan metadata Agenda terkait. |
| `live_marked_at` | DATETIME(6) nullable; waktu petugas menandai mulai, bukan actualStartTime YouTube. |
| `ended_marked_at` | DATETIME(6) nullable; waktu penandaan selesai. |
| `cancelled_marked_at` | DATETIME(6) nullable; waktu pembatalan saat ini. |
| `status_changed_at` | DATETIME(6) NOT NULL; diisi juga saat penciptaan status awal. |
| `status_changed_by`, `created_by`, `updated_by` | Masing-masing **INT UNSIGNED nullable**, FK `users.id`, DELETE SET NULL, UPDATE RESTRICT; selalu berasal dari actor server. |
| `created_at`, `updated_at` | DATETIME(6) NOT NULL, server UTC. |

Indeks: unique masing-masing `agenda_id`, `youtube_id`, `archive_video_id`; indeks `(status, agenda_id, id)` dan FK actor. Indeks tanggal, status, type, visibility, dan Program pada Agenda existing digunakan untuk join/filter; evaluasi EXPLAIN dan volume sebelum mengusulkan perubahan indeks legacy.

CHECK tambahan:

- `youtube_id` NULL atau tepat 11 karakter; alfabet `[A-Za-z0-9_-]` divalidasi aplikasi secara penuh. Empty string ditolak, bukan pengganti NULL.
- `scheduled`: ketiga marker waktu NULL. `live`: ID dan live marker wajib, marker akhir/batal NULL.
- `ended`: ID dan ended marker wajib, cancelled marker NULL; live marker boleh NULL untuk penutupan administratif ketika penandaan awal terlewat. Jika terisi, ended marker >= live marker.
- `cancelled`: cancelled marker wajib, live/ended marker NULL; hanya pembatalan sebelum siaran dimulai.

Aturan aplikasi di luar CHECK: `archive_video_id` hanya boleh diisi saat ended; ID Video harus cocok dan Agenda sudah publik. Jangan membuat CHECK yang menyebut FK tersebut karena menggunakan DELETE SET NULL. MySQL membatasi penggunaan kolom dengan referential action dalam CHECK. Status/marker yang bukan FK tetap memakai CHECK; konsistensi lintas tabel divalidasi service. [Batasan CHECK MySQL 8.0](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html).

Unique YouTube ID mengartikan satu broadcast YouTube sebagai satu LiveStream. Satu URL broadcast tidak digunakan untuk banyak record jadwal. Nullable unique memberi ruang beberapa persiapan tanpa URL. Perubahan ID hanya boleh saat scheduled atau cancelled, sebelum live dan sebelum arsip; status ended tidak mengganti ID. Jadwal ulang menggunakan record yang sama dan audit, bukan duplikasi broadcast.

### 3.2 `live_stream_events`

| Kolom | Tipe dan aturan |
| --- | --- |
| `id` | BIGINT UNSIGNED, PK, auto increment. |
| `live_stream_id` | BIGINT UNSIGNED NOT NULL, FK `live_streams.id`, DELETE RESTRICT, UPDATE RESTRICT. |
| `event_type` | VARCHAR(32) NOT NULL; CHECK created/agenda_updated/youtube_updated/status_changed/archive_linked/archive_unlinked. |
| `actor_id` | **INT UNSIGNED nullable**, FK users, DELETE SET NULL, UPDATE RESTRICT. |
| `actor_name` | VARCHAR(100) NOT NULL, snapshot nama petugas dari server agar penelusuran tetap ada setelah akun dihapus. Hanya untuk admin berwenang. |
| `reason` | VARCHAR(1000) nullable; wajib untuk pembatalan, penjadwalan ulang setelah batal, dan penutupan administratif tanpa penandaan awal. Tidak pernah ditampilkan publik. |
| `before_values`, `after_values` | JSON nullable; hanya whitelist metadata Live/Agenda yang berubah. Tidak menyalin object User, session, password, atau payload request mentah. |
| `created_at` | DATETIME(6) NOT NULL, UTC; tidak ada `updated_at`. |

Indeks `(live_stream_id, created_at, id)` dan `(actor_id, created_at)`. Event bersifat append-only pada jalur aplikasi, tidak mempunyai CRUD publik atau aksi edit/hapus admin. Saat dibuat, LiveStream dan event created berada dalam transaksi sama. Penolakan mutasi membatalkan event juga.

### 3.3 Integritas dan penghapusan

- Tidak ada soft-delete/hard-delete Live pada MVP: gunakan cancelled untuk rencana batal dan visibility internal untuk menghentikan publikasi; riwayat dipertahankan.
- FK Agenda RESTRICT melindungi acara yang sudah menjadi Live. Aksi hapus Agenda terkait nantinya perlu pesan dependency dan arahkan ke pembatalan/penarikan publikasi. Ini proteksi dependency, bukan perubahan role yang boleh mengelola Agenda.
- Penghapusan Program mengikuti perilaku existing: relasi Agenda menjadi NULL. Live tetap dapat ditampilkan tanpa Program; jangan memperketat hak TVClub atas Program dengan FK Live langsung ke Program.
- Penghapusan Video oleh role yang memang berhak tetap dapat berlangsung melalui FK SET NULL; Live tidak hilang. Riwayat unlink eksplisit tercatat, sedangkan penghapusan Video yang menyebabkan SET NULL tidak otomatis menghasilkan event Live. UI wajib menunjukkan arsip belum terhubung; bukan menjanjikan audit penghapusan Video dari FK saja.
- Snapshot actor pada event tetap ada jika users dihapus. Timestamp ini tidak boleh disebut waktu aktual YouTube.
- Schema Builder mendukung FK/unique dasar; CHECK bernama dan collation khusus perlu ditinjau terhadap grammar MySQL sebelum migration disusun. Tidak perlu generated column untuk desain ini. Urutan pembuatan: LiveStream setelah tabel legacy, lalu event. Tidak ada rollback otomatis yang menghapus histori; kebijakan rollback harus menolak penghapusan tabel berisi data.

## 4. Lifecycle dan perubahan atomik

### 4.1 Pemetaan dua status

| Status Live manual | Status Agenda konsisten | Makna dan publikasi |
| --- | --- | --- |
| scheduled | draft atau scheduled | Draft tetap persiapan tersembunyi. Muncul di jadwal hanya bila Agenda public + scheduled; URL boleh belum tersedia. |
| live | scheduled | Petugas telah menandai siaran berjalan. Publik hanya bila Agenda public. Label menyebut status dari WPiTV. |
| ended | completed | Petugas menutup siaran. Bukan bukti rekaman YouTube sudah siap. Publik menampilkan status selesai dan tautan arsip jika valid. |
| cancelled | cancelled | Rencana dibatalkan; dikeluarkan dari daftar siaran/jadwal. Detail publik yang sudah dibagikan dapat menampilkan pembatalan tanpa alasan internal. |

`visibility=internal` menyembunyikan semua status dari publik; preview hanya melalui route admin berotorisasi. Tidak membuat nilai `live` pada status Agenda. Jadwal rencana tidak berubah ketika petugas menandai mulai/selesai.

Transisi yang diizinkan:

1. Hubungkan Agenda jenis live atau buat melalui CRUD Agenda existing; buat konfigurasi Live berstatus scheduled. Default persiapan internal/draft; petugas memeriksa ulang judul dan deskripsi sebelum menjadikannya publik.
2. scheduled → live: Agenda sudah scheduled, tanggal/jam mulai terisi, ID YouTube valid, petugas memberi konfirmasi setelah memeriksa platform. Waktu rencana boleh berbeda dari waktu mulai yang ditandai.
3. live → ended: isi ended marker dan ubah Agenda menjadi completed dalam transaksi yang sama. Tidak ada auto-create Video.
4. scheduled → cancelled: alasan wajib; ubah Agenda menjadi cancelled. live → cancelled ditolak; siaran yang dihentikan ditutup sebagai ended dengan alasan.
5. cancelled → scheduled: aksi jadwalkan ulang eksplisit dengan alasan dan tanggal/jam baru di masa depan; reset cancelled marker saat ini, histori lama tetap dalam event. Visibility mengikuti pilihan eksplisit petugas.
6. scheduled → ended: penutupan administratif dengan alasan jika siaran sudah selesai tetapi penandaan terlewat; ID wajib dan live marker tetap NULL. Tidak mengarang waktu mulai aktual.
7. ended terminal untuk status/ID/jadwal acara. Petugas masih dapat memperbaiki metadata publik nonjadwal, menarik visibility, dan menghubungkan/melepas arsip dengan audit. Kesalahan histori status memerlukan prosedur koreksi terpisah, bukan membuka transisi bebas.

### 4.2 Status manual dibanding kondisi YouTube

MVP menyajikan dua konsep: `status` operasional WPiTV dan status YouTube **unknown/belum diverifikasi**. Tidak menyimpan hasil tebakan sebagai actual status. Jam lewat, iframe berhasil dimuat, video diputar, pause, buffering, atau berakhir di perangkat penonton tidak mengubah lifecycle server. Event player mencerminkan keadaan player penonton; desain ini tidak menganggapnya bukti lifecycle broadcast. [Referensi IFrame Player API](https://developers.google.com/youtube/iframe_api_reference).

Jika kelak diperlukan, YouTube Data API dapat memberikan `snippet.liveBroadcastContent` dan `liveStreamingDetails` termasuk actualStartTime/actualEndTime. Tambahan tersebut harus memisahkan observasi platform, waktu pemeriksaan, dan kegagalan pemeriksaan dari status manual. Timeout atau kuota habis berarti unknown, bukan ended; konflik ditampilkan kepada petugas, tidak menimpa keputusan manual secara diam-diam. Tidak menambahkan kolom/API key/job polling platform pada MVP. [Referensi resource Videos](https://developers.google.com/youtube/data/reference/rest/v3/videos).

### 4.3 Lock, versi, dan konsistensi lintas form

Semua perubahan Live maupun Agenda yang sudah terhubung harus melewati satu service koordinasi: `DB::transaction`, kunci **Agenda dahulu → LiveStream kemudian**, validasi ulang actor aktif, expected version, relasi, dan transisi; tulis perubahan beserta event dalam transaksi sama. Untuk aksi pengaitan arsip, kunci Video setelah LiveStream dan verifikasi ulang exact ID sebelum menyimpan. Semua jalur service Live memakai urutan tersebut. Preview/daftar tidak mengunci. CRUD Video legacy dapat berinteraksi melalui FK SET NULL saat penghapusan; deadlock tetap mungkin dan harus membatalkan seluruh operasi dengan feedback retry aman, bukan dianggap tidak mungkin hanya karena urutan service Live konsisten.

Create juga mengunci Agenda dan memeriksa ketiadaan LiveStream; unique agenda_id menangani dua request pembentukan bersamaan. Unique youtube_id/archive_video_id melindungi konflik antarevent. Exception database ditangkap menjadi pesan aman; jangan meneruskan raw SQL/binding atau mencatat seluruh form ke log.

Perubahan linked Agenda melalui form lama harus menaikkan Live version dan menulis event `agenda_updated`; `updated_at` legacy yang presisinya detik tidak cukup sebagai kontrol konflik. Dua aksi bersamaan dengan versi sama: hanya satu mutasi berhasil, lainnya diminta memuat ulang. Versi lama selalu ditolak; setelah timeout UI membaca ulang state dan versi sebelum mengulang. Jika versi saat ini valid dan state/payload sudah identik, aksi menjadi no-op tanpa audit ganda. Tidak menjanjikan receipt exactly-once untuk request ambigu; service tidak membutuhkan token transaksi inventaris atau service inventory.

Saat implementasi, integrasi terbatas pada Agenda yang memiliki LiveStream:

- Form Agenda tidak boleh mengganti type dari live, mengubah jadwal ended, atau mengatur status yang tidak sesuai lifecycle. Aksi status terkait didelegasikan ke service yang sama; batalkan/selesai tidak boleh menjadi update langsung.
- Tombol cancel pada Agenda memakai aturan Live; tombol delete memberi dependency feedback. CRUD Agenda yang tidak terhubung tetap memakai perilaku dan policy existing.
- Penerbitan serta penarikan visibility, perubahan jadwal, ID, dan status menggunakan expected version dari form yang dimuat. Identitas actor, marker waktu, dan field audit selalu diisi server.
- Tidak mengubah ProgramPolicy, VideoPolicy, atau WpitvAgendaPolicy untuk memberi News/TVClub akses Live.

## 5. Validasi URL dan player aman

### 5.1 Parser Live

Gunakan parser murni khusus yang dapat diuji tanpa database. Ambil hanya ID kanonis; jangan menyimpan/memakai input URL mentah sebagai `src`.

- ID langsung harus cocok penuh dengan `\A[A-Za-z0-9_-]{11}\z`. URL input dibatasi 2.048 karakter dan HTTPS.
- Allowlist host persis: `youtube.com`, `www.youtube.com`, `m.youtube.com`, dan `youtu.be`; tolak userinfo, port eksplisit, control character, protocol-relative URL, dan hostname tiruan.
- Terima `/watch?v={id}`, `youtu.be/{id}`, `/live/{id}`, serta `/embed/{id}` dengan segmen ID tepat. Tolak `v` ganda/array, ID berlebih yang terpotong regex, path tambahan, iframe HTML, playlist/channel/handle-live, Studio, serta scheme javascript/data. Parameter tracking/waktu tidak disimpan.
- Shorts tidak termasuk input Live MVP; dukungan Shorts pada CRUD Tayangan existing tetap seperti sekarang. URL embed youtube-nocookie hasil aplikasi bukan input bebas; host output ditetapkan server.
- ID valid sintaks tidak membuktikan video tersedia, milik WPiTV, dapat di-embed, atau benar-benar live. Pemeriksaan tautan oleh petugas dan konfirmasi sebelum publikasi tetap diperlukan; aplikasi tidak fetch URL arbitrary.
- Gunakan perbandingan byte/case-sensitive ketika mencari Video arsip. Jangan bergantung pada collation `ms_video.youtube_id` atau mengubah collation legacy pada fase Live.

Parser Tayangan existing memerlukan hardening tersendiri dengan regresi format lama bila kelak disentuh; hal itu tidak menjadi alasan mengubah source legacy pada fase audit ini.

### 5.2 Kontrak player

Rancang komponen Blade player Live memakai host tetap `https://www.youtube-nocookie.com/embed/{validated_id}`, `playsinline=1`, kontrol pengguna, dan autoplay nonaktif. Reuse pola poster/thumbnail serta rasio video existing, bukan menerima HTML embed admin. Privacy-enhanced mode membatasi personalisasi; tidak menjanjikan tidak ada komunikasi/data pihak ketiga. [Panduan embed YouTube](https://support.google.com/youtube/answer/171780?hl=en).

Escape seluruh teks dengan Blade; deskripsi berupa teks, bukan HTML mentah. Iframe bertitle jelas, allowlist kemampuan seperlunya, fullscreen, dan `referrerpolicy="strict-origin-when-cross-origin"`. Tautan watch dibangun dari ID dengan host YouTube tetap, membuka tab baru menggunakan `rel="noopener noreferrer"`. Kebijakan referrer iframe tidak diganti no-referrer. Tampilkan placeholder jika ID belum tersedia. Konten admin, alasan perubahan, actor, dan data session tidak menjadi atribut iframe atau URL publik.

Player responsif dengan viewport minimal 200 × 200; pada layar sempit gunakan tinggi minimum atau tawarkan tautan eksternal. Plain iframe tidak membutuhkan IFrame JavaScript API untuk MVP. Bila API ditambahkan kemudian, error 100 menunjukkan video tidak ditemukan/private, 101/150 penolakan embed, dan 153 ketiadaan identifikasi Referer/client; pemetaan error untuk UX tidak mengubah status siaran. [Ukuran player dan error API](https://developers.google.com/youtube/iframe_api_reference).

Jangan menyalin parameter `modestbranding` dari player beranda karena sudah tidak berfungsi; `rel=0` juga bukan jaminan menghilangkan seluruh rekomendasi. Jika menggunakan JS API kelak, tetapkan origin aplikasi yang tepercaya dan tinjau CSP script-src secara terbatas. Tidak melonggarkan CSP ke wildcard atau menonaktifkan header keamanan. [Parameter player resmi](https://developers.google.com/youtube/player_parameters).

### 5.3 Keadaan gagal

| Keadaan | Perilaku UI MVP |
| --- | --- |
| Jadwal tanpa URL | Jadwal dan keterangan tautan menyusul; tanpa iframe atau tautan kosong. |
| YouTube belum dimulai | Player dapat menampilkan waiting screen platform; label jadwal/status WPiTV tetap terpisah. Selalu tersedia tautan membuka YouTube dan penjelasan kemungkinan siaran belum dimulai. |
| Ditandai live tetapi video tidak tersedia | Jangan menyatakan verifikasi platform berhasil. Sediakan bantuan umum dan tautan YouTube; petugas memeriksa URL, privacy, dan izin embed. |
| Embed gagal atau dibatasi | Fallback eksternal selalu terlihat; plain iframe tidak dapat membaca error lintas origin secara andal. Jangan menganggap onload sebagai sukses pemutaran atau mengklaim deteksi otomatis. |
| Live selesai, rekaman belum siap | Tampilkan selesai dan arsip belum tersedia; operator menautkan Tayangan setelah memeriksa rekaman. |
| Batal | Tidak ada player; tampilkan keterangan pembatalan publik netral tanpa alasan internal. |
| Database bermasalah | Pesan generik aman; jangan menggantinya dengan klaim tidak ada siaran atau menampilkan SQL. |

## 6. RBAC dan antarmuka admin

### 6.1 Empat role

| Kemampuan | Super Admin | Admin Production | Admin News | TVClub |
| --- | --- | --- | --- | --- |
| Daftar/detail/preview admin Live | Ya | Ya | Tidak | Tidak |
| Hubungkan Agenda, atur ID, jadwal, visibility, status | Ya | Ya | Tidak | Tidak |
| Tautkan/lepas arsip dan lihat riwayat perubahan Live | Ya | Ya | Tidak | Tidak |
| Query/widget/quick action Live di dashboard | Ya | Ya | Tidak | Tidak |
| Program existing | VCED | VCED | Tidak | VCED |
| Tayangan existing | VCED | VCED | VCE | Tidak |
| Agenda existing | VCED | VCED | Tidak | Tidak |

Tidak ada delete Live atau role baru. Penonton anonim hanya mengakses materi yang publik; bukan akun peminjaman/mahasiswa atau role kelima. Admin News tetap boleh mengedit Video, tetapi tidak dapat mengubah jadwal/status/ID siaran Live melalui edit Video. TVClub tetap boleh mengelola Program sesuai matriks, tanpa otomatis mendapat akses Live.

Rancangan `LiveStreamPolicy` memakai actor aktif Super Admin/Production untuk viewAny/view/create/update/changeStatus/manageArchive. Jangan memakai `canPublishVideos()` sebagai gerbang Live karena helper itu juga mengizinkan News. Route admin mempertahankan middleware auth/session.valid/active dan persistent middleware Livewire, dengan pembatasan role SA/Production. Policy dijalankan pada mount, render, serta setiap aksi mutasi; service memverifikasi actor kembali. URL yang ditebak dan pemanggilan aksi Livewire langsung harus tetap ditolak.

### 6.2 Halaman admin yang diusulkan

- `/admin/live-streams`: daftar bergaya Mazer, pencarian judul Agenda, filter Program/status manual/visibility/tanggal, sorting allowlist, pagination existing, empty state, badge sumber status, serta waktu perubahan terakhir WIB.
- `/admin/live-streams/create`: pilih Agenda jenis live yang belum terhubung; tautan ke form Agenda existing bila perlu membuat acara. Tidak menyalin form Program atau membuat jadwal kedua.
- `/admin/live-streams/{liveStream}` dan `/edit`: detail/konfigurasi YouTube, preview, status, peringatan, arsip, dan riwayat. Tautan edit jadwal menuju Agenda; field judul/Program/waktu hanya ditampilkan sebagai data Agenda, bukan disimpan ulang.
- Aksi terpisah: tandai live, akhiri, batalkan, jadwalkan ulang, publik/internal, hubungkan/lepas arsip. SweetAlert2 existing menjelaskan dampak; tombol disable selama request dan versi stale meminta reload.
- Validasi Agenda saat publik: judul <= 200, jenis live, tanggal dan jam mulai wajib, Program opsional benar-benar ada, deskripsi untuk konsumsi publik. Jam selesai opsional; khusus Live, jam akhir lebih kecil berarti hari berikutnya sesuai keputusan MVP Phase 3B. Simpan field/alasan hanya lewat whitelist.

Agenda menyimpan tanggal/jam rencana sebagai waktu lokal **Asia/Jakarta**, mengikuti label WIB existing. Kombinasikan tanggal dan jam dengan timezone eksplisit lalu konversi UTC saat perbandingan; jangan mengandalkan today()/timezone mesin. Keputusan MVP Phase 3B menggantikan batasan awal Phase 3A: untuk Live, jam selesai lebih kecil dari jam mulai secara eksplisit berarti **hari berikutnya WIB**. Jam sama ditolak agar tidak ambigu antara durasi nol dan 24 jam; jam akhir boleh NULL. Durasi rencana yang didukung kurang dari 24 jam, termasuk lintas bulan/tahun. Rentang lebih panjang memerlukan desain lain. Tidak menyimpan salinan tanggal/jam di tabel Live atau mengubah aturan Agenda non-Live.

## 7. Frontend publik dan arsip tanpa duplikasi

### 7.1 Route dan sumber data

Usulan `/live` untuk siaran aktif, jadwal, dan arsip; `/live/{liveStream}` untuk detail jadwal/player. Tidak membuat CRUD, akun, form pengajuan, atau endpoint mutasi publik. Tautan Live ditambahkan konsisten di navigasi desktop/mobile.

Scope publik Live harus menyaring **type=live, visibility=public, dan pasangan status Agenda/Live yang konsisten** pada setiap query, termasuk route detail yang ditebak. scheduled/live memerlukan Agenda scheduled; ended memerlukan completed; cancelled memerlukan cancelled. Internal/draft atau inkonsistensi menjadi 404 pada detail, bukan ditampilkan lalu disembunyikan oleh Blade. Jangan memuat creator, audit, coverage pemohon, group anggota, atau alasan internal di payload publik.

- Bagian aktif: status live menurut WPiTV; jika beberapa, tampilkan kartu dan satu player pilihan pada detail, tidak autoplay seluruh daftar.
- Mendatang: scheduled dengan waktu WIB >= sekarang, urut terdekat. Scheduled yang sudah lewat tetapi belum ditindak operator ditampilkan terpisah sebagai jadwal menunggu pembaruan, tidak hilang tanpa keterangan dan tidak otomatis disebut ended.
- Arsip: ended dengan Video terkait yang masih ada, ID sama persis, dan Agenda publik; kartu menautkan ke `video.show`, bukan membuat halaman rekaman kedua. Gunakan pagination, eager load Agenda/Program/Video secukupnya, dan batas filter tanggal serta sort allowlist. Default 12 kartu per halaman; daftar aktif juga dibatasi/paginasi.
- Detail cancelled publik yang pernah dibagikan tetap dapat menampilkan pembatalan netral; tidak masuk feed aktif/mendatang/arsip. Internal selalu 404.
- Kalender Agenda mempertahankan satu record acara dan mendapat CTA ke Live bila relasinya publik/valid. Agenda live yang belum terhubung tetap tampil sebagai Agenda biasa, tanpa URL yang ditebak.
- Halaman Program dapat menampilkan blok Live melalui Agenda dengan Program tersebut dan scope publik yang sama. Daftar Video Program existing tidak diganti. Program tidak aktif/telah dihapus tidak menghilangkan Live; nama/link Program ditampilkan hanya jika layak ditautkan, dengan fallback identitas WPiTV.

Widget Live beranda harus berada di luar payload cache hero existing, misalnya komponen Livewire terpisah. Detail mengambil state terbaru; polling status database saat halaman terlihat dapat memakai interval sekitar 30 detik, bukan polling YouTube API. Jaga iframe dengan wire:ignore/key agar polling tidak merestart player; perubahan ID/status/visibility harus tetap mengganti atau melepas player. Penarikan publikasi terlihat pada request/poll berikutnya, bukan mencabut media yang sudah termuat seketika. Cache khusus Live, bila kelak dibutuhkan, harus diinvalidasi pada mutasi publikasi/status/arsip; tidak memasukkan data internal ke cache publik. Homepage hero/Tayangan terbaru tetap berfungsi seperti sekarang.

### 7.2 Arsip Tayangan

**MVP tidak membuat Video otomatis.** Setelah ended, petugas memeriksa rekaman YouTube, membuat Tayangan melalui form existing bila belum ada, lalu memilih satu Video dengan ID YouTube yang sama persis. Pengaitan hanya untuk Agenda public/completed dan Live ended. Tidak menyalin judul/deskripsi Video ke Live maupun melakukan sinkronisasi otomatis setelahnya. Field Program Video boleh NULL sesuai skema; bila berbeda dengan Program Agenda, tampilkan peringatan editorial, bukan mengubah data diam-diam.

Unique archive_video_id mencegah satu Video ditautkan ke dua Live; unique youtube_id mencegah dua Live untuk satu broadcast. Duplikasi Video legacy tetap mungkin karena tidak ada unique ID di ms_video: pilih satu arsip kanonis, jangan menambah unique constraint legacy atau melakukan deduplikasi massal. Tidak memodifikasi izin News untuk membuat/edit Tayangan.

Jika News mengubah youtube_id Video yang ditautkan, Live tetap memakai ID sendiri. Sebelum setiap CTA arsip publik, bandingkan ID secara case-sensitive; ketidakcocokan menyembunyikan CTA dan memberi peringatan pada admin Live. Video yang dihapus membuat relasi NULL. Kedua kondisi tidak mengubah lifecycle atau menghapus riwayat Live.

Mengubah Agenda ke internal menyembunyikan Live tetapi **tidak menyembunyikan Video existing**, karena Video tidak punya draft/visibility. UI harus memberi peringatan ini. Jangan mengarsipkan siaran internal melalui form Video publik; kebutuhan rekaman privat memerlukan desain otorisasi publikasi Video terpisah. Halaman Tayangan, Program, dan beranda menggunakan Video kanonis yang sama; tidak membuat salinan konten atau player arsip baru.

## 8. Integrasi dashboard

Tambahkan blok Live hanya pada cabang Super Admin/Production `AdminDashboardMetrics` setelah policy mengizinkan viewAny. Admin News/TVClub tidak membangun query Live sama sekali; menyembunyikan Blade saja tidak cukup.

Ringkasan: jumlah ditandai live, jumlah jadwal mendatang, dan jumlah ended publik yang belum mempunyai arsip valid. Tampilkan maksimal lima jadwal terdekat, urut tanggal/jam Agenda lalu ID sebagai tie-breaker; judul, Program opsional, WIB, dan status manual. Agregasi melalui query terfilter dan eager loading untuk daftar, bukan query per kartu. Peringatan jadwal lewat/status belum diperbarui bersifat operasional, tidak mengubah status atau mengirim notifikasi baru.

Quick action menuju Live, Agenda, dan Tayangan diperiksa policy masing-masing. Menu Live Streaming ditempatkan di Operasional menggunakan Gate yang sama. Jangan mengubah kartu, query, urutan tugas, atau quick action role lain. Empty state membedakan tidak ada jadwal dari kegagalan memuat data; tidak menampilkan alasan internal atau nama petugas pada widget publik.

## 9. Fase implementasi yang diusulkan

| Fase | Hasil kecil yang dapat ditinjau | Verifikasi sebelum fase berikutnya |
| --- | --- | --- |
| 3B — Fondasi backend | Dua migration additive, model/relasi, policy, parser URL, jadwal lintas malam, service lifecycle/konfigurasi/arsip dan audit. Belum menerapkan migration. | PHP lint, Pint, review FK/constraint, unit parser/status/RBAC/UTC tanpa database. |
| 3C — Integrasi penulis Agenda dan transaksi | Hubungkan seluruh penulis Agenda terkait ke service Live dan expected version; dependency feedback untuk cancel/delete. | Konflik dua form, rollback audit, lock order; pengujian database hanya setelah isolasi terverifikasi. |
| 3D — Dashboard admin Live | Daftar, konfigurasi, preview, aksi manual/visibility dan tautan jadwal existing. | Otorisasi ulang Livewire, stale form, validasi, SweetAlert2, konflik unique dan pesan error aman. |
| 3E — Publik | Halaman Live, player aman/fallback, navigasi, CTA Agenda, widget beranda/Program. | 404 internal/draft, timezone WIB, CSP/referrer, mobile, cache/polling, tidak ada audit/actor/payload privat. |
| 3F — Arsip dan dashboard internal | Tautkan Video existing, arsip Live ke route Tayangan, ringkasan/quick action SA/Production. | Exact ID, Video diubah/dihapus, tidak membuat duplikasi, serta nol query Live News/TVClub. |
| 3G — Integrasi dan rilis | Provisioning testing terisolasi yang disetujui, pengujian relasi/concurrency, UAT YouTube, lalu rencana deployment. | Semua gate lulus, backup terbaru terbaca, approval skema/manifest, deployment additive terkontrol dan verifikasi pascarilis. |

Saat schema testing bertambah dari 22 ke 24 tabel, manifest/allowlist guard harus diperbarui melalui prosedur review/approval proyek. Jangan menonaktifkan guard, memakai wpitv sebagai target test, atau memberikan DDL kepada runner DML. Verifikasi ulang identitas koneksi, privilege, serta isolasi session/cache/storage sebelum tes database; laporan fase lama bukan bukti runtime terbaru. Tidak ada provisioning atau tes database dalam fase 3A ini.

Skenario integrasi wajib kelak: create ganda Agenda/ID sama, perubahan status bersamaan dengan edit/cancel Agenda, lock order dan rollback event, stale version, waktu WIB sekitar tengah malam, batas public/internal pada seluruh route, penghapusan dependency, arsip dengan perbedaan case/ID, empat role melalui direct URL dan aksi Livewire, serta query count dashboard. Pengujian aplikasi menggunakan data/platform stub bila perlu; pemeriksaan YouTube riil dilakukan manual untuk upcoming/live/ended/private/embed-disabled tanpa mengandalkan ketersediaan broadcast eksternal dalam suite.

## 10. Risiko dan keputusan yang perlu dikonfirmasi

| Risiko | Keputusan/default rancangan dan tindak lanjut |
| --- | --- |
| Status manual basi atau bertentangan dengan YouTube | Label sumber status dan waktu pembaruan; operator wajib memperbarui. Konfirmasi siapa petugas dan SLA penutupan siaran. API otomatis di luar MVP. |
| Duplikasi jadwal atau konten | Agenda kanonis; satu companion per Agenda, satu broadcast per Live, arsip hanya menautkan Video existing. Jangan melakukan backfill berdasarkan label/type saja. |
| Edit Agenda menjadi jalur bypass status/versi | Seluruh mutasi Agenda terhubung harus memakai koordinasi transaksi. Integrasi ini wajib sebelum fitur dibuka, bukan pekerjaan opsional setelah rilis. |
| Semua Video langsung publik; News dapat mengedit arsip | Tidak membuat Video saat penjadwalan; kontrol exact ID pada CTA. Konfirmasi bahwa arsip MVP hanya publik. Rekaman privat memerlukan desain terpisah. |
| FK legacy berbeda dari default Laravel | Gunakan unsignedInteger untuk FK Agenda/Video/User; hanya FK antartabel baru BIGINT. Jangan menyalin foreignId BIGINT ke PK legacy. |
| Jadwal lintas hari dan timezone | MVP Phase 3B menyepakati akhir lebih kecil sebagai besok WIB, akhir sama ditolak, akhir opsional; UTC untuk timestamp baru dan instant turunan. Jadwal >= 24 jam belum didukung. |
| Lebih dari satu siaran bersamaan | Default diizinkan, publik menampilkan pilihan. Jika organisasi hanya mengizinkan satu, perlu aturan concurrency global yang disepakati; jangan mengandalkan satu tombol UI. |
| Izin embed, video privat/hilang, batasan perangkat, atau jaringan | Selalu sediakan watch link dan penjelasan; tidak menjanjikan autoplay, ketersediaan, atau deteksi error otomatis pada plain iframe. UAT lingkungan deployment wajib. |
| Cache dan publikasi | Isolasi widget Live dari cache beranda. Penarikan visibility berlaku pada fetch/poll berikutnya; tidak dapat menarik kembali video publik YouTube atau Video yang sudah diterbitkan. |
| Penghapusan Program/Video | Pertahankan RBAC dan FK legacy. Program menjadi opsional; archive link dapat terlepas. Jangan membiarkan FK baru mengubah hak editorial News/TVClub. |
| Histori dan penghapusan Live | Default tanpa hard-delete dan dua tabel additive termasuk event; konfirmasi masa retensi event/identitas petugas serta penggunaan cancelled → scheduled sebelum migration dirancang. |
| Konten yang boleh ditayangkan | MVP menerima ID YouTube valid tanpa verifikasi kepemilikan kanal. Konfirmasi SOP pemeriksaan kanal WPiTV oleh operator; allowlist kanal otomatis membutuhkan mekanisme terpisah. |

Keputusan desain yang direkomendasikan untuk MVP: Agenda sebagai sumber jadwal/Program, dua tabel additive tanpa perubahan 22 tabel existing, status manual terpisah dari observasi platform, player iframe aman tanpa Data API, empat role tetap, tidak ada akun publik, arsip lewat Tayangan existing, dan semua waktu tampilan WIB. Dokumen ini tidak menyatakan keseluruhan modul Live telah selesai atau siap rilis.

## 11. Adendum implementasi fondasi Phase 3B

Keputusan pengguna: dua tabel, beberapa Live bersamaan, jadwal lintas tengah malam, status manual, tanpa YouTube API, arsip Video existing, dan tanpa pembuatan Video otomatis. Jadwal lintas malam menggunakan konvensi eksplisit di bagian 6, bukan penambahan kolom tanggal pada 22 tabel existing.

File fondasi:

- `database/migrations/2026_11_09_000001_create_live_streams_table.php` dan `2026_11_09_000002_create_live_stream_events_table.php`: hanya CREATE tabel baru/FK baru, CHECK MySQL khusus lewat SQL di dalam migration; down menolak rollback otomatis untuk mempertahankan histori.
- `app/Models/LiveStream.php`, `LiveStreamEvent.php`; relasi tambahan saja pada `WpitvAgenda`, `Program`, dan `Video`. Timestamps model baru memakai UTC mikrodetik. Event menolak update/delete Eloquent; Live menolak delete. FK audit RESTRICT menjaga histori, FK arsip SET NULL mempertahankan perilaku hapus Video existing. Proteksi model tidak menggantikan pembatasan akses database terhadap query manual/bulk.
- `app/Policies/LiveStreamPolicy.php`: SA/Production aktif; delete Live ditolak untuk semua role. Policy existing tidak berubah dan discovery policy mengikuti Laravel.
- `app/Support/YouTubeVideoId.php`, `LiveStreamSchedule.php`, `LiveStreamState.php`: parser allowlist/ID penuh, output URL server, perhitungan WIB ke UTC termasuk lintas malam, aturan status dan versi murni tanpa database.
- `app/Services/LiveStreamLifecycle.php`: create, updateConfiguration, updateAgenda, transition, linkArchive/unlink; actor dimuat ulang, Gate, lock Agenda → Live → Video bila perlu, expected version, no-op versi saat ini, transaksi dan audit bersama. Satu perubahan status beserta perubahan Agenda terkait dicatat sebagai satu event dengan before/after keduanya. Tidak membuat atau mengubah Video.
- `app/Exceptions/LiveStreamOperationException.php` dan `app/Support/LiveStreamErrors.php`: kode konflik/stale/deadlock/database aman, tanpa previous SQL exception, logging hanya kode error. Boundary ini melindungi pemanggilan service; handler untuk route/read UI Live perlu ditambahkan bersama UI kelak.
- `tests/Unit/LiveStreamingFoundationTest.php`: PHPUnit murni tanpa Laravel bootstrap atau pembacaan .env.testing; resolver PDO sengaja diblokir. Mencakup parser, state/versi, jadwal malam dan tahun kabisat, UTC mikrodetik, policy empat role, relasi/scope melalui kompilasi query saja, arsip, sanitasi exception, dan proteksi model.

**Batas kesiapan pada akhir Phase 3B:** service koordinasi Agenda sudah tersedia, tetapi form save/cancel Agenda existing belum dialihkan. Aturan form Agenda existing juga masih menolak jam selesai lebih kecil. Kedua kebutuhan tersebut ditangani pada tingkat kode dalam Phase 3C.1 di bawah; integritas MySQL lintas form belum diuji. FK baru belum aktif sampai migration benar-benar diterapkan dan diverifikasi.

Migration, seeder, provisioning, tes database, route, halaman admin/publik, menu, dan dashboard Live **tidak dijalankan/dibuat pada Phase 3B**. Pemeriksaan integration/concurrency, CHECK/FK aktual, rollback audit, DDL compatibility, serta guard testing 24 tabel menunggu fase terisolasi yang disetujui.

Hasil verifikasi lokal Phase 3B: PHP lint 15 file lulus; Pint seluruh 15 file lulus; unit test murni **95 tests, 289 assertions**, dengan nol percobaan membuka PDO. Perintah unit: `php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/LiveStreamingFoundationTest.php`. Diff check dan pemeriksaan whitespace file baru dilakukan. PHPUnit/Pint dijalankan di luar sandbox setelah pemeriksaan akses file PHP dalam sandbox ditolak; suite tetap tidak memuat bootstrap Laravel, kredensial, atau koneksi database. Tidak menjalankan migration, termasuk mode pretend.

## 12. Integrasi Agenda — Phase 3C.1

Audit jalur aplikasi menemukan create/update pada `Admin/Agenda/Form` dan cancel/delete pada `Admin/Agenda/Index`; pemilihan status dilakukan melalui form. Tidak ditemukan penulis status Agenda lain pada aplikasi. Pembacaan Agenda pada dashboard/publik tetap memakai query existing. Referensial SET NULL akibat penghapusan Program/Peliputan/Grup tetap mengikuti FK legacy, tanpa perubahan permission atau trigger database.

Perubahan kode:

- `LiveStreamLifecycle::saveAgenda`, `cancelAgenda`, dan `deleteAgenda` mengunci Agenda terlebih dahulu, baru menemukan/mengunci Live jika skemanya tersedia. Actor dimuat ulang dan policy Agenda diperiksa. Bila terhubung, policy Live juga diperiksa dan seluruh perubahan memakai jalur transaksi/audit yang sama dengan service Live existing.
- `performMutation` menyatukan pemeriksaan expected version, konsistensi, penyimpanan, kenaikan versi, dan event. Metadata sekaligus transisi status menghasilkan satu event dengan before/after keduanya. `coverage_id` dan `group_id` ikut whitelist audit sebagai ID saja; objek pemohon, nomor kontak, dan data User tidak disalin.
- `LiveStreamAgendaChanges` memetakan status form: draft/scheduled hanya untuk Live scheduled; cancelled melalui pembatalan lifecycle; completed melalui ended. Live yang sedang berjalan tidak dapat dibatalkan. Penyelesaian tanpa penandaan mulai dan pembatalan membutuhkan alasan; penjadwalan ulang dari cancelled membutuhkan alasan, jadwal lengkap di masa depan, serta visibility eksplisit. Jenis linked Agenda tetap live dan jadwal ended tidak dapat berubah. Metadata nonjadwal yang diizinkan tetap dapat diedit.
- `AgendaLiveGuard` diikat request-scoped pada `AppServiceProvider`. Ketersediaan kedua tabel diperiksa sekali per koneksi per request melalui metadata. Bila keduanya tidak ada, tidak ada query data Live dan Agenda biasa tetap berfungsi. Bila hanya satu ada, perubahan ditolak dengan `LIVE_SCHEMA`; kegagalan metadata tidak dianggap skema tidak ada. Guard tidak menyimpan cache baris atau versi Live.
- `WpitvAgenda::save/delete` melewati guard, termasuk update/saveQuietly/deleteQuietly. Dengan skema tersedia, penulisan langsung mengunci Agenda lalu mengecek Live; linked write ditolak dengan `LIVE_COORDINATION`, linked delete dengan `LIVE_DEPENDENCY`. Ini juga menutup perlombaan penulisan Agenda biasa dengan pengaitan Live baru. Izin persist oleh service hanya berlaku untuk objek tersebut dalam transaksi aktif dan dilepas melalui finally. Query builder bulk/raw SQL manual tetap bukan API yang diizinkan untuk mengubah linked Agenda; guard model tidak mengklaim melindungi operasi yang melewati model.
- Form dan modal Agenda menyimpan expected version sebagai properti Livewire Locked bersama identitas target. Versi hanya dimuat pada mount/pembukaan detail, tidak diperbarui diam-diam saat render. Link yang baru dibuat setelah form dibuka membuat versi NULL ditolak sebagai stale. Render dan mutasi mengotorisasi ulang; role News/TVClub tetap ditolak oleh policy Agenda sebelum akses Live.
- Form Live mendukung rentang malam: akhir lebih kecil berarti besok WIB; jam sama ditolak, jam akhir boleh kosong. Aturan Agenda non-Live tetap menggunakan after_or_equal. Label waktu Live menandai `(+1 hari)`. Jam historis dengan detik dipertahankan bila input menit tidak berubah, termasuk saat mengedit metadata pada ended.
- Blade Agenda menampilkan status/versi, alasan perubahan, pesan stale/dependency, serta pembatasan field. Konfirmasi simpan/cancel memakai fasilitas SweetAlert2 submit existing; feedback aman melalui error bag/alert existing. Tombol hapus linked Agenda dinonaktifkan dan service tetap menolak request langsung. `HandlesLiveStreamErrors` menghentikan konflik aman pada boundary komponen tanpa membocorkan SQL.

File Phase 3C.1: kedua komponen dan Blade `Admin/Agenda/{Form,Index}`, `WpitvAgenda`, `AppServiceProvider`, `LiveStreamLifecycle`, `LiveStreamOperationException`, helper baru `AgendaLiveGuard`/`LiveStreamAgendaChanges`, concern `HandlesLiveStreamErrors`, `tests/Unit/AgendaLiveIntegrationTest.php`, penyesuaian whitelist pada unit foundation, serta dokumen ini. Migration, policy empat role, konfigurasi database, route publik, dan modul lain tidak diubah pada fase ini.

Pengujian database tetap tertunda: penerapan dua migration pada testing yang disetujui, verifikasi 24 tabel dan manifest, koneksi/privilege runner, interaksi Livewire nyata, rollback saat insert audit gagal, dua koneksi dengan commit/lock nyata, perlombaan pengaitan dengan save/delete, stale form/modal, permission yang dicabut, serta pengembalian transaksi pada deadlock. Unit dengan query mock hanya memeriksa orkestrasi/SQL lock order; bukan bukti locking, FK, atau atomicity pada MySQL. Belum ada klaim kesiapan rilis.

Verifikasi Phase 3C.1: **120 unit test murni, 398 assertions lulus** (95 foundation dan 25 integrasi Agenda di folder Unit), PHP lint/Pint 11 file PHP lulus, kedua Blade dikompilasi di memori dan diparse dengan `TOKEN_PARSE`, diff/whitespace check lulus. Suite menjaga nol akses PDO, termasuk saat memeriksa SQL lock melalui mock. Perintah: `php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/LiveStreamingFoundationTest.php tests/Unit/AgendaLiveIntegrationTest.php`. Tidak memuat bootstrap Laravel/.env.testing atau menjalankan migration, seeder, maupun tes database. Tidak mengubah policy Program/Agenda/Video, konfigurasi database, dependency, atau 22 tabel existing.

## 13. Manajemen admin dan Light/Dark Mode — Phase 3C.2

### 13.1 Halaman dan kontrol Live

- Komponen `app/Livewire/Admin/LiveStreams/{Index,Form,Show}.php` beserta Blade di `resources/views/livewire/admin/live-streams/` menyediakan daftar, tambah, edit konfigurasi, detail, dan feedback aman. `/admin/live-streams`, `/create`, `/{streamId}/edit`, dan `/{streamId}` berada pada grup auth/session/active dan role SA/Production. Parameter ID primitif numerik dipakai agar route model binding tidak membaca tabel sebelum pemeriksaan skema. Menu Operasional memakai `LiveStreamPolicy`; News/TVClub tidak mendapat menu, route, atau aksi Livewire.
- Daftar mempunyai pencarian judul/ID YouTube, filter status/Program/visibility/tanggal Agenda WIB, sorting allowlist, tie-break ID, dan pagination 10/25/50. `LiveStreamFilters` membatasi pencarian 150 karakter, memvalidasi tanggal dan rentang berurutan maksimal 366 hari bila kedua batas diisi. Join Agenda hanya satu per Live; relasi Agenda/Program/arsip dimuat eager. Filter invalid menampilkan feedback tanpa membangun query daftar Live.
- Form tambah hanya menawarkan Agenda jenis live berstatus draft/scheduled yang belum terhubung, maksimal 50 hasil dengan pencarian. Jadwal/judul/Program tetap dikelola lewat form Agenda existing. Form edit hanya menyimpan konfigurasi YouTube melalui parser dan service; ikatan Agenda tidak dipindah. Preview memakai URL server youtube-nocookie, ID tervalidasi, viewport minimal 200 × 200, tanpa autoplay/API, dan watch link tetap tersedia.
- Detail menyediakan tandai live, penutupan normal/administratif, pembatalan, jadwal ulang lintas malam dengan alasan dan visibility eksplisit, publik/internal, pengaitan/pelepasan Video existing, serta riwayat berhalaman. Semua mutasi memakai `LiveStreamLifecycle`, policy yang sesuai, dan expected version Locked. Versi hanya ditangkap pada mount atau keberhasilan aksi, tidak disegarkan diam-diam saat render. Riwayat baru kembali ke halaman pertama setelah aksi sukses. Tidak ada edit/delete transaksi histori atau pembuatan Video otomatis.
- Status diberi label manual WPiTV dan kondisi aktual YouTube belum diverifikasi. Judul/deskripsi/jadwal berasal dari Agenda. Audit actor/reason dan before/after hanya muncul pada detail admin berotorisasi, dengan output escaped. Pengaitan arsip memakai pencocokan ID case-sensitive, hasil pilihan dibatasi 50, dan service memverifikasi ulang saat lock; perubahan Video existing tidak menimpa ID siaran.
- `InteractsWithLiveStreams` memeriksa kedua tabel lewat guard sebelum query/mutasi. Bila tidak tersedia atau gagal dibaca, tampilan memberi pesan domain aman dan menyembunyikan aksi. Request mutasi langsung tetap ditolak; tidak ada fallback penulisan model tanpa lifecycle. `LiveStreamErrors` dan mapper di `bootstrap/app.php` menyanitasi read/route/Blade pada jalur Live termasuk endpoint Livewire bersama, menjaga 403/404/validasi, dan tidak menyimpan previous SQL exception. Mapper equipment existing tetap dipertahankan.

### 13.2 Tema admin

Audit menemukan Mazer sudah menggunakan `data-bs-theme`, `initTheme.js`, dan `dark.js` dengan key localStorage umum `theme`; admin.css telah mempunyai sebagian token dark tetapi belum toggle global dan masih ada warna light tetap. Website publik memakai key terpisah `wpitv-v2-theme`.

- `public/js/admin-theme-init.js` menjadi initializer sinkron pada head admin **sebelum stylesheet**. Mekanisme Mazer/Bootstrap `data-bs-theme` dipertahankan; dua initializer Mazer yang bersaing tidak lagi dimuat pada layout admin. Preferensi khusus admin memakai `wpitv.admin.theme`, hanya light/dark valid. Tanpa preferensi, mengikuti OS; pilihan eksplisit dipertahankan pada refresh dan antar-tab. Kegagalan storage tidak merusak halaman atau toggle; pilihan tetap berlaku dalam memori untuk halaman tersebut.
- Navbar `layouts/admin.blade.php` kini tampil pada desktop/mobile, menyediakan ikon matahari/bulan sesuai tema, label aksi aksesibel, dan burger sidebar mobile existing. Tidak mengubah otorisasi, proses bisnis, atau urutan menu role existing selain tambahan Live yang diizinkan.
- Token `admin.css` menghubungkan warna WPiTV dengan variabel Bootstrap untuk canvas, teks, heading, border, surface, form, card, tabel, dropdown/list, modal, pagination, badge/status, button dan focus ring. Komponen khusus Agenda/organisasi dan overlay SweetAlert2 mendapat adaptasi. Kode bisnis Inventaris/Peminjaman tidak diubah; kedua modul mengikuti layout dan token global.
- SweetAlert2 existing tetap menjadi konfirmasi/notifikasi, dengan warna popup dan tombol dari token tema. Sinkronisasi berjalan pada DOMContentLoaded, `livewire:navigated`, storage event khusus admin, serta preferensi OS bila belum memilih tema. CSS diwariskan pada update DOM Livewire tanpa polling atau listener baru per render. Script memakai event delegation dan tidak menggandakan listener ketika dimuat ulang.
- Layout, CSS, JavaScript, key preferensi, dan route publik existing tidak diubah. Tidak ada dependency tambahan, API YouTube, server streaming, atau polling otomatis.

### 13.3 File dan hasil pemeriksaan

20 file sumber dibuat/diubah pada fase ini: tiga komponen Live admin; concern `InteractsWithLiveStreams`; helper `LiveStreamFilters`; perluasan `LiveStreamErrors`; route, navigasi, mapper bootstrap; layout admin; CSS/JS admin; initializer tema statis; empat Blade Live (index/form/show/feedback); `tests/Unit/LiveStreamingAdminTest.php`; `tests/Unit/admin-theme.test.cjs`; dan dokumen ini. Asset Vite merupakan hasil build turunan.

Hasil nyata:

- PHP lint dan Pint: 10 file PHP lulus; diff check lulus. Seluruh Blade Live baru dan layout admin dikompilasi di memori lalu diparse tanpa merender aplikasi.
- Tiga suite PHP murni: **145 tests, 510 assertions lulus**, tanpa bootstrap Laravel/kredensial dan dengan PDO diblokir. Mencakup policy empat role, penolakan direct action News/TVClub/inactive, schema guard, ID/version Locked, filter, sanitizer route/Blade, serta regresi fondasi dan Agenda. Perintah PHPUnit menggunakan `--no-configuration --bootstrap vendor/autoload.php --do-not-cache-result` dan ketiga file unit Live.
- `node --test tests/Unit/admin-theme.test.cjs`: **7 tests lulus**, mencakup init sebelum body, preferensi/key terpisah dari publik, toggle/ikon/aria, refresh, simulasi navigasi Livewire, storage terblokir, perubahan antar-tab/OS, serta rasio kontras minimal 4.5:1 pada pasangan warna inti yang diuji. Ini simulasi DOM, bukan bukti tampilan seluruh halaman pada browser nyata.
- `npm run build` berhasil, 60 modul; warning PostCSS terkait opsi `from` tetap muncul dan tidak menggagalkan build. Tidak mengubah dependency untuk mengatasinya. Sintaks kedua file JavaScript admin lulus.
- Browser QA dicoba memakai computer-use pada Chrome existing, tetapi alat menghentikannya karena tidak dapat memastikan URL browser Windows dengan keyakinan yang cukup. Tidak melanjutkan kontrol browser atau mencoba melewati penghentian tersebut. Tidak ada hasil QA visual desktop/mobile, sesi autentikasi, SweetAlert nyata, atau navigasi Livewire nyata yang diklaim lulus.

**Status kesiapan:** implementasi kode dan quality gate statis/unit/build selesai; belum READY untuk rilis. Database/migration/seeder tidak dijalankan dan skema Live/runtime testing tidak diverifikasi pada fase ini. Integrasi MySQL, rollback audit, concurrency, UAT lifecycle/arsip, serta QA tema pada dashboard, Agenda, Live, Inventaris, dan halaman admin lain tetap memerlukan lingkungan dan sesi browser yang terverifikasi. Frontend Live publik serta widget KPI Live dashboard bukan bagian fase ini.

## 14. Integrasi ringkasan Live — Phase 3E

Homepage membaca highlights publik di luar cache hero existing; prioritas manual Live, fallback jadwal WIB terdekat, lalu empty/error state. Program menambahkan kelompok Live/scheduled/arsip melalui Agenda dengan scope publik dan pagination; arsip menuju Video existing. Dashboard memakai service khusus dengan policy guard sebelum query: hanya Super Admin/Production, tiga KPI, maksimum lima jadwal, maksimal empat query Live dan nol query Live bagi News/TVClub. Tidak mengubah Featured Video Selector, lifecycle, schema atau widget equipment. Hasil implementasi, file, tes dan batas QA tercatat pada bagian Phase 3E di [LIVE_STREAMING_VERIFICATION.md](LIVE_STREAMING_VERIFICATION.md).
