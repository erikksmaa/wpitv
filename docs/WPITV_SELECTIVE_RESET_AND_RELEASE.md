# WPiTV Selective Reset and Clean Release

Status: **PARTIAL — belum commit/push**, 21 November 2026.

## Git baseline

Fetch origin berhasil. main dan origin/main = `9a35b6725495a828c5ff3372f62f277cbb9d829c`; tidak ada commit main lokal yang belum dipush; index awal kosong. 32 tracked files berubah, ditambah source untracked. Implementasi Equipment tidak ditemukan pada baseline remote, sehingga tidak diperlukan commit penghapusan Equipment dari remote.

Branch kandidat `codex/clean-live-monthly-release` dibuat dari origin/main pada `storage/app/testing/clean-release`. HEAD masih baseline; perubahan kandidat belum di-stage atau commit. Tidak ada push ke main atau branch lain. Workspace utama sengaja dipertahankan sesuai instruksi, tidak di-reset dan tidak dihapus. Kandidat belum dikonfigurasi sebagai aplikasi aktif.

## Snapshot pemulihan

Snapshot lokal: `storage/app/release-safety/20261121-190619/`.

- `repository.bundle`: semua Git refs/history; `git bundle verify` lulus.
- `source.zip`: 6.760 file tracked dan untracked nonignored, diverifikasi CRC dan SHA-256 setiap entry terhadap manifest.json.
- `tracked.patch`: diff binary terhadap HEAD termasuk tracked working changes.
- `staged.patch`: kosong karena index awal kosong.
- `manifest.json`: SHA awal, branch, remote, daftar file/hash; `status.txt`: status awal.

Snapshot bukan backup database dan tidak mengandung file ignored seperti .env, vendor, database privat atau media ignored. Berkas ignored yang ada tetap di workspace asli. Pemulihan dilakukan pada direktori baru: clone repository.bundle, checkout SHA awal, kemudian ekstrak source.zip di clone tersebut dan cocokkan setiap hash manifest. Jangan ekstrak menimpa workspace aktif tanpa review. Snapshot dapat dibaca dan hash source diverifikasi; aplikasi hasil restore snapshot belum diuji.

## Pemetaan dan pemindahan

`WPITV_RELEASE_FILE_MANIFEST.json` mencatat pemetaan awal per file: LIVE_ONLY, MONTHLY_REPORT_ONLY, EQUIPMENT_ONLY, SHARED, OTHER_UNRELATED. Pemetaan adalah daftar review kepemilikan, bukan instruksi hapus atau klaim semua file telah diintegrasikan. Kode unrelated, termasuk tambahan TVClub Social yang belum ada di remote, tidak dibawa; TVClub baseline remote dipertahankan.

Kandidat mempertahankan source Live admin/public, lifecycle, status, YouTube, preview, archive, optimistic locking, policy, Agenda/Program/Video integration dan dashboard; source Monthly mencakup model, lifecycle/editor, approval/version, attachment, download/CSV/print, policy, navigation dan dashboard. Mempertahankan sembilan migration terpisah Live/Monthly termasuk manual Live. Tidak ada migration campuran Equipment/Live yang ditemukan.

Shared files direkonstruksi hanya bagian relevan: routes, navigation, AdminDashboardMetrics, exception mapping bootstrap, dashboard Blade, UTC cast/timestamp concern, admin theme CSS/JS/layout dan public Live integration. Dashboard component dan quick actions tetap baseline remote karena modifikasi lokalnya khusus Social/Equipment. Authentication/RBAC inti dan modul lain tidak diubah.

Dependency nyata MonthlyActivityDocumentController pada EquipmentReports::csvCell dipisahkan menjadi App\Support\CsvCell::sanitize dengan aturan formula injection dan invalid UTF-8 yang sama. Tidak membawa service Equipment demi helper ini.

## Equipment yang tidak dibawa

Kategori/item/unit/stock, POS checkout, returns/loss/recovery, borrowing reports, audit Equipment, compatibility/backfill, model/service/policy/exception, routes/menu/dashboard/views/tests/media handling/migration Equipment dan dokumen implementasinya tidak disalin ke kandidat. Tidak ada perubahan penghapusan pada source asli, DB atau media. Kandidat tidak memakai runtime fallback legacy. Tidak ada aplikasi kandidat kedua yang diaktifkan.

## Quality gates aktual

| Gate | Hasil |
| --- | --- |
| PHP lint | 218 file, nol syntax error sebelum format |
| Unit kandidat dengan class loader terarah ke kandidat | 128 tes / 567 assertion lulus: LiveStreamingFoundationTest, MonthlyActivityFoundationTest, AgendaLiveIntegrationTest |
| JavaScript admin theme | 7 tes lulus |
| Vite | Lulus; warning PostCSS `from` existing |
| Pint | Recheck seluruh app/routes/database/tests/bootstrap lulus, termasuk source untracked |
| Composer validate / install | Pemeriksaan lanjutan: validate dan install --no-scripts lulus pada kandidat; hanya Livewire berubah 4.4.0 → 4.4.9. Klaim awal bahwa baseline tidak memuat Livewire dikoreksi oleh diff aktual |
| Diff whitespace | Lulus pada tracked candidate diff |
| Feature/Livewire, browser, security, dashboard smoke | Belum dijalankan; membutuhkan clone dan dependency installation yang sah |
| Fresh migration clone | **BLOCKED**: remote baseline tidak memiliki migration schema inti; DEPLOYMENT.md mensyaratkan import SQL dump eksternal |
| Route list / Blade compilation pada runtime kandidat | Lulus; 86 route, nol route inventory/equipment. Konfigurasi proses memakai SQLite :memory:, bukan database aktif |

Tidak ada tes mutatif pada wpitv atau wpitv_testing. Pengujian awal memakai vendor existing; pengujian lanjutan memakai vendor kandidat sendiri setelah composer install berhasil, tetap 128 tes / 567 assertion. Package discovery dan kompilasi Blade juga lulus. Tidak ada database clone baru yang dibuat karena jalur fresh schema belum tersedia.

## Dependency tests yang belum aman dipisahkan

`WPITV_BLOCKED_TESTS.txt` berisi delapan test/worker files yang ditahan dari kandidat, tetapi tetap utuh dalam snapshot dan workspace asli. LiveScenarioTestCase mewarisi EquipmentScenarioTestCase, browser Live/Monthly mewarisi EquipmentBrowserTest; integration Monthly memakai EquipmentScenarioTestCase. LiveStreamingAdminTest juga mempunyai dependency Equipment. Menyalin harness tersebut melanggar clean-release contract; menghapus coverage lalu menyatakan rilis lengkap juga tidak dibenarkan. Kandidat **belum lengkap sebagai release teruji**.

## Langkah lanjutan sebelum commit dan push

1. Pisahkan harness Live/Monthly dengan penjagaan identitas clone, fixtures users/roles, ownership cleanup dan multiprocess race tetap setara; port seluruh tes yang ditahan, jangan membawa Equipment.
2. Rekonsiliasi composer.lock dengan requirements existing, review versi/dependency/transitive diff, lalu install bersih dan validasi.
3. Sediakan schema inti baseline yang otoritatif atau migration baseline yang ditinjau. Jangan mengarang schema dari model. Uji fresh provision tanpa Equipment pada instance clone terisolasi, lalu sembilan migration Live/Monthly.
4. Jalankan lint/Pint seluruh source baru, Blade/route checks, full Feature/RBAC/security, concurrency, dashboard smoke dan browser UAT pada clone. Bandingkan perilaku kedua modul dengan implementasi sumber.
5. Review shared hunks, manifest final dan scan secret; stage file/hunk eksplisit. Buat commit live dan monthly yang dapat diaudit (shared dependency dijelaskan pada commit terkait). Tidak perlu commit ketiga Equipment karena bukan bagian origin/main.
6. Fetch ulang; pastikan fast-forward dan semua quality gates lulus sebelum push tanpa force ke origin/main. Bila protection/divergence, gunakan release branch sesuai instruksi. Tidak ada deployment/migration production.

## Database dan baseline rebuild

wpitv/wpitv_testing tidak diubah (nama database kedua: `wpitv_testing`). Tidak ada DROP, migration, seed, reset, media delete atau ledger write. Audit sebelumnya pada sesi ini mencatat wpitv 43 tabel/25 migration, 12 item/65 unit dan 15 media unik; angka ini bukan hasil pemeriksaan ulang pada pekerjaan release ini. Ledger v2 tetap di lokasi aslinya. Source snapshot tidak menggantikan backup DB.

Rencana reset database Equipment dipisahkan dalam `EQUIPMENT_DATABASE_RESET_PLAN.md`; belum disetujui atau dieksekusi. Baseline Full Rebuild yang diusulkan adalah kandidat bersih setelah semua gate di atas lulus, bukan workspace Equipment lama dan bukan kandidat parsial sekarang.


## Pemeriksaan lanjutan setelah permintaan diulang

Origin di-fetch ulang dan tetap pada SHA baseline. Kandidat tetap pada branch codex/clean-live-monthly-release, belum ada commit baru, staging atau push. Source workspace utama tidak di-reset.

Composer update terbatas pada Livewire dengan --minimal-changes menghasilkan 4.4.9; perbandingan daftar nama/versi terhadap lock origin/main membuktikan hanya livewire/livewire berubah dari 4.4.0. Upaya mempertahankan 4.4.0 ditolak resolver karena advisory PKSA-xhv3-nr94-h8db; tidak ada ignore/security bypass. Composer install --no-scripts pertama gagal karena file Faker dikunci Windows; retry berhasil. Tidak ada dependency baru di composer.json dan tidak ada perubahan lockfile workspace asli.

Koreksi bukti: laporan awal bahwa lockfile baseline tidak memuat Livewire tidak didukung diff terkini; baseline benar-benar memiliki 4.4.0. Hasil Composer validate terbaru lulus. Versi 4.4.9 pada kandidat belum disetujui sebagai pengganti perilaku source melalui Feature/browser UAT; itu tetap bagian review kandidat.

Composer audit --locked tetap exit 1: 17 advisory pada 8 paket (termasuk laravel/framework; daftar advisory harus diambil dari output audit lengkap). Termasuk advisory critical/high. Tidak ada pembaruan framework massal atau penonaktifan security blocking. Gate security belum lulus, sehingga tidak ada push bahkan ke branch release.

Pemeriksaan runtime aman: process-local APP_ENV=local, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array dan SESSION_DRIVER=array. Tidak ada .env disalin atau database MySQL diakses. package:discover, route:list --json (86 route, nol Equipment) dan view:cache berhasil. Unit memakai vendor kandidat langsung, 128 tes / 567 assertion lulus. Pint --test app routes database tests bootstrap lulus; git diff --check lulus. Hasil Vite/JavaScript sebelumnya tetap berlaku karena source asset tidak berubah pada kelanjutan ini.

Status akhir tetap PARTIAL. Gate yang belum selesai: audit dependency bersih, port harness Live/Monthly tanpa Equipment, schema baseline/provisioning clone, fresh migration, Feature/security/dashboard dan browser UAT. Sesuai aturan menghentikan operasi terkait bila dependency belum aman dipisahkan, commit/push ditahan. Kandidat tidak diaktifkan sebagai baseline aplikasi, dan semua test yang ditahan tetap tersedia utuh dalam workspace sumber/snapshot.

Pengambilan ulang JSON audit untuk arsip dihentikan setelah timeout Packagist; jumlah advisory di atas berasal dari audit berhasil sebelumnya pada kelanjutan yang sama, bukan klaim audit jaringan terakhir lulus.
