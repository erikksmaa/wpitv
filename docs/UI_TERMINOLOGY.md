# Panduan Terminologi & Microcopy UI WPiTV

Dokumen ini menjadi acuan standar bahasa antarmuka WPiTV agar teks pada panel admin dan portal publik konsisten, natural, ringkas, dan mudah dipahami.

## Prinsip Bahasa

1. Gunakan bahasa Indonesia baku yang natural, ringkas, dan langsung pada maksud.
2. Pertahankan istilah teknis/media yang sudah lazim digunakan operator: `Dashboard`, `Live Streaming`, `Thumbnail`, `Username`, `TVClub`, `Reels`, `Stories`, `Carousel`, `Engagement Rate`, serta nama platform resmi (`YouTube`, `Instagram`, `TikTok`, `Facebook`).
3. Jangan menerjemahkan pengenal internal: nama tabel/kolom database, route, nama class/method, key enum/permission/event, dan data yang diinput pengguna.
4. Pertahankan penamaan sesuai dokumen atau templat resmi bila bersifat formal (misalnya judul resmi `Laporan Aktivitas Media Sosial Bulanan` dan bagian-bagian templat resmi).

## Glosarium Standar

| Istilah Sebelumnya / Variasi | Standar UI WPiTV | Catatan |
| --- | --- | --- |
| `Dasbor` | `Dashboard` | Judul halaman, sidebar, breadcrumb |
| `Inventory` / `Equipment` (konteks UI) | `Inventaris` / `Peralatan` | Sidebar, breadcrumb, label laporan, pesan kosong |
| `Audit Log` | `Riwayat Aktivitas` | Label menu, judul panel, tab |
| `Maintenance` | `Perawatan` | Status & aktivitas unit peralatan |
| `Retired` | `Nonaktif Permanen` | Status unit peralatan |
| `Upcoming` | `Jadwal Mendatang` | Status siaran / agenda |
| `Draft` (UI admin & konfirmasi) | `Draf` | Label status, tombol simpan, pesan draf WhatsApp |
| `Export` | `Ekspor` | Tombol dan deskripsi ekspor |
| `Download` | `Unduh` | Tombol unduh berkas/laporan |
| `Upload` | `Unggah` | Label input berkas, indikator proses |
| `Preview` (umum) | `Pratinjau` | Kecuali frasa teknis yang sengaja dipertahankan atau diuji khusus |
| `Role` | `Peran` | Label form, pesan validasi/flash, placeholder |
| `User` | `Pengguna` | Label tabel, filter, pesan sukses/galat, badge `Anda` |
| `Pelaku` (riwayat transaksi) | `Petugas` | Kolom pelaku pada riwayat aktivitas transaksi |
| `File` / `Cover` | `Berkas` / `Sampul` | Input upload gambar & berita/program |
| `Password` / `Log in` / `Profile` / `Save` / `Cancel` | `Kata Sandi` / `Masuk` / `Profil` / `Simpan` / `Batal` | Halaman autentikasi & profil |

## Aturan Microcopy

- **Form & Validasi**: Gunakan label singkat, placeholder contoh yang jelas, dan pesan galat spesifik dekat field terkait.
- **Konfirmasi Aksi Berisiko**: Gunakan kalimat tanya jelas yang menyebut dampak tindakan (misalnya penghapusan permanen atau perubahan status).
- **Empty State**: Jelaskan kondisi kosong secara ringkas beserta langkah berikutnya bila relevan.
- **Panduan Phase 2 (Publik)**:
  - Nada editorial kampus: komunikatif, ringkas, informatif, tidak kaku, tidak birokratis, serta meminimalkan kata ganti langsung (`Anda`/`kamu`) apabila kalimat aktif/netral sudah jelas.
  - Bedakan konsisten empat konsep media: **Program Acara** (kelompok program), **Tayangan** (katalog/episode video), **Berita** (artikel jurnalistik), dan **Siaran / Live Streaming** (siaran langsung & jadwal tayang).
  - CTA spesifik sesuai aksi: `Tonton Tayangan`, `Tonton Siaran`, `Baca Berita`, `Lihat Semua Program`, `Jelajahi Tayangan`, `Ajukan Peliputan`, `Cek Status`, `Reset Filter`, `Kembali ke Beranda`.
  - Istilah publik diselaraskan: `Pascaproduksi` (bukan `Post-Production`), `Peninjauan` (bukan `review` berulang pada alur Peliputan), `Tayangan Lepas WPiTV` (bukan `WPiTV Special`), serta pertahankan istilah akrab (`Live Streaming`, `On Air`, `TV Club`, `Broadcasting`, `Podcast`, platform sosial, dan slogan resmi `Brings The World Into You`).
  - Halaman galat publik (`resources/views/errors/`) memakai bahasa Indonesia singkat yang aman tanpa detail internal/stack trace.
