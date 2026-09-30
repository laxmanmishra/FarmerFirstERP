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
| 2 CRM | Workflow engine (pulled forward), products, farmers, enquiries + duplicate check + temperature, telecaller queue/claim/call attempts, pipeline + reopen, follow-ups, territory, geography import, CRM API | **Done** (see §11) |
| 3 Sales | Customers + duplicate check + Customer 360, price master, discount limits, versioned quotations with approval + print, deals with Deal Ready → Approve / Send Back / Reject | **Done** (see §12) |
| 4 Orders & Documents | Orders, fulfilment + tasks, document center, requirements, verification, reuse, documentation dashboard | **Done** (see §13) |
| 5 Fulfilment | Retail & Finance, Accounts, Inventory | **Done** (see §14) |
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

## 11. Phase 2 implementation notes
- **Workflow engine** (`WorkflowService`, `workflow_*` tables) drives enquiry validation and the sales pipeline.
  Definitions are uncontrolled by default (any move between active open stages); Administration → Workflow
  Configuration can add/rename/reorder/deactivate stages, set behaviour flags and switch on controlled,
  role-restricted, effective-dated transitions. Guards: used stages are never deleted and keep their code; every
  workflow keeps an active initial and an active final+completion stage.
- **Enquiry state** = two stage columns (`validation_stage_id`, `pipeline_stage_id`) + `closed_at` as the single
  "closed" truth. VALID moves the enquiry into the pipeline's initial stage; other final validation outcomes close it.
- **Assignment rule**: explicit (needs `enquiries.assign`) → creator if they only see their own enquiries → most
  specific primary salesman of the village's territory → unassigned. Territory uniqueness is enforced by the
  `primary_scope` unique column.
- **Claims** are a single conditional UPDATE (race-safe) with a timeout; `crm:release-stale-claims` tidies them.
- **Temperature** is stored for filtering and refreshed nightly by `crm:refresh-temperatures`.
- **Notifications** (assignment, reopen requested/decided, follow-up due/overdue) are queued database
  notifications: run `php artisan queue:work` (or `composer run dev`) and `php artisan schedule:work` locally.
- **EnquiryWon** is dispatched after commit when an enquiry reaches a final+completion pipeline stage; Phase 3
  attaches customer creation to it. Until then WON closes the enquiry only.
- **Windows note**: translation keys that equal a lang file name (`__('Validation')`) return arrays on
  case-insensitive filesystems — use descriptive keys.

## 12. Phase 3 implementation notes
- **WON → customer → deal**: `EnquiryWon` (after commit) runs `ConvertWonEnquiry`, idempotent via UNIQUE
  `deals.enquiry_id` and UNIQUE `customers.farmer_id`. A different farmer sharing the mobile creates a new customer
  flagged `possible_duplicate_of_id` for review (merge tooling is future work).
- **Money** is decimal strings via bcmath (`App\Support\Money`); `QuotationCalculator` is the single source of the
  commercial arithmetic (gross → discount → tax → line total; charges separate; net = items + charges − exchange;
  contribution = net − finance).
- **Quotations** are versioned documents (`quotation_no` + `version`); a discount above the preparer's role limit
  (`discount_limits`) needs approval by another user whose limit covers it. Accepting supersedes the enquiry's other
  quotations and refreshes an editable deal.
- **Deals** take their figures only from an accepted quotation (no re-entry). Deal stages are a configurable
  workflow whose five stages are `is_system` (renamable, never deactivated). Submit → approve / send back /
  reject with separation of duties and an immutable `deal_approvals` snapshot per step. Approval fixes the exchange
  tractor's approved value and dispatches `DealApproved`; since Phase 4 the order is booked inside the approval transaction.
- **Recipients** of approval notifications are loaded with `User::withPermissionInBranch()` (eager, no N+1).

## 13. Phase 4 implementation notes
- **Booking**: `DealApprovalFlow::decide(Approved)` calls `CreateOrderFromDeal` in the same transaction, so an approved
  deal always has exactly one order (UNIQUE `orders.deal_id`; a replay returns the existing order). The order freezes the
  deal's commercial values and a full `deal_snapshot`. `OrderBooked` (after commit) notifies departments with a required task.
- **Fulfilment tasks** come from `fulfilment_task_types` (admin-editable): condition on the deal flags → Required or
  Not Required. The requirement state is separate from the operational stage (generic `fulfilment_task` workflow until
  departments get their own in Phases 5–6). Only holders of the type's `update_permission` work a task; the system
  Cancelled stage is reachable only by cancelling the order. `orders.create` holders can switch Required / Conditional /
  Not Required with a reason; WAIVED is reserved for the Phase 7 waiver workflow.
- **Document requirements**: one row per order × document type × department, generated from
  `document_requirement_rules` (no task = every order; with a task = follows that task's requirement state). Rows are never
  deleted, only switched to Not Required. Their status is derived from the linked document, never stored
  (`DocumentRequirement::status()` and the matching `withStatus()` query scope are tested against each other).
- **Reuse**: reusable types are searched across the customer, others within the order (deal-level within the deal).
  "Use existing" links the same document; optional auto-link (setting `documents.auto_link_existing`, default on) links
  verified reusable documents when an order is booked.
- **Verification**: permission per document type (`verification_permission`); the uploader of the current version can
  never verify it. A new version resets verification; versions, verifications and access logs are immutable.
- **Security**: files on the private `local` disk under random names; `DocumentFileController` checks visibility,
  sensitivity (`documents.view_sensitive`, the type's verifiers or the uploader) and logs every view/download; sensitive
  access is also audited. Reference numbers (Aadhaar, PAN …) are encrypted at rest and shown masked.
- **Expiry**: `documents:mark-expired` runs daily; requirements also treat a past expiry date as expired immediately.
- **Unit-level documents** (insurance policy, RC, PDI report) attach to the order until inventory units exist (Phase 5).

## 14. Phase 5 implementation notes
- **Department files**: `DepartmentFileProvisioner` opens a finance file when the finance task applies and an account
  file for every order — on booking and again after a requirement change (idempotent, UNIQUE order_id). Tasks with
  `driven_by` (finance file, account file, allocation) cannot be moved by hand; `FulfilmentTaskFlow::follow()` maps the
  file stage flags onto the task (completion → Completed, hold / final rejection → On hold, initial → Pending, else In
  progress). Allocation drives the inventory task by allocated vs required units.
- **Finance** status is the configurable `finance` workflow and mirrors what the external financer reports (INV-03);
  completion needs a financer and sanctioned amount. Queries (`file_queries`, Open → In progress → Submitted → Resolved)
  and follow-ups (polymorphic `follow_ups`, `Followable` interface) hang off the file; reminders cover them too.
- **Accounts**: record → verify (another person; issues the branch-numbered receipt) → clear. Rejection, bounce
  (cheque / DD only; cancels the receipt) and reversal (manager, not the recorder; new negative entry referencing the
  original, original kept as Reversed) never edit amounts — the model refuses. Completion is refused while the cleared
  balance is short or payments are uncleared; a later bounce or reversal moves a completed file to the system stage
  Payment short. Refunds: request (≤ excess, or ≤ cleared when the order is cancelled) → approval by someone else
  holding `accounts.approve_refund` → paid as a negative cleared entry.
- **Inventory**: GRN creates Available units with an inward movement; chassis / engine numbers are unique. Allocation
  locks the order and unit rows, checks product, branch and required quantity, and relies on the UNIQUE
  `active_unit_key` as the last guard (T5). Release / reallocation keep history; transfers and block / unblock are
  movements. Cancelling an order releases its units and cancels its finance file; the account file stays open for
  refunds.
- **Not yet**: stock reservations, in-transit transfers, finance file per multiple financers, GST invoices.
