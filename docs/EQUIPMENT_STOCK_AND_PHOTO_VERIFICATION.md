# Verifikasi Equipment 6C.4B–D

HEAD saat pemeriksaan `9a35b67`. Perubahan berada di working tree. Preflight loopback mengonfirmasi 35 tabel dan 20 migration pada kedua skema, termasuk dua migration Live Dual Creation. Guard mengonfirmasi cache/session array, storage testing terisolasi serta runner wpitv_test_runner@localhost dengan SELECT/INSERT/UPDATE/DELETE khusus testing.

## Provisioning dan pemulihan

Backup privat pra-DDL memakai snapshot konsisten, verifikasi DDL, hitungan per tabel, encoding, footer dan SHA-256. Dump disimpan di LocalAppData/WPiTV-Backups/phase6c4-20261119.

| Sumber | Baris | Byte | SHA-256 |
|---|---:|---:|---|
| wpitv | 36.565 | 4.353.067 | 0fd0c95f6695de401fe61bd63b71f9bcc9efe0adedf6957f06891017f67f6514 |
| wpitv_testing | 169 | 132.175 | 47997914d7196705dceba071fe4d150cff2bb3c66795cd7fbdb0b54ca1e06341 |

Tiga migration tanggal 2026-11-19 dijalankan secara eksplisit pada testing, lalu pada database aplikasi lokal setelah validasi backup, fingerprint dan laporan tes. Masing-masing berakhir dengan 38 tabel dan 23 migration. DDL tabel lama serta hash baris historis dan riwayat migration sebelumnya cocok. DDL tiga tabel baru di kedua target cocok. Percobaan provisioning testing pertama berhenti sebelum CREATE karena binding Schema Builder pada operator lokal belum tersedia. Operator diperbaiki dan baseline diverifikasi kembali sebelum menjalankan DDL.

## Fitur

EquipmentStock menghitung satu kelompok per unit dengan JOIN ke active_unit_id yang unik. inventory_unit_photos memakai FK ke unit dan event aktor, UNIQUE(unit_id,revision) dan storage_key unik. equipment_return_photos memiliki FK ke receipt returned dan key unik. Kedua tabel menyimpan MIME, SHA-256, byte, UTC mikrodetik serta CHECK MIME/ukuran. equipment_return_photo_items memakai PK gabungan dan indeks balik serta FK ke foto dan item. Semua relasi media memakai RESTRICT.

Petugas memilih satu foto khusus per unit, maksimal 10 foto dalam satu retur. Hash return-v2 menyertakan pemetaan item/hash. Metadata disimpan bersama receipt dalam transaksi. Upload privat memakai nama acak, pengecekan format, decode ulang, batas 5 MiB/20 MP dan pembersihan file percobaan yang tidak terreferensi. Unit lama dapat ditampilkan tanpa foto. Aktor berwenang dapat membuka foto historis melalui route yang memeriksa induknya. Dalam kasus proses mati mendadak sebelum cleanup, rekonsiliasi objek yatim dan recovery media bersama SQL tetap merupakan pekerjaan operasional.

Daftar inventaris menyediakan enam KPI, kartu unit, pencarian dan filter. Checkout memiliki empat langkah. Retur menampilkan kondisi awal/akhir, preview per unit serta konfirmasi. Receipt memiliki timeline dengan identitas petugas dan tautan bukti. Badge memakai label, ikon dan warna. Pembuatan fixture UAT baru secara kode disesuaikan dengan bukti sintetis. Seeder tidak dijalankan.

## Bukti pengujian

| Pemeriksaan | Hasil |
|---|---|
| Unit penuh | 463 tes, 2.159 assertions lulus |
| Equipment pure/integration/concurrency | 138 tes, 638 assertions lulus termasuk delapan skenario dua proses. Suite Equipment memuat sebagian tes Unit sehingga hitungan dipisah. |
| Regresi dashboard | 15 tes, 68 assertions lulus |
| Node, tema admin | 7 tes lulus |
| Kualitas kode | PHP lint, Pint, kompilasi Blade dan build Vite lulus. Vite mengeluarkan peringatan PostCSS nonfatal. |
| Browser Chrome headless pada fixture testing | 1366 px terang dan 390 px gelap, keenam KPI dan navigasi empat langkah hingga review tampil, tanpa overflow horizontal pada halaman review. |

Tes mencakup kelima kelompok stok, prioritas kehilangan dan kerusakan, nilai nol, file JPEG/PNG/WebP, batas ukuran dan signature, histori penggantian, cleanup setelah stale, akses file per parent/role, bukti wajib, retur parsial, retry identik, rollback audit dan versi. Tes dashboard menghitung delta terhadap data awal karena database testing memuat data UAT. Jurnal ownership fixture kini mencoba ulang rename ketika Windows menahan file sejenak. UAT menyeluruh dan drill restore media privat tetap tertunda.

## Delta manifest calon rilis

SHA-256 byte berkas lokal. Tanda — menunjukkan berkas belum tercantum pada manifest 6B. Dokumen bukti ini dikecualikan dari hash dirinya sendiri.

| Berkas | Manifest 6B | Setelah 6C.4 |
|---|---|---|
| app/Http/Controllers/EquipmentPhotoController.php | — | 2aade3aafd876e711ea786358b15d77274fde837169959902ec27c6ad7cba479 |
| app/Livewire/Admin/EquipmentLoans/Form.php | 4bcaaa4da3a6e3834a77a21aaf34ad288257f7d38ecb1751b6dab290be650d40 | ec0b8f26734a381e5f74dbf228bee8763aa8387651944f240246d55bb2db3969 |
| app/Livewire/Admin/EquipmentLoans/ReturnForm.php | 6a3b35929a27c265bd3ce3a5457db9b1dfa748eae2c760d13277533790c4dc41 | 5a6227241325fe5ab603d406a36df486791528bd5d1ed75b5288d9ee5a9fed51 |
| app/Livewire/Admin/EquipmentLoans/Show.php | fa5d8da670ca8597168eedf8b97d8cada638652a46c962c9fff2185b62dd24bf | 088676340050f9ee53eef55a0f90410159ce9562e64e1daa14065d1ec3e8c568 |
| app/Livewire/Admin/Inventory/Units/Form.php | ce8c9ae8d6701f0c43793a06e83ac618367266f163c5cd8beff36cd883cb9f1b | f36f4c51b3bb8ac0d811c963c7438929ea2755b867743a929b985e428d62201c |
| app/Livewire/Admin/Inventory/Units/Index.php | b0df015b19251442835884379ae89ac2a414045e74584c8a2f3ea4744267f411 | 8261dcab91da6e5f7e341107b3738b4083ff6f4d2957ee30521c736833c1b4b9 |
| app/Livewire/Admin/Inventory/Units/Show.php | ce75ffd9e73cadd78130bedd4ac297506020c6b24e6c9b2baa099f27a85e30d6 | 352b26779033605342fbbf98089f2c03b0ccf9e33a5b03fbfae8b23f51efbd5a |
| app/Services/EquipmentLoanReturn.php | b132a376e09c87e6765946f4c61468b7983b884bf77867e56e8bc65cb87d2257 | 8172aac071fa9d67f3c27859d4bd97bcb6873f74e7ff890b9e41ab779f089827 |
| app/Services/EquipmentPhotos.php | — | 5bd9c983249ad20c342a993d4dcc0ec26ac8660083aa151a8feb09c683b38b22 |
| app/Services/EquipmentStock.php | — | 9ea417ff1f217693c484fcf005249645152e0bb45506717849503872e0a6b462 |
| database/migrations/2026_11_19_000001_create_inventory_unit_photos_table.php | — | 2081f5d7ce64f8c834148bc19164895bb48644c8595022b1fd7c38106624e46d |
| database/migrations/2026_11_19_000002_create_equipment_return_photos_table.php | — | ebc03705c445928e55f99ca594d707b66eda8d15e9bb1c6ad123a88725d7adac |
| database/migrations/2026_11_19_000003_create_equipment_return_photo_items_table.php | — | 6d20703eb23dc69c0c3bc40981eff3b5a12752fce58e32f9e80eef4b2a711777 |
| database/seeders/WpitvUatSeeder.php | — | cba9f58426283b068a93b676cc03eddce28bc61b0d7e2531d0e24a8678309c44 |
| docs/EQUIPMENT_STOCK_AND_PHOTO_DESIGN.md | — | 62e4f205f075698a6ab6747954258f956630d28adf1f7f89b97cdb087bd4f067 |
| docs/FINAL_PROJECT_AUDIT.md | 79edab876f137a4378585e15076bbe2c882f098fd2e3c41b68f97f844ecab600 | 01a645d15fd89ba438d303e9cd20dcd01e85be71fec28c97dc62a78cfdef857e |
| docs/FINAL_UAT_CHECKLIST.md | 90b13b863b08eddbb49ec6d6be7892ad2c0f462864043483f974ddba4e95883a | b63ae45cd328c6456401e5c824277c55cac75600844fea05656553f0c03edfd6 |
| resources/views/components/equipment-status.blade.php | — | 6aff9e44bced252deafe181c71d509439a8f3688c5e6f0906cf0e8fc6c433af4 |
| resources/views/livewire/admin/equipment-loans/form.blade.php | 3d3c2306e4465a6070ccf51e15a0f2a38cc6b6aae9a021b2c24e10a651144eb8 | 49e5a6bb46c2b049594094f4299a51997731c56bb0db44e7265885ea92ee6688 |
| resources/views/livewire/admin/equipment-loans/index.blade.php | 9aba7048e7bbb6eda01053a1a24078cb4985fb29075769ba77f820d28a10888a | 8a6ec4b39e6b579ca2b747527882ed7d9d535d47345ed1de4b5b9c68c12d63d5 |
| resources/views/livewire/admin/equipment-loans/return-form.blade.php | fb5cf7975b43cfc0fcce6c357f40974c4590d133c7a03ba8ed3b41ab9cd6efeb | a9f15a551d04cb1e64dbe192dac9a397f3dd83d1cf97307bc9b42b990d8c9596 |
| resources/views/livewire/admin/equipment-loans/show.blade.php | dabe3dfd544b6dc9c9e37900b70231608fbdeef2f8e9d5091a557de8ce906cfc | fb1fc167eb1a9fb907381a728dec94485756c30083a553b4b403a13fc70c174f |
| resources/views/livewire/admin/inventory/units/form.blade.php | 59ce630c1dfa570deb493489f8ca5c88a72c6c257e694780bc124a772d25ee52 | 9a22ed442193882160dceb33dfda7f0f787970747b34cda6765b95c523d2faa5 |
| resources/views/livewire/admin/inventory/units/index.blade.php | 6db53d56b590962c84b3e58d65a09a469001199a60f33d5df007d6c5e83129f9 | 9c671339360299204797a13e7c6f05a95c713d653abc9e3abe5da93e9b2e242e |
| resources/views/livewire/admin/inventory/units/show.blade.php | 776f68abb6b3295662b911142a0a397653ce7363f0665ffea8b8a6d225383a14 | 55cd94887c43ab22203ffce9e0600e6d4ba1cb79573905497de7a721461cd6dc |
| routes/web.php | f3de4f5f179dac778f5ad888830b4354bf395cce095018100ac19d09ac84e993 | a947168f4b1b4a6782b063b084b9b6b992123cbef377d320e98e38549443fb49 |
| tests/Browser/EquipmentBrowserTest.php | 27121b3859cd7162c4d0a7ab357f8af9428cca9b3bc7863f187e3bfc2f0cde7a | efcd3f55a4556ec87d279fc7b3f3be2ef934d8fcce51403f004479402df919fd |
| tests/Browser/router.php | 277321207b3fe81d8971dd3f140820f5240275758417c84d684e7e2dc8b7e659 | 010d71a2dbf032057faf8ce56494323a47851226906e4268f0923bd9cd2c6c94 |
| tests/Concurrency/Equipment/EquipmentConcurrencyTest.php | ea28c766ba62d371a52a84ef14d6f36393163627b075cda260743d45cda01710 | abd89c1052fa6f4120984ca9342a2531eafb9f8f952f315d2ff5290e416d69f3 |
| tests/Concurrency/Equipment/worker.php | 21074efe870ed9156c506dc5ae648188ff3aa5415ee27c48f769fbff25a489c0 | 371c1fc5e7e85e567779221586b770687b3710c94bc96d36064e2c1413723620 |
| tests/Integration/Equipment/EquipmentIntegrationTest.php | f1bf1e66513f9725521514fdfb1ade5f8c96227647c39563b0fb7e42d9ba0bc6 | a69028d8c5f78e0c70d75e0173ba302e6c8e110ba725d9ef2d0d4ce4caceaec2 |
| tests/Integration/Equipment/EquipmentPhotoTest.php | — | 37af832539c1279dde473b9131fd0fe54475d592fd66cb3ec0da91256ca10f62 |
| tests/Support/EquipmentConcurrencyGuard.php | f14870a05d5ad9b9789d64c6e5b0d744c42fdcd2a2e033c398056636f997a9a8 | f673c3f163e41d1fb8adfbd02bf4d4ff46fdaea49a4293831abec16466b4fe99 |
| tests/Support/EquipmentPhotoFixtures.php | — | d60bb2e15e08b808351871e571a1aee6ffc3425d4be014b6bc8eb4a19a9f03fb |
| tests/Support/EquipmentScenarioTestCase.php | 6b8e9fe15a9ed0c54af9d5cabf74853597d4f525570d3dae4b7e742b946b650c | b48cd9b4860c591219b40538d9f6365300c74dc6af583c5d65d7745f4f8ae68f |

## Pemeriksaan akhir browser dan integritas

READY LOCAL. Pada Chrome dengan fixture testing, petugas memilih satu dari dua unit, mengunggah PNG melalui kontrol file, melihat preview, mengonfirmasi dengan SweetAlert2, lalu membuka bukti dari receipt. Browser berhasil mendekode gambar privat dengan lebar 12 piksel sesuai fixture. Perbaikan tombol Simpan membatasi indikator nonaktif pada aksi save agar unggahan selesai tidak meninggalkan tombol terkunci. Fixture dibersihkan oleh harness. Verifikasi setelah browser mencocokkan kembali hash backup, skema dan fingerprint baris kedua database.


## 6C.4F · READY LOCAL

Baseline kedua skema 38 tabel/23 migration. Backup privat pra-DDL, SHA-256 wpitv 644ea33bb6c2d05560a0d6240c53f806218c8b5404ea621e751de2e1cfd65c6e (36.569 baris) dan wpitv_testing f9f5bcabaa7f752dd48147dab280d2de125d70e11633033241688c6e9fb8f178 (172 baris). Operator memverifikasi DDL, footer, jumlah baris dan hash. Satu migration inventory_unit_members diterapkan pada testing dahulu dan kemudian pada wpitv lokal. Kedua target mencapai 39 tabel dan 24 migration; fingerprint data lama, skema lama, histori migration dan cleanup fixture diverifikasi setelah pengujian.

Suite Equipment 139 tes/657 assertions, termasuk rollback audit penambahan, konflik version dan delapan skenario konkurensi. Suite Unit 463/2.159, Dashboard 15/68. Hitungan suite saling tumpang tindih. Chrome menguji tombol penambah kuantitas dan wizard pada 1366 px terang/390 px gelap serta retur parsial melalui pemilihan jumlah, unggahan bukti, SweetAlert2 dan pembacaan gambar dari receipt. Pengujian aplikasi memakai fixture hanya pada testing. Pint pada kode layanan/komponen/tes, kompilasi Blade dan diff check selesai. UAT operator dan deployment masih terpisah.

Delta SHA-256 calon rilis (laporan ini tidak menghash dirinya sendiri):

| Berkas | SHA-256 |
|---|---|
| app/Livewire/Admin/EquipmentLoans/Form.php | 16cc5eb47d6c928b49e0cbe319cffb53faa188e4d1ed67dd43e54db057a2eb36 |
| app/Livewire/Admin/EquipmentLoans/ReturnForm.php | 3fca48ee5b8f15c47b07b42ee154769e39cdbc81494bf5fb4362a933514524a2 |
| app/Livewire/Admin/Inventory/Units/Form.php | 74706cae1dc80733d2e7ea13ac4a88c07d5e93d204322ad69b3dabd3d5e4f00c |
| app/Livewire/Admin/Inventory/Units/Index.php | fa9c80bc83e4be5d26549c2c34a9159dcdd1446cd9430b1c96de89f0ee7deb26 |
| app/Livewire/Admin/Inventory/Units/Show.php | d552d275c8546e48ac7151f8affbd00244a903d6ae88867f8b0cfd2824617e8b |
| app/Services/EquipmentQuantity.php | 55f5d52109f94f041c4f00dfd1513e8de64389bb7a726e01471e8583d3d6e4d9 |
| database/migrations/2026_11_20_000001_create_inventory_unit_members_table.php | 260c5b92c5d9f9e607386d8a2cefa78792711a0bced8e926237a2ca6d056400a |
| docs/EQUIPMENT_STOCK_AND_PHOTO_DESIGN.md | abc874cd26f621b6dfb460ef3d69db07dc2d2314066729f89e598f5f2b2ffd4e |
| docs/FINAL_PROJECT_AUDIT.md | 83a471b66cfa43fac0563afc484299aca6242f4547c1d4d627aa35bd14122f77 |
| docs/FINAL_UAT_CHECKLIST.md | 1e41c005d0a53e29ec8a0b1dcee0c6f826a24c1fed99fc9900243531a886e167 |
| resources/views/livewire/admin/equipment-loans/form.blade.php | 63ad81b50c08e2db9fe397504c2e22b31866f481e2ba59c7479e8b5f9065e416 |
| resources/views/livewire/admin/equipment-loans/return-form.blade.php | 3a24d3cd3eeb4da0ed63eeca604263efae4ebb3a385a68a4d242b1d2ade2cd83 |
| resources/views/livewire/admin/inventory/units/form.blade.php | f548ea3aa20e545c0820bbcd348cab7cbab0ffbd5b668eddcdbfff5ddb2a50f2 |
| resources/views/livewire/admin/inventory/units/index.blade.php | c0b1edd2da036acdf14723ba121e5e84a3921eac46910db9c6ffaf3b376d7409 |
| resources/views/livewire/admin/inventory/units/show.blade.php | c6c5f18aaf1b58fb8d5371ab7ad51871052efd4d8c2cae665fb9b70c5a6f61d0 |
| tests/Integration/Equipment/QuantityStockTest.php | 5e0f225eb6e917de4570d0e1349a0b6e48d9c2addcc89c37798cc7c0a362a5b3 |
| tests/Support/EquipmentConcurrencyGuard.php | e803b34222f82317739268e72105f0d00b1bb397d8606294e3087170c47729a2 |
| tests/Support/EquipmentScenarioTestCase.php | 9d9695584c0108e655bb4254620277dfbdd7f477f6007fa8d3f66071717de9d6 |

### Phase 6C.4G | READY LOCAL

Baseline aktual diambil sebelum fixture. Kedua database memiliki 39 tabel dan 24 entri migration. Jumlah baris historis berbeda dari catatan operator 6C.4F. Perbandingan sebelum/sesudah fase ini menggunakan fingerprint aktual masing-masing database. Pemeriksaan akhir lulus. Runner testing hanya memiliki DML. Seluruh perubahan memakai hubungan induk existing, sehingga tidak diperlukan DDL atau backfill.

| Verifikasi lokal | Hasil |
| --- | --- |
| Unit penuh | 463 tes, 2.159 assertion |
| Suite Equipment, termasuk dua proses membuat model sama, checkout dan pengembalian serentak | 140 tes, 677 assertion |
| Dashboard | 15 tes, 68 assertion |
| Chrome headless dan harness terisolasi | 1 tes harness, 2 assertion PHP; pemeriksaan DOM, dimensi gambar, tema dan receipt dijalankan di browser |
| JavaScript tema | 7 tes lulus |
| PHP lint, Pint, Blade, Vite, git diff --check | Lulus. Build memberi peringatan PostCSS tentang opsi from. |

Jumlah suite dilaporkan terpisah karena sebagian tes unit juga termasuk dalam suite Equipment. Chrome memeriksa kartu, form tambah/edit, kolom stok, preview PNG sebelum simpan, detail, dan review wizard pada 1366 px terang dan 390 px gelap. Alur kembali sebagian menyimpan bukti dan browser mendekode gambar privat dari receipt. UAT pengguna dan inspeksi fisik label barang tetap dijadwalkan terpisah.

| Database | SHA-256 skema | Fingerprint isi sebelum dan sesudah |
| --- | --- | --- |
| wpitv | 0abe089e7087b0e805d367a13fc428817d7ea834b5f6f418ba3d56e3c844c430 | dd088a2000ec5012b0a7e0200fb8d7789d17ffbf9f876cbb3414b9c3b596c0a3 |
| wpitv_testing | eb3daa05deb6c098822c6bdb2498b1a5dd7b6f911b352809a1af9cea5d7a8e27 | 80378eeb7ee9adcddf7920d400ff24a1223f89c5396ef0f7c5ec71e3f21e5536 |

#### Manifest perubahan fase ini

SHA-256 dihitung dari byte berkas lokal. Laporan ini dikecualikan dari hash dirinya sendiri.

| Berkas | SHA-256 |
| --- | --- |
| app/Livewire/Admin/EquipmentLoans/Form.php | 9032e0b13efeefa063e7d0e34756432e16e8caa820a1571146cc3667ecae8a2c |
| app/Livewire/Admin/Inventory/Units/Form.php | b5bd91d058ef65347d236c0bb7f92b114888ab0577728b40c115672013f88030 |
| app/Livewire/Admin/Inventory/Units/Index.php | 9c607fcadd9f619ffa6dd1cc4bf698f30dc219552fa0737e003c5562175d7525 |
| app/Livewire/Admin/Inventory/Units/Show.php | 360b272a01dfa500d90ad483ae905860feb35102a0baf802f18019217c8bf654 |
| app/Services/AdminDashboardMetrics.php | a2c7c46ae826db3cc6cb43860ddc070382b15694af64db8b2165b73a591aecc1 |
| app/Services/EquipmentLoanCheckout.php | 3450b66c2548c42edd7bc25476eb0f9b00b0d2c27d106bc5d71a2cba32d25b70 |
| app/Services/EquipmentQuantity.php | 805f3b43317306dd777ec210a3307fc4257cb24346cd166cae05746278300f88 |
| app/Services/EquipmentStock.php | aee2b5445c8f49c42364ced100aea18edcf56f48448cb5b396ad0260bcc7b41e |
| docs/EQUIPMENT_STOCK_AND_PHOTO_DESIGN.md | 4518b76309d36923a4516246631b9f97d8cd091d7a638e3b8d44122844172973 |
| docs/FINAL_PROJECT_AUDIT.md | dc238cf0fb990d0c404a6ec24c2947dbf2878f989c6c8b781940bea68286deb6 |
| docs/FINAL_UAT_CHECKLIST.md | 39f007bea5673f998e58811c0ccbecefff57d6735635f8028a9f95d6de62ed15 |
| resources/views/components/equipment-status.blade.php | 884fed9792507cc736e71679f88ba70900cb68629f88bc061aa14a7a311d4eef |
| resources/views/livewire/admin/equipment-loans/form.blade.php | 36cf44760e9e3c9dd73cf6be491e2b0dc14d57e7218e3600d5ec5b899aba9b51 |
| resources/views/livewire/admin/inventory/units/form.blade.php | a900a89b88ad7af744caa8ab4ee82bb46e03db7c8ad5bbe17b08dddf78fe28f3 |
| resources/views/livewire/admin/inventory/units/index.blade.php | 3ddb35854a13a2cb65f8a835624f6bdf67b2f96d79adb717269502b8f06a4956 |
| resources/views/livewire/admin/inventory/units/show.blade.php | 797a4862b4b67e35682a0a47fa9bbb42224db374d491e79744ec7c13fa9118ac |
| tests/Concurrency/Equipment/EquipmentConcurrencyTest.php | 10e4e6bf9adbd0df81190aaf5b7555daf7f35bd2634db22b3f4b974fb43c4ed5 |
| tests/Concurrency/Equipment/worker.php | 1d884d91dfecfbab432edcc8e0bf7176f12702a04f4c94820a432d237dc7b1bb |
| tests/Integration/Equipment/EquipmentIntegrationTest.php | dbefa86047abd88d93d0a86905772659b6777da56e625683c82eb668efa37db0 |
| tests/Integration/Equipment/QuantityStockTest.php | 7e84c55d7b00e0ed0b5ead870441b77455d9ab302976a7586d457c9c2eefd243 |
| tests/Support/EquipmentScenarioTestCase.php | 4557ddbfef288a003058df33ec874615638ec9f40325213e31e3f3c3c17d7f81 |

### Phase 6C.4H | Checkout berbasis jumlah

Petugas mencari barang melalui nama atau kode, menambahkan jenis alat, memilih jumlah dan memeriksa unit yang akan diserahkan. Mode otomatis memilih ID fisik layak terkecil dalam kelompok. Mode manual memeriksa keanggotaan, kondisi, jumlah dan status pinjaman. Satu barang memiliki satu baris. Batas transaksi 50 unit. Daftar pencarian menampilkan maksimal 30 barang dan pencarian manual menampilkan maksimal 50 unit per barang. Persempit pencarian berdasarkan kode untuk menemukan unit lain.

Review menyimpan pilihan unit beserta versinya di properti Livewire yang dikunci. Perubahan input membatalkan review. Konfirmasi SweetAlert2 meneruskan pilihan tersebut ke EquipmentLoanCheckout yang memeriksa ulang role, versi, pinjaman terbuka dan status aktif di dalam transaksi. ID unit yang sudah diperiksa petugas dipertahankan sampai konfirmasi. Konflik membatalkan transaksi dan menandai item yang harus diperiksa ulang. Total fisik, histori, foto, receipt dan retur parsial tetap menggunakan unit individual.

| Pemeriksaan | Bukti lokal |
| --- | --- |
| Unit penuh | 463 tes, 2.159 assertion |
| Equipment dengan MySQL | 141 tes, 721 assertion |
| JavaScript | 7 tes |
| Chrome lokal | Desktop 1366 px terang dan mobile 390 px gelap. Dua jenis barang, tiga unit, tombol jumlah, mode otomatis/manual, review, dan SweetAlert2 lulus. Pembatalan diuji di desktop, konfirmasi hingga detail dan histori di mobile. Harness 1 tes/2 assertion PHP, ditambah pemeriksaan DOM. |
| Pemeriksaan kode | PHP lint, Pint, kompilasi Blade, build Vite dan git diff --check lulus. Build memberi peringatan PostCSS tentang opsi from. |

Uji konflik stok terakhir memakai dua review POS dan dua proses PHP dengan koneksi MySQL independen. Pengujian mencakup quantity invalid, pilihan lintas kelompok, stok kosong, edit setelah review, mode manual/otomatis serta rollback satu batch. Rincian serah terima, kondisi masuk, receipt privat dan pengembalian bertahap menggunakan modul retur existing. UAT operasional oleh pengguna masih tertunda.

Kedua database berisi 39 tabel dengan 24 migration. Verifikasi menggunakan runner DML-only untuk fixture di wpitv_testing. Fingerprint akhir skema dan baris kedua database cocok dengan awal fase. Dua percobaan awal harness membuka halaman publik lewat tombol keluar sehingga tercatat satu baris kunjungan loopback testing. Setelah selector konfirmasi diperbaiki, baris sintetis tersebut diverifikasi berdasarkan hash lalu dibersihkan.

| Database | SHA-256 skema | Fingerprint baris |
| --- | --- | --- |
| wpitv | 0abe089e7087b0e805d367a13fc428817d7ea834b5f6f418ba3d56e3c844c430 | dd088a2000ec5012b0a7e0200fb8d7789d17ffbf9f876cbb3414b9c3b596c0a3 |
| wpitv_testing | eb3daa05deb6c098822c6bdb2498b1a5dd7b6f911b352809a1af9cea5d7a8e27 | 80378eeb7ee9adcddf7920d400ff24a1223f89c5396ef0f7c5ec71e3f21e5536 |

#### Manifest perubahan

Kolom sebelumnya mengambil hash terakhir yang tersedia dalam laporan Equipment. Tanda kosong berarti berkas belum memiliki hash di laporan ini.

| Berkas | SHA-256 sebelumnya | SHA-256 fase ini |
| --- | --- | --- |
| app/Livewire/Admin/EquipmentLoans/Form.php | 9032e0b13efeefa063e7d0e34756432e16e8caa820a1571146cc3667ecae8a2c | ab4d8bc7acff28a2566905818e14bc7d6dfb0ca33919ebb1a57f44f05fbcc4fb |
| docs/FINAL_PROJECT_AUDIT.md | dc238cf0fb990d0c404a6ec24c2947dbf2878f989c6c8b781940bea68286deb6 | 1f82eac559dfacd087e1f3216f9e03f936179c30e15dba42477717e8c891b8ee |
| docs/FINAL_UAT_CHECKLIST.md | 39f007bea5673f998e58811c0ccbecefff57d6735635f8028a9f95d6de62ed15 | 6f8528909362342db7758bf009a7a1e9fb4857f93bd3f20ed557df891e83c88d |
| resources/views/livewire/admin/equipment-loans/form.blade.php | 36cf44760e9e3c9dd73cf6be491e2b0dc14d57e7218e3600d5ec5b899aba9b51 | a190080e122fd27a5e9c407bca72085f66f551433bcf58a071e79df6d3b002b2 |
| resources/views/livewire/admin/equipment-loans/show.blade.php | dabe3dfd544b6dc9c9e37900b70231608fbdeef2f8e9d5091a557de8ce906cfc | e3f7f9ca52e7eb0192e5576c1fda42e53a554154f3608b3596818f25f2a04556 |
| tests/Browser/EquipmentBrowserTest.php | 27121b3859cd7162c4d0a7ab357f8af9428cca9b3bc7863f187e3bfc2f0cde7a | cc186061072f229594f4644caba13e9a33cd7f74e056aae80e16305f0698ef57 |
| tests/Concurrency/Equipment/EquipmentConcurrencyTest.php | 10e4e6bf9adbd0df81190aaf5b7555daf7f35bd2634db22b3f4b974fb43c4ed5 | d7f26fff52429ef9ff56cc3c490742f42f9cbb7137f8678f754a5ba220df36a6 |
| tests/Integration/Equipment/EquipmentIntegrationTest.php | dbefa86047abd88d93d0a86905772659b6777da56e625683c82eb668efa37db0 | 0bec769196a8cd1d0086003104f5372a73ef8c2987a6920dbf8c9d030df6cbc6 |
| tests/Integration/Equipment/QuantityStockTest.php | 7e84c55d7b00e0ed0b5ead870441b77455d9ab302976a7586d457c9c2eefd243 | c7a7a3e51a1f8c12eb0d17d877c9d10af8558c3cd677216a7bd0bd665c479def |

### Phase 6C.4K | UI Equipment dan POS

- Kategori menampilkan jumlah barang dan unit melalui `inventory_items` dan `inventory_units.item_id`. Tautan Lihat barang membawa filter kategori, sedangkan Edit / Status menggunakan policy dan audit existing.
- Kartu menggunakan satu master per barang, dengan status pengelolaan master, hitungan stok fisik, cover privat, dan tautan ke detail atau stok. Detail mempertahankan daftar unit, riwayat pinjaman, penyesuaian, foto serta audit.
- Edit Barang mengubah identitas dan status master secara terpisah dari kondisi fisik Unit 1. Code master immutable. Unit tambahan baru memakai counter master yang dikunci, dengan akhiran seperti `-U02`. Unit 1 memakai kode awal yang diterbitkan. Penyesuaian minus mengarsipkan unit yang memenuhi syarat dalam transaksi dengan alasan dan audit. Foto cover mempunyai riwayat revisi privat dan fallback dari foto lama.
- POS memakai master untuk pemilihan barang serta unit eligible untuk mode manual. Review tetap mengunci pilihan sampai checkout memvalidasi ulang seluruh unit dan menyimpan snapshot serta audit secara atomik. Tanggal peminjaman dan jatuh tempo tetap berbasis tanggal WIB. Retur mencatat tanggal dan jam aktual per unit.
- Form retur menampilkan jumlah pilihan dan batas batch. Bukti privat memverifikasi keterkaitan receipt, audit dan detail unit pada skema baru. Bukti lama tetap bisa diakses melalui jalur historis berpolicy. Dashboard memakai kategori stok dari layanan yang sama dan menampilkan jenis barang, total fisik, unit tersedia, dipinjam, rusak, hilang, serta pinjaman aktif.

Perubahan mencakup Form, Index dan Show Livewire Equipment, Blade terkait, EquipmentStock, EquipmentQuantity, EquipmentLoanCheckout, EquipmentPhotoController, InventoryCategory, dan KPI Equipment pada AdminDashboardMetrics. Struktur database memakai rollout 6C.4J. Fitur memakai kode konten dan komponen Mazer existing.

| Pemeriksaan | Hasil |
| --- | --- |
| Equipment, termasuk Feature/Livewire dan konkurensi dua koneksi | 142 tes, 746 assertion |
| Unit | 464 tes, 2.162 assertion |
| JavaScript | 7 tes lulus |
| Chrome headless 1366 px dan 390 px, tema terang dan gelap | Empat kombinasi lulus. Kategori dan filter, detail stok, preview dan hapus pilihan foto, form retur, checkout otomatis/manual, review, SweetAlert2, serta konfirmasi checkout di mobile gelap terverifikasi. Harness lulus 1 tes, 3 assertion PHP ditambah pemeriksaan browser. Penyimpanan retur diverifikasi oleh suite Equipment. |
| Kualitas | PHP lint, Pint, Blade, Vite, diff check lulus. Vite melaporkan peringatan PostCSS existing. |

Posisi schema 43 tabel dan 25 migration pada kedua database. UAT manual perangkat asli masih tertunda.

Regresi dashboard lulus 15 tes dan 68 assertion, termasuk batas maksimal 40 query untuk Super Admin. KPI Equipment menghitung jumlah barang, klasifikasi unit, jumlah pinjaman aktif dan keterlambatan memakai agregat tetap. Fingerprint database utama dan testing cocok dengan catatan rollout setelah fixture dibersihkan. Hash media privat, ledger demo v2 dan arsip v1 juga cocok. Verdict **READY LOCAL**.
