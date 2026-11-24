# WPiTV — PHASE 6C.4L.2A
# EQUIPMENT SCHEMA PREFLIGHT

Branch: rebuild/wpitv-clean
Mode: READ-ONLY + DOCUMENTATION
DDL / MIGRATION / PROVISIONING: NOT AUTHORIZED

## TUJUAN

Menyiapkan bukti kesiapan implementasi schema Equipment v3.2 tanpa mengubah aplikasi atau database.

## TUGAS

1. Verifikasi branch dan working tree.
2. Baca Technical Approval v3.2, Schema Readiness, Constraint Matrix dan dokumen terkait seperlunya.
3. Periksa EQUIPMENT_TECHNICAL_REVIEW_R1.md.
   - Jika bukan laporan R1, jangan mengarang atau menimpa bukti.
   - Cari salinan otoritatif hanya di lokasi dokumentasi/worktree yang relevan.
   - Jika tidak ditemukan, catat BLOCKED evidence.
4. Identifikasi schema baseline resmi dari migration atau artifact existing.
5. Verifikasi secara statis tipe users.id dan kecocokan FK.
   - Jika membutuhkan koneksi database, tandai sebagai pending.
6. Susun proposal manifest 35 tabel.
7. Audit konfigurasi guard dan testing secara read-only.
8. Identifikasi risiko collision legacy Equipment.
9. Susun checklist provisioning clone, backup, restore, fingerprint dan negative tests.
10. Buat laporan EQUIPMENT_SCHEMA_PREFLIGHT_v3.2.md.

## BATASAN

- Jangan menjalankan SQL atau koneksi database.
- Jangan membuat migration.
- Jangan provisioning database atau akun.
- Jangan mengubah .env, guard, source atau konfigurasi.
- Jangan mengubah wpitv/wpitv_testing.
- Jangan menyalin data privat.
- Jangan commit/push/deploy.
- Jangan reset atau cutover.
- Jangan mengubah Live Streaming dan Monthly Report.

## OUTPUT

Maksimal 150 kata:
- Bukti yang berhasil diverifikasi.
- Bukti yang masih BLOCKED.
- Kondisi laporan R1.
- Risiko schema/guard.
- Kesiapan provisioning clone.
- Verdict: READY FOR CLONE APPROVAL atau BLOCKED.

Berhenti setelah preflight.
