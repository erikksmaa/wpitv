# RBAC + TVCLUB ROLE + ROLE-AWARE DASHBOARD REPORT

> Historical implementation report. Its read-only lower-role matrix was superseded on 2 September 2026. The authoritative current matrix is `docs/RBAC_ACCESS_MATRIX.md`.

Update 1 September 2026: Banner is RETIRED / REMOVED FROM APPLICATION. Its table and historical images remain intact. The active module inventory below excludes Banner; earlier validation figures are historical. See BANNER_FEATURE_RETIREMENT_REPORT.md for current results.

Date: 31 August 2026. Scope: TVClub role, RBAC alignment, role-aware dashboard, and current-role/status enforcement on Livewire. No deployment or schema changes.

### Follow-up audit after user confirmation — 31 August 2026

The role, runtime authorization changes, role-specific dashboards/navigation, seeder, and initial tests described below were already present when this follow-up began. They were preserved and inspected, not recreated. A read-only runtime check confirmed exactly one `TVClub` role; no seeder or account mutation was needed in this follow-up.

Current environment: cached `local` configuration, MySQL, no `.env.testing`, no dedicated test-database name, and no isolation opt-in. **FEATURE SECURITY TEST ENVIRONMENT NOT ISOLATED.** No feature suite or destructive database operation was run.

Follow-up changes are limited to this report and two test files:

- `tests/Feature/Admin/RoleSecurityTest.php`: Program deletion assertions now resolve the actual model table (`ms_program`); private-query assertions resolve model table names (`tr_perliputan`, `ms_news`) and inspect both FROM and JOIN instead of silently checking nonexistent generic table names.
- `tests/Unit/RoleAuthorizationTest.php`: added denied edit-form save/render checks using in-memory models, admin-route middleware checks, existing-role quick-action checks, and focused dashboard empty-state rendering without database fixtures.

Latest validation: **196 unit tests / 898 assertions PASS**; Pint PASS; Vite build PASS (existing non-fatal PostCSS `from` warning); Blade clear/cache PASS; diff check PASS. Prepared HTTP/Livewire security tests remain unexecuted in this follow-up, and prior skip counts below are historical, not passed tests. Browser navigation to the dashboard reached the login form; only the unauthenticated in-app browser was available. **AUTHENTICATED ROLE BROWSER QA PENDING.** No release-ready claim is made.

## 1. Initial Architecture Audit

| Area | Initial classification | Evidence / finding |
|---|---|---|
| Existing roles | EXISTING | Super Admin, Admin Production, Admin News existed in `roles`; identity resolved through `users.role_id`. |
| TVClub role | MISSING | No exact `TVClub` role or helper existed. |
| Admin shell | PARTIAL | Auth, active, and role middleware existed; role allowlist included three roles only. |
| Gates | PARTIAL | `access-admin`, `manage-production`, `manage-news`, `manage-users` existed. No consistent recognized-role check on all read policies. |
| Module policies | INCONSISTENT | Most mutations were constrained, but Video create/update and several view methods accepted any active user. |
| Livewire revocation | PARTIAL | Form mount/save authorization existed, but custom active/role middleware was not persistent and forms did not reauthorize render. |
| Dashboard | EXISTING / INCONSISTENT | One service assembled the full system metrics for every role; widgets had the same emphasis. |
| Sidebar | PARTIAL | Mazer grouping and system gates existed, without role-specific priority/read-only labeling. |
| Test isolation | MISSING | phpunit DB overrides are commented out; cached application configuration resolves to local, not testing. |

Audit covered User/Role models, middleware, Gates, module policies, admin/auth routes, layout/sidebar, dashboard service/component/views, form/action authorization, seeders, and existing tests. Existing public-page and spacing changes were preserved.

## 2. TVClub Role

Added exact-name constants and `ADMIN_ROLES` in User, plus `isTVClub()`, `canAccessAdmin()`, `canManageProgramsAndTVClub()`, and `canPublishVideos()`.

Added an independent, idempotent `TVClubRoleSeeder` using `firstOrCreate` by name. Ran only this seeder, not the demo/data seeder. The TVClub role now exists exactly once. Repeating it preserved every existing role ID, name, and description. No existing user was reassigned, no credential changed, and no migration was created.

For another environment, the narrowly scoped provisioning command is `php artisan db:seed --class=TVClubRoleSeeder`. Do not substitute the generic demo seeder. Role lookup does not assume a numeric ID.

## 3. Final RBAC Matrix

V = view, C = create, E = edit, D = delete; — = denied. Deletion still obeys existing dependency/self-protection rules.

| Module | Super Admin | Admin Production | Admin News | TVClub |
|---|---|---|---|---|
| Dashboard / admin shell | V | V | V | V |
| Program Acara | VCED | VCED | V | VCED |
| Video / Tayangan | VCED | VCED | VCE | V |
| News / Berita | VCED | V | VCED | V |
| Peliputan | VCED | VCED | V | V |
| TV Club Generations | VCED | VCED | V | VCED |
| TV Club Members | VCED | VCED | V | VCED |
| TV Club Groups | VCED | VCED | V | VCED |
| TV Club Organization | VCED | VCED | V | VCED |
| WPiTV Organization | VCED | VCED | V | V |
| Agenda | VCED | VCED | V | V |
| Visitor Statistics | V | V | V | V |
| Users | VCED | — | — | — |
| Roles | VCED | — | — | — |

TV Club group/organization assignment follows edit permission. Peliputan status/admin-note processing is restricted to Super Admin and Production. News can create/edit Video but cannot delete it. TVClub can manage all Programs, with no new ownership restriction. Inactive and unrecognized roles are denied across the matrix.

## 4. Policy Changes

- ProgramPolicy: TVClub added to create/update/delete; recognized active roles may view.
- TVClubGenerationPolicy, TVClubMemberPolicy, TVClubGroupPolicy: TVClub added to management; group assignments included.
- TvClubOrganizationPositionPolicy: TVClub management and member assignment enabled.
- VideoPolicy: create/update limited to Super Admin, Production, News; delete remains Super Admin/Production; TVClub is view-only.
- NewsPolicy: recognized-role reads; Super Admin/News mutation unchanged.
- CoveragePolicy, WpitvAgendaPolicy, WpitvOrganizationPositionPolicy: recognized-role reads; Super Admin/Production mutation unchanged.
- ContentPolicy: admin viewing uses the same recognized active-role helper; broad management gates were not widened to TVClub.
- UserPolicy and RolePolicy: unchanged Super Admin-only behavior, covered by the new matrix tests.

## 5. Route / Middleware Changes

Admin routes use the central four-role allowlist. `EnsureUserIsActive` reads the current user and role from the database and replaces the guard's cached actor for this request. Missing/inactive users are logged out, their current session invalidated, and redirected to login. `EnsureUserHasRole` rejects inactive/unrecognized actors.

Both middleware classes are registered as Livewire persistent middleware. Standard auth and route binding remain in place. Password confirmation GET/POST and password update now also require active status. Logout remains auth-only and available after deactivation.

The dashboard and visitor statistics reauthorize access during render. No per-role dashboard routes were added; existing admin aliases remain.

## 6. Sidebar Changes

`AdminNavigation` centralizes role order and policy-derived link visibility. Existing Mazer classes/grouping are retained.

- TVClub: Program Acara first, then Portal TV Club, then supporting read-only media/operations.
- News: Berita first in Media & Artikel, with Video next.
- Production: production content and operational links remain accessible.
- Super Admin: all sections, including access control.
- Read-only module links are marked `· Lihat`.
- Users/Roles are omitted for all non-Super Admin roles.
- Nested route patterns retain the parent/child active state, including generation organization pages.

## 7. Super Admin Dashboard

System overview: active/inactive users and role distribution; upcoming Agenda; Program/Video/News/Peliputan KPIs; production trend; visitor metrics/trend; program ranking; content health; recent content; TV Club resource summary. Quick actions include Program, News, Video, Agenda, Peliputan processing, and Users, each guarded by policy.

Existing charts are retained for Super Admin. Existing cards retain the dashboard card-body padding rule.

## 8. Admin Production Dashboard

Focus: active Programs, Video volume, upcoming Agenda, pending/in-progress Peliputan, production groups. Upcoming Agenda precedes the operational queue. Recent Programs and Videos support follow-up work.

Actions: add Program, Video, Agenda, and review Peliputan. No News-create or Users/Roles controls. No system-user or News metrics are queried.

## 9. Admin News Dashboard

Editorial focus: total articles, articles created this month, articles updated this week, and Video context. Latest updated articles appear before supporting summaries. Copy explicitly uses creation/update time, without inventing a publication-status workflow.

Actions: write News, add Video, view Agenda. Supporting Peliputan is counts only. No TV Club member data, system-role totals, or Peliputan contact fields are queried for this dashboard.

## 10. TVClub Dashboard

Focus: active generations, total/active members, groups, organization positions, current active generation, filled/vacant active positions, active/total Programs, recently updated Programs, recent active crew, and production groups. Supporting Agenda and Video are view-only.

Actions: add Program, generation, crew, group, and open organization structure. If there is no active generation, the organization action goes to the generation list and the panel explains the empty state. Filled/vacant positions are based on assignments to active members in the latest active generation, not an invented membership state.

No News, Peliputan mutation or Users/Roles action is exposed.

## 11. Dashboard Query Optimization

The service requires an authorized User and chooses one query branch before loading data. Upcoming Agenda is shared; bounded latest lists and eager-loaded relations avoid per-row lookup patterns.

Read-only local probes using in-memory role actors measured:

| Role | Service queries | Livewire component render |
|---|---:|---|
| Super Admin | 31 | Passed |
| Admin Production | 13 | Passed |
| Admin News | 9 | Passed |
| TVClub | 20 | Passed |

Counts describe the current local dataset, excluding HTTP middleware; empty relations can change eager-loading counts. No test user was inserted for these probes. They verify query execution/rendering, not authenticated HTTP access.

TVClub loads no Users/Roles, News, or Peliputan queries. News loads no TV Club crew/system metrics. Dashboard crew columns exclude NIM/photo, and Peliputan rows exclude requester contacts/internal notes. `select` precedes `withCount` for the new bounded group/program lists.

## 12. Livewire Role Revocation

On a real Livewire update, persistent middleware re-reads database status/role before component execution. An unrecognized role is rejected; a newly restricted recognized role is checked again by the component policy. All twelve form renders now reauthorize create/update, and their save paths enforce mutation permission. Agenda save now checks authority before validation as well.

Expected behavior: Production → TVClub cannot replay a Video save; Production → News cannot replay a Program save; a demoted Super Admin cannot refresh Users; a deactivated actor cannot refresh a dashboard or save an open form.

The implementation and unit checks are complete. Full HTTP replay tests are written but SKIPPED pending safe DB isolation; the end-to-end behavior is not claimed verified. This change is request-time enforcement, not global session/device revocation.

## 13. Direct URL Authorization

The isolated feature suite contains four-role allow/deny cases for index/create routes across Programs, Videos, News, Peliputan, Agenda, TV Club generations/members/groups, Users, and Roles, plus organization/statistics viewing. Unknown-role and inactive-role requests are included.

These authenticated HTTP cases were NOT executed because their fixture DB is unsafe. Browser guest navigation to `/admin/dashboard` showed the login form as expected. Policy decisions were executed independently in memory.

## 14. Livewire Mutation Authorization

Executed in-memory tests cover denied save and render on TVClub's read-only forms, News Program/TV Club forms, unknown-role Video, and inactive actors. Complete policy tests cover create/edit/delete, TV Club member assignment, and Peliputan status permissions.

Prepared, isolated feature tests cover TVClub Program CRUD; generation/member/group CRUD; group assignment/role changes/removal; organization-position CRUD and assignment/removal; full HTTP stale-role saves, stale system-component refresh, inactive dashboard/save requests, and sensitive auth routes. These remain skipped, not passed.

## 15. Privacy / Least Privilege

Dashboard query scope follows the actor's role. TVClub's dashboard exposes no credentials, user-management records, NIMs, Peliputan contacts, or internal notes. News/Production dashboards exclude system-role distribution.

Existing News read access to Peliputan details/internal notes and TV Club NIMs is deliberately retained on the authorized module pages, as required by the matrix. This task does not silently redefine those existing read privileges. Dashboard labels and hidden buttons supplement server policy checks; they are not the security boundary.

## 16. Files Modified

Files in this implementation:

- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Middleware/EnsureUserIsActive.php`
- `app/Livewire/Admin/Agenda/Form.php`
- `app/Livewire/Admin/Agenda/Index.php`
- `app/Livewire/Admin/Dashboard/Index.php`
- `app/Livewire/Admin/News/Form.php`
- `app/Livewire/Admin/Peliputan/Form.php`
- `app/Livewire/Admin/Programs/Form.php`
- `app/Livewire/Admin/Roles/Form.php`
- `app/Livewire/Admin/TVClub/Generations/Form.php`
- `app/Livewire/Admin/TVClub/Groups/Form.php`
- `app/Livewire/Admin/TVClub/Members/Form.php`
- `app/Livewire/Admin/TVClub/Organization/Index.php`
- `app/Livewire/Admin/Users/Form.php`
- `app/Livewire/Admin/Videos/Form.php`
- `app/Livewire/Admin/VisitorStatistics/Index.php`
- `app/Models/User.php`
- `app/Policies/ContentPolicy.php`
- `app/Policies/CoveragePolicy.php`
- `app/Policies/NewsPolicy.php`
- `app/Policies/ProgramPolicy.php`
- `app/Policies/TVClubGenerationPolicy.php`
- `app/Policies/TVClubGroupPolicy.php`
- `app/Policies/TVClubMemberPolicy.php`
- `app/Policies/TvClubOrganizationPositionPolicy.php`
- `app/Policies/VideoPolicy.php`
- `app/Policies/WpitvAgendaPolicy.php`
- `app/Policies/WpitvOrganizationPositionPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/AdminDashboardMetrics.php`
- `app/Support/AdminNavigation.php`
- `database/seeders/TVClubRoleSeeder.php`
- `resources/views/components/admin/role-navigation.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/livewire/admin/dashboard/agenda.blade.php`
- `resources/views/livewire/admin/dashboard/focused.blade.php`
- `resources/views/livewire/admin/dashboard/index.blade.php`
- `resources/views/livewire/admin/dashboard/quick-actions.blade.php`
- `resources/views/livewire/admin/dashboard/system.blade.php`
- `routes/auth.php`
- `routes/web.php`
- `tests/Feature/Admin/DashboardTest.php`
- `tests/Feature/Admin/RoleSecurityTest.php`
- `tests/IsolatedSecurityTestCase.php`
- `tests/Unit/RoleAuthorizationTest.php`
- `docs/RBAC_TVCLUB_ROLE_DASHBOARD_REPORT.md`

Pre-existing modifications to `resources/css/admin.css`, `resources/css/public.css`, `resources/views/livewire/public/agenda/index.blade.php`, and `resources/views/livewire/public/home.blade.php` were not changed by this implementation. Existing spacing/security-audit reports were also preserved. Build outputs are generated assets, not additional source changes.

## 17. Targeted Tests

- `php vendor/phpunit/phpunit/phpunit --testsuite=Unit`: PASS, 161 tests / 730 assertions (includes pre-existing unit tests).
- `php vendor/phpunit/phpunit/phpunit tests/Feature/Admin/RoleSecurityTest.php`: 118 SKIPPED, zero assertions; explicitly not a security pass.
- Read-only metrics and Livewire component rendering: all four roles passed.
- TVClubRoleSeeder repeat: exactly one TVClub record; all role IDs/names/descriptions preserved.
- `git diff --check`: PASS.

`IsolatedSecurityTestCase` checks testing environment, an explicitly designated dedicated test DB (`_test`, `_testing`, or in-memory SQLite), and `WPITV_TEST_DATABASE_ISOLATED=true` before opening a transaction or creating fixtures. No schema creation or reset is performed. The isolated schema must be provisioned separately. Existing feature classes do not inherit this guard.

## 18. Full Test Suite

**FEATURE SECURITY TEST ENVIRONMENT NOT ISOLATED**

Not run. Existing DatabaseTransactions tests would still touch the configured project database; a rollback alone is not sufficient isolation. Cached configuration currently reports `local`. Do not run the full suite until the test process resolves exclusively to an approved, separate test database and isolated session/cache/storage settings.

Classification: test-isolation limitation. No full-suite regression verdict is available; skipped tests are not hidden as passes.

## 19. Pint

`php vendor/bin/pint --test`: PASS. Initial findings were formatting/line endings/import ordering; Pint corrected only changed PHP files. No unrelated source cleanup was undertaken.

## 20. Build

`npm run build`: PASS (Vite 6.4.3, 60 modules). A non-fatal PostCSS warning remains about a plugin not passing the `from` option; build artifacts were generated successfully. No dependency upgrade was attempted.

`php artisan view:clear`: PASS. `php artisan view:cache`: PASS.

## 21. Browser QA

**AUTHENTICATED ROLE BROWSER QA PENDING**

The available in-app browser redirected dashboard navigation to the login form. No authenticated role sessions were available, and no other browser connection was present. No account was fabricated and no login bypass was introduced.

Pending for all four roles: 1440×900, 1280×720, 1024×768, 768×1024, 430×932, and 390×844; light/dark rendering; widgets/actions; nested sidebar active states; card spacing; overflow and touch behavior. Server-side rendering passed, but it is not a substitute for this visual QA.

## 22. Remaining Security Issues

The following previous-audit findings remain separate and were NOT silently remediated:

- User-controlled upload/existing-image paths and deletion boundaries.
- Peliputan receipt access based on receipt code.
- News raw HTML / stored-XSS handling.
- Temporary-upload storage exposure and cleanup hardening.
- NIM-derived media filenames.
- Global session revocation, device management, idle timeout, and MFA.
- Core-role rename/immutability and last-admin protection beyond existing behavior.
- CSP and production deployment hardening.

The exact-name role approach means renaming a primary role can make it unrecognized; a dedicated core-role administration hardening task remains appropriate. The present work only aligns runtime authorization to the agreed role matrix.

## 23. Final Verdict

**IMPLEMENTED — VALIDATION PENDING; NOT YET READY FOR RELEASE.**

TVClub is provisioned; policies, sidebar, dashboard query branches, form rendering, and persistent current-role/status checks are aligned. Unit checks, four-role component rendering, formatting, build, and Blade compilation passed. No schema migration or unrelated public/security fix was introduced.

The remaining release gates are an approved isolated feature-test environment, successful authenticated HTTP/Livewire integration tests, and authenticated browser QA for every role. Stop here for review; no deployment, global session overhaul, upload-path, receipt, or XSS remediation is included.
