# Farmer First ERP — UI/UX Specification

## 1. Principles
Commercial ERP feel: dense but calm, data-first, every number clickable, every blocker actionable,
mobile-usable for field staff. Server-rendered (Livewire 4) with Alpine for local interactions.

## 2. Design tokens (`resources/css/app.css`, Tailwind 4 `@theme`)
- Brand: deep green `--color-brand-*` (agriculture), accent amber for attention states.
- Neutrals: slate. Semantic: success (emerald), warning (amber), danger (rose), info (sky).
- Typography: Inter (system fallback), 14px base in tables, 13px meta text; tabular numerals for figures.
- Radius: `lg` (8px) cards/inputs, `full` badges. Elevation: 1 level for cards, 2 for drawers/modals.
- Dark mode: not in Phase 1 (tokens are structured so it can be added).

## 3. App shell
- **Sidebar** (collapsible, 16rem; off-canvas on < lg): grouped sections exactly as the master prompt §5,
  driven by `config/erp/navigation.php`; items render only if the user holds the item's permission **and**
  the route exists (unbuilt modules stay hidden until shipped).
- **Top bar**: global search (Phase 10 full; Phase 1 placeholder with keyboard shortcut `/`), pending tasks,
  notifications bell with unread count, branch selector (permitted branches), user menu (profile, change password, logout).
- Breadcrumbs + page header (title, subtitle, primary/secondary actions).

## 4. Component library (`resources/views/components/ui/`)
| Component | Notes |
|---|---|
| `page-header`, `breadcrumbs` | title, description, actions slot |
| `card`, `kpi-card` | KPI: label, value, delta/trend, href (drill-down), tone |
| `button` | variants primary/secondary/ghost/danger; sizes; loading state via `wire:loading` |
| `input`, `select`, `textarea`, `checkbox`, `field` | label, hint, error from `$errors`, required marker |
| `badge`, `status-badge` | tone from stage colour token |
| `table` (+ Livewire `WithDataTable` trait) | search, sortable headers, filters slot, per-page, pagination, empty state, skeleton rows, bulk-select |
| `modal`, `drawer`, `confirm-dialog` | Alpine + `wire:model` entangled open state, focus trap, Esc closes |
| `empty-state`, `error-state`, `skeleton` | |
| `toast` | global stack, dispatched from Livewire `$this->dispatch('toast', …)` |
| `timeline`, `stepper`, `progress` | Phase 2+ |
| `kanban` | Phase 3 (pipeline; `wire:sort`) |
| `file-uploader`, `document-card` | Phase 4 |

## 5. Patterns
- **List screens**: header → filter bar (search + filters + saved filters later) → table → pagination.
  Row click opens show/360 page; quick edit in a drawer.
- **Forms**: drawer for masters, full page for transactional documents; inline validation on blur;
  submit disabled while saving; toast on success; business-rule errors shown as a banner, not a field error.
- **Destructive/irreversible** actions (deactivate, reject, reverse): confirm dialog with mandatory reason when the SRS requires one.
- **Status**: always a badge with the configured colour; history available from the timeline.
- **Blockers**: grouped card per department: requirement, current status, responsible, age, action button.
- **Empty states** explain *why* empty and offer the next action.
- **Accessibility**: labels on every input, focus rings, 4.5:1 contrast, keyboard operable dialogs.
- **Mobile**: tables collapse to stacked cards < md; primary actions become full-width; touch targets ≥ 44px.

## 6. Phase 1 screens
Login · Change password · Dashboard (welcome + role-aware placeholders) · Users · Employees · Roles & permission matrix ·
Branches · Departments · Designations · Geography (States/Districts/Tehsils/Villages tabs) · Company & number series settings · Audit log.
