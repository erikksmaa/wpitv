# WPiTV — Project Handover

Modernisasi portal publik dan operasional WPiTV berbasis Laravel/Livewire dengan database existing. Baseline dokumentasi: **25 November 2026**, branch `rebuild/wpitv-clean`, termasuk perubahan lokal yang belum commit.

## Mulai membaca

1. [AGENTS.md](AGENTS.md): aturan kerja AI, keamanan data, pengujian dan Git; berlaku juga bagi Google Antigravity.
2. [PROJECT_CONTEXT.md](docs/PROJECT_CONTEXT.md): tujuan, aktor, modul dan alur bisnis.
3. [ARCHITECTURE.md](docs/ARCHITECTURE.md): versi aktual, struktur kode/database, authorization, media dan setup lokal.
4. [DEVELOPMENT_STATUS.md](docs/DEVELOPMENT_STATUS.md): status seluruh modul, tes, blocker SQL/config dan identitas commit.
5. [ROADMAP.md](docs/ROADMAP.md): prioritas tersisa dan acceptance criteria.
6. [DECISIONS.md](docs/DECISIONS.md): kontrak aktif, alasan dan rancangan yang digantikan.

## Menjalankan dan melanjutkan

Ikuti [setup lokal](docs/ARCHITECTURE.md#menjalankan-secara-lokal) setelah dependensi, konfigurasi privat dan schema yang diotorisasi tersedia. Jalankan `php artisan serve` dan `npm run dev`, atau `npm run build` untuk aset statis. Portal tersedia pada `/`, login pada `/login`, admin pada `/admin` di URL server lokal.

Migration repository bukan bootstrap seluruh database. Jangan menjalankan reset/migration/seeder otomatis pada sumber. Media Sosial masih memiliki SQL dan reviewer tertunda; keberadaan halaman/kode tidak berarti siap digunakan. Detail verifikasi ada pada DEVELOPMENT_STATUS.

Dokumen deployment dan checklist lama tetap tersedia sebagai referensi historis/operasional. Scope tugas terkini dan AGENTS.md mengatur tindakan; tidak ada izin commit, push, SQL atau deployment yang tersirat dari dokumentasi ini.
