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

## 3a. Phase 2 coverage (implemented)
- Workflow engine: admin-added stage usable without code changes, flags enforced, controlled transitions by role, inactive stages refused, used stages undeletable and code-locked, initial + completion guards, immutable history.
- Enquiries: numbering, UNVERIFIED + history, temperature bands (unit) and nightly refresh, past dates refused, duplicate detection by farmer/mobile/product/deal type with reasoned override, exchange + multi-line requirements, edit refused when closed, assignment rules and notifications.
- Telecaller: single-holder claim, expired-claim takeover, claim required, VALID → pipeline, INVALID needs remark and closes, CALLBACK needs a future time, immutable call attempts, screen flow.
- Pipeline: moves with history, LOST needs a configured reason, WON closes + EnquiryWon, direct reopen to last open stage, rejected → validation queue, WON not reopenable, request → approve/reject with separation of duties and notifications, drag-and-drop with and without permission.
- Visibility: own / team / all / branch scope, 404 for other salesmen's enquiries (web + API), private exchange photos.
- Follow-ups: schedule / complete / next, owner-or-manager rule, derived overdue, idempotent reminders.
- Territory: one primary per area (service + DB), replacement keeps history, village > tehsil > district precedence, inactive salesman skipped.
- Geography import: preview without writes, all-or-nothing on errors, XLSX, screen flow + audit.
- API: farmer/enquiry creation, duplicate contexts, envelope errors, visibility, follow-ups.
- Every CRM/admin screen renders with demo data and is refused without permission.

The suite also runs on MySQL 8: `composer test:mysql` (database `farmer_first_erp_test`).

## 3b. Phase 3 coverage (implemented)
- Money and calculator: exact decimals, rounding, Indian grouping, discount/finance/exchange limits.
- Conversion: WON creates CUSTOMER_ID and draft deal with primary salesman; idempotent; repeat buyer linked; same-mobile other farmer flagged; accepted quotation applied.
- Quotations: numbering and versions, validated enquiry required, discount approval by limit and not self, owner override, revise/supersede, accept supersedes others, expiry, price-master prefill, protected print view.
- Deals: readiness list, submit notifies approvers and locks, approve raises DealApproved, salesman/submitter cannot decide, send back needs remarks and reopens editing, reject closes, exchange value approved, finance/booking limits, immutable history, system stages protected, screen flow and visibility.
- Price master precedence (variant > generic, branch > all, effective dates) and auto-ending of the previous price.
- Sales screens render and are refused without permission; sales API visibility.

## 3c. Phase 4 coverage (implemented)
- Booking: approval creates one order with the frozen snapshot, items, fulfilment and 7 tasks; task and document requirement states follow the deal flags; idempotent; only approved deals; departments with required tasks notified; order visibility.
- Tasks: owning department only, order enters fulfilment, hold needs remark, Cancelled only through order cancellation, not-required tasks locked, requirement change needs permission + reason and refreshes documents, assignment limited to the department, cancellation keeps history.
- Documents: satisfied immediately without verification need; verification by another permitted user only; start review; rejection needs reason and notifies uploader; new version keeps history and resets verification; versions immutable; reuse across orders (customer level) but not order-level; auto-link of verified reusable documents; expiry by date and by nightly job (idempotent); file type/size limits with no stray files; upload must match requirement; status scopes equal derived status.
- Screens: orders list/detail tabs and visibility, task update / requirement change / cancel flows, checklist upload → verify → use existing, sensitive file access restricted and logged + audited, Document Center dashboard drill-downs, repository and verification queue, document detail reject → new version, configuration admin-only with rule uniqueness and effect on next booking, customer 360 / deal / dashboard integration.
- API: orders list/detail, upload validation + 201, file access log, visibility 404s.

## 3d. Phase 5 coverage (implemented)
- Finance: file only for financed orders and on later requirement change (idempotent); file status drives the task (in progress, on hold, completed); completion needs financer + sanction; system stage and manual task moves refused; detail validation and permissions; query lifecycle with required resolution; follow-ups on files incl. manager completion and reminders; cancellation cancels the file.
- Accounts: receivable from order; verify by another person issues receipt; clear; recording rules (amount, reference, financer/disbursement, future date); amounts immutable; completion blocked while short; **T7** reversal; bounce only for instruments; refunds only when refundable, approved by someone else, paid as negative entry.
- Inventory: GRN, duplicate chassis/engine (in stock and within GRN), permission; allocation completes the task and release reverts it; **T5** second allocation refused by service and database; product and branch checks; transfer; reallocation keeps history; movements immutable; block/unblock; cancellation returns units.
- Screens: every department screen/tab renders and is refused without permission; finance workspace (details, status, follow-up, query, financer master); accounts workspace (record, verify, clear, receipt print, error toast); inventory GRN with duplicate check, allocation from the queue, release from the unit page.

## 4. Acceptance traceability
Each SRS acceptance list (`docs/SRS.md` §3) maps to feature tests named `test_<requirement_id>_…` added in the phase that implements the module.
