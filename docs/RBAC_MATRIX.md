# Farmer First ERP — RBAC Matrix

Source of truth: `config/erp/permissions.php` (registry) and `config/erp/roles.php` (defaults).
Seeder: `Database\Seeders\RolesAndPermissionsSeeder`. Roles and grants are editable at
Administration → Roles; the seeder is additive and never revokes admin-made grants.

## 1. Model
- Permission = `{module}.{action}` (spatie/laravel-permission, guard `web`; API uses the same user + permissions via Sanctum).
- A user holds **any number of roles**; effective permissions = union.
- **Super Admin** bypasses all checks (`Gate::before`). **Owner** is granted every permission explicitly (visible in the matrix).
- Record-level scope is applied in queries, not by permission alone:
  - `*.view_own` → records where the user's employee is the owner/assignee
  - `*.view_team` → own + direct/indirect reports (`employees.reports_to_id`)
  - `*.view_all` → all records in permitted branches
  - Branch scope: employee ↔ branches pivot; Super Admin/Owner see all branches.
- Waiver **approval** is a separate permission from task update `[§32]`; `waivers.approve_critical` is Owner-only by default.
- Separation of duties: an approver cannot approve their own request (reversal/refund/waiver/discount) — enforced in the Action, not by permission.
- External parties (financers, insurers, RTO agents) are never users `[INV-08]`.
- Orders are visible branch-wide to department staff and approvers (`Order::FULFILMENT_WIDE_PERMISSIONS`); salesmen see
  their own (or team) orders. A fulfilment task is worked only by holders of its type's `update_permission`; a document
  is verified only by holders of its type's `verification_permission`, never by the uploader of that version.

## 2. Default matrix (summary)
Legend: ● full module · ◐ partial · ○ view · – none

| Module | Owner | Sales Mgr | Salesman | Telecaller | Dept Employee* | Dept Manager* |
|---|---|---|---|---|---|---|
| Administration (users, roles, branches, geography, settings, audit) | ● | employees ○ | – | – | – | – |
| Farmers | ● | ● | ◐ create/update | ◐ create | – | – |
| Enquiries | ● | ● all + reopen | ◐ own + reopen request | ◐ all + reopen request | – | – |
| Telecaller queue | ● | ○ | – | ● | – | – |
| Pipeline / Follow-ups | ● | ● | ◐ move own | follow-ups ● | – | – |
| Customers / Quotations / Deals | ● | ● incl. approve | ◐ create | – | customers ○ | customers ○ |
| Orders | ● | ◐ view/create | ○ | – | ○ | ○ |
| Documents | ● | ◐ upload, verify, sensitive, dashboard | ◐ upload | – | ◐ upload | ◐ + verify, sensitive |
| Own department (Finance / Accounts / Inventory / RTO / Insurance / PDI / Delivery) | ● | delivery ◐ complete | – | – | ◐ operational actions | ● |
| Waivers | ● incl. critical | ◐ request/approve/extension | – | – | ◐ request | ◐ request |
| Readiness | ● incl. override | ○ | ○ | – | ○ | ○ |
| Targets | ● | ◐ team + manage | ◐ own | – | – | – |
| Reports | ● | ◐ sales/customers/delivery/documents | – | – | – | ◐ own dept + export |

\* Department roles: Retail (finance), Accounts, Inventory, RTO, Insurance, PDI, Delivery — each
with an *Employee* and *Manager* role. Accounts Employee additionally holds `accounts.clear_payment`;
reversals and refund approvals are manager-only. Inventory Manager also manages products.

## 3. Enforcement points
1. Route middleware `auth`, `active` (deactivated users are logged out immediately).
2. Livewire components call `$this->authorize()` in `mount()` **and** in every mutating action.
3. Policies per aggregate (`UserPolicy`, `RolePolicy`, `BranchPolicy`, …) delegate to permissions + scope.
4. Navigation items declare a permission; menus render only permitted items (`config/erp/navigation.php`).
5. API endpoints use the same policies.
6. Tests: every protected screen has an unauthorised-user test (`tests/Feature/Admin/*`).
