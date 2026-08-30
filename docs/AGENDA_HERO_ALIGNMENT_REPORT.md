# AGENDA HERO ALIGNMENT REPORT

Tanggal: 30 Agustus 2026. Lingkup perubahan aplikasi: hero publik Agenda saja.

## 1. Initial Difference

Markup lama menggunakan `public-agenda-hero`, eyebrow tanpa badge, judul dengan aksen `<em>WPiTV</em>`, dan tanpa breadcrumb. Style lama menggunakan judul `clamp(2.7rem, 7vw, 5.2rem)` serta padding hingga 128 px, berbeda dari hero ringkas halaman listing. CTA juga tidak mendapat perlakuan putih khusus `.pub-hero`.

## 2. Reference Pages

Implementasi Tentang, Program, Tayangan, Berita, Peliputan, dan TV Club diperiksa. Acuan utama untuk ukuran dan struktur adalah Program/Tayangan/Berita; Tentang menjadi acuan gradasi, badge, orbit, dan CTA bersama. Peliputan dan TV Club memiliki hero editorial lebih besar sehingga ukuran keduanya tidak disalin ke halaman kalender. Tentang, Peliputan, dan Program dibandingkan langsung melalui browser dalam tema terang dan gelap.

## 3. Structure Alignment

Hero kini menggunakan `pub-hero`, dekorasi `hero-deco`, `pub-container`, breadcrumb, `hero-eyebrow`, `hero-title`, paragraf pendukung, lalu CTA. Section dihubungkan dengan judul melalui `aria-labelledby`. Tidak ada komponen, fitur, atau CSS baru.

## 4. Breadcrumb

Ditambahkan **Beranda / Agenda**, mengikuti markup dan utility class Program/Tayangan/Berita. Tidak tersedia komponen breadcrumb publik bersama. Link Beranda menggunakan route yang sudah ada; Agenda memakai `aria-current="page"` dan separator dekoratif disembunyikan dari pembaca layar.

## 5. Eyebrow

Teks tetap **Kalender Publik WPiTV**. Menggunakan badge bersama: 12 px, semibold, uppercase, tracking lebar, border transparan, radius pill, dan titik cyan bawaan `.pub-hero`. Listing memiliki override titik oranye tersendiri; Agenda memakai varian cyan bersama seperti keluarga hero Tentang, tanpa membuat override baru.

## 6. Title Typography

Judul tetap **Agenda WPiTV**, berwarna putih tanpa split-color/italic. Mengikuti `hero-title` dengan Plus Jakarta Sans, weight 800, tracking rapat, ukuran 36/40 px pada desktop/tablet dan 30/36 px pada mobile, sama dengan ukuran hero listing publik.

## 7. Subtitle

Copy tidak diubah. Lebar dibatasi `max-w-2xl` (672 px), ukuran 16 px pada desktop dan 14 px pada mobile, line-height relaxed, warna `--text-muted` yang diwarisi dari hero, serta jarak 24 px sebelum CTA.

## 8. CTA

**Lihat Agenda Terdekat** tetap menuju `#agenda-terdekat`. Menggunakan `pub-btn pub-btn--primary`: permukaan putih, teks biru, radius 10 px, padding 12/20 px, hover dan focus styling bersama. Tinggi terukur 44 px; lebar otomatis sekitar 192 px. Target anchor diverifikasi melalui browser. Tidak ada perubahan JavaScript atau perilaku navbar.

## 9. Background / Decoration

Menggunakan gradasi biru 135 derajat, radial glow, serta dua orbit tipis dari `.pub-hero`/`.hero-deco` yang sudah ada. Mode gelap mendapat gradasi navy bawaan. Dekorasi berada di belakang konten dan dipotong oleh overflow hero, tanpa menimbulkan scroll horizontal halaman.

## 10. Hero Height & Spacing

Padding atas/bawah menjadi 64 px pada breakpoint md ke atas dan 48 px pada mobile. Tinggi terukur sekitar 344 px pada desktop/tablet dan 330 px pada mobile. Jarak breadcrumb 16 px, eyebrow ke judul 12 px, judul ke subtitle 12 px. Jarak menuju Agenda Terdekat memakai section spacing yang sudah ada, tanpa overlap atau margin tambahan.

## 11. Container Alignment

Hero memakai container publik 1240 px yang sama dengan Agenda Terdekat, kalender, dan halaman referensi. Tepi konten ketiganya sejajar pada seluruh viewport yang diuji. Padding mengikuti CSS bersama: 32 px desktop, 20 px pada 768 px, dan 16 px pada mobile kecil.

Catatan bawaan proyek: navbar memakai maksimum 1400 px, sehingga tepi logo navbar tidak selalu identik dengan container isi 1240 px pada desktop lebar. Lebar/navbar tersebut tidak diubah karena di luar lingkup; Agenda mengikuti grid konten publik yang sudah ada.

## 12. Mobile

430x932 dan 390x844 diperiksa. Breadcrumb dan badge muat dalam satu baris, judul tetap satu baris, subtitle membungkus alami, tombol muat tanpa dipaksa full-width, dan tidak ada scroll horizontal. Hero tidak memakai tinggi minimum desktop.

## 13. Dark Mode

Judul putih, subtitle terang/muted, breadcrumb cyan, badge transparan, serta CTA putih tetap terbaca. Warna hero berasal dari token bersama; tidak ada teks hitam di atas navy pada hero. Style mode gelap global tidak diedit.

## 14. Files Modified

- `resources/views/livewire/public/agenda/index.blade.php`: hanya blok hero pertama.
- `docs/AGENDA_HERO_ALIGNMENT_REPORT.md`: laporan ini.
- Bukti QA, snapshot sebelum edit, patch lokal, log, dan screenshot disimpan di `storage/app/agenda-hero-qa/` (diabaikan Git).

Perbandingan snapshot membuktikan isi dari section `agenda-terdekat` sampai akhir file identik. Tidak ada edit oleh task ini pada query, kalender, controller/Livewire, model, route, database, admin, homepage, Peliputan, Organization, Global Search, navbar, footer, atau sumber CSS/JS bersama.

## 15. Browser QA

Pengujian akhir memakai halaman nyata `http://localhost:8000/agenda`, bukan hanya preview statis.

| Viewport | Tema | Tinggi hero | Judul | Hasil hero |
| --- | --- | --- | --- | --- |
| 1440x900 | Light + dark | ~344 px | 36 px | Lulus |
| 1280x720 | Light + dark | ~344 px | 36 px | Lulus |
| 1024x768 | Light + dark | ~344 px | 36 px | Lulus |
| 768x1024 | Light + dark | ~344 px | 36 px | Lulus |
| 430x932 | Light + dark | ~330 px | 30 px | Lulus |
| 390x844 | Light + dark | ~330 px | 30 px | Lulus |

Screenshot tersedia sebagai `storage/app/agenda-hero-qa/agenda-{width}-{light|dark}.png`; pengukuran tersimpan di `browser-qa.json`. Semua ukuran bebas overflow horizontal halaman. Orbit yang melampaui batas internal hero memang dipotong oleh `overflow: hidden`, sesuai implementasi referensi.

## 16. Tests / Build

| Validasi | Hasil |
| --- | --- |
| Tiga tes yang langsung memeriksa halaman Agenda | 3 passed, 21 assertions |
| Seluruh `AgendaPublicTest` setelah cache diperbarui | 3 passed, 1 failed; kegagalan pada teaser homepage |
| `php artisan test --no-output --log-junit=...` | JUnit mencatat 532 tests, 1745 assertions, 23 failures, 0 errors (~52.8 detik) |
| `php vendor/bin/pint --test` | Passed |
| `npm run build` | Passed |
| `php artisan view:clear` | Passed |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed |
| Perbandingan konten non-hero terhadap snapshot awal | Identik |

Kegagalan suite lengkap: Authentication (2), PasswordConfirmation (2), PasswordUpdate (2), AboutPage (4), Homepage (11), teaser homepage di AgendaPublicTest (1), dan OrganizationStructurePublicTest (1). Kegagalan teaser terakhir mengharapkan `Homepage Agenda 1` tampil; bukan kegagalan halaman `/agenda`. Detail tersimpan dalam `tests-after.xml` dan `agenda-final.xml`.

Seluruh kasus suite telah selesai dan menghasilkan XML JUnit lengkap. Proses formatter terminal yang masih menulis dump HTML panjang dihentikan setelah hasil JUnit dibaca; hasil suite tidak dinyatakan hijau.

## 17. Remaining Issues

- Suite proyek belum hijau; kegagalan di luar hero tidak diperbaiki dalam task ini.
- Navbar bawaan tampak padat/bertumpuk pada 1024 px dan memakai grid lebih lebar daripada konten pada desktop. Tidak disentuh sesuai batas pekerjaan.
- Saat audit, banyak file proyek dipulihkan/diubah oleh proses lain, termasuk route dan CSS. Audit referensi diulang dan QA akhir dilakukan setelah `/agenda` tersedia. Perubahan tersebut bukan hasil task ini dan membuat baseline suite awal tidak cocok sebagai perbandingan commit yang bersih.
- CSS hero Agenda lama yang kini tidak dipakai dibiarkan agar task ini tidak mengubah file CSS bersama yang sedang disentuh proses lain.

## 18. Final Verdict

**Penyelarasan hero selesai dan lulus pemeriksaan visual dalam lingkupnya.** Hero memakai sistem publik bersama, lebih ringkas, responsif, dan terbaca dalam kedua tema. Kalender serta business logic tidak berubah. Proyek secara keseluruhan belum dinyatakan READY karena kegagalan tes dan masalah navbar di luar lingkup di atas. Pekerjaan berhenti pada hero Agenda.
