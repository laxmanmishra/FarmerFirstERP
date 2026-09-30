# Farmer First ERP — Status & Transition Configuration

All business statuses are **data** in `workflow_stages`, grouped by `workflow_definitions`, with allowed moves in
`workflow_transitions` `[§76–78, §88]`. The lists below are the **initial seed**, not hard-coded values.
Code only relies on the semantic flags, never on names/codes (except seed-time wiring of system events).

## Stage flags
| Flag | Meaning / consumer |
|---|---|
| `is_initial` | allowed first stage on creation |
| `is_final` | closes the file (no further transitions except configured reopen) |
| `is_completion` | the department's work is genuinely done → readiness input |
| `is_hold`, `is_rejection`, `is_cancellation` | reporting & dashboard grouping |
| `blocks_delivery` | this stage keeps the readiness task blocked |
| `requires_remark` / `requires_document` / `requires_followup` / `requires_approval` | enforced by `WorkflowService` on entry |
| `sla_hours` | ageing / escalation threshold |
| `color` | badge colour token |

Transition: `from_stage (null = any)` → `to_stage`, allowed roles, requires approval, effective from/to, active.
Every transition writes `workflow_status_histories` (subject, from, to, user, remarks, at) `[INV-10]`.
Used stages: deactivate only `[INV-07]`.

## Initial definitions
| Definition | Initial stages (seed) | Completion stage(s) |
|---|---|---|
| `enquiry_validation` | UNVERIFIED → VALID / INVALID; sub-outcomes CALLBACK, NO_ANSWER, NOT_INTERESTED, DUPLICATE, WRONG_NUMBER, CUSTOMER_NOT_KNOWN (non-final) | VALID |
| `sales_pipeline` | VALIDATED, CONTACTED, FOLLOW_UP_REQUIRED, CUSTOMER_INTERESTED, PRODUCT_DISCUSSION, QUOTATION_REQUIRED, QUOTATION_GIVEN, NEGOTIATION, PURCHASE_DECISION_PENDING, WON (final), LOST (final), DROPPED (final) | WON |
| `deal` | DRAFT, DEAL_READY, SENT_BACK, APPROVED (final), REJECTED (final) | APPROVED |
| `finance` | FILE_CREATED, FINANCER_REFERRAL, FI_PENDING, FI_SCHEDULED, FI_DONE, CREDIT_APPROVAL_PENDING, CREDIT_APPROVED, CREDIT_REJECTED, QUOTATION_PENDING, QUOTATION_RECEIVED, DO_PENDING, DO_RECEIVED, DO_EXPIRED, DISBURSEMENT_PENDING, DISBURSED, ON_HOLD, CANCELLED, FINANCE_COMPLETED | FINANCE_COMPLETED (readiness default: DO_RECEIVED+ configurable) |
| `accounts` | PAYMENT_PENDING, ADVANCE_RECEIVED, PART_PAYMENT_RECEIVED, VERIFICATION_PENDING, PAYMENT_CLEARED, PAYMENT_SHORT, PAYMENT_RETURNED, ON_HOLD, ACCOUNTS_COMPLETED | PAYMENT_CLEARED / ACCOUNTS_COMPLETED (plus computed balance = 0 rule) |
| `payment` | ENTERED, VERIFICATION_PENDING, VERIFIED, CLEARED, REJECTED, RETURNED, REVERSED | CLEARED |
| `inventory_unit` | AVAILABLE, RESERVED, ALLOCATED, PDI_PENDING, PDI_FAILED, PDI_PASSED, READY_FOR_DELIVERY, IN_TRANSIT, BLOCKED, DELIVERED, RETURNED, INSPECTION_REQUIRED | READY_FOR_DELIVERY |
| `rto` | RTO_PENDING, DOCUMENTS_PENDING, DOCUMENTS_VERIFIED, APPLICATION_PREPARED, APPLICATION_SUBMITTED, RTO_PROCESSING, QUERY_RAISED, QUERY_RESOLVED, REGISTRATION_NUMBER_RECEIVED, RC_PENDING, RC_RECEIVED, RTO_COMPLETED, ON_HOLD, CANCELLED | RTO_COMPLETED |
| `insurance` | INSURANCE_PENDING, PROPOSAL_CREATED, DOCUMENTS_PENDING, DOCUMENTS_VERIFIED, PROPOSAL_SUBMITTED, QUOTE_RECEIVED, PREMIUM_PAYMENT_PENDING, PAYMENT_CONFIRMED, POLICY_ISSUED, POLICY_VERIFICATION_PENDING, POLICY_VERIFIED, INSURANCE_COMPLETED, QUERY_RAISED, ON_HOLD, CANCELLED | POLICY_VERIFIED / INSURANCE_COMPLETED |
| `pdi` | PDI_PENDING, INSPECTION_SCHEDULED, INSPECTION_IN_PROGRESS, FAILED_RECTIFICATION_REQUIRED, RECTIFICATION_IN_PROGRESS, REINSPECTION_PENDING, REINSPECTION_IN_PROGRESS, PASSED, PDI_COMPLETED, ON_HOLD, CANCELLED | PASSED / PDI_COMPLETED (guard: no unresolved mandatory failures) |
| `delivery` | DELIVERY_PENDING, READINESS_CHECK, BLOCKED, READY_FOR_DELIVERY, DELIVERY_SCHEDULED, VEHICLE_DISPATCHED, HANDOVER_IN_PROGRESS, DELIVERED, DELIVERY_COMPLETED, ON_HOLD, CANCELLED | DELIVERY_COMPLETED |
| `document` | PENDING, UPLOADED, UNDER_VERIFICATION, VERIFIED, REJECTED, EXPIRED | VERIFIED |
| `query` | OPEN, IN_PROGRESS, SUBMITTED, RESOLVED, CLOSED | RESOLVED/CLOSED |
| `waiver` | REQUESTED, APPROVED, REJECTED, EXTENSION_REQUESTED, CLOSED (work completed), REVOKED — with computed flag OVERDUE | — |

**Code guards independent of configuration**
- PDI → any `is_completion` stage refused while mandatory failed items are unresolved `[§184]`.
- Accounts → `is_completion` refused while computed cleared balance < receivable, unless a valid waiver exists (and even then the stage is not changed — the *task* is waived).
- Delivery → `DELIVERED`/`DELIVERY_COMPLETED` refused unless `DeliveryReadinessService` ∈ {READY, READY_WITH_WAIVERS} and mandatory images/checklist satisfied.
- Inventory stage changes happen only through `InventoryAllocationService` / PDI integration.
