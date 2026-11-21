# WPiTV — Checklist UAT final

Status awal seluruh skenario adalah **belum dijalankan**. Gunakan staging/QA yang ditetapkan, identitas uji untuk empat role, data sintetis, dan konfigurasi integrasi yang disetujui. Catat commit/build, lingkungan, browser/viewport, role, ID fixture, hasil aktual, bukti tersanitasi, dan tiket. Gunakan status Lulus, Gagal, Terblokir atau Tidak Berlaku dengan alasan.

## 1. Persiapan dan akses

- [ ] Konfirmasi database dan storage staging terisolasi, 35 tabel sesuai manifest rilis, aset tersedia, serta akun uji aktif. Siapkan satu fixture Peliputan, Agenda Live, draft per divisi, revisi sosial terbit dan unit inventaris.
- [ ] Login dan logout dengan keempat role melalui form normal. Verifikasi password salah, sesi kedaluwarsa, dan penolakan setelah akun dinonaktifkan. Cocokkan sidebar, widget, dan aksi cepat dengan hak role.
- [ ] Untuk aksi terlarang di bawah, uji tautan langsung dengan ID fixture valid dan aksi pada form yang terbuka sebelum perubahan akses. Harapkan penolakan tanpa data atau mutasi. Uji pengiriman formulir tulis tanpa token CSRF melalui prosedur QA yang disetujui.

## 2. Role dan alur lintas modul

| Role | Skenario UAT dan hasil yang diharapkan |
| --- | --- |
| Super Admin | Kelola Users/Roles dan periksa proteksi akun sendiri. Lihat statistik agregat. Review kedua divisi, minta revisi, setujui snapshot akhir dan unduh lampiran historis. Kelola akun sosial resmi serta unduh kedua CSV. Aksi ubah konten, catat metrik dan terbitkan versi sosial ditolak. Uji koreksi Equipment yang khusus Super Admin. |
| Admin Production | Kelola Program/Tayangan, Peliputan dan Agenda. Proses fixture Peliputan hingga terhubung ke Agenda. Kelola Live dari jadwal hingga akhir dan arsip. Checkout dua unit, kembalikan sebagian, periksa ketersediaan, histori dan laporan. Buat dan submit laporan Production, tindak lanjuti permintaan revisi. Laporan News, laporan sosial, Users/Roles dan Berita ditolak. |
| Admin News | Buat/edit/publikasi Berita dan buat/edit Tayangan. Penghapusan Tayangan ditolak. Tambah kegiatan kandidat dan manual, lampirkan bukti, lalu submit laporan News. Periksa penolakan laporan Production, Equipment, admin Live, laporan sosial dan Users/Roles. |
| TVClub | Kelola Program serta generasi, anggota, kelompok, dan jabatan TVClub. Catat akun/unggahan, metrik dan pengikut. Koreksi metrik dengan alasan, terbitkan revisi bulan WIB dan bandingkan snapshot lama. Unduh kedua CSV. Admin Tayangan, Berita, Equipment, admin Live, laporan bulanan dan Users/Roles ditolak. |

- [ ] Equipment mempertahankan histori saat retur parsial, perpanjangan, laporan kehilangan dan pemulihan. Periksa filter, KPI, versi stale, serta CSV tanpa data pribadi peminjam.
- [ ] Laporan bulanan mendukung laporan nihil, kandidat dengan sumber berubah, revisi dan approval. Periksa versi lama, pemisahan divisi, CSV approved dan penolakan ekspor dokumen yang belum disetujui.
- [ ] Sosial memperlihatkan NULL berbeda dari 0, cakupan per platform, followers per akun dan batas bulan WIB. Setelah koreksi atau pengarsipan, CSV versi lama tetap cocok. Periksa judul sintetis berawalan karakter formula dan nilai integer besar pada aplikasi spreadsheet yang dipakai operator.

## 3. Publik, mobile dan integrasi

- [ ] Kunjungi homepage, Tentang/struktur, pencarian, Berita, Program, Tayangan, Agenda, Live, Peliputan dan portal TVClub. Periksa daftar/detail, navigasi aktif, URL langsung, keadaan kosong, dan tampilan publik yang menyaring data draft/internal.
- [ ] Uji pada desktop dan mobile. Periksa menu, fokus keyboard, tabel/form yang dapat dibaca, tema terang/gelap admin setelah refresh, pesan validasi dan konfirmasi SweetAlert.
- [ ] Buat pengajuan Peliputan sintetis. Uji receipt pada sesi yang sama, lookup dengan kode dan telepon yang benar, penolakan data salah, dan perlindungan nomor telepon pada tampilan publik.
- [ ] Buka tautan WhatsApp dari sisi pemohon dan admin menggunakan tujuan yang disetujui. Periksa nomor, konteks pesan dan fallback jika tujuan belum dikonfigurasi. Gunakan persetujuan operasional sebelum mengirim pesan uji.
- [ ] Putar Tayangan dan siaran WPiTV yang diizinkan pada desktop/mobile. Periksa autoplay nonaktif pada Live, fallback video unavailable, beberapa siaran, status operator, jadwal lintas tengah malam WIB, arsip dan penyaringan visibility.

## 4. File, unduhan dan cetak

- [ ] Unggah gambar valid melalui file picker asli pada modul media. Periksa konversi WebP dan penolakan tipe/ukuran yang salah.
- [ ] Unggah JPEG, PNG dan PDF ke draft laporan bulanan. Verifikasi feedback, unduhan, pelepasan, penolakan pada submitted/approved dan unduhan versi historis sesudah revisi. Gunakan ID lampiran dari laporan lain untuk memverifikasi penolakan. Browser E2E lampiran masih PARTIAL dari fase sebelumnya.
- [ ] Unduh CSV Equipment, bulanan dan dua CSV sosial. Periksa nama, header, isi, privasi dan kesesuaian filter atau snapshot. Pakai bukti Chrome 5F.1 untuk unduhan sosial dan ulangi pada calon rilis bila artefak atau environment berubah.
- [ ] Buka halaman cetak approved, pilih A4, simpan melalui dialog Save as PDF dan buka hasilnya. Periksa pagination, pemotongan tabel, periode, status dan lampiran yang diizinkan. Dialog belum diuji pada fase sebelumnya.

## 5. Penutupan

- [ ] Hapus fixture sesuai ledger ID dan verifikasi lingkungan QA kembali ke baseline. Simpan bukti yang sudah disanitasi.
- [ ] Urutan eksekusi adalah preflight, login/role, alur lintas role, publik/mobile, lalu file dan integrasi eksternal. Gunakan kembali fixture dan bukti yang sama untuk tiap skenario terkait.
- [ ] Catat kegagalan per prioritas. Untuk perbaikan, ulangi skenario terdampak dan satu putaran regresi calon rilis. Pemilik proses menyetujui hasil UAT dan daftar risiko yang masih terbuka sebelum go/no-go.

[Audit proyek dan urutan fase](FINAL_PROJECT_AUDIT.md) · [Baseline calon rilis dan bukti recovery](RELEASE_BASELINE_AND_BACKUP_VERIFICATION.md)

## Bukti sebelum UAT

Gunakan hasil otomatis Phase 6C sebagai bukti awal. Prioritaskan upload lampiran lewat browser, Save as PDF, siaran WPiTV, tujuan WhatsApp staging dan login operator asli. Cocokkan artefak staging dengan manifest Phase 6B dan perubahan yang dicatat pada Phase 6C. Verifikasi pemulihan berkas publik dan privat sebelum persetujuan produksi. [Verifikasi Phase 6C](FINAL_REGRESSION_AND_SECURITY_VERIFICATION.md)

## Live Streaming dua mode

- [ ] Setelah staging memakai skema 6C.2, buka Tambah Live sebagai Production dan Super Admin. Pastikan Manual terpilih. Isi jadwal lintas tengah malam WIB, ganti mode dua kali dan periksa bahwa judul, jam, pilihan Agenda dan input YouTube tetap ada.
- [ ] Buat Live Manual dengan dan tanpa Program. Periksa judul, jadwal dan sumber di detail, filter daftar, dashboard, homepage, Program dan halaman Live publik. Uji penolakan URL langsung saat Internal.
- [ ] Buat dari Agenda Live yang memenuhi syarat dan periksa ringkasannya. Ubah jadwal lewat Agenda dan periksa hasil di Live. Ulangi dengan Agenda yang sudah dipakai dan pastikan aksi ditolak.
- [ ] Jalankan edit, pembatalan, jadwal ulang, tandai Live, selesai, dan kaitkan arsip yang cocok pada kedua mode. Periksa audit, konflik versi, policy empat role, preview YouTube, desktop terang dan mobile gelap. [Verifikasi Phase 6C.2](LIVE_DUAL_CREATION_VERIFICATION.md)
- [ ] Pada staging, periksa 35 tabel dan 20 catatan migration sebelum mencoba mode Manual. Gunakan login operator asli. Ulangi pesan validasi URL YouTube yang ditolak dan pastikan input form tetap ada, lalu konfirmasi preview muncul setelah fokus berpindah. Bukti browser loopback 6C.3 menggunakan akun fixture. [Verifikasi Phase 6C.3](LIVE_DUAL_CREATION_VERIFICATION.md)


## Equipment 6C.4B–D

- [ ] Super Admin/Production membuat unit berfoto, mengganti foto, membuka versi lama dan memeriksa ketersediaan setiap kategori.
- [ ] Petugas memilih beberapa unit melalui empat langkah, mengembalikan sebagian, mengecek kondisi rusak dan menemukan bukti pada timeline. Ulangi untuk retur terakhir.
- [ ] Bandingkan kartu dan badge di desktop terang serta ponsel gelap. Periksa foto lama kosong, file salah, ukuran berlebih, konflik versi dan umpan balik konfirmasi.
- [ ] Verifikasi News/TVClub ditolak pada halaman dan tautan foto langsung. Cocokkan media privat dengan database dalam drill recovery sebelum rilis.


## Phase 6C.4F · Pengelolaan kuantitas

- [ ] Tambah barang dengan jumlah 10 dan satu cover. Periksa sepuluh kode berbeda serta foto khusus salah satu unit.
- [ ] Pinjam tiga melalui pemilih jumlah, cocokkan kode fisik pada review, lalu coba melebihi stok.
- [ ] Tambahkan dan nonaktifkan stok dengan alasan. Pastikan unit dipinjam/rusak/hilang tetap terlindungi dan jumlah lima kelompok sama dengan total.
- [ ] Kembalikan sebagian dengan foto per unit, lalu kembalikan sisanya rusak. Cocokkan stok tersedia, riwayat penyesuaian dan receipt.

### Phase 6C.4G | UAT petugas inventaris

- [ ] Sebagai Production dan Super Admin, buka **Unit Inventaris → Tambah Barang**, buat beberapa unit semodel, periksa preview foto, kode unik, dan satu kartu barang.
- [ ] Buka **Detail → Kelola Stok**, lalu **Edit**. Cocokkan daftar unit dengan label fisik. Periksa nama yang ditandai serupa sebelum menetapkan hubungan legacy.
- [ ] Bandingkan filter, total unit, stok layak pinjam, unit rusak, arsip, serta transaksi aktif. Uji pengurangan dengan alasan dan penolakan jumlah berlebih.
- [ ] Pinjam berdasarkan jumlah, cocokkan kode saat menyerahkan barang, lalu kembalikan sebagian dengan foto per unit. Periksa bukti lama, unit rusak, dan sisa pinjaman.
- [ ] Periksa tampilan ponsel, kedua tema, dan penolakan akses untuk Admin News serta TVClub. Catat hasil UAT pengguna secara terpisah dari QA otomatis.

### Phase 6C.4H | Peminjaman beberapa jenis alat

- [ ] Sebagai Production atau Super Admin, cari dua jenis alat, tambah keduanya, gunakan tombol jumlah pada item pertama dan pilih kode unit manual pada item kedua.
- [ ] Bandingkan jumlah, periode WIB dan semua kode pada review dengan alat yang diserahkan. Uji batal dan konfirmasi.
- [ ] Buka transaksi dari perangkat kedua dan pinjam stok terakhir. Pastikan petugas pertama mendapat konflik dan dapat memperbaiki baris terkait.
- [ ] Kembalikan satu unit baik dan satu unit rusak dengan bukti masing-masing. Cocokkan hitungan sudah/belum kembali, histori serah terima dan ketersediaan di inventaris.

### Phase 6C.4J | Equipment schema expansion

After the testing gate, confirm that Unit 1 preserves its physical identity, partial returns show the correct evidence, and prior inventory codes still resolve. [Verification](EQUIPMENT_DATABASE_RESTRUCTURING_VERIFICATION.md)

### Phase 6C.4K | UI Equipment dan POS

- [ ] Dari kategori, periksa jumlah jenis dan unit lalu buka daftar barang terfilter.
- [ ] Edit merek barang dan ganti cover. Buka riwayat foto dan pastikan kode serta kondisi unit mempertahankan nilainya.
- [ ] Tambah stok dan cocokkan unit baru berkode angka dengan benda fisik. Nonaktifkan master lalu periksa ketersediaannya pada POS dan dashboard.
- [ ] Pinjam beberapa jenis alat melalui mode otomatis dan manual. Kembalikan sebagian dengan foto per unit dan cocokkan bukti dengan receipt.
- [ ] Uji desktop dan ponsel pada kedua tema. Cocokkan hasil UAT perangkat asli dengan [hasil QA otomatis](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md).
