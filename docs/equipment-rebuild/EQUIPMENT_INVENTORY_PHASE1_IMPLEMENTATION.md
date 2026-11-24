# Inventory Management — Phase 1 implementation

23 November 2026 · Branch rebuild/wpitv-clean · Local implementation, no commit/push/deployment.

## Actual schema first

Inspected all eleven equipment_* tables in local wpitv using information_schema + SHOW CREATE inside a read-only transaction. No DDL/migration/provisioning was performed. The runtime uses the actual schema, not unverified v3.2 column names:

- categories: code/name/description/is_active/version, with unique code (no sort_order).
- models: category_id/model_code/name/description/next_unit_no/is_active/version; no brand/model_name columns.
- units: condition_notes/status_reason/closed_at/closed_reason, actual enum conditions/statuses.
- photos: storage_path/mime_type/file_size/sha256/uploaded_by; model is_cover, no revision/photo_type columns.
- audit: actor_name_snapshot/event_name/occurred_at, before_data/after_data.
- operation keys include mandatory actor_id and actor_key.

These differences were accommodated without changing SQL. Inventory reads equipment_loan_units to reject changes to on_loan units and retain loss history. Loans/returns workflows are outside this phase.

## Delivered

Admin route /admin/inventory and permission-gated Mazer menu. Category create/update/deactivation; model create/update/reclassification/deactivation with initial stock; physical units, safe stock additions, immutable asset numbers, serial/condition edits, maintenance/loss/found/retire; derived stock with separate integrity warnings; search/category/status/condition filters and Bootstrap pagination; confirmation, validation and success/error feedback; private model/unit photo galleries and controlled downloads; audit list.

Query Builder is used against inspected tables, avoiding speculative Eloquent columns. InventoryService owns all writes. Model lock protects next_unit_no and asset allocation; inconsistent counters are rejected rather than repaired silently. Versions/stale checks, token actor/operation/hash matching and atomic business/audit/result completion prevent duplicate effects. No hard-delete action or mutable aggregate stock is exposed. Retired is terminal; no unit management mutation while on_loan; lost→found goes to maintenance; historic loan outcomes are untouched.

EquipmentPermissions defines an explicit catalog and role-to-permission default mapping using existing users/roles. Gates check active account and permission; no Super Admin bypass. SA/AP ordinary management, retire SA default; News/TVClub/unknown denied. No new role, grant table, dependency or change to other module policies/guards.

Uploads: actual image decode, exact input/output limit 5,000,000 bytes, JPEG/PNG/WebP only, dimensions64..4096/side, pixel cap, normalized PNG strips metadata/trailing payload, checksum over stored bytes. One model cover or up to eight reference photos per unit action. First photo can use generated reason; further photos/revisions require manual reason. Newest cover row is selected without deleting historical photos. Files remain on private local disk and are served through authenticated, permission-gated controller. Attempt-owned unreferenced files are cleaned after rollback/retry only after checking all three evidence tables; uncertain ownership retains private object for reconciliation. No historical media purge.

## Validation

- 23 targeted unit tests / 27 assertions: lifecycle, borrowed rejection, terminal retire, counter bounds, permission allow/deny, forged images and safe reencoding.
- 31 manual rollback smoke checks passed: category edits/stale rejection; atomic initial stock/additions; same-token replay/conflict; unique numbering/no reuse; condition/maintenance/lost/found/retire; borrowed retire denial; Livewire rendering and edit; private model/unit upload; revision reason and invalid-image rejection; transaction boundary/counts restored.
- Browser: user signed in with their own account; Mazer Inventory menu visible, category/model tabs and create forms responded to Livewire. No persistent synthetic records were created through browser.
- Targeted PHP lint, route registration and Blade rendering passed. Formatter applied only to changed PHP files. Full suite was not run.

No isolated testing database with these eleven tables was available. The opt-in manual smoke tests/EquipmentInventoryRollbackSmoke.php uses the inspected local database, an outer transaction and only newly-created synthetic records. All such rows are rolled back; private test photos use a unique test root and are removed. Existing rows are never edited/deleted. AUTO_INCREMENT surrogate IDs can have gaps after rollback. The test does not prove multi-connection race behavior; locks/unique constraints still require a separately authorized isolated concurrency environment for stress testing.

## Main files

app/Livewire/Admin/Inventory/Index.php; resources/views/livewire/admin/inventory/index.blade.php; app/Services/Equipment/InventoryService.php, InventoryRules.php, InventoryPhotos.php; app/Support/EquipmentPermissions.php; app/Http/Controllers/EquipmentPhotoController.php. Integration additions: AppServiceProvider, routes/web.php and layouts/admin.blade.php. Existing Live/Monthly code and database guards were not changed.
