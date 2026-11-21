# Equipment Database Reset Plan — NOT EXECUTED

Reset source tidak memberi izin reset data. Tidak ada DDL/DML pada database sumber dalam pekerjaan release.

1. Audit ulang baseline aktual, semua FK masuk/keluar, jumlah baris tiap tabel, snapshot transaksi, ledger v2 dan seluruh hash media. Daftar kandidat domain adalah inventory_categories, inventory_items, inventory_units, inventory_unit_members, inventory_item_photos, inventory_unit_photos, equipment_loans, equipment_loan_items, equipment_return_receipts, equipment_return_items, equipment_return_photos, equipment_return_photo_items, equipment_audit_events.
2. Buat backup terbaru seluruh DB dan media privat; manifest, hash dan restore drill pada instance terisolasi. Pisahkan users/roles dan seluruh tabel modul lain yang wajib dipertahankan.
3. Tetapkan apakah histori dimigrasi atau diarsipkan immutable; susun mapping ID/code/snapshot/audit/evidence. Jangan menganggap data dummy, jangan memakai ledger testing untuk kepemilikan data utama.
4. Uji rencana clone, FK closure, media reference accounting, rollback dan kompatibilitas Live/Monthly. Ledger v2 asli tidak diubah. Jangan jalankan cleanup demo lama pada hasil konversi.
5. Jika penghapusan data dipilih, minta persetujuan TERPISAH dengan daftar tabel/baris/FK/media aktual, dampak histori dan rencana recovery yang konkret. Dokumen ini tidak memberikan izin tersebut.
6. Jalankan hanya setelah approval fase database tersendiri; tanpa DROP/TRUNCATE/reset/migrate:fresh pada DB aktif dalam release ini. Pertahankan backup dan bukti verifikasi.
