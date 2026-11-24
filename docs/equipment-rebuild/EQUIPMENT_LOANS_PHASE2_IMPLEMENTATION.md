# Equipment Loan & Return Management — Phase 2

24 November 2026 · Branch rebuild/wpitv-clean · Local implementation, no commit/push/deployment.

## Schema verified before implementation

Read information_schema/SHOW CREATE metadata of equipment_loans, equipment_loan_units, equipment_returns, equipment_return_photos, equipment_units, equipment_operation_keys and equipment_audit_log from local wpitv inside a read-only transaction. Runtime code follows actual columns; no DDL/migration/schema change.

Important actual contracts: loan_number, borrower_contact/affiliation and created_by; no header status or original_due_date. loan_units stores immutable checkout category/model IDs, generated STORED open_unit_id UNIQUE, and requires resolution_at for returned rows. Returns have an ID rather than receipt-code column. Return photos use storage_path/mime_type/file_size/sha256 and a composite FK to the returned loan-unit. Audit uses actor_name_snapshot/event_name/occurred_at. Operation keys require actor_id as well as actor_key.

## Delivered

- /admin/equipment-loans: search by number/name/identity, borrower/progress/overdue filters, pagination, detail links and responsive Mazer navigation.
- /admin/equipment-loans/create: mahasiswa/dosen/staf snapshot identity/contact/affiliation/purpose, DATE due at end of WIB day, selection basket across available-unit pages, up to50 units/20 models, automatic unique EQ-L-ULID number.
- /admin/equipment-loans/{loanId}: borrower detail, historical checkout labels, original/current deadlines, derived progress, overdue, unit conditions/outcomes and immutable resolution details, receipts, private evidence and audit.
- Partial/full returns: selected1..10 units per batch, per-unit condition/notes and mandatory1..8 photos, <=40 batch photos, per-file5,000,000-byte input/output caps and separate100,000,000-byte input/output totals. Damaged/late return requires notes. Good free returned units are available automatically; damaged units remain ineligible until repaired through Inventory.
- Extension: forward DATE with mandatory reason, header/version lock and audit; prior resolved snapshots stay untouched.
- Void: special permission, explicit non-handover attestation, reason and all units unresolved; all-or-nothing, no deleted history.
- Loan loss: selected on_loan units atomically become lost outcomes + lost physical units; immutable due/late/time/reason facts, parent and child audit. Found/retire stays in Phase1 and never rewrites resolved loan outcomes.

## Transaction and authorization guarantees

Same Phase1 permission catalog/Gate pattern, active-user reauthorization for mutation/replay. SA/AP default ordinary loan operations; void SA-only default; News/TVClub/unknown denied. No role bypass or new borrower/permission table. Only necessary Phase1 integration edits: permission catalog, menu and rejecting loan operations through Inventory entry points.

Every mutation claims a global UUID key and completes business+audit+result in one transaction; canonical actor/operation/payload/photo checksum matching returns stable existing result. Replay precedes current-version/deadline/lifecycle checks. No committed pending token is allowed by service flow. A duplicate token is re-read after rollback; mismatched payload/actor is rejected.

Lock order: existing loan header first where relevant, categories for checkout, model IDs ascending, physical units ascending, then selected loan-unit IDs ascending. All integrate with Phase1 model/unit locks. Generated open_unit_id is never assigned by application and backs double-allocation prevention. Header expected_version serializes overlapping return/loss/extend/void. No mutation of already-resolved detail outcomes. Query Builder writes only inspected existing columns.

All event strings are supplied as UTC DATETIME(6); due_date remains a WIB DATE. Late means event's WIB date strictly exceeds due_date, including the exact midnight boundary. Progress is active/partially_resolved/closed/voided derived from all details; empty/mixed void is an integrity error, overdue is separate.

Return images reuse Phase1 decode/reencode/private-storage pipeline, staged one image at a time without retaining a full batch of decoded bytes. Server computes final metadata/checksum. Receipt/loan-unit/photo binding is checked on write and on authorized download. Failed/replayed attempt-owned random objects are removed only if no evidence table references them; uncertain ownership retains the object and logs a minimal reconciliation warning. Historical evidence is never purged.

## Historical snapshots and physical handover

Checkout append-only audit captures original deadline, asset/serial, condition and category/model IDs/names. Detail reads these snapshots rather than current renamed masters. Existing rows without such an audit display historical IDs and explicitly unavailable original-deadline evidence; no history is fabricated or backfilled. Current actor names at each event are snapshotted in audit.

There is no handover-state column in the actual schema. The approved void rule therefore relies on explicit operator attestation + dedicated permission + all-unresolved validation and audited reason; physical handover truth cannot be inferred from a DATE or an on_loan outcome. No hidden schema extension or workflow was added.

## Tests and observed result

- Phase2 unit:21 tests/36 assertions passed: progress truth table, canonical payload and end-of-WIB boundaries, explicit permissions and UTC display.
- Phase2 rollback integration:57 checks passed: checkout/replay/conflict, service double-checkout rejection AND actual cross-loan generated UNIQUE violation, snapshot master rename, partial/full return/photos, overdue/late notes, forward/stale extension, void permission/attestation/all-unit rules, loss and Inventory recovery preserving historical loss, denied cross-loan evidence, scoped private download, Livewire return/loss/list filters, photo limit and repeated-resolution denial.
- Related Inventory regression:23 unit tests/27 assertions and31 rollback smoke checks passed after permission/entry-point integration changes.
- Browser: user authenticated normally; Mazer menu, transaction list, checkout form and two-unit selection basket were observed working. No borrower transaction was persisted through browser solely for QA. Detail/return actions were exercised through Livewire integration with synthetic fixture transactions.
- Targeted syntax, route registration, Blade rendering and formatting checks; no full test suite repeated.

No separately provisioned Equipment testing clone was available. Explicit opt-in manual smoke scripts use only NEW synthetic rows under an outer transaction on the inspected local schema and a unique private photo test root. Finally rolls back all fixture rows and removes test-owned photos. Existing rows are not edited/deleted. Counts checked before/after; AUTO_INCREMENT surrogate IDs may have gaps. Future-clock tests disable temporary-upload expiry only in that test process, with config restored afterwards. No .env, database guard or application config file was changed.

Two-connection contention/deadlock stress tests remain unexecuted without an isolated schema; sequential competing checkout plus database-generated uniqueness were verified. Actual provisioning remains outside this implementation. No migration, reset, table creation/alteration, data-history change, commit, push or deployment.

## Main files

app/Services/Equipment/LoanService.php and LoanRules.php; app/Livewire/Admin/EquipmentLoans/Index.php and Show.php; resources/views/livewire/admin/equipment-loans/index.blade.php and show.blade.php; app/Http/Controllers/EquipmentReturnPhotoController.php; tests/Unit/EquipmentLoanRulesTest.php; tests/EquipmentLoansRollbackSmoke.php. Integrations: EquipmentPermissions, routes/web.php, layouts/admin.blade.php and narrow Inventory entry-point validation.
