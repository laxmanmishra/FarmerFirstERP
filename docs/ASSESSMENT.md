# Farmer First ERP — Project Inspection & Architecture Assessment

Date: 2026-09-30 · Baseline: *Farmer First ERP Final SRS v6.1 (Production Architecture Baseline)*

## 1. Current architecture assessment

The workspace `C:\projects\FarmerFirstERP` was **empty** at inspection (no source, no git repository,
no database schema). There was no existing functionality to preserve and no conflicting code.

Toolchain found on the machine:

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.5.1 NTS x64 | Extensions present: pdo_mysql, pdo_sqlite, intl, gd, bcmath, zip, fileinfo, sodium, redis **absent** |
| Composer | 2.9.2 | |
| Node / npm | 25.2.1 / 11.6.2 | |
| MySQL | 8.0.44 (service `MySQL80` running) | root requires a password that was not provided |
| Docker | 29.2.1 | |
| Git | 2.53 | |

Actions taken: scaffolded **Laravel 13.34** (satisfies "Laravel 12+"), Tailwind CSS 4, Vite 8,
Livewire 4.4, Sanctum (API tokens), spatie/laravel-permission 8.3 (multi-role RBAC), Laravel Boost (dev).

## 2. Existing functionality
None beyond the Laravel skeleton.

## 3. Missing functionality
Everything in SRS v6.1. See `docs/SRS.md` for the module map and `docs/ARCHITECTURE.md` §9 for the roadmap.

## 4. Database assessment
- Target: MySQL 8 (InnoDB, utf8mb4). All migrations are written to be portable to SQLite so the test
  suite runs in-memory; MySQL-specific safeguards (generated columns for partial uniqueness,
  `SELECT … FOR UPDATE`) degrade safely on SQLite and are exercised against MySQL in CI/staging.
- **Blocker for local MySQL:** credentials needed. Until provided, local dev uses `database/database.sqlite`.
  Switching is an `.env` change only (`DB_CONNECTION=mysql` …).

## 5. UI/UX assessment
No UI existed. Decision: server-rendered Blade + Livewire 4 (class-based components) + Alpine (bundled
with Livewire) + Tailwind 4 design tokens. A reusable component library (`resources/views/components/ui/*`)
is built in Phase 1 and used by every module.

## 6. Security assessment (greenfield risks to design out)
| Risk | Control |
|---|---|
| Privilege creep across departments | spatie permissions `module.action`, policies on every model, permission-aware menus, server-side checks in every Livewire action |
| Brute force | RateLimiter lockout on login, failed-login tracking, `login_histories` |
| Sensitive documents | private disk only, signed/authorized download controller, access log |
| Audit tampering | `audit_logs` is append-only (model refuses update/delete) |
| Secrets in git | `.env` ignored, `.env.example` has placeholders only |
| External parties | financer / insurer / RTO agents are data records, never `users` |

## 7. Technical risks
1. **Scope** — ~200 SRS sections. Mitigation: strict phase gating with tests per phase.
2. **Configurable workflows vs. business invariants** — admins may add statuses, but invariants
   (WAIVED ≠ COMPLETED, no double allocation, no duplicate achievement) must hold regardless of
   configuration. Mitigation: invariants live in services + DB constraints, never in status names;
   statuses carry *semantic flags* (`is_final`, `is_completion`, `blocks_delivery`, …).
3. **Concurrency** — allocation, number series, achievement. Mitigation: transactions + row locks +
   unique constraints + idempotency keys.
4. **Redis unavailable** in the PHP build — cache/queue use the database driver locally; Redis in Docker/production.
5. **Windows dev vs Linux prod** — Docker compose provided for parity (Phase 10).

## 8. Recommended architecture
Modular monolith on Laravel. See `docs/ARCHITECTURE.md`.

## 9. Development phases
As specified in the master prompt (Phases 1–10), with one adjustment: the **generic workflow/status
engine** (`workflow_definitions/stages/transitions`) is pulled forward from Phase 7 into Phase 2,
because Enquiry, Pipeline and every fulfilment department consume it. Building departments first and
retrofitting configurability later would mean hard-coded statuses, which the SRS (§76, §89) forbids.

## 10. Exact first implementation steps (Phase 1)
1. Organisation schema: company, branches, departments, designations, employees (+ branch/department pivots).
2. Geography: states → districts → tehsils → villages (uniqueness: district + tehsil + village name).
3. RBAC: permission registry (`config/erp/permissions.php`), default role matrix, seeder, `Gate::before` for Super Admin.
4. Auth: login (rate-limited, lockout, active-account check), logout, change password, login history.
5. Cross-cutting: append-only audit log + `Auditable` trait, request-ID middleware, number-series service
   (Indian FY, branch-aware, transaction-safe), system settings, API response envelope (`/api/v1`).
6. Base UI shell: sidebar from a permission-aware navigation registry, top bar, component library.
7. Admin screens: users, roles & permissions, branches, departments, geography, audit log viewer.
8. Tests: auth, RBAC boundaries, audit immutability, number-series uniqueness, geography uniqueness.
