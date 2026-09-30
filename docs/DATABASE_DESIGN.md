# Farmer First ERP — Database Design

Engine: MySQL 8 InnoDB, `utf8mb4_unicode_ci`. Tests: SQLite in-memory (all migrations portable).

## 1. Conventions
| Convention | Rule |
|---|---|
| Primary keys | `id` BIGINT unsigned auto-increment (internal, permanent). Never reused. |
| Business numbers | Separate `*_no` column (e.g. `order_no = FF/ORD/2026-27/00001`) from `NumberSeriesService`, **unique** index |
| Foreign keys | Always declared; `restrictOnDelete` for business links, `cascadeOnDelete` only for owned child rows of config records |
| Userstamps | `created_by`, `updated_by` (nullable FK users) via `$table->userstamps()` macro; auto-filled by `HasUserstamps` trait |
| Timestamps | `created_at`, `updated_at` on every table; history tables are insert-only (`created_at` only) |
| Deletion | Masters: `is_active` deactivation (no soft delete). Transactions: never hard-deleted; cancellations are status + reason. Soft deletes used only where a UI "archive" is required |
| Money | `DECIMAL(14,2)`; never float |
| Status | FK to `workflow_stages` (configurable) **plus** semantic flags on the stage; never an ENUM of business statuses |
| JSON | only for audit snapshots, settings values and extensible metadata — never for data that is filtered/reported |
| Partial uniqueness | e.g. "one *active* allocation per unit": nullable generated column `active_key` + unique index (NULLs are not considered equal on MySQL & SQLite) |

## 2. Phase 1 schema (implemented)

### Organisation
```
companies            id, code UQ, name, legal_name, gstin, pan, address, phone, email,
                     financial_year_start_month (default 4), userstamps, timestamps
branches             id, company_id FK, code UQ, name, address, district_id FK?, phone, email,
                     is_active, userstamps, timestamps
departments          id, code UQ, name, description, is_operational, sort_order, is_active, userstamps, timestamps
designations         id, code UQ, name, is_active, userstamps, timestamps
employees            id, employee_code UQ, user_id FK UQ? , name, mobile, email, designation_id FK?,
                     reports_to_id FK(employees)?, date_of_joining, is_active, userstamps, timestamps
branch_employee      employee_id FK, branch_id FK, is_primary        PK(employee_id, branch_id)
department_employee  employee_id FK, department_id FK, is_primary, is_head   PK(employee_id, department_id)
```
An employee may belong to many departments and branches (ORG-02). `reports_to_id` forms sales teams.

### Identity & access
```
users                + mobile UQ?, is_active, must_change_password, password_changed_at,
                       failed_login_count, locked_until, last_login_at, last_login_ip, current_branch_id FK?
roles / permissions / model_has_roles / model_has_permissions / role_has_permissions   (spatie)
login_histories      id, user_id FK?, identifier, event (success|failed|locked|logout), ip, user_agent, created_at
personal_access_tokens (Sanctum)
```

### Geography (GEO-01)
```
states     id, code UQ, name UQ
districts  id, state_id FK, code UQ?, name, is_active     UQ(state_id, name)
tehsils    id, district_id FK, code UQ?, name, is_active  UQ(district_id, name)
villages   id, tehsil_id FK, code UQ?, name, pin_code, is_active   UQ(tehsil_id, name)
```
`UQ(tehsil_id, name)` + tehsil→district enforces the SRS rule *District + Tehsil + Village Name unique*.

### Cross-cutting
```
audit_logs        id, user_id FK?, event, module, auditable_type, auditable_id, old_values JSON, new_values JSON,
                  reason, ip_address, user_agent, url, request_id, created_at
                  IDX(auditable_type, auditable_id), IDX(module, created_at), IDX(user_id, created_at)
                  -- append-only: the model throws on update/delete
number_series     id, entity UQ, name, prefix, format, padding, reset_policy (never|financial_year),
                  per_branch, is_active, userstamps, timestamps
number_series_counters  id, number_series_id FK, scope_key, next_value, timestamps   UQ(number_series_id, scope_key)
system_settings   id, group, key UQ, value JSON, description, userstamps, timestamps
```
Number generation: `SELECT … FOR UPDATE` on the counter row inside the caller's transaction; the
consuming table's unique index on `*_no` is the final guard.

## 3. Planned schema by phase (outline)

**Phase 2 — Workflow & CRM (implemented)** — plus `brands`, `products`, `product_variants`, `lookup_values`,
`territory_assignments` (unique `primary_scope` = one active primary per area), `import_batches`.
`workflow_definitions(code UQ, module, name)`, `workflow_stages(definition_id, code, name, sequence, color,
is_initial, is_final, is_completion, is_hold, is_rejection, blocks_delivery, requires_remark,
requires_document, requires_followup, requires_approval, sla_hours, is_active)` UQ(definition_id, code),
`workflow_transitions(definition_id, from_stage_id?, to_stage_id, allowed_roles JSON→ pivot, requires_approval,
effective_from, effective_to, is_active)`, `workflow_status_histories(subject morph, from_stage_id, to_stage_id,
remarks, user_id, created_at)`.
`farmers`, `enquiries`, `enquiry_requirements`, `exchange_tractors`, `enquiry_attachments`,
`enquiry_assignments`, `call_attempts`, `follow_ups`, `territory_assignments`, `reopen_requests`.

**Phase 3 — Sales (implemented)** — as outlined plus `discount_limits`, `product_prices` (effective-dated), `deal_approvals` (immutable snapshots), `customers.possible_duplicate_of_id`, `enquiries.customer_id`, `workflow_stages.is_system`. Planned list below kept for reference: `categories`, `brands`, `products`, `product_variants`, `price_lists`, `price_list_items`,
`quotations`, `quotation_items` (versioned: `quotation_no + version` UQ), `customers` (UQ farmer_id),
`customer_addresses`, `customer_contacts`, `deals`, `deal_items`, `deal_approvals`.

**Phase 4 — Orders & Documents (implemented)**: `orders` (UQ order_no, UQ deal_id; frozen commercial columns +
`deal_snapshot` JSON; cancellation fields), `order_items`, `fulfilment_task_types` (UQ code; department, condition,
blocks_delivery, update_permission), `fulfilments` (UQ fulfilment_no, UQ order_id), `fulfilment_tasks`
(UQ fulfilment_id + fulfilment_task_type_id; requirement_state, stage_id, responsible), `document_types` (master §187),
`documents` (UQ document_no; customer/deal/order/department context, status, encrypted reference_no, expiry),
`document_versions` (UQ document_id + version; immutable), `document_verifications` (immutable),
`document_requirement_rules` (UQ document_type_id + department_id; optional task type), `document_requirements`
(UQ order_id + document_type_id + department_id; `document_id` is the reuse link — no separate link tables),
`document_access_logs` (append-only).

**Phase 5 — Fulfilment**: finance (`finance_files` UQ order_id, `financers`, `financer_contacts`,
`finance_followups`, `finance_queries`, …); accounts (`account_files`, `payments`, `receipts`,
`payment_verifications`, `payment_reversals`, `refund_requests`, `refund_transactions`);
inventory (`stock_locations`, `inventory_items` UQ chassis_no, UQ engine_no, `stock_inwards`,
`stock_inward_items`, `stock_movements`, `stock_reservations`, `stock_allocations` with
`active_key` generated column UQ → no double allocation, `allocation_histories`).

**Phase 6** RTO / Insurance / PDI tables per SRS §132, §156, §181.

**Phase 7** `readiness_rules`, `readiness_results`, `readiness_blockers`, `readiness_evaluation_logs`,
`waivers`, `waiver_approvals`, `waiver_extensions`, `waiver_escalations`, `business_rules`.

**Phase 8** `deliveries` (UQ order_id), `delivery_schedules`, `delivery_checklist_items`,
`delivery_checklist_results`, `delivery_image_types`, `delivery_images`, `delivery_handovers`,
`delivery_signatures`.

**Phase 9** `sales_targets`, `sales_target_achievements` (**UQ(delivery_id, event)** → idempotent credit),
`sales_target_adjustments`, `management_alerts`, `kpi_definitions`.

**Phase 10** `notifications` (Laravel), `notification_rules`, `escalation_rules`, `import_batches`,
`import_rows`, `idempotency_keys`.
