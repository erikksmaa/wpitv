# WPiTV Admin RBAC Access Matrix

This matrix is the current source of truth for admin module access. `V` means view, `C` create, `E` edit, and `D` delete. An em dash means no module access. Dependency protections and self-protection rules still apply to allowed deletes.

| Module | Super Admin | Admin Production | Admin News | TVClub |
|---|---|---|---|---|
| Dashboard | V | V | V | V |
| Program | VCED | VCED | — | VCED |
| Tayangan / Video | VCED | VCED | VCE | — |
| Berita / News | VCED | — | VCED | — |
| Peliputan | VCED | VCED | — | — |
| Agenda | VCED | VCED | — | — |
| TV Club Generations | VCED | VCED | — | VCED |
| TV Club Members | VCED | VCED | — | VCED |
| TV Club Groups | VCED | VCED | — | VCED |
| TV Club Organization | VCED | VCED | — | VCED |
| WPiTV Organization | VCED | VCED | — | — |
| Visitor Statistics | V | — | — | — |
| Users | VCED | — | — | — |
| Roles | VCED | — | — | — |

Admin News may create and edit Tayangan but may not delete it. Users and Roles require an authenticated, active Super Admin session; they do not require a separate recent-password confirmation. Login, idle/absolute session limits, password-HMAC revocation, role/status refresh, CSRF, and password-update throttling remain active.

Sidebar and dashboard content must reflect only operational responsibilities. Hiding a link is never the authorization control; the policies and Livewire reauthorization are authoritative.
