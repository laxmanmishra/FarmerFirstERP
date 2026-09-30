# Farmer First ERP — Architecture

## 1. Style
**Modular monolith** on Laravel 13 (PHP 8.4+/8.5), MySQL 8, one deployable, one database.
Microservices are explicitly out of scope: the domain is highly transactional across modules
(order ↔ allocation ↔ payment ↔ delivery ↔ achievement) and needs ACID guarantees, not eventual consistency.

## 2. Stack
| Concern | Choice |
|---|---|
| Web UI | Blade + **Livewire 4 (class-based components)** + Alpine.js (bundled with Livewire) |
| Styling | Tailwind CSS 4 with CSS-first design tokens (`resources/css/app.css` `@theme`) |
| Build | Vite 8 |
| API | `/api/v1` REST/JSON, Sanctum tokens, API Resources, uniform envelope |
| RBAC | spatie/laravel-permission (multi-role), Laravel Policies, `Gate::before` for Super Admin |
| Queue / cache | database driver locally; Redis in Docker/production |
| Scheduler | Laravel Scheduler (overdue follow-ups, waiver deadlines, document/policy expiry, escalations) |
| Files | `local` private disk for all business documents; authorised streaming controller only |
| Tests | PHPUnit 12, SQLite in-memory; MySQL job in CI for constraint/locking tests |

## 3. Code layout
Laravel conventions, with domain logic out of controllers:

```
app/
  Actions/<Module>/        single-purpose write operations (CreateEnquiry, ApproveDeal, AllocateUnit…)
  Services/                cross-cutting & engine services (AuditService, NumberSeriesService,
                           WorkflowService, DocumentRequirementService, DeliveryReadinessService,
                           WaiverService, AchievementService, InventoryAllocationService,
                           CustomerDuplicateService, NotificationService)
  Enums/                   fixed semantic enums only (never configurable business statuses)
  Models/                  Eloquent models (+ Concerns/ traits: Auditable, HasCreator, BelongsToBranch)
  Policies/                one per aggregate root
  Http/Controllers/        thin; web controllers for non-Livewire endpoints, Api/V1 for the API
  Http/Requests/           Form Requests (API + shared rule sets)
  Http/Resources/          API Resources
  Http/Middleware/         AssignRequestId, EnsureUserIsActive …
  Livewire/<Module>/       screens (index/table, form, show/360)
  Events/ Listeners/ Jobs/ Notifications/
  Support/                 small helpers (ApiResponse, FinancialYear, Navigation)
config/erp/                permissions.php (registry), navigation.php, number_series.php, roles.php
resources/views/components/ui/   reusable UI kit
resources/views/layouts/         app shell, guest shell
docs/
```

**Rule of placement:** a business rule lives in exactly one Service/Action. Controllers, Livewire
components, API endpoints, jobs and console commands all call the same Action — this is what makes
"mobile actions use the same backend authorisation and business rules as web" `[v6.1 §10]` true by construction.

## 4. Operation pipeline
Every important write follows
`DATA → VALIDATION → AUTHORIZATION → WORKFLOW → STATUS → AUDIT → NOTIFICATION → REPORTING`:

1. Livewire/API layer validates input (Form Request or `$this->validate()` with shared rule sets).
2. Layer authorises (`$this->authorize()` / policy).
3. Action opens a DB transaction, locks what it mutates, checks business rules
   (throws `BusinessRuleException` → rendered as a friendly message, HTTP 422 in API).
4. Workflow transition validated by `WorkflowService` (configured transitions + role + requirement flags).
5. State written; status history appended.
6. Audit entry appended (`AuditService`, same transaction).
7. Domain event dispatched **after commit** (`ShouldDispatchAfterCommit`) → notifications, readiness recalculation, achievement.
8. Reporting reads live tables / indexed views; no hand-maintained counters.

## 5. Cross-cutting services
| Service | Responsibility |
|---|---|
| `AuditService` | Append-only `audit_logs` (user, IP, UA, request id, module, entity, action, old/new JSON, reason) |
| `NumberSeriesService` | `next('ORDER', $branch)` → `FF/ORD/2026-27/00001`; row lock on `number_series`, unique index on generated numbers |
| `WorkflowService` (Phase 2) | Generic stage/transition engine used by every configurable status |
| `DocumentRequirementService` (Phase 4) | Requirement generation from rules, reuse lookup, satisfaction evaluation |
| `DeliveryReadinessService` (Phase 7) | Aggregates tasks/docs/waivers → READY / BLOCKED / READY_WITH_WAIVERS / NOT_READY + blockers |
| `WaiverService` (Phase 7) | Request/approve/extend; deadline computation; overdue marking |
| `AchievementService` (Phase 9) | Idempotent credit/reversal keyed on delivery event |
| `InventoryAllocationService` (Phase 5) | Reserve/allocate/release/reallocate with locks + DB uniqueness |
| `NotificationService` (Phase 10) | Event → rule → recipients → channels → escalation |

## 6. Configurability model
- **Configurable** (data): statuses, transitions, pipeline stages, document types/requirements,
  readiness rules, waiver rules, notification/escalation rules, checklists, image types, number formats.
- **Fixed** (code/DB constraints): the invariants in `docs/SRS.md` §1.
- Configurable statuses carry **semantic flags** so code never switches on a status *name*:
  e.g. readiness asks "is the task's current stage flagged `is_completion`?" — so an admin adding
  "Legal Verification" `[§69]` needs no code change.

## 7. Multi-branch & data scoping
`company → branches → departments → employees`. Transactional records carry `branch_id`.
Users have a set of permitted branches (pivot) and a *current branch* (session, top-bar selector).
Record-level visibility = permission ∧ branch membership ∧ (ownership/team for sales roles).
Global scopes are avoided for security-critical filtering; visibility is applied explicitly through
query scopes (`visibleTo($user)`) so it is testable and never silently bypassed.

## 8. Security architecture
See `docs/RBAC_MATRIX.md`. Highlights: hashed passwords (bcrypt/argon), login rate-limit + lockout,
login history, inactive-user middleware kills sessions, CSRF on all web forms, output escaping by
Blade, Eloquent parameter binding, uploads validated by MIME + extension + size and stored on a
private disk with random names, signed + authorised downloads, security headers middleware (Phase 10),
production `APP_DEBUG=false` with request-ID-tagged generic error pages.

## 9. Roadmap
| Phase | Scope | Status |
|---|---|---|
| 1 Foundation | Org structure, geography, auth, RBAC, audit, number series, settings, UI shell, admin screens, API envelope | **Done** (see §10) |
| 2 CRM | Workflow engine (pulled forward), farmers, enquiries + duplicate check + temperature, telecaller queue/claim/call attempts, follow-ups, territory assignment, geography import | Planned |
| 3 Sales | Pipeline (Kanban), products/price master, quotations, customers + duplicate service, Customer 360, deals + approval | Planned |
| 4 Orders & Documents | Orders, fulfilment + tasks, document center, requirements, verification, reuse, documentation dashboard | Planned |
| 5 Fulfilment | Retail & Finance, Accounts, Inventory | Planned |
| 6 Compliance | RTO, Insurance, PDI | Planned |
| 7 Readiness | Rules engine, waivers, readiness engine | Planned |
| 8 Delivery | Delivery file, scheduling, checklist, images, signature, completion | Planned |
| 9 Management | Targets, achievements, dashboards, reports | Planned |
| 10 Hardening | Notifications, global search, imports/exports, Docker, monitoring, backups, performance | Planned |

## 10. Phase 1 implementation notes
- **Authorization:** `UserPolicy` guards account administration (self-lockout, Super Admin protection). Other
  Phase 1 masters authorise directly on permission names (`$this->authorize('branches.manage')`), which spatie
  resolves through the Gate. Record-scoped policies arrive with the first transactional module (Phase 2).
- **Super Admin bypass:** `Gate::before` returns true for Super Admin, so invariants that must hold even for Super
  Admin (e.g. cannot deactivate own account) are enforced inside Actions, not policies.
- **Livewire persistence:** `EnsureUserIsActive` is registered as persistent middleware so a deactivated user's open
  page cannot keep calling actions.
- **Strict models:** lazy loading and silently discarded attributes throw outside production.
- **Deferred to later phases** (not stubbed): global search, notification generation (the bell reads the real
  `notifications` table, which stays empty until Phase 10 events exist), geography Excel/CSV import (Phase 2),
  2FA, Docker compose (Phase 10).
