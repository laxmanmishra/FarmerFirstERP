# Farmer First ERP — API Specification

Base: `/api/v1` · JSON only · Auth: Sanctum bearer token (`Authorization: Bearer …`).
The API calls the same Actions/Policies as the web UI — no separate business logic.

## Envelope
```json
{ "success": true, "message": "Order created successfully", "data": {}, "meta": {}, "request_id": "…" }
```
Errors:
```json
{ "success": false, "message": "The given data was invalid.", "type": "validation_error",
  "errors": { "mobile": ["The mobile has already been taken."] }, "request_id": "…" }
```
| type | HTTP | When |
|---|---|---|
| `validation_error` | 422 | Form Request failure |
| `business_rule_error` | 422 | `BusinessRuleException` (e.g. unit already allocated) |
| `permission_error` | 403 | policy/permission failure |
| `unauthenticated` | 401 | missing/invalid token |
| `not_found` | 404 | model not found (no internal details) |
| `rate_limited` | 429 | throttle |
| `system_error` | 500 | anything else; message generic in production, correlate with `request_id` |

Every response carries header `X-Request-Id`.

## Conventions
- Pagination: `?page=1&per_page=25` (max 100) → `meta.pagination {current_page, per_page, total, last_page}`.
- Filtering: `?filter[status]=VALID&filter[branch_id]=2`; search `?search=9876543210`; sorting `?sort=-created_at`.
- Idempotency (critical POSTs, Phase 10): `Idempotency-Key` header; replay returns the original response.
- Throttle: `api` 120/min per user; `auth` 5/min per IP+identifier.

## Phase 1 endpoints (implemented)
| Method | Path | Description |
|---|---|---|
| POST | `/api/v1/auth/token` | `{email, password, device_name}` → token (same lockout rules as web) |
| POST | `/api/v1/auth/logout` | revoke current token |
| GET | `/api/v1/me` | current user, roles, permissions, employee, branches |
| GET | `/api/v1/branches` | permitted branches |
| GET | `/api/v1/geography/districts` | `?filter[state_id]` |
| GET | `/api/v1/geography/districts/{id}/tehsils` | |
| GET | `/api/v1/geography/tehsils/{id}/villages` | |

## Phase 2 endpoints (implemented)
| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/farmers` | `?search=` name, mobile or farmer no (branch-scoped) |
| POST | `/api/v1/farmers` | create; send `confirm_not_duplicate=true` after a `duplicate_farmer` 422 (matches in `context.farmer_ids`) |
| GET | `/api/v1/farmers/{id}` | farmer with village / tehsil / district |
| GET | `/api/v1/enquiries` | visible enquiries; `filter[view]=open\|validation\|pipeline`, `filter[farmer_id]`, `search` |
| POST | `/api/v1/enquiries/duplicates` | potential duplicates before submitting (SRS §9) |
| POST | `/api/v1/enquiries` | create; `duplicate_override_reason` required after a `duplicate_enquiry` 422 (`context.enquiry_ids`) |
| GET | `/api/v1/enquiries/{id}` | detail (404 when not visible) |
| GET | `/api/v1/follow-ups` | `filter[status]=today\|overdue\|upcoming\|all_pending` |
| POST | `/api/v1/follow-ups/{id}/complete` | `{ "outcome": "…" }` |

Business-rule errors carry machine-readable `context` (e.g. duplicate ids) alongside `type: business_rule_error`.

## Planned (per phase)
`farmers`, `enquiries` (+ `duplicates` check endpoint), `telecaller/queue`, `telecaller/{enquiry}/claim`,
`call-attempts`, `follow-ups`, `pipeline`, `customers`, `quotations`, `deals`, `deals/{id}/approve`, `orders`,
`documents` (multipart upload, authorised download), `finance-files`, `payments`, `inventory/units`,
`allocations`, `rto-files`, `insurance-files`, `pdi-files`, `waivers`, `orders/{id}/readiness`, `deliveries`,
`deliveries/{id}/images`, `targets`, `achievements`, `search`, `notifications`.
