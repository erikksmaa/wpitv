# Roadmap Lanjutan WPiTV

Baseline **25 November 2026**, `rebuild/wpitv-clean`. Ini prioritas pekerjaan tersisa berdasarkan [status aktual](DEVELOPMENT_STATUS.md), bukan izin DDL/deploy atau daftar fitur untuk dibangun ulang.

## Phase yang sudah diimplementasikan

| Phase/domain | Hasil tersedia | Batas status selesai |
| --- | --- | --- |
| Modernisasi core | Portal publik, konten program/tayangan/berita, peliputan, TVClub/organisasi, Agenda, auth/RBAC/dashboard/statistik | Implementasi ada; QA dan kesiapan rilis keseluruhan masih memiliki pekerjaan tersisa. Banner retired. |
| Rebuild Live, 21 November | Live manual/Agenda, lifecycle/arsip/highlights | Implemented dan tested pada baseline rebuild; modul Aktivitas Bulanan telah retired dari kode aplikasi pada 26 November. |
| Equipment Inventory Phase 1, 23 November | Kategori/model/unit, stok turunan, lifecycle, foto privat, audit/idempotency | Implemented + unit/smoke historis; concurrency terisolasi belum selesai. |
| Equipment Loans Phase 2, 24 November | Checkout, retur parsial/bukti, extend/loss/void dan histori | Implemented + unit/smoke historis; jangan merencanakan checkout/retur lagi sebagai fitur kosong. |
| Equipment Reports | Dashboard/ringkasan, inventaris/peminjaman, periode checkout/retur, CSV dan drilldown | Implemented pada HEAD + perubahan lokal; validasi perubahan terbaru masih perlu. |

## Phase sedang dikerjakan pada working tree

- Polish Equipment: pemisahan Inventory categories/equipment/activity, helper validasi/form/gallery/unit photo, laporan/filter, penanganan foto, UI pinjam/retur, perbaikan drilldown laporan, pemetaan bahasa aktivitas, dan panduan operator. QA browser langsung dan uji concurrency terisolasi masih perlu dijalankan.
- Live Streaming: peningkatan label status/riwayat berbahasa Indonesia, validasi preview form, dan konsistensi UI admin/publik telah diterapkan; QA browser langsung masih perlu dijalankan.
- Social fase awal: katalog akun/publikasi, workflow laporan empat platform, helper pengisian followers, fallback schema belum siap, dan dokumen berdasarkan template resmi sudah ada. SQL/config reviewer dan pembuktian MySQL/UAT belum selesai.
- Handover lintas modul: enam dokumen konteks ini menjadi titik masuk agent baru; perubahan dokumentasi belum di-commit.

## Prioritas berikut dan acceptance criteria

### P0 — Menyelesaikan kesiapan Media Sosial

Pekerjaan: review `database/sql/social_media_phase1.sql` terhadap schema target; siapkan backup/clone dan rencana pemulihan pada scope yang disetujui. Terapkan hanya setelah ada otorisasi terpisah. Tetapkan manager/reviewer berbeda secara privat; periksa kebutuhan UI followers/koreksi sebelum menambah fitur.

Acceptance criteria:

- Tabel reports ada dengan FK/UNIQUE sesuai kontrak; empat platform diterima, URL kosong ditangani; constraint lama tetap berlaku. Bukti metadata dan negatif MySQL tersedia pada target uji.
- Manager/reviewer aktif yang diotorisasi dapat mengakses fungsi masing-masing; role tanpa penugasan ditolak; self-review dan stale version ditolak.
- Draft → submit → revision → resubmit → approved berfungsi pada MySQL terisolasi; snapshot/riwayat tidak berubah oleh edit master setelah submit.
- Agregasi periode WIB, nol vs kosong, ER/growth, jenis publikasi dan referensi best/lowest cocok dengan data uji.
- Preview draft diberi status yang tepat, print/XLSX resmi hanya approved; logo, periode, nilai, analisis panjang dan penanda tangan cocok template resmi. Hasil cetak diperiksa visual.
- Tidak mengekspor identitas privat atau membuka akses otomatis Super Admin; SQL/config/run hasil dicatat pada DEVELOPMENT_STATUS.

### P1 — Menutup regresi Equipment dan laporan terbaru

Pekerjaan: sediakan clone/akun/storage/manifest Equipment terisolasi tanpa melemahkan guard; uji perubahan lokal yang belum tercakup laporan 23–24 November.

Acceptance criteria:

- Dua koneksi bersaing checkout/retur/extend/loss membuktikan locking, UNIQUE open_unit_id, konflik versi, retry dan replay menghasilkan satu efek/audit yang benar.
- Stock/available tidak memasukkan unit dipinjam, rusak, maintenance/lost/retired atau model tidak aktif; warning integritas tidak ditutupi.
- Filter laporan checkout vs retur, batas hari WIB, pagination, ringkasan dan CSV memakai cohort yang sama; export permission dan formula-injection protection diuji.
- Menu Inventory terpisah, galeri/foto berorientasi benar, validasi inline, keranjang lintas halaman dan retur parsial lolos QA desktop/mobile serta penolakan role News/TVClub.
- Bukti privat tidak dapat diakses lewat loan/unit lain; tidak ada penghapusan media historis; fixture dan runtime tes bersih sesuai ownership.

### P2 — QA integrasi seluruh modul dan konten resmi

Pekerjaan: regresi terarah modul terdampak, lintas role dan jalur publik/admin; prioritas integrasi Peliputan → Agenda → Live → kandidat Monthly. Jadwalkan suite luas pada tugas validasi tersendiri bila diotorisasi, bukan pada handover ini.

Acceptance criteria:

- Semua modul pada PROJECT_CONTEXT memiliki checklist skenario normal/empty/error/forbidden serta hasil yang terkait commit + perubahan lokal tertentu.
- Responsif, keyboard/focus/label, kontras, dark mode, editor, konfirmasi/toast, chart dan pemutar diuji pada perangkat representatif.
- Visi/misi/sejarah/pengelola memakai informasi resmi pemilik; data yang belum disetujui tidak dibuat-buat.
- Lighthouse/Core Web Vitals dan ukuran asset memiliki baseline terukur; prioritas perbaikan berbasis hasil, bukan dugaan berat.
- Status SEO canonical/OG/sitemap/robots dan player lintas browser dicatat setelah inspeksi, tidak otomatis dianggap hilang hanya dari checklist lama.

### P3 — Checkpoint dan kesiapan rilis

Pekerjaan: setelah izin Git/release diberikan, review file eksplisit, pisahkan perubahan sesuai domain dan siapkan staging/backup/restore/config production.

Acceptance criteria:

- Seluruh artefak yang dibutuhkan agent/deploy tersedia pada checkpoint; tidak menyertakan secret, data pribadi, backup/media privat atau dependencies.
- Git bersih pada checkpoint yang disepakati; status SQL/config sesuai target, tidak disamakan dengan baseline lokal.
- Konfigurasi URL/HTTPS/session/cache/queue/mail/storage dan permission filesystem diverifikasi; restore drill dan rollback plan terbukti.
- Staging UAT publik/semua role/workflow Equipment/Monthly/Social lulus, kemudian deployment dan smoke production dilakukan hanya dalam scope persetujuan rilis.

## Di luar scope tanpa keputusan baru

Jangan memulihkan Equipment arsip, mengaktifkan Banner, mereset/migrasi histori sumber, menambah portal peminjam/approval Equipment, menyatukan kedua laporan bulanan, atau membuat integrasi API Social/mesin streaming hanya karena terlihat sebagai kelanjutan alami. Setiap perluasan tersebut memerlukan kebutuhan dan acceptance criteria tersendiri.
