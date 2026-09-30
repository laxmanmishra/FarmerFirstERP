# Farmer First ERP — Document Requirements

## 1. Concepts (kept separate — INV-09)
| Concept | Table | Question it answers |
|---|---|---|
| Document type | `document_types` | What kind of document is this and how does it behave? |
| Document | `documents` + `document_versions` | Does a file exist, which version, verified?, expired? |
| Requirement | `document_requirements` | Does this order/department *need* this type, is it satisfied, who is responsible? |
| Link | `document_requirement_links` / `document_links` | Which physical document satisfies which requirement (reuse without copying) |

## 2. Document Type Master fields `[§187]`
`code`, `name`, `category`, `level` (customer | deal | order | unit | department), `is_reusable`,
`expiry_applicable`, `verification_required`, `verification_permission`, `sensitivity` (normal | sensitive),
`allowed_mimes`, `max_size_kb`, `is_active`, `sort_order`.

## 3. Initial seed
| Code | Name | Level | Reusable | Expiry | Verify | Default owner dept |
|---|---|---|---|---|---|---|
| AADHAAR | Aadhaar Card | customer | ✔ | – | ✔ | Sales |
| PAN | PAN Card | customer | ✔ | – | ✔ | Retail & Finance |
| ADDRESS_PROOF | Address Proof | customer | ✔ | – | ✔ | Sales |
| PHOTO | Customer Photograph | customer | ✔ | – | – | Sales |
| LAND_DOC | Land Document | customer | ✔ | – | ✔ | Retail & Finance |
| BANK_DOC | Bank Statement / Passbook | customer | ✔ | – | ✔ | Retail & Finance |
| FIN_APPLICATION | Finance Application | order | – | – | ✔ | Retail & Finance |
| CREDIT_APPROVAL | Credit Approval Letter | order | – | – | ✔ | Retail & Finance |
| FIN_QUOTATION | Financer Quotation | order | – | – | – | Retail & Finance |
| DO | Delivery Order (DO) | order | – | ✔ | ✔ | Retail & Finance |
| PAYMENT_PROOF | Payment Proof | department | – | – | ✔ | Accounts |
| INVOICE | Invoice / Retail Invoice | order | – | – | – | Accounts |
| INS_PROPOSAL | Insurance Proposal | order | – | – | – | Insurance |
| INS_POLICY | Insurance Policy | unit | ✔ | ✔ | ✔ | Insurance |
| RTO_APPLICATION | RTO Application | order | – | – | ✔ | RTO |
| RC | Registration Certificate | unit | ✔ | – | ✔ | RTO |
| PDI_REPORT | PDI Checklist Report | unit | – | – | ✔ | PDI |
| HANDOVER_FORM | Customer Handover Form | order | – | – | – | Delivery |
| DELIVERY_RECEIPT | Delivery Receipt | order | – | – | – | Delivery |

## 4. Requirement rules
`document_requirement_rules`: `(document_type, department, fulfilment_task_type?, blocks_delivery, due_offset_days, is_active)`,
one rule per type × department. Without a task type the document is required on every order; with one it follows the
task's requirement state, and the task type's `condition` reads the deal flags (`finance_required`, `rto_required`,
`insurance_required`, `pdi_required`, `always`). Conditions on product category, order type, customer type and branch
are future extensions of the task condition.

Examples:
- finance_required = true → PAN, LAND_DOC, BANK_DOC, FIN_APPLICATION, CREDIT_APPROVAL, DO (DO blocks delivery).
- rto_required = true → AADHAAR, ADDRESS_PROOF, INVOICE, INS_POLICY, RTO_APPLICATION.
- always → AADHAAR, HANDOVER_FORM (blocks completion), INVOICE.

## 5. Satisfaction algorithm (`DocumentRequirementService`) `[§199, §211]`
1. On order creation / context change: generate requirements from active rules (idempotent: one requirement per order × type × department).
2. For each requirement: search repository at its level (customer → deal → order → unit) for a document of that type
   that is *verified if required*, *not expired*, *latest version*.
3. Found → offer "Use Existing" (explicit user action creates a link; auto-link is a configurable setting).
4. Satisfied ⇔ linked document exists ∧ verification ok ∧ not expired. Waived and Not Required are tracked separately and never counted as completed.
5. Version replacement: new version must be verified again; requirements linked to the document re-evaluate.
6. Expiry job (daily) marks expired documents and re-evaluates dependent requirements → readiness recalculation.

## 6. Documentation dashboard `[§205–217]`
Counters per type × state (Pending, Uploaded, Verification Pending, Verified, Rejected, Expired), per department,
per employee; ageing buckets 0–1, 2–3, 4–7, 8–15, 15+ days; Documents Blocking Delivery view. All counters are
live queries on `document_requirements` and drill down to the exact rows.
