<?php

/*
|--------------------------------------------------------------------------
| Permission Registry
|--------------------------------------------------------------------------
|
| Single source of truth for every permission in the ERP. Permission names
| are "{module}.{action}". The seeder syncs this list into the database and
| docs/RBAC_MATRIX.md documents the defaults. Add new modules here; never
| check a permission in code that is not declared in this file.
|
*/

return [
    'dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],

    // Administration
    'users' => ['label' => 'Users', 'actions' => ['view', 'create', 'update', 'deactivate', 'reset_password', 'assign_roles']],
    'employees' => ['label' => 'Employees', 'actions' => ['view', 'create', 'update', 'deactivate']],
    'roles' => ['label' => 'Roles & Permissions', 'actions' => ['view', 'manage']],
    'branches' => ['label' => 'Branches', 'actions' => ['view', 'manage']],
    'departments' => ['label' => 'Departments & Designations', 'actions' => ['view', 'manage']],
    'geography' => ['label' => 'Geography', 'actions' => ['view', 'manage', 'import']],
    'settings' => ['label' => 'System Settings', 'actions' => ['view', 'manage']],
    'number_series' => ['label' => 'Number Series', 'actions' => ['manage']],
    'workflow' => ['label' => 'Workflow Configuration', 'actions' => ['view', 'configure']],
    'audit' => ['label' => 'Audit Logs', 'actions' => ['view']],

    // CRM
    'farmers' => ['label' => 'Farmers', 'actions' => ['view', 'create', 'update']],
    'enquiries' => ['label' => 'Enquiries', 'actions' => ['view_own', 'view_team', 'view_all', 'create', 'update', 'assign', 'reopen_request', 'reopen']],
    'telecaller' => ['label' => 'Telecaller Queue', 'actions' => ['queue', 'claim', 'validate']],
    'follow_ups' => ['label' => 'Follow-ups', 'actions' => ['view', 'manage']],
    'pipeline' => ['label' => 'Sales Pipeline', 'actions' => ['view', 'move']],
    'territory' => ['label' => 'Territory Assignment', 'actions' => ['view', 'assign']],

    // Sales
    'customers' => ['label' => 'Customers', 'actions' => ['view', 'create', 'update', 'merge']],
    'products' => ['label' => 'Products & Pricing', 'actions' => ['view', 'manage']],
    'quotations' => ['label' => 'Quotations', 'actions' => ['view', 'create', 'approve_discount']],
    'deals' => ['label' => 'Deals', 'actions' => ['view', 'create', 'update', 'approve']],
    'orders' => ['label' => 'Orders', 'actions' => ['view', 'create', 'cancel']],

    // Fulfilment
    'documents' => ['label' => 'Documents', 'actions' => ['view', 'upload', 'verify', 'view_sensitive', 'configure', 'dashboard']],
    'finance' => ['label' => 'Retail & Finance', 'actions' => ['view', 'update', 'assign', 'configure']],
    'accounts' => ['label' => 'Accounts', 'actions' => ['view', 'record_payment', 'verify_payment', 'clear_payment', 'reverse', 'approve_refund']],
    'inventory' => ['label' => 'Inventory', 'actions' => ['view', 'inward', 'allocate', 'reallocate', 'transfer', 'configure']],
    'rto' => ['label' => 'RTO', 'actions' => ['view', 'update', 'assign']],
    'insurance' => ['label' => 'Insurance', 'actions' => ['view', 'update', 'assign']],
    'pdi' => ['label' => 'PDI / Workshop', 'actions' => ['view', 'inspect', 'approve']],
    'waivers' => ['label' => 'Waivers', 'actions' => ['view', 'request', 'approve', 'approve_critical', 'approve_extension']],
    'readiness' => ['label' => 'Delivery Readiness', 'actions' => ['view', 'override']],
    'delivery' => ['label' => 'Delivery', 'actions' => ['view', 'schedule', 'execute', 'complete', 'reverse']],

    // Management
    'targets' => ['label' => 'Sales Targets', 'actions' => ['view_own', 'view_team', 'view_all', 'manage', 'adjust']],
    'reports' => ['label' => 'Reports', 'actions' => ['sales', 'customers', 'finance', 'accounts', 'inventory', 'rto', 'insurance', 'pdi', 'delivery', 'documents', 'export']],
];
