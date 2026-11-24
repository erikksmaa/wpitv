# Equipment Permission Matrix v3.2

## Status berlaku — Phase 6C.4L.1E, 23 November 2026

**APPROVED sebagai baseline desain** berdasarkan EQUIPMENT_TECHNICAL_APPROVAL_v3.2.md: paket teknis v3.2 setelah koreksi R1, lost→retired dan reason policy Technical7.1. Label/kalimat proposal, rekomendasi atau menunggu approval dari Phase1D/R1 yang masih dikutip untuk detail desain dalam scope tersebut kini dibaca APPROVED; catatan historis bukan keputusan pending baru. Ini tidak mengesahkan bukti lingkungan, hasil tes, provisioning, grant runtime atau cutover. Bukti yang belum tersedia tetap **BLOCKED**; Phase6C.4L.2 belum diotorisasi. Strategi histori, loan legacy aktif, identitas target, manifest operasional, backup/restore dan cutover tetap memerlukan keputusan/bukti tersendiri. Record1E menjadi otoritas status terbaru, record1C tetap historis.

Phase1D — DOCUMENTATION ONLY. APPROVED: permission-based direct actions, reasons/audit/transaction, no approval workflow/queue/table. Nama permission, default mapping dan desain integrasi di dokumen ini **APPROVED DESIGN Phase1E**, belum memberi hak runtime; bukti implementasi tetap BLOCKED. Baca kontrak teknis bab7 dan record1C. Tidak membuat role/permission/seeder/policy/config runtime.

## Matriks aksi

SA = Super Admin; AP = Admin Production; AN = Admin News; TC = TVClub. Y di bawah adalah default mapping desain yang disetujui untuk grant eksplisit kelak, bukan hardcoded role bypass. N berarti jangan di-grant oleh mapping default; semua aktor tetap harus aktif, terautentikasi dan memenuhi permission serta scope/lifecycle.

| operation/read | permission | SA | AP | AN | TC | Scope / alasan |
| --- | --- | --- | --- | --- | --- | --- |
| category.create | equipment.category.create | Y | Y | N | N | Audit reason otomatis + metadata; tanpa alasan manual tambahan; scope/lifecycle, transaksi+audit+key tetap |
| category.update | equipment.category.update | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| model.create | equipment.model.create | Y | Y | N | N | Audit reason otomatis + metadata/stok awal; tanpa alasan manual tambahan; scope/lifecycle, transaksi+audit+key tetap |
| model.update | equipment.model.update | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| stock.add | equipment.stock.add | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| unit.condition | equipment.unit.condition | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| unit.maintenance_start | equipment.unit.maintenance | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| unit.maintenance_finish | equipment.unit.maintenance | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| unit.lost | equipment.unit.loss | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| unit.found | equipment.unit.recover | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| unit.retire | equipment.unit.retire | Y | N | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| loan.checkout | equipment.loan.checkout | Y | Y | N | N | Purpose wajib; audit reason otomatis; tanpa alasan manual kedua; scope/lifecycle, transaksi+audit+key tetap |
| loan.extend | equipment.loan.extend | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| loan.void | equipment.loan.void | Y | N | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| loan.loss | equipment.loan.loss | Y | Y | N | N | Alasan manual eksplisit wajib; resource policy dan validasi lifecycle; transaksi+audit+key |
| return.create | equipment.loan.return | Y | Y | N | N | Audit reason otomatis retur good tepat waktu; rusak/late wajib notes per loan-unit + ringkasan audit; scope/lifecycle, transaksi+audit+key tetap |
| model.photo_add | equipment.model.photo.add | Y | Y | N | N | Alasan manual revisi cover; first cover boleh audit reason otomatis + metadata; scope/lifecycle, transaksi+audit+key tetap |
| unit.photo_add | equipment.unit.photo.add | Y | Y | N | N | Alasan manual inspection/damage/foto berikut; first reference boleh audit reason otomatis + provenance; scope/lifecycle, transaksi+audit+key tetap |
| category.read | equipment.category.view | Y | Y | N | N | Kategori current; tidak memutasi |
| inventory.read | equipment.inventory.view | Y | Y | N | N | Catalog/model/unit/stock current |
| loan.read | equipment.loan.view | Y | Y | N | N | Loan header/unit/receipt satu resource graph |
| history.read | equipment.audit.view | Y | Y | N | N | Histori domain scoped; tanpa raw private payload berlebih |
| photo.read | equipment.photo.view | Y | Y | N | N | Harus juga lolos parent view, scope file+receipt+unit |
| report.read | equipment.report.view | Y | Y | N | N | Historical snapshots, filter/DTO terotorisasi |
| report.csv | equipment.report.export | Y | Y | N | N | Juga report.view; default redacted |
| report.print | equipment.report.print | Y | Y | N | N | Juga report.view; HTML escaped |
| borrower.pii | equipment.borrower.view | Y | Y | N | N | Detail PII hanya kebutuhan operasional, tidak di log |
| borrower.csv_pii | equipment.borrower.export | Y | N | N | N | Juga report.export+borrower.view; inclusion eksplisit |

## Integrasi permission dan denial

PROPOSED v1: katalog permission dan mapping role→set permission terpusat yang direview, tanpa tabel Equipment baru. User.roles existing dipakai resolver untuk memperoleh SET permission, kemudian policy mengecek nama permission, akun aktif, scope dan state. Jangan `isSuperAdmin() => true`, Gate-before global atau mengganti check permission dengan manage-production. Custom per-user grant storage tidak didesain/diaktifkan v1. User disable atau mapping revoke harus terlihat pada setiap request/action dan sebelum replay, bukan hanya saat render menu. Default mapping ini disetujui sebagai desain pada Phase1E; perubahan mapping memerlukan persetujuan tersendiri; tidak otomatis memberi seluruh permission pada role baru/unknown. Baseline app/User/Role/Gate yang dibaca masih role-centric; bukti interface permission belum ada (BLOCKED untuk implementasi, bukan alasan bypass).

401 tanpa autentikasi, 403 missing permission/inactive; mismatch ID/resource graph 404 setelah parent permission check, bukan disclosure existence. Denial tidak menerbitkan business audit sukses/key completed; security log minimal tanpa borrower PII/token/hash/file path raw. Actor baru hanya dari session server, bukan actor_id request. Operasi nonowner creator tetap boleh oleh authorized operational actor; tidak mengarang tenant/borrower-user ownership. Yang harus cocok: unit milik model terpilih, loan-unit milik loan, receipt milik loan, evidence milik returned loan-unit pada receipt yang sama, staging upload milik actor/operation. Media read mensyaratkan permission file DAN resource parent, bukan sekadar mengetahui URL.

## Empat aksi sensitif dan event audit

APPROVED: retire ditolak saat on_loan, terminal/no hard-delete; found hanya lost→maintenance; loan.loss menutup outcome secara atomik tanpa membuka edit stok bebas; void hanya salah input sebelum handover, seluruh unit unresolved dan alasan+attestasi. Permission, alasan nonblank, locking/transaction dan audit selalu wajib. Retire/void default grant hanya SA adalah mapping desain APPROVED, bukan role bypass: jika mapping AP diubah melalui persetujuan, permission check yang sama tetap menentukan akses. Recovery/loss default SA+AP direkomendasikan, tidak pernah membutuhkan approver.

Event registry utama satu-ke-satu dengan operation table pada Technical Contract bab4: category.created/updated, model.created/updated/photo_added, stock.added, unit.condition_changed/maintenance_started/maintenance_finished/lost/found/retired/photo_added, loan.checked_out/extended/voided/loss_recorded, return.created. Event unit.lost per selected unit pada loan.loss adalah child audit dalam transaksi/token sama. Audit menyimpan actor_id dan nama snapshot server, subject allowlist, loan context bila relevan, before/after, reason dan UTC time. Photo events memakai model/unit subject serta ID foto di JSON, bukan FK polymorphic baru.

PROPOSED actor FK RESTRICT untuk semua reference users; nonaktifkan akun, bukan hard-delete yang sudah punya histori. Runtime account audit_log hanya SELECT/INSERT, tanpa UPDATE/DELETE; key tidak dihapus runtime. Test cleanup memakai akun clone-only terpisah dan ownership manifest/journal. Audit immutable bukan klaim bahwa akun provisioning/DBA tidak mungkin memodifikasi; akses tersebut dibatasi prosedur/monitoring/approval terpisah. Akun historis yang sudah terhapus dapat diimpor dengan nullable actor FK dan nama snapshot bila bukti import disetujui.

## Approval dan negative specifications

APPROVED DESIGN: mapping, penamaan, semantik resolver, scope PII dan pemisahan grant akun. Bukti integrasi/grants dan negative tests tetap BLOCKED sampai fase yang diotorisasi. Negative tests: SA tanpa permission tetap deny; AP tanpa retire/void deny; AN/TC tanpa grant deny; unknownrole/config missing deny; revoked mapping/inactive user replay deny; IDOR receipt/photo/staging deny; permitted actor yang belum memenuhi state/reason tetap deny. Tidak ada runtime code/test DB yang dibuat atau dijalankan pada Phase1D.
