# WPiTV Archive and Clean Rebuild

Verdict: **REBUILD BASELINE READY** for independent Live Streaming and Monthly development. Local only; no push, PR or deployment.

## Baseline and archive

- main and origin/main remain `9a35b6725495a828c5ff3372f62f277cbb9d829c`.
- Original workspace is now on `archive/failed-equipment-20261121` at `2b005082dc58e521ae6f7c114e755cbf301b0163`; source status clean after committing all 279 reviewed modified/untracked source files. Original Equipment was preserved before any cleanup; Live, Monthly, Social, migrations, tests, reference source and documentation were archived too.
- Archive commit: `archive: preserve pre-rebuild WPiTV development`.
- Recovery snapshot `storage/app/release-safety/20261121-190619/` remains untouched. Reverified ZIP CRC and SHA-256 for all 6,760 manifest files. Git bundle was verified in the earlier recovery stage. The archive adds the current source/documentation that followed that snapshot.
- No credentials, ignored environment files, SQL dumps, private media, fixture ledgers, dependencies, runtime files, ZIPs or bundles were staged. Ignored recovery data remains locally available outside Git.
- Original workspace and old `codex/clean-live-monthly-release` worktree were not removed or reset. Main was not checked out/reset or committed to.

## Rebuild branch and source provenance

Branch `rebuild/wpitv-clean` was created directly from fetched origin/main into `storage/app/testing/rebuild-clean`. Its initial HEAD was exactly the baseline, with a clean worktree and no untracked source. Archive commit is not an ancestor; no mixed commit was cherry-picked.

Source was read from the archive commit. The old candidate supplied the separation reference: source-only files retain archive content, shared files receive the necessary trimmed changes, and independent harness helpers are derived from the archived test foundations. No old Equipment runtime was used as a fallback. The independent CSV helper was retained as the specifically permitted exception: it preserves formula-injection and invalid UTF-8 handling without importing EquipmentReports.

Full per-file provenance and module allocation are in `WPITV_REBUILD_FILE_MANIFEST.json` (124 source files). Final integration depends on all three local commits below; the last commit assembles routes/shared infrastructure for both modules.

| Commit | SHA | Scope |
| --- | --- | --- |
| feat(live): rebuild live streaming integration | 249961970e7bc2f58bd1fc4e7354889376265ea1 | 58 files: admin/public components, lifecycle, audit, model/policy, Agenda integration, YouTube/preview/archive, four migrations, dashboard partial, browser/Feature/unit/concurrency tests |
| feat(monthly): restore monthly reporting module | 38acca019e0b14774f524341f5fd4b1319dd633d | 37 files: model/lifecycle/editor, approval/version history, attachments/private downloads, CSV/print, policy, five migrations, dashboard partial and tests |
| chore(rebuild): integrate shared foundation and verification | See final HEAD / git log | Shared routes/navigation/dashboard, theme, timestamps, security lock, neutral harness, clone guards and this report |

## Shared dependencies and exclusion review

Keep baseline authentication, users/roles, authorization, Mazer, News, TVClub and other modules. Only required shared changes were restored: Program/Video/Agenda relations and lifecycle guard, UTC cast/timestamp concern, Live+Monthly exception handling, scoped navigation and dashboard, theme assets, print stylesheet and module routes. Shared hooks use the existing RBAC framework; no new role types.

Independent testing uses ReleaseConcurrencyGuard, ReleaseConcurrencyTestCase, ReleaseScenarioTestCase and ReleaseBrowserTest. Preserve fixed clone database/account/host allowlist, DML-only grants, source access denial, independent PDO connections, schema signature, session settings, isolated runtime/storage, fixture ownership journal, prefix/actor checks, exact-ID cleanup, worker joining and quarantine on cleanup failure. Monthly and Live concurrency workers are independent of Equipment. Original assertions were retained, with only clone identity/shell expectations and the unrelated-domain exception sentinel adapted.

Not transferred: inventory categories/items/units/membership/stock, loans/POS/returns/loss/recovery, borrowing reports, Equipment photos/audit/compatibility/backfill, their routes/menu/dashboard, model/service/policy/helper/migrations/tests, or local Social additions. Those remain in the archive. No baseline main module was removed. Source scan of app/routes/resources/tests found no Equipment or Inventory reference; direct App import scan must resolve against the final tree.

## Dependency remediation

composer.json is unchanged. The reviewed lockfile includes Livewire 4.4.9 and eight compatible security updates: Laravel 12.71.0, CommonMark 2.10.7, Flysystem 3.36.0, Symfony HTTP Foundation/Kernel/Routing 7.4.23, Mime 7.4.22 and YAML 8.1.11. Existing major versions retained; no broad update, audit ignore or bypass.

A genuinely empty rebuild vendor directory was installed from this lockfile. The first extraction was interrupted by Windows locks; serial retry completed installation. Composer validate passes. Saved rebuild composer audit reports **zero advisories and zero abandoned packages**. Package discovery succeeds. The QA session middleware was extracted into its own PSR-4 class to remove the previous browser-router autoload warning.

## Official schema and database safety

New isolated target: `wpitv_rebuild_testing`, with dedicated `wpitv_rebuild_runner@localhost` account granted only SELECT/INSERT/UPDATE/DELETE on this database. Neither wpitv nor wpitv_testing was a migration/test target. The older candidate clone is also preserved.

Authoritative source is the previously approved private wpitv backup `equipment-main-6c4j4-20261121-103924-a4cec7/wpitv.sql`, reverified SHA-256 `688f19b0061dd993839f6d87c3cf84e88e36787df93d009cede2dc69094cfe2b`. Extracted only 17 core CREATE TABLE definitions, excluded Equipment, local Social and Live/Monthly definitions, and imported no source rows or account statements. Applied in FK dependency order, then ran the nine archive migrations (four Live, five Monthly) unchanged. Seeded only canonical role names; generated synthetic fixtures through tests. No speculative Eloquent-derived schema or private dump entered Git.

After regression and browser cleanup: **24 tables, nine migration rows, 37 FK constraints, zero orphans**, zero synthetic users, zero Live rows and zero Monthly rows. Fixtures were cleaned by the DML-only runner. No original database, media or ledger changes occurred. Code/source backup is not a database reset or backup operation.

Reproduction requires the same private authoritative backup and its hash, an empty separately named approved clone, a provisioning connection for initial DDL, and a separate DML-only testing account. Extract CREATE statements using the core allowlist and topological FK order; reject unknown references and existing target databases. Apply the nine migrations, seed canonical roles, fingerprint SHOW CREATE and write a private approval manifest. Generate a separate test APP_KEY and configure APP_URL=http://localhost, TRUSTED_HOSTS=localhost, and the exact guarded clone identity. Never point the guards at either original database. Actual private provisioning scripts, credentials and audit/test evidence remain under ignored rebuild storage.

The remote baseline still uses an externally provisioned core schema as documented in DEPLOYMENT.md. This rebuild does not pretend that nine feature migrations can create the legacy core tables by themselves. Availability of the verified private baseline remains an environment prerequisite.

## Verification performed on the new worktree

| Check | Actual result |
| --- | --- |
| Pure Live/Monthly/Agenda/clone-guard tests | 167 tests / 707 assertions, pass |
| Live/Monthly integration, Feature/Livewire, auth/RBAC/IDOR, private file, lifecycle, concurrency, security and dashboard suite | 142 tests / 1,817 assertions, pass |
| Browser fixture runs | 2 tests / 6 assertions, pass; original fixture accounts cleaned |
| Combined PHP tests | **311 tests / 2,530 assertions** |
| Browser observations | Live list/detail/lifecycle controls and Monthly list/detail/manual editor rendered in the in-app browser. Dark responsive layout inspected. No data submitted through UI |
| PHP lint | All application/routes/migration/test PHP files checked; no syntax failures |
| Pint | All transferred/reviewed PHP paths formatted and rechecked; unrelated baseline formatting preserved |
| Blade and routes | Compiled successfully; 86 routes, no inventory/equipment endpoints |
| Vite and JavaScript | Build and seven admin theme tests pass; existing PostCSS `from` warning remains |
| FK and cleanup | 37 full constraints including composite keys, zero orphans; no synthetic leftovers |
| Secret/artifact checks | Explicit source-only lists; no env, private dump/media, ledger, backup, vendor or runtime files staged; key/token pattern scan clean |
| Git | Explicit staged module lists and staged diff/check before each commit; no force/reset/clean/push/PR |

Browser QA found the original Windows shutdown cleanup could append HTTP 500 after successful rendering. The archived shared test-environment fix was restored and the same page reloaded without the error. This is a test harness fix, not a production error-handling relaxation. Browser smoke does not claim exhaustive visual, desktop/mobile breakpoint or external YouTube player/network acceptance testing. Stateful workflows, uploads, roles and races are covered by the clone suites; formal operator UAT can be expanded later.

## Risks and next Full Equipment Rebuild

- All three rebuild commits form the verified integration; use final HEAD, not intermediate module-only commits as the development baseline.
- No push is authorized for this phase. Review these commits, then request a later explicit branch push/PR decision; do not push main or archive.
- Original database Equipment tables/history remain intentionally untouched and inaccessible to this clone runner. Database reset is a separate decision.
- Begin future Equipment design in this new branch only, with an agreed schema/business contract and acceptance criteria. `WPiTV_Equipment_Clean_Target_Schema_v2.sql` was previously unavailable; obtain the original before claiming final schema review. Keep the historical preservation/mapping strategy, private media protections and controlled clone-first migration plan. Do not restore old Equipment compatibility or harness dependencies.
- No Equipment implementation has been started as part of this archival/bootstrap work. The independent Live+Monthly baseline is ready; the next Equipment phase remains a separate scoped design task.
