# Farmer First ERP — Software Requirements (Working Digest)

> Authoritative source: **Farmer First ERP Final SRS v6.1 — Production Architecture Baseline** (PDF).
> This file is a developer-oriented digest with stable requirement IDs for traceability in code/tests.
> Section numbers in brackets refer to the PDF (e.g. `[§24]`, `[v6.1 §14]`). Where the digest and
> the PDF differ, the PDF wins. Changes follow the change-control rule `[v6.1 §22]`.

## 0. Business context
Tractor & implement dealership. One central database, role- and department-specific workspaces.
Lifecycle `[v6.0 B]`:

```
Territory → Farmer → Enquiry → Telecaller Validation → Sales Pipeline → WON → Customer → Deal
→ Document Checklist → Deal Ready → Deal Approval → Order/Booking → Fulfilment
→ Parallel Department Processing → Central Readiness Check → Delivery Scheduling
→ Delivery Handover → Delivery Evidence → Delivered → Order Completed → Achievement
```

## 1. Global invariants (must hold regardless of configuration)

| ID | Invariant | Source |
|---|---|---|
| INV-01 | Farmer, Enquiry, Customer, Deal, Order are separate entities. Farmer 1:N Enquiry; Farmer 1:0..1 Customer; Customer 1:N Deal; Deal 1:1 Order; Order 1:1 Fulfilment; Fulfilment 1:N Tasks | §3, §31 |
| INV-02 | **WAIVED is never COMPLETED.** A waiver bypasses a task temporarily; the task stays open until the real work is done | §3, §24, §86 |
| INV-03 | A department status describes only that department's work (Finance Disbursed ≠ Accounts Cleared; Insurance Payment Confirmed ≠ Accounts Cleared) | §86, §97, §147 |
| INV-04 | No physical inventory unit may be allocated to two orders at the same time | §53, v6.1 §14 |
| INV-05 | One qualifying delivery produces at most one achievement unit, credited to `PRIMARY_SALESMAN_EMP_ID`, not the delivery operator | v6.0 C, G |
| INV-06 | Transaction history is never hard-deleted by normal users; corrections are reversals/new records | §33, §101, v6.1 §6 |
| INV-07 | Used statuses cannot be hard-deleted — only deactivated | §69, §76 |
| INV-08 | External financers / insurers / RTO agents never receive ERP logins | §65, §115, §137 |
| INV-09 | Document existence ≠ verification ≠ requirement satisfaction | §200, v6.0 H |
| INV-10 | Every status change records user, timestamp, previous, new, remarks | §58, §70, v6.0 E |
| INV-11 | Counters/KPIs are always computed from live data; never hand-maintained | §206, §219 |
| INV-12 | Every dashboard counter is clickable and opens the exact filtered records | §55, §103, §207, §245 |

## 2. Modules and requirements

### 2.1 Foundation (Phase 1)
- **ORG-01** Hierarchy Company → Branch/Location → Department → Employee; multi-branch capable even if one branch today `[v6.1 §3]`.
- **ORG-02** A user may hold multiple roles, departments and branches `[v6.0 J, v6.1 §2]`.
- **SEC-01** Password policy, reset, activation/deactivation, forced logout; hashed passwords `[v6.1 §2]`.
- **SEC-02** Login history, failed-login tracking, lockout `[v6.1 §2]`.
- **SEC-03** RBAC at module, action, record and sensitive-field level `[v6.1 §2]`.
- **SEC-04** Session timeout; optional 2FA architecture (future).
- **AUD-01** Created/Updated By/At on all transactions `[§33]`.
- **AUD-02** Audit record: who, what, when, old value, new value, reason, source module, source entity, IP `[v6.0 E]`.
- **NUM-01** Configurable number series per entity: prefix, branch, financial year, sequence, reset rule; unique within scope; transaction-safe `[v6.1 §8]`.
- **FY-01** Indian FY (Apr–Mar), configurable boundaries; FY-based numbering; period locking `[v6.1 §9]`.
- **GEO-01** District → Tehsil → Village, permanent IDs; village unique on District + Tehsil + Name `[§6]`.
- **GEO-02** Bulk import (Excel/CSV) with validate → preview → error report → confirm; no partial imports `[§6, v6.1 §12]`.

### 2.2 CRM (Phase 2)
- **FRM-01** Farmer master: FARMER_ID, name, mobile, alt mobile, father/husband name, WhatsApp, territory, status, audit `[§7]`.
- **ENQ-01** Enquiry: ENQUIRY_ID, FARMER_ID, creator, current assignee, territory, one or more requirement lines (Tractor/Implement), deal type (New/Exchange), expected purchase date, auto temperature, budget, source, status, validation `[§7]`.
- **ENQ-02** Exchange captures old tractor details and customer-expected sale price; photos optional `[§7]`. Internally approved exchange value is separate `[v6.1 §4]`.
- **ENQ-03** Temperature from expected purchase date: 0–1 d Extra Hot, 2–6 Hot, 7–14 Warm, 15+ Cold; past dates rejected; recalculated on change `[§8]`.
- **ENQ-04** Duplicate check before submission on mobile/farmer identity, product, deal type, active status, date; user may proceed after confirmation `[§9]`.
- **TEL-01** All new enquiries enter a common UNVERIFIED queue; telecaller must Claim before validating `[§10]`.
- **TEL-02** Outcomes VALID / INVALID / UNVERIFIED (+ configurable sub-outcomes: callback, no answer, wrong number, duplicate…); every call attempt is its own record `[§10]`.
- **PIP-01** Only VALID enquiries enter the pipeline; configurable stages; finals WON / LOST / DROPPED `[§11]`.
- **PIP-02** Manager/Owner reopen directly with reason; Salesman/Telecaller request reopen for approval `[§11]`.
- **FUP-01** Follow-ups with due date/time, assignee, outcome, next follow-up; Due Soon/Today/Overdue/Completed `[§29]`.
- **TER-01** Salesman territory at District/Tehsil/Village level; one primary salesman per village initially `[§6]`.

### 2.3 Sales (Phase 3)
- **CUS-01** WON triggers duplicate customer check; else permanent CUSTOMER_ID linked to FARMER_ID `[§12]`.
- **CUS-02** Customer 360: profile, enquiries, deals, documents, payments, fulfilment, activities, timeline `[§12, §188]`.
- **QUO-01** Price master with effective dates; quotation with lines, discount, accessories, charges, exchange, finance amount, customer contribution; versioned; discount approval thresholds by role `[v6.1 §4]`.
- **DEA-01** DEAL_ID linked to CUSTOMER/FARMER/ENQUIRY; approved commercial snapshot; Deal Ready when configured info/documents complete `[§14]`.
- **DEA-02** Approval: Approve / Send Back / Reject with approver, time, old/new values, remarks `[§14]`.

### 2.4 Orders, Fulfilment & Documents (Phase 4)
- **ORD-01** Approved deal creates ORDER_ID/BOOKING_ID with snapshot of commercial data; order is the bridge to fulfilment `[§15]`.
- **FUL-01** Each order gets FULFILMENT_ID and configured department tasks with Requirement State (Required / Not Required / Conditional / Waived) separate from Operational Status `[§23, §234–235]`.
- **DOC-01** Central repository; documents linked at customer, deal, order, unit or department level; upload once, reuse via links `[§185, §201]`.
- **DOC-02** Document Type Master: code, name, category, level, reusable, expiry applicable, verification required/role `[§187]`.
- **DOC-03** Statuses Pending, Uploaded, Under Verification, Verified, Rejected (reason), Expired; versioning; old versions retained `[§194–196]`.
- **DOC-04** Document Requirement Engine: search repository → link existing valid doc → else upload → verify → satisfied `[§199]`.
- **DOC-05** Documentation dashboard: per-type counters, drill-down, department/employee accountability, ageing buckets, Documents Blocking Delivery view `[§205–217]`.
- **DOC-06** Access controlled by role/department/sensitivity; downloads of sensitive docs audited `[§197]`.

### 2.5 Fulfilment departments (Phases 5–6)
All departments: own queue, dashboard, files, documents, follow-ups/queries, activities, status history,
**configurable STATUS_MASTER + WORKFLOW_MASTER** `[§74–78]`, waiver participation.

- **FIN-01** Retail & Finance: finance file auto-created when order requires finance; Retail employees update status per external financer; financer + financer contacts master; follow-ups; queries (Open → In Progress → Submitted → Resolved) `[§57, §65–73]`.
- **ACC-01** Accounts: account file per order; many payments per file; each payment independently verified/cleared; receipts; short/excess flags; returns/bounces/reversals reference original; refunds with approval; separation of duties `[§91–113]`.
- **INV-01** Inventory: product ≠ physical unit; inward/GRN, locations, reservation, allocation, reallocation (immutable history), transfer, stock movements; statuses AVAILABLE … DELIVERED `[§53–56]`.
- **RTO-01** RTO file, applications, registration number/RC as data fields (not statuses), queries, follow-ups `[§114–135]`.
- **INS-01** Insurance file, insurer master/contacts, proposal, policy, expiry/renewal reminders (30/15/7/1 days) independent of workflow status `[§136–159]`.
- **PDI-01** PDI file per allocated unit; configurable checklist master; item results Pass/Fail/NA; defects with severity; rectification; multiple reinspection cycles; supervisor approval; PDI Passed impossible with unresolved mandatory failures `[§160–184]`.

### 2.6 Workflow, Waiver & Readiness (Phase 7)
- **WFL-01** Generic configurable status/transition engine: flags is_initial, is_final, is_hold, is_rejection, requires_remark/document/followup/approval, blocks_delivery; transitions with allowed roles, effective dates `[§76–78, §88]`.
- **WAV-01** Waiver request by responsible department employee or authorised Sales Manager; approval by Manager/Owner per configurable rules (critical/compliance/financial/multi-department → Owner) `[§24]`.
- **WAV-02** Approved waiver requires responsible employee + deadline (X days after approval / X days after delivery / specific date); reminders before deadline `[§25]`.
- **WAV-03** Deadline passed without completion → WAIVED + OVERDUE (automatic); appears in department overdue list and Manager/Owner dashboards; escalation configurable; overdue does not cancel the waiver `[§26]`.
- **WAV-04** Extension request with approval; all prior deadlines retained `[§27]`.
- **RDY-01** Central Delivery Readiness Engine returns READY / BLOCKED / READY_WITH_WAIVERS / NOT_READY with an explicit blocker list (department, task/doc, status, responsible employee, age, rule, action) `[§233–242]`.
- **RUL-01** Rules engine with active flag, effective dates, priority, audit `[v6.1 §15]`.

### 2.7 Delivery (Phase 8)
- **DLV-01** Delivery file linked to order, fulfilment, customer, exact unit; configurable workflow; readiness gate before Ready for Delivery `[§220–223]`.
- **DLV-02** Scheduling with reschedule history; blocked when readiness blocked unless waiver/override `[§224]`.
- **DLV-03** Configurable checklist and image types (front, rear, left, right, chassis, engine, hour meter, customer + tractor …); missing mandatory images block Delivery Completed unless waiver `[§225–226]`.
- **DLV-04** Handover: acknowledgement, signature, recipient, items handed over `[§227]`.
- **DLV-05** Completion → inventory DELIVERED, order/fulfilment updated, achievement +1 for primary salesman (idempotent) `[§229, v6.0 G]`.
- **DLV-06** Delivery reversal triggers inventory and achievement recalculation through approved business events `[v6.1 §6–7]`.

### 2.8 Management (Phase 9)
- **TGT-01** Unit-count targets (not value), Monthly/Quarterly/Yearly, per salesman/manager/team, optional product scope `[§248–249]`.
- **TGT-02** Achievement system-generated from qualifying deliveries; manual entry prohibited; adjustments authorised + audited; closed periods locked `[v6.0 C]`.
- **MGT-01** Owner Control Tower KPIs with drill-down; delivery blocker control; department workload; alerts `[§244–257]`.
- **RPT-01** Reports per module with period, branch, department, employee, product, territory, status filters; exports permission-controlled `[v6.1 §18]`.

### 2.9 Production hardening (Phase 10)
- **NTF-01** Central notification service: event type, source, recipient user/role, priority, message, due, read/action status, escalation level; in-app first, email/SMS/WhatsApp via adapters `[v6.0 D]`.
- **SRC-01** Global search across customers, farmers, enquiries, deals, orders, units (chassis/engine), department files `[v6.1 §17]`.
- **API-01** Versioned REST/JSON API with auth, rate limiting, validation, idempotency for critical calls `[v6.1 §11, §14]`.
- **OPS-01** Backups (DB + files separately), restore tests, RPO/RTO, monitoring, health checks, safe error messages `[v6.1 §13, §19]`.
- **MOB-01** Responsive mobile workflows for field staff; camera capture; optional GPS; offline drafts (future) `[v6.1 §10]`.

## 3. Acceptance criteria
The consolidated acceptance lists in `[§34, §63, §75, §90, §113, §135, §159, §184, §204, §217, §242, §257, v6.0 M]`
are tracked in `docs/TEST_PLAN.md` as test cases.

## 4. Open questions for the business (to confirm during sign-off `[v6.1 §23]`)
1. Salesman reassignment before delivery — which salesman gets credit? (default proposed: the primary salesman at the moment of the qualifying delivery event).
2. Does Finance "credit approved" or "disbursed" satisfy delivery readiness by default? (proposed: DO Received).
3. Default RTO / Insurance / PDI delivery-blocking flags per product category.
4. Number-series formats (e.g. `FF/ORD/2026-27/00001`).
5. GST / e-invoice integration timing.
