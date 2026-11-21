# Phase 6C.4A · Stok, foto unit dan bukti pengembalian

Dokumen keputusan teknis untuk implementasi berikutnya. Diagram pengguna menggambarkan alur sasaran. Saat menerima alat rusak, petugas tetap menyelesaikan pencatatan retur dan sistem tetap menghitung sisa pinjaman.

## Temuan repository

- Lima migration Equipment tanggal 2026-11-08 membentuk kategori, unit, header pinjaman, item dan audit. Satu inventory_code unik mewakili satu benda. Kondisi dibatasi CHECK pada good, minor_damage dan major_damage. Indeks unit meliputi (category_id,is_active,condition).
- EquipmentLoanCheckout mengunci dan memeriksa pilihan sebelum serah terima. UNIQUE pada kolom turunan active_unit_id menjamin hanya satu item terbuka per unit. EquipmentLoanLoss mempertahankan item terbuka dan menonaktifkan unit. Retur alat temuan tetap memerlukan inspeksi sebelum Super Admin mengaktifkannya.
- EquipmentLoanReturn menerima hingga 50 item, mengunci header → unit menurut ID → detail, lalu mencatat kondisi, penerima, waktu, version, dan satu EquipmentAuditEvent bertipe returned. after_data.items dan remaining_items mengidentifikasi isi dan hasil batch. operation_token unik, actor key dan hash return-v1 melindungi retry. Receipt ini cukup sebagai induk bukti.
- InventoryUnit memiliki relasi category, loanItems dan auditEvents. Kelima tabel belum memiliki relasi berkas. Contoh akses privat ada di MonthlyActivityAttachmentController dan disk lokal berakar di storage/app/private. Attachment laporan memiliki pemilik dan siklus hidup tersendiri.
- routes/web.php, policy InventoryUnit/EquipmentLoan, halaman Inventory/Units, EquipmentLoans/Form, ReturnForm, Show dan Blade pasangannya merupakan titik integrasi. EquipmentReports memisahkan inventaris saat ini dari transaksi berdasarkan periode dan membatasi ekspor 10.000 baris. AdminDashboardMetrics sudah memiliki widget Equipment. Pertahankan arti KPI lama dan filter transaksi historis.
- EquipmentIntegrationTest, EquipmentConcurrencyTest, EquipmentSafetyTest dan EquipmentBrowserTest menjadi dasar regresi. FINAL_PROJECT_AUDIT mencatat delapan race terdahulu. FINAL_REGRESSION_AND_SECURITY_VERIFICATION mencatat catatan terbuka mengenai ekspor GET yang menulis audit. RELEASE_BASELINE_AND_BACKUP_VERIFICATION mencatat restore SQL 35 tabel, tetapi recovery media masih terbuka. Itu bukti historis. Jumlah tabel/migration aktif dan penerapan Live Dual Creation belum diperiksa pada fase ini.

## Stok otomatis

Klasifikasikan satu baris per unit. Gabungkan inventory_units u dengan LEFT JOIN equipment_loan_items i ON i.active_unit_id = u.id. Indeks unik menjamin maksimal satu pasangan. CASE berurutan berikut memilih kelompok pertama yang cocok.

| Prioritas | Syarat | Kelompok |
|---|---|---|
| 1 | Item terbuka dengan lost_at terisi | Hilang |
| 2 | Item terbuka lain | Dipinjam |
| 3 | Kondisi minor_damage atau major_damage | Rusak |
| 4 | is_active salah | Nonaktif |
| 5 | Aktif, good, tanpa item terbuka | Tersedia |

Total = jumlah lima kelompok. Unit rusak sekaligus nonaktif masuk Rusak. Unit temuan baik yang masih nonaktif masuk Nonaktif. Label inspeksi bersifat tambahan. Filter kondisi dan aktif tetap membaca atribut asli. Kelompokkan subquery klasifikasi menurut category_id dan kelompok, hitung COUNT(*), isi kelompok kosong dengan nol. Terapkan filter kategori/pencarian sebelum agregasi dan pagination sesudah klasifikasi pada daftar. Ringkasan stok saat ini terpisah dari jumlah retur selama periode laporan. Checkout selalu memeriksa ulang unit saat menyimpan.

## Penyimpanan dan relasi yang diusulkan

Tambahkan tiga tabel InnoDB. PK foto dan FK Equipment memakai BIGINT UNSIGNED. Semua FK baru RESTRICT saat hapus/update agar histori terlindungi. Timestamp UTC DATETIME(6), tampilan WIB. Simpan byte di disk privat. Metadata kedua tabel foto meliputi id, storage_key acak UNIQUE, mime allowlist, byte_size positif, sha256 CHAR(64) ASCII dan created_at. Gunakan batas ukuran yang sama pada service dan CHECK.

| Tabel baru | Relasi dan aturan |
|---|---|
| inventory_unit_photos | unit_id → inventory_units, audit_event_id → equipment_audit_events UNIQUE, revision positif, UNIQUE(unit_id,revision). Foto utama adalah revisi terbesar. Lock unit, periksa version, simpan revisi dan event created/updated bersama. Event memasok identitas petugas. |
| equipment_return_photos | receipt_id → equipment_audit_events, indeks (receipt_id,id). Verifikasi event returned dan loan_id dalam service. Setiap baris mewakili satu objek gambar immutable. |
| equipment_return_photo_items | photo_id → equipment_return_photos, loan_item_id → equipment_loan_items. PK gabungan dan indeks balik (loan_item_id,photo_id). Service memastikan item milik pinjaman dan tercantum pada receipt batch itu. |

Foto utama wajib pada pembuatan unit baru setelah aktivasi. Unit lama tanpa foto tampil dengan placeholder dan dapat diberi revisi pertama. Penggantian menambah versi, menyimpan revisi lama tanpa menampilkan galeri pada daftar. Setiap batch retur baru wajib memiliki bukti, setiap item harus terpetakan. Satu foto kelompok boleh mencakup beberapa unit jika kode dan kondisinya terlihat. Untuk kondisi rusak/berubah wajib foto khusus item dan catatan. Aturan catatan keterlambatan dan pemulihan tetap berlaku. Receipt lama tanpa foto diberi label “Bukti belum diwajibkan saat retur”. Tidak ada backfill buatan. Batasi update/delete metadata, termasuk quiet dan jalur query langsung di aplikasi. Rollback rilis dengan menonaktifkan fitur dan mempertahankan tabel, objek, serta histori. Jangan membuka penulisan retur lama tanpa bukti setelah aturan baru berlaku.

## Upload, transaksi dan kegagalan

Usulan batas JPEG/PNG/WebP 5 MiB per file, maksimal 50 foto per batch untuk mendukung 50 unit rusak. Upload bertahap satu file per request. Cek ekstensi, MIME isi, signature, decode gambar serta batas piksel (usulan 20 MP). Buang metadata lokasi, bentuk turunan thumbnail privat dan hitung hash byte final. Gunakan ID staging acak yang terikat actor, operasi, masa berlaku dan sesi. Abaikan nama/path dari klien.

Sebelum lock, validasi staging dan siapkan objek privat dengan nama unik untuk percobaan tersebut. Verifikasi byte dan hash sebelum commit. Pada transaksi retur yang sama, cek policy, token, expected version, lock existing, kelengkapan bukti, kepemilikan item, lalu simpan retur, receipt, metadata dan pemetaan. Gunakan return-v2 dengan daftar terurut hash konten dan ID item untuk idempotensi, independen dari nama file atau urutan upload. Replay identik mengembalikan receipt awal. Payload berbeda ditolak. Retry v1 hanya boleh membaca receipt lama yang persis cocok. Retur parsial selanjutnya membuat token dan bukti baru.

Gagal tulis file berarti transaksi belum boleh commit. Gagal DB berarti perubahan unit/item/receipt dibatalkan. Hapus hanya objek percobaan sendiri yang terbukti tidak dirujuk. Jika hasil commit ambigu, baca ulang receipt di koneksi penulis sebelum cleanup. Pembersih staging/orphan memakai masa tenggang, memeriksa referensi dan mengecualikan operasi berjalan. Jangan hapus objek historis ketika preview gagal. Tampilkan placeholder dan catat ID internal untuk rekonsiliasi. Backup SQL dan direktori privat sebagai satu set dengan manifest hash serta uji restore terisolasi.

## RBAC dan akses

Super Admin dan Admin Production mengakses daftar, detail, upload, preview dan unduh melalui policy target. Aktivasi pascakehilangan tetap khusus Super Admin. Admin News dan TVClub ditolak sejak route sebelum query media. Otorisasi ulang saat render, upload sementara, submit, dan setiap pembacaan file. Resolve ID melalui unit atau pinjaman → receipt → foto. Tolak substitusi ID antartarget, staging milik pengguna lain dan path input. Preview raster tervalidasi melalui controller privat, Cache-Control private/no-store, nosniff, MIME eksplisit. Download menggunakan attachment dan nama aman. Mutasi terproteksi CSRF, kuota staging serta throttle per actor. Audit menyimpan ID, hash dan asosiasi, tanpa URL privat, byte gambar atau kontak peminjam. CSV tidak memuat lokasi file.

## Rancangan layar Mazer

- Dashboard inventaris memuat enam kartu KPI berikon dengan angka dan aksen berbeda, pencarian, filter kategori/kondisi/status, aksi Tambah Unit dan grid berhalaman. Toggle daftar opsional menggunakan query yang sama. Muat foto utama untuk halaman aktif dalam satu batch. Gunakan thumbnail rasio tetap, object-fit cover, placeholder unit historis dan fallback gagal muat.
- Card memuat nama, kode, kategori, lokasi, kondisi, badge dan aksi sesuai policy. Detail desktop dua kolom untuk foto/identitas serta kondisi/lokasi/status, dengan timeline di bawah. Tablet mengurangi kolom dan mobile memakai satu kolom.
- Checkout empat langkah: identitas (nama/NIM/prodi/WhatsApp), keperluan dan jadwal, pilihan unit berthumbnail dengan pencarian/filter dan hitungan terpilih, lalu review jadwal/peminjam/unit dan konfirmasi. Nonaktifkan pilihan yang tidak tersedia. Jika terjadi konflik saat submit, pertahankan isian dan tunjukkan unit yang harus dipilih ulang.
- Retur memakai card per unit dengan kode, foto utama, kondisi awal, kondisi kembali, catatan, picker dan preview bukti. Tampilkan progres unit kembali, tag unit pada foto bersama, error dekat field, ringkasan batch, konfirmasi SweetAlert2 dan hasil simpan. Drag-and-drop opsional, picker tetap tersedia. Timeline detail menampilkan receipt parsial, waktu WIB, penerima serta foto yang tepat untuk tiap item.
- Pakai komponen Blade badge status/kondisi, equipment card, KPI, empty state, preview dan timeline. Token Bootstrap/Mazer dipusatkan. Tersedia/baik/selesai hijau, dipinjam/aktif biru, rusak/terlambat merah, hilang ungu, nonaktif abu-abu, inspeksi/retur sebagian oranye. Terlambat berasal dari jatuh tempo dan sisa item. Hilang menunjukkan operasi kehilangan, inspeksi menunjukkan kebutuhan pemeriksaan, keduanya tidak menjadi pilihan condition_in. Tidak ada aksi pembatalan pinjaman pada lifecycle ini.
- Pertahankan identitas WPiTV dengan spacing, rounded card, bayangan halus dan ikon existing. Badge memakai teks serta ikon. Sediakan skeleton, empty state beraksi, pesan gagal, fokus keyboard, label dan aria-describedby, alt gambar, target sentuh nyaman, kontras dua tema dan reduced motion.

## Tahapan dan kriteria penerimaan

| Fase | Pekerjaan dan bukti sebelum lanjut |
|---|---|
| 6C.4B | Preflight aktual kedua skema, riwayat migration, backup dan manifest setelah perubahan Live. Review migration additive terpisah. Uji constraint di testing dan klasifikasi seluruh kombinasi hilang/rusak/nonaktif, jumlah kategori dan total, kategori kosong, query plan serta jumlah query per halaman. Bangun KPI dan badge. |
| 6C.4C | Foto unit baru/lama, penggantian bersamaan, histori, preview privat, checkout bertahap dan kartu. Uji MIME palsu, ukuran, piksel, storage gagal, IDOR serta keempat role. |
| 6C.4D | Bukti per batch dan pemetaan per item. Uji retur sebagian/penuh, rusak, pemulihan, foto kelompok, bukti kurang, dua petugas, token identik/berbeda, rollback audit, orphan dan baca receipt lama. Verifikasi retur terakhir menutup pinjaman walau unit kembali rusak. |
| 6C.4E | Regresi Equipment, dashboard, laporan, query tanpa N+1 dan otorisasi Livewire. QA desktop terang/mobile gelap, keyboard, badge, loading/empty/error, picker/preview, foto hilang, multiunit dan timeline. Dokumentasikan screenshot, hasil tes dan drill pemulihan media sebelum UAT. |

Konfirmasi operasional yang tersisa meliputi batas ukuran/piksel/jumlah foto, keterbacaan kode pada foto kelompok, masa retensi bukti dan kapasitas penyimpanan. Foto dapat memuat wajah atau dokumen sekitar alat. Beri petunjuk pengambilan yang berfokus pada alat dan kode inventaris. Audit ini berupa pembacaan repository; seluruh gate implementasi, browser dan database di atas masih terjadwal.


## Equipment 6C.4B–D

Implementasi 6C.4B–D menggunakan satu foto khusus per unit yang dipilih, maksimal 10 unit/foto per receipt dan 5 MiB per foto. Batch lebih besar dapat dibagi menjadi beberapa retur parsial. Nomor revisi foto mengikuti version unit saat pencatatan. Aktor diperoleh dari event yang terhubung. Bukti operasional tercatat di [laporan verifikasi](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md).


## Phase 6C.4F · Pengelolaan kuantitas

Satu unit induk menyimpan identitas barang dan cover. Setiap unit lama memulai kelompok tersendiri; kesamaan nama/model tidak cukup untuk menggabungkan histori. Tabel inventory_unit_members hanya memetakan unit tambahan ke induk yang lebih dahulu dibuat. PK unit_id, dua FK RESTRICT, indeks (parent_id,unit_id) dan CHECK parent_id < unit_id melindungi relasi. Foto khusus unit mengalahkan cover induk. Kode pertama adalah kode input; unit tambahan mendapat kode unik dan nomor seri kosong. Input awal massal untuk unit baik/aktif dan penyesuaian masing-masing dibatasi 50. Pengurangan mengarsipkan unit tersedia menurut ID naik; total fisik tetap menghitung arsip. Histori penyesuaian mencatat alasan, aktor, hitungan sebelum/sesudah serta ID terdampak. Wizard menampilkan alokasi sebelum checkout; konflik saat simpan meminta petugas memilih kembali. Pilihan jumlah retur menandai item terbuka menurut ID, dapat diperiksa atau diubah sebelum menyerahkan bukti tiap unit.

### Phase 6C.4G | Barang dan unit fisik

Kunci barang adalah ID unit induk. Tabel `inventory_unit_members` menghubungkan unit tambahan ke induknya. Induk ikut dihitung sebagai satu unit fisik. Unit lama tanpa hubungan tetap berdiri sendiri. Kartu dengan nama induk serupa menampilkan pengingat untuk memeriksa model dan kode.

Saat membuat barang, petugas memilih kategori, jumlah awal (1–50), kondisi, status aktif dan foto utama. Server menormalisasi merek/model menjadi huruf kapital dan angka, kemudian menambahkan ID auto-increment yang dialokasikan MySQL. Jika merek/model kosong, server memakai nama barang, dengan fallback INV/UNIT. Constraint unik dan maksimal tiga percobaan melindungi penerbitan kode. Mengedit identitas deskriptif mempertahankan kode. Unit tambahan memakai generator turunan ULID yang sudah tersedia.

Kartu menghitung seluruh unit tercatat, termasuk arsip. Klasifikasi per unit mengikuti urutan hilang, dipinjam, rusak, nonaktif, tersedia. Unit baik dapat dipilih jika aktif, induknya aktif, dan tidak ada pinjaman terbuka. Penyesuaian minus mengarsipkan unit yang memenuhi syarat, memprioritaskan anak. Penyesuaian, audit, alokasi checkout, serta bukti pengembalian tetap merujuk ID fisik. Lokasi historis tetap ada di database. Foto unit mengutamakan foto sendiri lalu menggunakan foto induk jika tersedia.

### Phase 6C.4J | Equipment schema expansion

Equipment expansion keeps the legacy physical unit and receipt keys during backfill. The normalized item and receipt relationships are recorded in the 6C.4J report. [Verification](EQUIPMENT_DATABASE_RESTRUCTURING_VERIFICATION.md)
