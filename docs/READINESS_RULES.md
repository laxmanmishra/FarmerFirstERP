# Farmer First ERP — Delivery Readiness Rules

## 1. Inputs `[§234]`
Each order's `fulfilment_tasks` (one per department/area) carry:
- `requirement_state`: REQUIRED | NOT_REQUIRED | CONDITIONAL | WAIVED
- `stage_id` (department workflow stage — operational status)
- `blocks_delivery` (from rule)
- responsible department / employee, `pending_since`

Plus: `document_requirements` with `blocks_delivery = true`, inventory allocation, accounts computed balance,
and approved waivers.

## 2. Rule table (`readiness_rules`, configurable, audited, effective-dated)
| Rule | Condition | Satisfied when | Waivable (default) |
|---|---|---|---|
| INVENTORY_ALLOCATED | always (physical unit orders) | active allocation exists, unit not BLOCKED, unit stage ∈ {READY_FOR_DELIVERY} or PDI not required | ✗ |
| ACCOUNTS_CLEARED | always | cleared amount ≥ receivable (customer contribution + other charges; finance receipt where configured) | ✔ (Owner) |
| FINANCE_MILESTONE | finance_required | finance stage flagged completion *or* ≥ configured milestone (default DO_RECEIVED) | ✔ |
| RTO_COMPLETED | rto_required ∧ rto blocks_delivery | rto stage `is_completion` | ✔ (common: post-delivery RTO) |
| INSURANCE_VERIFIED | insurance_required | insurance stage `is_completion` | ✔ (Owner) |
| PDI_PASSED | pdi_required | pdi stage `is_completion` and no unresolved mandatory failures | ✔ (Owner) |
| DOCUMENTS | any requirement with blocks_delivery | each requirement satisfied | per document type |
| CUSTOM_TASK | any future task marked blocks_delivery | task stage `is_completion` | per rule |

## 3. Evaluation `[§235]`
```
for each applicable rule:
    if satisfied → OK
    elif valid waiver (APPROVED, not revoked/expired, rule waivable) → WAIVED (listed, counted)
    elif required data missing / task not started → PENDING
    else → BLOCKER(department, task/doc, current status, responsible employee, age, rule, action)

result = BLOCKED            if any BLOCKER
       = NOT_READY          elif any PENDING
       = READY_WITH_WAIVERS elif any WAIVED
       = READY
```
- Overdue waiver (WAIVED + OVERDUE) still satisfies readiness unless the rule says `overdue_waiver_blocks = true`; always shown as critical exception.
- Results persisted in `readiness_results` + `readiness_blockers` with an evaluation log; recalculated on every
  relevant domain event (task stage change, payment cleared/reversed, document verified/expired, waiver approved/revoked, allocation changed).
- Overrides (`readiness.override`) are exceptional, require reason, and are logged in `readiness_overrides`.

## 4. Output contract
```json
{ "order_id": 123, "result": "BLOCKED", "waiver_count": 1, "evaluated_at": "…",
  "blockers": [ { "department": "Retail & Finance", "rule": "FINANCE_MILESTONE", "item": "DO",
                  "status": "DO Pending", "responsible": "R. Sharma", "age_days": 3, "action_url": "…" } ],
  "waived":   [ { "department": "RTO", "deadline": "2026-10-15", "overdue": false } ] }
```
