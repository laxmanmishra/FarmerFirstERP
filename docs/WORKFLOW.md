# Farmer First ERP — Business Workflows

## 1. End-to-end
```
Farmer ──1:N── Enquiry ──(Telecaller claim → VALID)──► Pipeline ──WON──► Customer (dup-check) ──1:N── Deal
Deal ──Deal Ready → Approve──► Order/Booking ──1:1── Fulfilment ──1:N── Department Tasks (parallel)
Tasks + Documents + Waivers ──► Delivery Readiness Engine ──READY──► Delivery ──Completed──► Achievement(+1 primary salesman)
```

## 2. CRM
1. **Enquiry create** (salesman / telecaller / walk-in): farmer lookup by mobile → create or link farmer →
   duplicate enquiry check (mobile, product, deal type, open status, date window) → user confirms if
   matches → enquiry saved **UNVERIFIED**, temperature computed from expected purchase date.
2. **Telecaller**: common queue → *Claim* (row lock; one claimant) → call attempt recorded (outcome, duration,
   remarks, next callback) → outcome VALID (to pipeline) / INVALID (reason, retained) / keep UNVERIFIED
   (callback, no answer…). Unclaim/timeout returns to queue.
3. **Pipeline**: VALID → configurable stages → WON / LOST / DROPPED. Stage change = workflow transition
   with history. Reopen: Manager/Owner direct with reason; others via reopen request approval.
4. **Follow-ups**: attached to enquiry/customer/any department file; scheduler marks overdue & notifies.

## 3. Sales
1. **WON** → `CustomerDuplicateService` (farmer link, mobile, name + village) → link existing or create
   CUSTOMER_ID.
2. **Quotation** (versioned) → discount above role threshold requires approval.
3. **Deal** created from WON enquiry + accepted quotation snapshot → document checklist generated →
   **Deal Ready** when requirements satisfied → **Approval**: Approve / Send Back (to salesman with remarks) / Reject.
4. **Order** created in the same transaction as approval, with `order_no`, commercial snapshot,
   `primary_salesman_employee_id`, and `fulfilments` + tasks generated from readiness configuration
   (finance required? RTO? insurance? PDI?).

## 4. Fulfilment (parallel)
Each department works its own file/queue; there is **no enforced order** unless a configured dependency
exists (e.g. "Insurance completed before RTO submission" `[§128]`).

| Department | Created when | Owner of |
|---|---|---|
| Retail & Finance | order.finance_required | finance file status (mirrors external financer) |
| Accounts | every order | payments, verification, clearance, balance |
| Inventory | every order with a physical unit | reservation / allocation / stock status |
| RTO | order.rto_required | registration workflow & data |
| Insurance | order.insurance_required | proposal, policy, expiry |
| PDI | on allocation when PDI required | inspection status (follows reallocated unit) |
| Delivery | every order | scheduling, handover, evidence |

## 5. Waiver lifecycle `[App. B]`
```
Pending task → Waiver Request (reason, responsible emp, deadline rule)
  → Manager/Owner approval (Owner if critical/compliance/financial/multi-dept)
  → task.requirement_state = WAIVED  (operational status untouched, still pending)
  → reminders before deadline
  → [work completed → task Completed, waiver Closed]
    [extension requested → approved → new deadline, history kept]
    [deadline passed → WAIVED + OVERDUE → escalation]
```

## 6. Delivery
`Delivery Pending → Readiness Check → (Blocked | Ready for Delivery) → Scheduled → Dispatched →
Handover In Progress → Delivered → Delivery Completed`. *Delivered/Completed* require readiness
(or valid waivers), mandatory checklist, mandatory images, handover evidence. Completion (one transaction):
delivery completed → inventory unit DELIVERED → order/fulfilment completed → `DeliveryCompleted` event →
`AchievementService::credit()` (idempotent on delivery id).

## 7. Reversal / cancellation `[v6.1 §6–7]`
Always a new event with reason + authorisation; originals retained. Delivery reversal → unit status per
rule (RETURNED / INSPECTION_REQUIRED / BLOCKED / AVAILABLE) → achievement reversal record (never delete).
