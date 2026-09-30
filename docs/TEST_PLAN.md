# Farmer First ERP — Test Plan

## 1. Levels
| Level | Tooling | Scope |
|---|---|---|
| Unit | PHPUnit | pure logic without the framework (FY calculation, temperature, formatting) |
| Feature | PHPUnit + Laravel HTTP/Livewire testing | every screen/endpoint: auth, authorisation, validation, happy path, business-rule failure |
| Workflow | Feature tests over Actions | multi-step lifecycles (enquiry → order → delivery → achievement) |
| DB integrity | Feature tests on MySQL CI job | unique/partial-unique constraints, locking under concurrency |
| Security | Feature tests | unauthorised access, cross-branch/record scope, file access, lockout |

Run: `php artisan test --compact` (SQLite in-memory). MySQL: `DB_CONNECTION=mysql php artisan test --group=mysql`.

## 2. Mandatory business tests (master prompt §53)
| # | Scenario | Expected | Phase |
|---|---|---|---|
| T1 | Primary salesman Dwarika, delivery completed by Sales Manager | achievement +1 for Dwarika; `delivery_completed_by` = manager | 9 |
| T2 | Delivery-completed event processed twice | exactly one achievement row | 9 |
| T3 | Waiver approved on RTO task | task requirement_state WAIVED, operational stage unchanged, not counted completed | 7 |
| T4 | Waiver deadline passes | waiver flagged OVERDUE by scheduler; in overdue lists | 7 |
| T5 | Allocate same chassis to two orders | second allocation rejected (service + DB unique) | 5 |
| T6 | WON enquiry for existing farmer/customer | existing customer linked, no duplicate | 3 |
| T7 | Payment reversal | original kept, reversal references it, balance recalculated, audit present | 5 |
| T8 | Delivery reversal | unit status per rule, achievement reversal record, audit present | 8/9 |

## 3. Phase 1 coverage (implemented)
- Auth: login success, wrong password, inactive user refused, lockout after N failures, login history rows, logout, change password (current password check, policy).
- RBAC: seeder creates registry permissions & roles; Super Admin bypass; users without permission get 403 on each admin screen; menu hides unpermitted items.
- Audit: create/update of audited models writes old/new values & user; audit rows cannot be updated or deleted.
- Number series: sequential numbers, FY rollover resets counter, per-branch scope, format rendering.
- Geography: village uniqueness within tehsil, same village name allowed in another tehsil.
- Admin screens: create/edit/deactivate users, employees (multi-department/branch), roles permission sync, branches, departments.
- API: token issue, `/me`, envelope format, 401/403/422 shapes, request id header.

## 4. Acceptance traceability
Each SRS acceptance list (`docs/SRS.md` §3) maps to feature tests named `test_<requirement_id>_…` added in the phase that implements the module.
