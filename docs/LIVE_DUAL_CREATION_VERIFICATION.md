# Phase 6C.2 — Dua mode pembuatan Live Streaming

**READY LOCAL pada Phase 6C.3.** Kedua migration telah diterapkan dan diverifikasi pada database lokal.

## Phase 6C.3 — Provisioning dan verifikasi integrasi

Preflight memverifikasi identitas server dan kedua database, 35 tabel, 18 catatan migration, schema hash dan akun testing DML-only. Pengecualian data yang ditemukan sebelum DDL hanya satu baris tambahan di `wpitv.tr_usercounter` terhadap baseline lama. Baris tersebut termasuk dalam fingerprint aktual yang dipakai untuk verifikasi setelah provisioning.

| Backup pra-DDL | Tabel | Baris | Byte | SHA-256 |
| --- | ---: | ---: | ---: | --- |
| wpitv | 35 | 36.409 | 4.281.752 | `02c9fb6fcffb957f1093a51bcefb25897f6e9619f3f04a2a9d9735226033d800` |
| wpitv_testing | 35 | 18 | 64.830 | `67e78aa095b817d801158a4325980aadedc7172694e77baefe062a17d43b18f0` |

Dump single-transaction privat tersimpan di luar repo dan web root dengan ACL akun pelaksana. DDL, jumlah INSERT per tabel, UTF-8, footer dan hash diverifikasi. Akun provisioning terpisah menjalankan dua migration 6C.2 secara eksplisit pada testing. Manifest runner diperbarui setelah pemeriksaan DDL. Tabel tetap 35 dan histori menjadi 20 catatan. Pemeriksaan membandingkan FK Agenda, UNIQUE, CHECK, indeks lama, kolom manual, FK Program dan CHECK event. Fingerprint histori memakai proyeksi kolom lama untuk tabel Live, dengan verifikasi seluruh kolom manual baru tetap NULL pada baris historis.

QA browser loopback memakai sesi fixture Super Admin dan Production. Desktop terang 1366×850 dan mobile gelap 390×844 menampilkan kedua mode. Browser membuktikan default Manual, persistensi judul saat mengganti mode, ringkasan Agenda, pembuatan dan edit kedua mode, daftar, detail, publikasi Manual, serta 403 untuk News dan TVClub. Perbaikan binding `wire:model.live.blur` membuat preview YouTube muncul saat fokus berpindah. Putaran pertama mencapai timeout penanda selesai setelah interaksi UI. Putaran ulang menampilkan penolakan URL invalid, lalu harness lulus dan cleanup terverifikasi.

| Gate | Tes | Assertions | Hasil |
| --- | ---: | ---: | --- |
| Unit penuh | 463 | 2.153 | Lulus |
| Feature penuh dari salinan calon rilis privat | 729 | 3.738 | Lulus |
| Race Live MySQL dengan proses dan koneksi independen | 6 | 58 | Lulus |
| Harness browser putaran ulang | 1 | 2 | Lulus |
| JavaScript tema | 7 | 7 skenario | Lulus |

Putaran Feature pertama mengungkap sesi admin sintetis yang belum disiapkan pada satu tes baru dan marker Vite pada workspace pengembangan aktif. Setup tes diperbaiki. Suite penuh diulang dari salinan privat yang mengecualikan marker, memakai runner testing DML-only, dan menghapus salinan kredensial testing setelah selesai. PHP lint, Pint untuk PHP yang berubah, parsing delapan Blade, Vite build dan diff check lulus. Vite melaporkan peringatan PostCSS yang sudah ada.

Setelah gate testing, operator memeriksa ulang kedua hash backup dan fingerprint, lalu menerapkan tepat dua migration yang sama ke `wpitv` lokal. Kedua database mencapai **35 tabel dan 20 catatan migration**. Verifikasi ulang mengonfirmasi metadata DDL Live yang cocok, semua catatan migration lama dan seluruh data historis terjaga, termasuk tambahan baris `tr_usercounter`. Tabel Live utama tidak berisi baris pada preflight. Kompatibilitas data Live berbasis Agenda dibuktikan dengan fixture testing. Fingerprint testing cocok setelah cleanup. Bukti recovery Phase 6B mencakup skema lama. Backup baru fase ini merupakan titik pemulihan pra-DDL. UAT staging dan login operator asli tetap dalam checklist.

### Delta calon rilis Phase 6C.3

SHA-256 berikut memperbarui entri delta Phase 6C.2. HEAD, manifest Phase 6B, dan hash kedua migration tetap sama. Laporan ini memuat delta sehingga tidak mencantumkan hash dirinya sendiri. Backup, laporan PHPUnit, operator provisioning dan manifest testing lokal berada di luar paket rilis.

| Berkas | SHA-256 |
| --- | --- |
| docs/FINAL_UAT_CHECKLIST.md | `c13ad9493058c5cd8f313428ff5318432b3d5e5ff52cb2ed6a4b71c3cf4fbd3e` |
| resources/views/livewire/admin/live-streams/form.blade.php | `130be966bbd1a0fdae57e7bba1140ebaadaa3b3dbda83af9824a504ccef801e8` |
| tests/Browser/LiveStreamingBrowserTest.php | `0eaccf5652dccc1157e0b6aef0817c47341b69873a02194c8f919de824c8cd1a` |
| tests/Feature/Admin/LiveStreamingManagementTest.php | `df28f684c348e4eda07b231b4b28a188822fa025ae137005988182c5102fd0c7` |

## Temuan audit dan keputusan skema

Skema sebelumnya mewajibkan `live_streams.agenda_id`. FK dan UNIQUE menjaga hubungan satu siaran per Agenda. Agenda menyuplai judul, jam, Program, status editorial, dan visibility. Read model publik, filter admin, dashboard, homepage dan Program sebelumnya mengasumsikan Agenda selalu tersedia. Status Live, ID YouTube, arsip, versi dan audit sudah disimpan terpisah.

Migration pertama membuat FK Agenda nullable dengan tetap mempertahankan FK dan UNIQUE. Data baru untuk mode Manual memakai judul, Program opsional, awal/akhir UTC dan visibility pada tabel Live. FK Program memakai `ON DELETE SET NULL`. CHECK memvalidasi kelengkapan kelompok kolom manual dan urutan waktu. Service mengunci pemilihan sumber saat penciptaan. Migration kedua memperluas CHECK event untuk `manual_updated`. Keduanya memakai DDL terpisah, tanpa backfill dan tanpa rollback otomatis. Baris Agenda lama tetap menggunakan FK dan data Agenda yang sama. Penyimpanan waktu memakai WIB pada form dan UTC pada kolom Live, dengan jam selesai lebih awal berarti hari berikutnya WIB.

## Perilaku

Manual menjadi default form admin dan menyimpan jadwal sendiri. Mode Agenda mempertahankan pilihan Agenda Live yang memenuhi syarat dan menampilkan ringkasannya. Pergantian mode menyimpan input form, dan server hanya menerima input mode aktif. Form edit menampilkan sumber jadwal. Jadwal manual bisa diubah saat scheduled, dengan jadwal ulang dari cancelled melalui aksi lifecycle. Visibilitas dan arsip tetap mengikuti rule lifecycle dan policy. Program manual divalidasi terhadap policy Program dan publik hanya memuat Program aktif. Live manual internal tidak masuk scope publik. Perubahan status Agenda yang terhubung tetap dikoordinasikan oleh service yang sama.

Service memakai versi dan audit transaksi yang sama. Mutasi Agenda mengunci Agenda lalu Live. Mutasi Manual langsung mengunci Live, kemudian Video bila memasang arsip. Audit pembuatan mencatat `creation_mode`. Pengaitan Manual ke Agenda setelah penciptaan masih menjadi pekerjaan lanjutan.

## Verifikasi dan status data

- Unit penuh lulus 463 tes dan 2.153 assertions, termasuk waktu WIB lintas hari dan lifecycle Manual.
- Feature Agenda publik lulus 5 tes dan 28 assertions pada runner testing. Kalender tetap memakai scope khusus Live berbasis Agenda.
- Lima skenario race yang kompatibel dengan skema awal lulus 50 assertions dengan `--process-isolation`, termasuk race pembuatan dari Agenda yang sama. Dua percobaan tanpa isolasi proses masing-masing mengalami satu `EQCT_SETUP_FIXTURES` pada dataset berbeda. Penyebab setup tersebut belum dipastikan.
- PHP lint dan Pint pada file PHP yang berubah, kompilasi dan parsing delapan Blade, tujuh tes JavaScript tema, Vite dan diff check lulus. Vite masih melaporkan peringatan PostCSS.
- Tes Feature Manual, race perubahan Manual, regresi query publik/dashboard dan QA browser dual mode masih menunggu skema baru. Pengujian DDL dan rollback transaksi Manual juga menjadi gate provisioning.
- Kedua database mempertahankan 35 tabel dan 18 migration. Fingerprint skema keduanya cocok, testing DML-only dan cleanup kembali ke baseline. Pemeriksaan awal menemukan satu baris tambahan pada `wpitv.tr_usercounter` terhadap baseline lama. Tabel lain cocok, dan fase ini tidak menulis fixture ke database utama.

## Gate provisioning terisolasi

1. Tetapkan identitas database testing dan provisioning lewat preflight read-only. Rekam fingerprint aktual dan backup lengkap baru, termasuk drift penghitung pengunjung yang telah terlihat, sebelum persetujuan. Backup Phase 6B mencakup skema lama.
2. Pada target testing terisolasi, review dan terapkan kedua migration sesuai timestamp dengan koneksi provisioning terpisah. Runner tetap DML-only. Simpan bukti DDL aktual dan histori. Target akhir tetap 35 tabel dengan 20 catatan migration. Periksa khusus nullability, FK/UNIQUE Agenda, FK Program, CHECK audit, serta preservasi data historis.
3. Jika DDL kedua gagal setelah DDL pertama sukses, periksa skema dan histori aktual sebelum melanjutkan. Setiap ALTER berdiri sendiri. Pertahankan versi aplikasi lama pada lingkungan pengguna selama provisioning dan selesaikan pemeriksaan kedua perubahan sebelum membuka fitur Manual.
4. Perbarui manifest testing setelah review skema aktual. Jalankan Feature Live/Agenda/Program/dashboard, dua race baru dan regresi Live. Periksa hash baris, integritas audit, dan cleanup. Uji browser desktop terang dan mobile gelap dengan QA yang terisolasi.
5. Terapkan kode baru pada staging setelah kedua perubahan skema lulus. Read model 6C.2 memerlukan kolom baru. Sebelum menyetujui rollout selanjutnya, uji recovery SQL dan pertahankan data Manual serta audit dalam rencana rollback. Backup file publik/privat tetap menjadi gate rilis.

## Delta SHA-256

Baseline asal tetap manifest 247 berkas Phase 6B pada HEAD `9a35b6725495a828c5ff3372f62f277cbb9d829c`. Tabel berikut mencatat perbedaan byte lokal dari baseline. Laporan 6C.2 ini memuat delta sehingga hash dirinya tidak dicantumkan. Laporan regresi Phase 6C tercatat sebagai perubahan dari fase sebelumnya.

| Berkas | SHA-256 saat ini |
| --- | --- |
| app/Livewire/Admin/LiveStreams/Form.php | 30eac594939ac2bb814efbc5cae465405a4c63b64889bba5ca9910f78c676392 |
| app/Livewire/Admin/LiveStreams/Index.php | a077af597e3d577c89a78449d2ecfe51fe04c7fa5351ca901b8e31e65914ea7f |
| app/Livewire/Admin/LiveStreams/Show.php | dac5b8a7ed5cee0b016e34608be257860d9b134064a265197b36bdaa5a8bd21f |
| app/Livewire/Concerns/InteractsWithLiveStreams.php | 61d1ddb35a77c719a351526f3b0bbbe31c3463347f50e302a7a4cbe451161285 |
| app/Livewire/Public/Agenda/Index.php | 52233314b2dc5677b390fd8dd9d90977024bbc2ec25c68407d01e21c676903d7 |
| app/Livewire/Public/LiveStreams/Index.php | bdf2cdbef24b5b4a868a3975c7d226d3b4687e00b4c435d3b4f13c5812513b27 |
| app/Livewire/Public/LiveStreams/Show.php | 5cee18b511dcdba35116d4992319ea28da129a28e0e7094d631e50b085f8b8c0 |
| app/Models/LiveStream.php | 7110ed8df8abfe08b2441dfb83bac812c69cf95666f5ec2bba0ee02c0d178340 |
| app/Models/LiveStreamEvent.php | 727c05c86af9af34baa635ab7ba680cbc57ab9b029745b76c20d96b9c949ed75 |
| app/Services/LiveDashboardMetrics.php | f94ec4dd38689c894d8287bda8ae0b11248a07851cb099edecf401f4eee71ad0 |
| app/Services/LiveStreamHighlights.php | f9616565dd8570773d67f7c7462d57b66109fcaf28e86eb84726168436d11af9 |
| app/Services/LiveStreamLifecycle.php | 5ac3542d8e57803899142c16b3550fa5c5510a7e2a16d1cf5fe96dd28012b99e |
| app/Support/LiveStreamErrors.php | 0d9660d319e2b70478ace40709df4ce17249ee2e6c8f469e25b5016b97f58666 |
| app/Support/LiveStreamFilters.php | 5a414a0998807749a5d8d8c1b69605767716b6e384e218570cf824f189748873 |
| app/Support/LiveStreamSchedule.php | fc3cd07cc574380703a5993a826973c92fed7220d4b3ed5867dbc2152a070f6e |
| app/Support/LiveStreamState.php | 0b0e97f5df4bd567c2f4b168fb6d173d56c905731934d4eab80607a646cca7e3 |
| app/Support/PublicLiveStreams.php | 8dfda409811e270013a0a44b99890cde355de00fcb02ecda6f380acbe600dc95 |
| database/migrations/2026_11_18_000001_support_manual_live_streams.php | 1a3f365eacbab45f4554d149a183bbc499db5db18a2c996ddc3d206787d625b2 |
| database/migrations/2026_11_18_000002_allow_manual_live_audit_event.php | cd8fac0a7d9f2dadf7cca1229bf5fd302bc0bd7d71d46692b92aae74eed46927 |
| docs/FINAL_PROJECT_AUDIT.md | f09c991e5ae99ce194cb52bcea645e134aa59a9810f01d980ec88712ba6e2d47 |
| docs/FINAL_REGRESSION_AND_SECURITY_VERIFICATION.md | 7f5e2e6c00c394ef62ca881077ff140e7add728fa7e6e3afafb4fcc316482e82 |
| docs/FINAL_UAT_CHECKLIST.md | 10b8a51d7c041020e230a02c21d61c25083d895bc66e1121c39fd1b0c79b1d08 |
| resources/views/livewire/admin/dashboard/live.blade.php | 1638538209b5e73cba588daf6c03782d35e44ac516f106391d2cedfbdfedd55c |
| resources/views/livewire/admin/live-streams/form.blade.php | 0edfcaf4eceef2e00e396bc670bb361324e227d814d623e677f782fdfad739ca |
| resources/views/livewire/admin/live-streams/index.blade.php | 994bcb790762e6c5284abc6511f3e1b4700d1366b8ff503f2c033bc1e8777f0e |
| resources/views/livewire/admin/live-streams/show.blade.php | 3775d961e9ade018563bd8c0031a74a42bbc162193852b044dd1e4ee9ad8ebfc |
| resources/views/livewire/public/live-streams/highlights.blade.php | 283ee41983b18596fedb684b70999c154c41a278a3aa19f3f3775749179bd23b |
| resources/views/livewire/public/live-streams/index.blade.php | 748dde6324a342fc6c3dc0f29b91e891480d3f349b81f386a62f4bad7ba84f51 |
| resources/views/livewire/public/live-streams/show.blade.php | 71bd069f9ab23a85dd036fe790ccf6141896344d8ec7fb7ee951375d49f88595 |
| tests/Concurrency/Live/LiveConcurrencyTest.php | 1a56fb8ce8c7d84df4d7b0cc59c682b5aebedd0eaaf50e062ff0436a607f2e73 |
| tests/Concurrency/Live/worker.php | 56c0fb0a73e9acb29133f1d807bfb45d512d851e4d32e27cc6e5f554ffd5d565 |
| tests/Feature/Admin/LiveStreamingManagementTest.php | a6f0cd7c1dc7f06bbb5c0a1a03d652c727a9ea3570f4b22106d7762fdc502884 |
| tests/Support/LiveScenarioTestCase.php | 4ff88913d6efc2788088794d3feba1f5c1ac7fd3a55e7787dcd7fb9db11a1d44 |
| tests/Unit/LiveStreamingAdminTest.php | 62c39bd211ca52ed7eb6599fd89f56e5e06ebcab7bb620b23011ddb3a233d959 |
| tests/Unit/LiveStreamingFoundationTest.php | 2d558dfb9f1bd4e210cd5bdfa906575149e6d422169084ff5de2336f75f10420 |
