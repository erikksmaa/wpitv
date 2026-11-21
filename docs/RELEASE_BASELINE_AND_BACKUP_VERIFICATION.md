# WPiTV Phase 6B — Baseline rilis dan recovery

## Calon rilis

Baseline adalah branch `main` pada HEAD `9a35b6725495a828c5ff3372f62f277cbb9d829c` ditambah working tree yang tercatat di lampiran manifest. Manifest memakai SHA-256 byte lokal. Git dapat menormalkan line ending saat staging. Periksa ulang file yang dipilih dan build dari revisi yang disetujui.

Review sebelum commit mencakup integrasi Agenda/Live, dashboard, tema dan navigasi, empat modul baru, 18 migration additive, route dan middleware, tes serta dokumen fase. Dua aset statis di `public/css` dan `public/js` merupakan bagian aplikasi. Folder `references/wpi-tv` merupakan bahan review dan dikecualikan dari paket runtime. Tes dan dokumentasi tetap tersedia untuk review source.

Kecualikan `.env`, `.env.testing`, credential lokal, SQL dump, media/data privat, state provisioning, fixture, cache, log, `public/hot`, `vendor` dan `node_modules` dari manifest perubahan rilis. Bangun aset Vite dari lockfile calon rilis. Backup ini berada di direktori privat profil Windows di luar repository dan web root dengan ACL akun pelaksana.

## Backup SQL

| Sumber | Tabel | Migration | Baris | Byte | SHA-256 |
| --- | ---: | ---: | ---: | ---: | --- |
| wpitv | 35 | 18 | 36.408 | 4.281.669 | `d44d2b55edffe49938c02c8a7776523b7149fa32d0b0cb9055adbac3ec9e8181` |
| wpitv_testing | 35 | 18 | 18 | 64.828 | `9c87b86ee63ddc3512814912b43b5d898a703cb5120461085833190726a7214d` |

MySQL 8.0.30, dump single-transaction dengan struktur, data, indeks dan constraint, serta opsi routine/event/trigger. Kredensial backup memakai akun lokal yang sudah sah, terpisah dari runner testing. Kedua dump lolos pemeriksaan UTF-8, SET NAMES utf8mb4, hitungan tabel dan INSERT per tabel, footer, dan SHA-256. Fingerprint sumber sebelum dan sesudah dump cocok. Runner tetap DML-only dan fixture testing bersih.

## Restore drill

**READY FOR 6C.** Kedua dump diimpor dengan MySQL 8.0.30 pada instance loopback disposable, port 57965, UUID `476c30f9-df2d-11f1-bec4-84a9382acf0d`, dan direktori data privat terpisah. UUID, port, direktori data dan skema kosong diperiksa sebelum import. Akun import mempunyai grant terbatas pada `restore_6b_primary` dan `restore_6b_testing`. Aplikasi tidak terhubung ke instance tersebut.

| Target | Hasil | Durasi import |
| --- | --- | ---: |
| `restore_6b_primary` | 35 tabel, 18 migration, 36.408 baris | 12,791 detik |
| `restore_6b_testing` | 35 tabel, 18 migration, 18 baris | 2,544 detik |

Hash seluruh baris dan metadata charset/collation per kolom identik. DDL cocok setelah menormalkan penulisan ulang charset utf8mb4 yang eksplisit sebelum COLLATE. Perbandingan tersebut mencakup FK, CHECK, indeks dan AUTO_INCREMENT. Percobaan awal berhenti pada perbandingan path Windows atau representasi DDL. Pemeriksaan akhir mengonfirmasi metadata encoding secara independen. Fingerprint kedua sumber masih cocok setelah restore. Instance dihentikan dan direktori data hasil drill dipertahankan secara privat. Restore SQL selesai. Recovery media publik dan lampiran privat masih perlu dibuktikan.

## Environment dan gate Phase 6C

Environment lokal memakai debug aktif, session/cache file, queue database, filesystem local dan mail log. Testing memakai debug nonaktif, session/cache/mail array, queue sync dan disk local. Template produksi memakai debug nonaktif, session/cache file, queue sync, disk default public dan mail log. Lampiran laporan memakai disk local privat secara eksplisit. Konfirmasi APP_KEY melalui secret store serta validasi session, cache, queue dan mail pada staging. Verifikasi HTTPS, trusted hosts/proxies, CSP, DocumentRoot public, batas upload, private storage dan izin web server pada lingkungan target. Uji tujuan WhatsApp dan siaran WPiTV yang disetujui.

Matriks RBAC kini mencakup empat modul modernisasi. Checklist produksi mencatat baseline 35 tabel/18 migration serta recovery media publik dan lampiran privat. Gate Phase 6C mencakup review calon rilis, persiapan staging terisolasi, validasi backup media dan lampiran bersama SQL, dan penjadwalan UAT dari checklist. Deployment dan UAT manual tetap menunggu fase tersendiri.

[Audit proyek](FINAL_PROJECT_AUDIT.md) · [Checklist UAT](FINAL_UAT_CHECKLIST.md) · [RBAC](RBAC_ACCESS_MATRIX.md) · [Checklist produksi](PRODUCTION_CHECKLIST.md)

## Lampiran manifest working tree

| Status | Berkas | SHA-256 |
| --- | --- | --- |
| Baseline | .env.example | f94ff19ec1a113b5bd211124576fbbf3bf73071f63ef4fb4737f0bd1cbf4abbe |
| Baru | app/Casts/UtcDateTime.php | e77c398473f29b64883bbae9f92478d2e1f88904ae4bef48279f8812f9a82291 |
| Baru | app/Exceptions/EquipmentOperationException.php | af4e183b717fd68b8dab5b6493390ca7bdd5c67296c2f2fc8c2d58d4c36540f1 |
| Baru | app/Exceptions/LiveStreamOperationException.php | f280a9f32a760e415387322f94dd5569ea16edfedf61b4cb719f648af32666c7 |
| Baru | app/Exceptions/MonthlyActivityException.php | 8e8f08a7eb8110f9dbf134f72a8dbdadf1850de3af71c5d0e891fc8e4fe9dc6d |
| Baru | app/Exceptions/TVClubSocialException.php | 4b225a34554f7a0557cb063c87bc5ff4e88d7ba9157183c88da3f0f4d2965141 |
| Baru | app/Http/Controllers/EquipmentReportExportController.php | f82eb3b0db262dca5e9f1125390199177f3a692a0457d0e37d3cbef9d3201f88 |
| Baru | app/Http/Controllers/MonthlyActivityAttachmentController.php | 5481c3994186debc60b0b0684b0dbe530b65f8e4eaff5e4778ae298b14299c08 |
| Baru | app/Http/Controllers/MonthlyActivityDocumentController.php | 46916ad3c06842d585a6ca7b6eb126fcfe9d4715ea6ef2dfa5e997cd5aea932d |
| Baru | app/Http/Controllers/TVClubSocialExportController.php | 86ffae65c48cc58b60d59349af2447771e9e427152e78ff45f5002c194aa4569 |
| M | app/Livewire/Admin/Agenda/Form.php | 81081faa491b5a5a7b11a015fbffca6645aa69dee113ebe441aa63a0c10c1294 |
| M | app/Livewire/Admin/Agenda/Index.php | 05996cfa9d77108f4fbaca7c4cd6e15c8475ab96a11b4773e8e7c339a77c2160 |
| M | app/Livewire/Admin/Dashboard/Index.php | 9ad97f4ffe269f13f2517b3cd8f07fa10c47c49f73c49697262937c7080c3ed8 |
| Baru | app/Livewire/Admin/EquipmentLoans/CorrectionForm.php | 565c73a0b24f73d0021ba1063a52eb33d2171eff830c4294512ed5aaa8128d44 |
| Baru | app/Livewire/Admin/EquipmentLoans/ExtensionForm.php | a7e6d65cfd682d3b0a7c0a6688f2d12f32db0ef69b4dcd4eb017a80246088e80 |
| Baru | app/Livewire/Admin/EquipmentLoans/Form.php | 4bcaaa4da3a6e3834a77a21aaf34ad288257f7d38ecb1751b6dab290be650d40 |
| Baru | app/Livewire/Admin/EquipmentLoans/Index.php | 2498711b0ea0c9f02f4b02421d834007409f57ae397a207432efc11f32745f9d |
| Baru | app/Livewire/Admin/EquipmentLoans/LossForm.php | 94dccc30a5495d78370b2809abdfafdef3447104215e196919d32b27f5fa7fc7 |
| Baru | app/Livewire/Admin/EquipmentLoans/Reports.php | 6a79b24998dba6057ffa46e45472713a56725e1cf67a389438c7dda9aa15e4bb |
| Baru | app/Livewire/Admin/EquipmentLoans/ReturnForm.php | 6a3b35929a27c265bd3ce3a5457db9b1dfa748eae2c760d13277533790c4dc41 |
| Baru | app/Livewire/Admin/EquipmentLoans/Show.php | fa5d8da670ca8597168eedf8b97d8cada638652a46c962c9fff2185b62dd24bf |
| Baru | app/Livewire/Admin/Inventory/Categories/Form.php | 153469c473f496eeca117fdad485b76a1bbe9d02854f9ea9d0cbc8c38b051f43 |
| Baru | app/Livewire/Admin/Inventory/Categories/Index.php | 05582da8a094b04f2acb5d1f6975e4bb126f450980614181cfc959aa8fce0803 |
| Baru | app/Livewire/Admin/Inventory/Units/Form.php | ce8c9ae8d6701f0c43793a06e83ac618367266f163c5cd8beff36cd883cb9f1b |
| Baru | app/Livewire/Admin/Inventory/Units/Index.php | b0df015b19251442835884379ae89ac2a414045e74584c8a2f3ea4744267f411 |
| Baru | app/Livewire/Admin/Inventory/Units/Show.php | ce75ffd9e73cadd78130bedd4ac297506020c6b24e6c9b2baa099f27a85e30d6 |
| Baru | app/Livewire/Admin/LiveStreams/Form.php | f5af2945f4e69f3c72be0c94ead68aad44e558bf36051bd01cd26bfc2c44ea8a |
| Baru | app/Livewire/Admin/LiveStreams/Index.php | 5a98b6d50761863a650503ae41771b7defc128ef7c7f2f57a8a0e50183833678 |
| Baru | app/Livewire/Admin/LiveStreams/Show.php | 28ccf2f59ee0932cf918df6354beb76b29932e804f355d54541cf9907f56850a |
| Baru | app/Livewire/Admin/MonthlyReports/Index.php | 11cec424dc260b233f89f3944e14fc0b20b827ccd743922d580c73879577aaef |
| Baru | app/Livewire/Admin/MonthlyReports/Show.php | 05084486389632446c99ec450c75f4a092b995a1e842dc30f64748001b8f5eb6 |
| Baru | app/Livewire/Admin/TVClub/Social/AccountForm.php | c03c17cb137d449426c61feaedc3d0d5625b183d8935ec989ff3da3ef6312f01 |
| Baru | app/Livewire/Admin/TVClub/Social/Index.php | a618b189ea8d94e279990dc44533e47bb0fbedb35ba91d4dc10c5e270dd86f27 |
| Baru | app/Livewire/Admin/TVClub/Social/Observations.php | 9b9420357288c3f1706b9ddda16f2526fa524fb50a181151a74789c879f7c852 |
| Baru | app/Livewire/Admin/TVClub/Social/PostForm.php | 97c41ff832388eb918b77f544b4706cbff36a37f19be09f4df87d3c9dfe955fc |
| Baru | app/Livewire/Admin/TVClub/Social/Reports.php | 5ba57a866d437769b6e9a37e034cddd81a11768b07e59dbe1d0472a6383486fa |
| Baru | app/Livewire/Concerns/HandlesEquipmentErrors.php | 7c62cfb8f68a2b351083a2a807d2ca4c9554d31f313b5dee3409e02a7459c9ad |
| Baru | app/Livewire/Concerns/HandlesLiveStreamErrors.php | f163818457ad8a6090124bac6ca4cef62a9711d5deb0fc2826c6734c6d05bc2f |
| Baru | app/Livewire/Concerns/InteractsWithLiveStreams.php | 8e62a98235f01a2fbfbf60904816b222959ad6baafd76d9cdf7d2a882d84167b |
| M | app/Livewire/Public/Agenda/Index.php | 579be2173eef45b1aef50389c77718aa93a8e50c67060263ca0279477e492864 |
| M | app/Livewire/Public/Home.php | 08d95b08d1feaa7e68cd73f551d4c24056985a9936f687581c81d87a568afd0f |
| Baru | app/Livewire/Public/LiveStreams/Index.php | ea2d126afc3955b7f3af4e50b8ad56b6803f7dfff5ef9d7fa65d7b33420ae7bb |
| Baru | app/Livewire/Public/LiveStreams/Show.php | 3a02ee9dca7c9b2f35cc01ce0a58e719d28bea43bf71cf27bce402b62f0047cd |
| M | app/Livewire/Public/Programs/Show.php | 2a80e1c4e757db06d9717f8ae4312ee2c077ee730a04c4653906c4c83aea9eda |
| Baru | app/Models/Concerns/HasUtcTimestamps.php | 00986eaac27bc2684694a72770e94f42fa260254af095256516d24b288af6528 |
| Baru | app/Models/Concerns/MonthlyActivityHistory.php | 942a879217169560d75dcf66c8753782706e00032334188668a75d696c688bd5 |
| Baru | app/Models/Concerns/MonthlyActivityRecord.php | 74ffbaebf1118114dfda365203533f08a6516f570f87142c1f698c241adcef51 |
| Baru | app/Models/Concerns/TVClubSocialHistory.php | 977a4fddc304634538d3499a045628431467eb4bf5a6ecb29f65422140ff3e43 |
| Baru | app/Models/Concerns/TVClubSocialRecord.php | ed3c459fd3fac39f2bba027cf3797964453b1da2b173149a3a82f392a76290fa |
| Baru | app/Models/EquipmentAuditEvent.php | 7542e13452e987454c73d3c760813f849c1f448e4b68227042f656b049da31ad |
| Baru | app/Models/EquipmentLoan.php | 5b0a49e7ca355dca79fb1e3316551019070eef9a8ffaea8fdb016518497d6f10 |
| Baru | app/Models/EquipmentLoanItem.php | 7d0cb6992df107bcfa830b89ee47537d474d84f0e6a47ab1b81dac909f11e4f1 |
| Baru | app/Models/InventoryCategory.php | d959b879a408cc19efcd1504f737eea472e43316549d00ff6ca27f84f9ef4d0b |
| Baru | app/Models/InventoryUnit.php | e2578859772bf625c2f2ce5fe21c91d2f264c2c67af9ea47f980da3452c9c141 |
| Baru | app/Models/LiveStream.php | 1f830a1e7db6965810a152eca4a73a0d6d6c81bff18fcc557812ebd0d76482b6 |
| Baru | app/Models/LiveStreamEvent.php | 18a2c2e7a93447097742bd7139a9745b8fdf8f6257305d94c70d9f6f7e501bf1 |
| Baru | app/Models/MonthlyActivityAttachment.php | 9c79165a1330e80f518cf0761bad57f4dd2c0959c0c0c46cef72a950bae5d9fb |
| Baru | app/Models/MonthlyActivityEvent.php | f98cc24f07f68b782d37695da373a92ecbcea6a05f303fc2ea9ff38d8c32acdd |
| Baru | app/Models/MonthlyActivityItem.php | cd7da9ef6496153073cc81bcf034c1a73c758f438ae289d3e0bd8d7528c30c69 |
| Baru | app/Models/MonthlyActivityReport.php | a1a7f699f8d9d353c7e4e2b209bf5b6f833fd5f0c9ea7b27c1cf1e190b1059fb |
| Baru | app/Models/MonthlyActivityVersion.php | ccbf6310014e88ed29ad9a977939523683239467c28e37e2883b33ee2bc8f539 |
| M | app/Models/Program.php | 6826553f1950eb4f4645642ab152b46313bad9300063870fa6ee396bf45bbfc0 |
| Baru | app/Models/TVClubSocialAccount.php | 5540ae459f4df7092f643cdc13f453707e2c6bac4bce7fe7d056bb6b57436bca |
| Baru | app/Models/TVClubSocialAccountObservation.php | 456a5fab9c077957696832511b91642084af16138605eabb5aaf9a84d2103c2b |
| Baru | app/Models/TVClubSocialEvent.php | cc8fa85c0c043a7414038fe7b424a3e351fcbd7e167c60f27bead21c33fabe2b |
| Baru | app/Models/TVClubSocialPost.php | a302b27235008df56840ac4fbc32ae726af0ad804686f3836ed67d225c69e23f |
| Baru | app/Models/TVClubSocialPostObservation.php | f0acc9d021cc6a1f791fb13531fad82ae85b76858c6ecfc02b033f2038cc70e7 |
| Baru | app/Models/TVClubSocialReportVersion.php | e920f0d18e790458ca0460e5f7a59018fe922df225135d0585dae8490a954b13 |
| M | app/Models/Video.php | 0172a60a8fd74d90e2c4a4c78600ad17e949578493ae97f7ebcdf2a52650af62 |
| M | app/Models/WpitvAgenda.php | eb70552fdb18deefe43093dc2ea4d2ab38a9496e41d41cc7085bbcae357be2f5 |
| Baru | app/Policies/EquipmentLoanPolicy.php | 46b1019604fe6ae37b4bc5426b640dc5cb9e0c338aeb98b962acb33f60e3ac93 |
| Baru | app/Policies/InventoryCategoryPolicy.php | 41af184805c1fd624d451fee73e54792f00d9ae0f9f51b2ed2a067a6555c1b33 |
| Baru | app/Policies/InventoryUnitPolicy.php | bd61c1cabe0da4426e398f986b4790af786e21010fa2cf5a7aca883af048c608 |
| Baru | app/Policies/LiveStreamPolicy.php | 04695cf690a3a891cc8085a61d8d4e9c67b9e8528fce77b6a3c423e46be20bec |
| Baru | app/Policies/MonthlyActivityReportPolicy.php | 6f2f42f7406eb71d3d10ade41080b7afbaf0fc9fc786b159b55d6d2316d50953 |
| Baru | app/Policies/TVClubSocialPolicy.php | 7f7c81d588db2850638c6cb163f0427faba3fda16eaa42f694932160eb3a57bb |
| M | app/Providers/AppServiceProvider.php | c962f383e4b213c0602875f4fa755dc0e677ee006ff9c5ff7d81aad8ab0f2dfa |
| M | app/Services/AdminDashboardMetrics.php | 83793700649f6b471b9f9308ade964fb23448b39409547d9941b36501ee92b10 |
| Baru | app/Services/EquipmentLoanCheckout.php | 9a997c9af0ec5ea6ed82dda39ec0196d44efd73fa746dbcc4ab6c10164ac0d0d |
| Baru | app/Services/EquipmentLoanCorrection.php | bbdba3b83e571d9053b160f209d10bbed484497c9fa9d6844a843f78780d5ecf |
| Baru | app/Services/EquipmentLoanExtension.php | 1eddabc9b7c4d1490314f954b02d77a4aa88635a841fde944d8fe82bb5d3a4c0 |
| Baru | app/Services/EquipmentLoanLoss.php | 034186146db415180663c5aa128632dd7edb0c7f656994243a3edcbc8cf7d95d |
| Baru | app/Services/EquipmentLoanReturn.php | b132a376e09c87e6765946f4c61468b7983b884bf77867e56e8bc65cb87d2257 |
| Baru | app/Services/EquipmentReports.php | 3cde5c275030b5be27fa302389318d1aaec709a568a2e8caed7c7fd6c5929003 |
| Baru | app/Services/InventoryUnitRecovery.php | f17ee72d698802e2c257d9f7da3199c8fe43465cfccb588981a8449285f4499b |
| Baru | app/Services/LiveDashboardMetrics.php | 9fbd4afcf3f97181fe7dfa02ee281e4c9a07a34f08d5928868c6c15b0768bf56 |
| Baru | app/Services/LiveStreamHighlights.php | 6e0b8937ab2fb86bce3aac0c7a3021bbef2d5edfba204120b38e1c575f3d04e3 |
| Baru | app/Services/LiveStreamLifecycle.php | fb6c202deb71ebd1ea60a2266fc48819452f8bba2c7b9779f3524afbd478611f |
| Baru | app/Services/MonthlyActivityCandidates.php | ccb7c33f3c81c0b3ba5487c09292e62cf2f1fce06b574652f9d547806067babd |
| Baru | app/Services/MonthlyActivityDashboard.php | 08d1780eb027116672f2774dedaab4a8e982d5da26433af53af459dd969409c2 |
| Baru | app/Services/MonthlyActivityFiles.php | e7538d0c87020f694de4ede2b39c6a20230d034816d42e8fa1ed1d4e2c57fe4e |
| Baru | app/Services/MonthlyActivityLifecycle.php | 2e9400011d5d778e8370f0701b299629869b117411a2898cc2c838334f9378ba |
| Baru | app/Services/TVClubSocialDashboard.php | b2558853ba1c23520aa4358eba7c51904c1ac4179af3c5add0bbf069cbbd0c95 |
| Baru | app/Services/TVClubSocialService.php | 3e69676a3f5ee1930ea1ca49e48ec9aba43f0a07f7c8eb620445881d5a1aa288 |
| M | app/Support/AdminNavigation.php | 9d5e202ee94ab1ed6737a467fc671c76ad7eb9e6941a39c4ecabf8111c65410e |
| Baru | app/Support/AgendaLiveGuard.php | ab4cfd7f7038db9d4d1b67a9ab0a874d0f8b1fc14ad5dbe54151027edc10d293 |
| Baru | app/Support/EquipmentErrors.php | 143073cb0822861792589842b05355875dd6f775f8ff2478a1db872ff7017af5 |
| Baru | app/Support/LiveStreamAgendaChanges.php | 54e52dc0c08d44dc005b47a2837afdb056e37290f3300bd2fe69a92957118f78 |
| Baru | app/Support/LiveStreamErrors.php | 08329a3707761201a113635e20791659206111efda95eb4ce8f5d67e0072738a |
| Baru | app/Support/LiveStreamFilters.php | 8c13c95af78e925a78ae17e96f7c9222a9788e91806dee1c4f3d1b4f4c5f91bd |
| Baru | app/Support/LiveStreamSchedule.php | 6d3b1b4ea075da7da0777f652457897459ffa9c4b38961010d05a1b8ead908d9 |
| Baru | app/Support/LiveStreamState.php | 0f42a188998024252992ace99415af612dba9ae2d38d10f33dd16ea0181d6df8 |
| Baru | app/Support/MonthlyActivityRules.php | fcf46dc5e6a7995f48eed80083c12f269126978899ca990ea735be9d8d609e56 |
| Baru | app/Support/PublicLiveStreams.php | d5aadb7d76bc1486c99c32ae4ab36990d188229b68460bf08b2e15920fe66406 |
| Baru | app/Support/TVClubSocialRecap.php | c5af0b65c3622639b19f275a5ecf06ab1c9359d6061a97797421201fefaba830 |
| Baru | app/Support/TVClubSocialRules.php | 61540ec4d1ca7d055cb4c29a4f0c7c22a21e95e8296f6d0285a2146169a603ae |
| Baru | app/Support/YouTubeVideoId.php | a83d10ab7148e80468a300ec309517790165e5d62b5a9bbf14154d107a5de88f |
| M | bootstrap/app.php | 810586c90bcb5015d69f4a4edec50e1d4d5de0605fe983f68a06f830cebdec7c |
| Baseline | composer.lock | 11f5b3467bb679d03f2cb730ceaf00937be3f1798a3d2264ded8acb25fc832fc |
| Baseline | config/filesystems.php | 46b977d9555ed617aa0d42055a0c76a34c9d16c63ee634ff6683bc95712c9317 |
| Baseline | config/security.php | 42bff7a045e06e470d19b9b5878ef81668fd3419c6b801db2f9b2ceb9944f5ba |
| Baru | database/migrations/2026_11_08_000001_create_inventory_categories_table.php | 10fc9d9ddc31d70192c44c34b0089bd09f9a314060980708242c47deb413bdef |
| Baru | database/migrations/2026_11_08_000002_create_inventory_units_table.php | 58094cdd351131f473c0d2e74bbc9d1daf0847a4700c158bfb587a791d8ebb92 |
| Baru | database/migrations/2026_11_08_000003_create_equipment_loans_table.php | 210b6258909f762b58a67fada43575b0eaf9e172932ee372f4760554b014ef0e |
| Baru | database/migrations/2026_11_08_000004_create_equipment_loan_items_table.php | 02f90e7c1747cd7123117af7091a01d0a971afc36e0781482e3c961a1d0659e8 |
| Baru | database/migrations/2026_11_08_000005_create_equipment_audit_events_table.php | fe123abe12dafff4306dd1fb704c4161d56161bc484c60b24478a7630246eccc |
| Baru | database/migrations/2026_11_09_000001_create_live_streams_table.php | 471efd2da26d0e7ecbdf64680570c3de570c084b59b0a840a86bdb8ab0306db2 |
| Baru | database/migrations/2026_11_09_000002_create_live_stream_events_table.php | c321ab00f7f4722c1be852e1664e2c3c7eb620474f97c71a842535e9a3a40f81 |
| Baru | database/migrations/2026_11_11_000001_create_monthly_activity_reports_table.php | 8ae675fe5defd1192d4f3a4d54b46394c949d9a5e5c63cba5bac15cd3fb28396 |
| Baru | database/migrations/2026_11_11_000002_create_monthly_activity_items_table.php | 6f110ce4d0718c47ef966be111bc9fa86cd243d790fa53ee42b048ab7c234471 |
| Baru | database/migrations/2026_11_11_000003_create_monthly_activity_attachments_table.php | 0d6e39e6eb191510dee64dd24846a02bdd453d58ef5e36073b5970051322bba1 |
| Baru | database/migrations/2026_11_11_000004_create_monthly_activity_versions_table.php | d60cd9fcc09dd0eeb9ef261a48ff9b66fc0d6a89f9931744e650de70013136d6 |
| Baru | database/migrations/2026_11_11_000005_create_monthly_activity_events_table.php | 8b8bfa08963d505e3e754beffeaec36251ffda87979afd352c0cf87ad10011db |
| Baru | database/migrations/2026_11_12_000001_create_tvclub_social_accounts_table.php | a878c05786a85d90c3ccc60524596b2251770358b66bf2e34d7a0bd3a0cce2d8 |
| Baru | database/migrations/2026_11_12_000002_create_tvclub_social_posts_table.php | 7bc8920f10472765d5261e544e1483d6d07b844f199a2e4782ecbe92b59a8aff |
| Baru | database/migrations/2026_11_12_000003_create_tvclub_social_post_observations_table.php | 4467620609727d54d387c63abce68ad7c7f3a4d51e4e387208002f7a740fb2fc |
| Baru | database/migrations/2026_11_12_000004_create_tvclub_social_account_observations_table.php | 9b25ae76e6b2096328b5ff3aeea2b592ecd13a28a3183541e674de621d89c830 |
| Baru | database/migrations/2026_11_12_000005_create_tvclub_social_report_versions_table.php | ff52296b4b4765854ecf6b9963f08ef295fef20b923f6f1cf0ca6403be4cae81 |
| Baru | database/migrations/2026_11_12_000006_create_tvclub_social_events_table.php | adcb3f7d9fdf839370acbf9abf27f4bb2ec4c553b55e35a1d5d806d2f4da3fa7 |
| Baseline | DEPLOYMENT.md | 70d1ed522c9f7eaac8b5280847e82ed3e5656d1c20cf0cc1d147ac44a702fa05 |
| Baru | docs/EQUIPMENT_CONCURRENCY_TESTING.md | d63e8492a7fc4af6f526fbae9861ac610bca24ccbb1c9799d03df60e927124dc |
| Baru | docs/EQUIPMENT_VERIFICATION.md | 1ec151921132ec795779379ceb96b2a09623f3c908d32d6863d37624490080a6 |
| Baru | docs/FINAL_PROJECT_AUDIT.md | 79edab876f137a4378585e15076bbe2c882f098fd2e3c41b68f97f844ecab600 |
| Baru | docs/FINAL_UAT_CHECKLIST.md | 90b13b863b08eddbb49ec6d6be7892ad2c0f462864043483f974ddba4e95883a |
| Baru | docs/INVENTORY_LOAN_DESIGN.md | f79d36fa542d1ab4d11f846380e0259722055be003c3b30071ddd9fbb4877961 |
| Baru | docs/LIVE_STREAMING_DESIGN.md | 18ed7e5a10fc3158596ed4821492c995f88b65b1ddbc57b709e48d150924e688 |
| Baru | docs/LIVE_STREAMING_VERIFICATION.md | b23cf6dce356a75b1cf72176ef4c77bef2b154242c8673b99a10e11c03dc61aa |
| Baru | docs/MONTHLY_ACTIVITY_REPORT_DESIGN.md | e4d6d0ee0d0ed0b275c09b943767b9420899efe8ae14bb0193e1bfba356b8902 |
| Baru | docs/MONTHLY_ACTIVITY_REPORT_VERIFICATION.md | 6088efb58888305b7efe11fbf2c63df3035d93b5a0bf9e959268b608a1adc0f5 |
| M | docs/PRODUCTION_CHECKLIST.md | 8e8bc57ac82ebc192b6b42947c9522a90ad0cebe870e064206467b63614165f9 |
| M | docs/RBAC_ACCESS_MATRIX.md | 5b2786826c35b1fb77028cdf1a0a4a5dc77ca6ed2f8057773b99b9911f9b72c6 |
| Baru | docs/TVCLUB_SOCIAL_MEDIA_REPORT_DESIGN.md | 453e4e50dbbf931b198bca25bc449d548f31067a754649a176493b2e213f0a40 |
| Baru | docs/TVCLUB_SOCIAL_MEDIA_REPORT_VERIFICATION.md | 4136eafdf993dbe93805eb29a5ab146f58b2b08eb493102445654183fdd4f6af |
| Baseline | package-lock.json | b8cd9f74dbafbf2319ab306ed9cb64820db40e66e88fc0d1f84536e9148a0406 |
| Baru | phpunit.equipment.xml | 5a98526f077cf1a9dc67e759c29ba2b296bb31842d368deabac183bdd1604a47 |
| Baru | public/css/monthly-report-print.css | e09ecf31c21eeb8b57506a80d2d951d09737ed2ff16bf829e9eda354bedea8da |
| Baru | public/js/admin-theme-init.js | 4bca5408a12c80991d2941403abf371f08a8027fa1b88f9f1012607a926c1572 |
| Review saja | references/wpi-tv/layouts/public.blade.php | c3f1222f6946244721dd6f4ef6e64ddf3d39761d37cd0bcb134d2315ec87e19a |
| Review saja | references/wpi-tv/pages/agenda.blade.php | 8f331ea2ef636c36e85fc2f6e403c3b434950ceb687701b679e5d2a3a366c070 |
| Review saja | references/wpi-tv/pages/home.blade.php | f65481442a42f3a412d88a61320e1909ac9b060d945b0061f6b7df0e7e7e2555 |
| Review saja | references/wpi-tv/pages/news-index.blade.php | e4fd8fcd1c1e2b583a55e7a41de7b621292fef855e020ae70484405604ad96e9 |
| Review saja | references/wpi-tv/pages/programs-index.blade.php | 7b4988fe2d3a3e00cf3a8862a9ebccec878dff09b9062f7506ef076c70ee53fc |
| Review saja | references/wpi-tv/pages/programs-show.blade.php | 391459083a809666c23033b4167ea0a16a46938c2df8f7a91d0977ddb7629299 |
| Review saja | references/wpi-tv/scripts/admin.js | 9dd6061e5411795787d005963e75f7de47767dc9fbde30a8d632fea26ff37b50 |
| Review saja | references/wpi-tv/scripts/app.js | ef2d6f5db880db7aa4407c2d3930e6ca3c298ae19182aeceae5b4b889ff284f4 |
| Review saja | references/wpi-tv/scripts/bootstrap.js | 6fd9fc82e68fa915a882f4e227b9faacbe7bfd66cb953c628840e83bda6b72f4 |
| Review saja | references/wpi-tv/scripts/public.js | 4eed56f42cd6a0662d56818c1ae7d31100f59d5b8a7de123f12be16ef0a79f17 |
| Review saja | references/wpi-tv/styles/admin.css | 152e971590e6f781b7c30a44c1d8784ac88d1e6d77962be538767e428a068178 |
| Review saja | references/wpi-tv/styles/app.css | cc1a7ad0d019ddb1d32d0ecb588ba0ac26ce41d8625dd6c366348b25f83a28ec |
| Review saja | references/wpi-tv/styles/public.css | 43615d495c1abf7bf0302d7b8bfcd932555d65a2a74f82497a42f82eb671fd04 |
| Review saja | references/wpi-tv/styles/tailwind.config.js | 5cca85962e54bfadbc76b40e7f8945f692cf87f762efafe3ed893a5fb62ea811 |
| M | resources/css/admin.css | a9797b7c6a25726958025b240f50a1ff70cf2b8fadd2904fcfc15b225279192a |
| M | resources/css/public.css | db5bb57e78fd8adac1fcdaa92e1f2a93a9ba767bc0c4b70bde5698baf1da4650 |
| M | resources/js/admin.js | 4357ea53ac9545caafcb270df575633fa6d0282a6e7559aae037efc6cd64528d |
| Baru | resources/views/admin/monthly-report-print.blade.php | b8974c13c2e3b5d429f5bdbab23faee5ae5df7c094efa70a2308339ff683dd29 |
| M | resources/views/layouts/admin.blade.php | ef9242fe6ae5a47bac99de205e8104e220eec640ec3991929893407492848da9 |
| M | resources/views/layouts/public.blade.php | 725444caf092f5dcdf761785378c1984e8e58dc9afe6b8b460db5a5d4edf32f1 |
| M | resources/views/livewire/admin/agenda/form.blade.php | 23503d67f47a9d31da515ea2a16190141e21c48b23615ce97884d2a216b87de8 |
| M | resources/views/livewire/admin/agenda/index.blade.php | 8c5fc6a66bc5cb6f9ef7a36393c82b0128c0017c056dee98e80fa3bd29d49b87 |
| Baru | resources/views/livewire/admin/dashboard/equipment.blade.php | a957a2fe2dba8e840a82a3ba1909ca7d691bfe198ee9c27103fdb354739cbb2b |
| M | resources/views/livewire/admin/dashboard/index.blade.php | 8d73b5dd00ccdc2daff8cb6b91c1d5529bbc64cbe434a9c139498bbfcd61a87c |
| Baru | resources/views/livewire/admin/dashboard/live.blade.php | 80a431783ad8b868372f265d95537bec219868b867fe7dbe8ee5ae59a435ffd2 |
| Baru | resources/views/livewire/admin/dashboard/monthly.blade.php | a8226e52327b56821e8d822857b776d46b0ea86218a02d1cff8a010a389b705b |
| M | resources/views/livewire/admin/dashboard/quick-actions.blade.php | 0a07f0840e23164f284c8eef8c7c4c1fbcc9b16dcd307b69cc4ed701f04ebb03 |
| Baru | resources/views/livewire/admin/dashboard/social.blade.php | e76b036ece5882d94c1676327bc46f0b03898aefc3af28e5088aebf3f5829ca5 |
| Baru | resources/views/livewire/admin/equipment-loans/correction-form.blade.php | 62d03c2d529c1df62acf2b6f81b350dde93094d87f4e368ce2896d977ae0a9d8 |
| Baru | resources/views/livewire/admin/equipment-loans/extension-form.blade.php | bd7382baf82600a1b1b7f29e5100b3833d7d9c16843d51093947517dd0514bd3 |
| Baru | resources/views/livewire/admin/equipment-loans/form.blade.php | 3d3c2306e4465a6070ccf51e15a0f2a38cc6b6aae9a021b2c24e10a651144eb8 |
| Baru | resources/views/livewire/admin/equipment-loans/index.blade.php | 9aba7048e7bbb6eda01053a1a24078cb4985fb29075769ba77f820d28a10888a |
| Baru | resources/views/livewire/admin/equipment-loans/loss-form.blade.php | 4831823849c76c19a4ad681c52e8a7cf08411946555adce0dbec90dd3eea0815 |
| Baru | resources/views/livewire/admin/equipment-loans/reports.blade.php | c30e0d97c2ad846d3c8d08dbf999c3bdc6efa3b600bae30d63900f80d5b8b7fb |
| Baru | resources/views/livewire/admin/equipment-loans/return-form.blade.php | fb5cf7975b43cfc0fcce6c357f40974c4590d133c7a03ba8ed3b41ab9cd6efeb |
| Baru | resources/views/livewire/admin/equipment-loans/show.blade.php | dabe3dfd544b6dc9c9e37900b70231608fbdeef2f8e9d5091a557de8ce906cfc |
| Baru | resources/views/livewire/admin/inventory/categories/form.blade.php | 61a0f609b37cd1db55aff9e9a264d588ad8c104054990755f118e9a0e3027f62 |
| Baru | resources/views/livewire/admin/inventory/categories/index.blade.php | bb1d9d0d83ef49efd3c70fa9070b2a94911c538a656c8971ba197ecab240a417 |
| Baru | resources/views/livewire/admin/inventory/units/form.blade.php | 59ce630c1dfa570deb493489f8ca5c88a72c6c257e694780bc124a772d25ee52 |
| Baru | resources/views/livewire/admin/inventory/units/index.blade.php | 6db53d56b590962c84b3e58d65a09a469001199a60f33d5df007d6c5e83129f9 |
| Baru | resources/views/livewire/admin/inventory/units/show.blade.php | 776f68abb6b3295662b911142a0a397653ce7363f0665ffea8b8a6d225383a14 |
| Baru | resources/views/livewire/admin/live-streams/feedback.blade.php | 9f231e24edd5fe27f1c934a73ec09f2c9512d093cddc83ae43366fe81a8541a6 |
| Baru | resources/views/livewire/admin/live-streams/form.blade.php | 221731ead859bbb9b2e94478f1b1ec4b36b9119edb582e47de53a36e3a21cbfd |
| Baru | resources/views/livewire/admin/live-streams/index.blade.php | 2f109f70d07ede538e32481db2702961f0ea19cc0852ca802603c96a2528d08d |
| Baru | resources/views/livewire/admin/live-streams/show.blade.php | d5d7bd5b85d153ea5ae8644d04183f64e64932b1c4da315367c23b2f35adc0d0 |
| Baru | resources/views/livewire/admin/monthly-reports/history.blade.php | a27694952a6671778daa3deed7d3cafb02ad3965709971c79ece27b0c028e448 |
| Baru | resources/views/livewire/admin/monthly-reports/index.blade.php | 8fd3b9dce183d67d790e79ee1bead5a8eb5c155715a22ae288bfe33bb62bff41 |
| Baru | resources/views/livewire/admin/monthly-reports/show.blade.php | 06a1dd09c7a850e8516ade253703a67e950371dabdd5860c1debf83bddeff96c |
| Baru | resources/views/livewire/admin/monthly-reports/upload.blade.php | 1f580a236d5910d7d3755d0ecc574f915a3110de5578452692afe21ee5043bdc |
| Baru | resources/views/livewire/admin/tvclub/social/account-form.blade.php | 05e864c78a68e1321d5d3c3811a1648322fa104793ad7b1b5aa36811d8589f72 |
| Baru | resources/views/livewire/admin/tvclub/social/index.blade.php | 2505ff34dc58f5ddbab5f40f6795fce373b09ad9e781137642f8dc16df88d376 |
| Baru | resources/views/livewire/admin/tvclub/social/observations.blade.php | 68883566df3e35615c48dc4d459159b1b2576585fa247470e65ee5f9745e3e77 |
| Baru | resources/views/livewire/admin/tvclub/social/post-form.blade.php | 25761cd10577194f6790691944fa2c6ccdd89da45d68ca8266098e07bf9abfc7 |
| Baru | resources/views/livewire/admin/tvclub/social/reports.blade.php | d88e8c21cf9cb18407e37fa29cbc73d8294d730e7962b96efb379d7a1e9beb71 |
| M | resources/views/livewire/public/agenda/index.blade.php | 15597174c385f8be10e04809cbfccde3d54d1eabbe04fcc5ea1d06cb42ce1af2 |
| M | resources/views/livewire/public/home.blade.php | 71d697902540b401e8923ac50d3a609e21fd4d0316e1a2c28588b7c0bbf99d8b |
| Baru | resources/views/livewire/public/live-streams/highlights.blade.php | 27fd48af288e1c52f4a1b6ab0fc1059888025d64cc4baebd3a1eb0c94438eb64 |
| Baru | resources/views/livewire/public/live-streams/index.blade.php | 131337243f3d237a9a1154b032a2eb2cfa97ad8f611731f4ef78942e0a4e44e3 |
| Baru | resources/views/livewire/public/live-streams/show.blade.php | acd49c7c01519fbd2976471744f72ef975c54b5e01defe996d1c8dc24cf5a8cb |
| M | resources/views/livewire/public/programs/show.blade.php | 96223b4037a3c2c90e675cf946aea15b2ec3dfdb52d132336ffdb40806bc3a45 |
| M | routes/web.php | f3de4f5f179dac778f5ad888830b4354bf395cce095018100ac19d09ac84e993 |
| Baru | tests/Browser/EquipmentBrowserTest.php | 27121b3859cd7162c4d0a7ab357f8af9428cca9b3bc7863f187e3bfc2f0cde7a |
| Baru | tests/Browser/LiveFinalBrowserTest.php | 40b7011380570b8ac25ea2623682324b4e0652a1fd082c798fa9f7dcb67a7e3a |
| Baru | tests/Browser/LiveHighlightsBrowserTest.php | cda6f0e7904ebd8297425b2b480f037e2eaaaa6d5e57ae0bf663f74e8568f31a |
| Baru | tests/Browser/LiveStreamingBrowserTest.php | 322c37d231d26640a580c37a6ecf932ec5564e9077caa82f2b1890ce2a0b6a8a |
| Baru | tests/Browser/MonthlyActivityBrowserTest.php | fdd3eae73051fd6a4edf737c093b2358896956f9a8333337ac9975d181339816 |
| Baru | tests/Browser/PublicLiveStreamingBrowserTest.php | 3e880f914df19b38b9690a045205663c01c78eb89f90cb979cff019eebbca088 |
| Baru | tests/Browser/router.php | 277321207b3fe81d8971dd3f140820f5240275758417c84d684e7e2dc8b7e659 |
| Baru | tests/Browser/TVClubSocialBrowserTest.php | 8c9e2948908db4c0c3864e3f0cd3d4a6702ac79c0a802bbfcfd2815b22a129dc |
| Baru | tests/Concurrency/Equipment/EquipmentConcurrencyTest.php | ea28c766ba62d371a52a84ef14d6f36393163627b075cda260743d45cda01710 |
| Baru | tests/Concurrency/Equipment/worker.php | 21074efe870ed9156c506dc5ae648188ff3aa5415ee27c48f769fbff25a489c0 |
| Baru | tests/Concurrency/Live/LiveConcurrencyTest.php | ae137af855a8e8b4b78257297941fa6614a224a1eae18200f401763ed2bcbf56 |
| Baru | tests/Concurrency/Live/worker.php | 6a6d667e40904241233600b891d7e633b9cc2ff9fa40ec8f19ca92ac37bc69cc |
| Baru | tests/Concurrency/Monthly/worker.php | d4fd21d5fb2d6da5b0118e2be1f4422535596dadc522087ce89b107d43da47c6 |
| Baru | tests/Concurrency/Social/worker.php | 893c5f80089cf46e6fd90fa88d6aefcf26e1bbfeface1d9a97ca21c3984a27e2 |
| Baru | tests/EquipmentConcurrencyTestCase.php | 348f41939b871bdc8ca855954c5d39ff1637db018bf2869f484e580a04007fad |
| M | tests/Feature/Admin/DashboardTest.php | 3df823d7256a5236ea2399a661d92bb9931993b54946746c68003051fdc78e17 |
| Baru | tests/Feature/Admin/LiveStreamingManagementTest.php | ef955684c54769ed87f35b0b6a2aa99f9466016b397b588d0d4873b311c560a0 |
| M | tests/Feature/ProductionSecurityTest.php | 892c24cd7d21b8d476d13e6c9fae1cf9c04a1f37ab6bc0d7fbc359c0e20d31e6 |
| Baru | tests/Feature/Public/LiveHighlightsTest.php | 0fc540e18242899feca7666d56407b2a7df8f28050d16140bb46e72143bdc1e6 |
| Baru | tests/Feature/Public/LiveStreamingPublicTest.php | 23becb0a296b26c8f340c8a987050b1ba602afa4df78fddcc411e369353076e0 |
| Baru | tests/Integration/Equipment/EquipmentIntegrationTest.php | f1bf1e66513f9725521514fdfb1ade5f8c96227647c39563b0fb7e42d9ba0bc6 |
| Baru | tests/Integration/MonthlyActivityAttachmentTest.php | 196af1a0642d9d3356e6fa612b86c3dee44410fdb55cfb8d6c9cbc0571d231a8 |
| Baru | tests/Integration/MonthlyActivityEditorTest.php | 7d4c5f4ae3a0e02f7582fb435f9520f1fb53e8e996e94df921be7ce58ce3e9c9 |
| Baru | tests/Integration/MonthlyActivityIntegrationTest.php | c88fddacebe3e2969098df699cc5e3b9ca5d3e4c1b5dc8923fa7046607e27f0d |
| Baru | tests/Integration/TVClubSocialIntegrationTest.php | d3fae70d737682aeb983b2d1d7785735d5e273cd6d826a2315c2397304fa7c9c |
| Baru | tests/Support/EquipmentConcurrencyGuard.php | f14870a05d5ad9b9789d64c6e5b0d744c42fdcd2a2e033c398056636f997a9a8 |
| Baru | tests/Support/EquipmentScenarioTestCase.php | 6b8e9fe15a9ed0c54af9d5cabf74853597d4f525570d3dae4b7e742b946b650c |
| Baru | tests/Support/LiveScenarioTestCase.php | b6c5c2e532fb8ac83ece48074e37c72bce3979eacba295a04387ed97266b601c |
| M | tests/Support/TestEnvironment.php | a5a6e64f29712d24bcfbc367d14cafea407ec69be7e521f4be3305e646c39782 |
| Baru | tests/Unit/admin-theme.test.cjs | f55599004912461ac6e6e499aaf43ac17e1e9a9a65c0e860cff7787fe649d638 |
| Baru | tests/Unit/AgendaLiveIntegrationTest.php | 3c709d2a63d33c707f80a5161ee096d7f9221d1b05773584f53ae7ff06566690 |
| Baru | tests/Unit/EquipmentConcurrencyGuardTest.php | d2c358582967cbfd8d2037c319ed3b740145a88077a2f5980f07752f37d6f484 |
| Baru | tests/Unit/EquipmentSafetyTest.php | 4355f2f16092cc0b2f30d62b8f015ea3d5107851abdd5fd20b7d306ab2686ee9 |
| Baru | tests/Unit/LiveStreamingAdminTest.php | 8601d6bf122b6e652b0f9b19aecf88132b222c36ea5f26af8cbde293c0fe1ed1 |
| Baru | tests/Unit/LiveStreamingFoundationTest.php | 9b73d36bdf4a15e8573aaff757441a7e7a3d49fc2f907a8d0d6a82561fde9b1a |
| Baru | tests/Unit/MonthlyActivityFoundationTest.php | 0fb982860c0b95ca2edab1b0bb7376bf77ed0d9c2f9bd450d594ad415c462d53 |
| M | tests/Unit/RoleAuthorizationTest.php | 401dad55977f11a184e55d974044fb622452691bb4387afcedb0c35b85be2e51 |
| Baru | tests/Unit/TVClubSocialFoundationTest.php | 130462e2f776d3d0fb638c370c8a1426e87a4b139c749dab4a6db1fedf777d56 |
| Baru | tests/Unit/TVClubSocialRecapTest.php | e3614a017ed02848f11f143ddee235593139bc188a24331ba5aed4d5e115155d |
