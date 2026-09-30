<?php

/*
|--------------------------------------------------------------------------
| Default Role Matrix
|--------------------------------------------------------------------------
|
| Default permissions per role, seeded on install. Roles remain editable in
| Administration → Roles; re-running the seeder only adds missing roles and
| never strips permissions an administrator granted later.
|
| Patterns: "module.*" grants every action of a module, "*" grants all.
| "Super Admin" bypasses checks via Gate::before and needs no list.
|
*/

$departmentEmployee = fn (string $module, array $actions): array => array_merge(
    ['dashboard.view', 'documents.view', 'documents.upload', 'waivers.view', 'waivers.request', 'readiness.view', 'customers.view', 'orders.view'],
    array_map(fn (string $action): string => "{$module}.{$action}", $actions),
);

$departmentManager = fn (string $module, string $report): array => array_merge(
    $departmentEmployee($module, ['*']),
    ['documents.verify', "reports.{$report}", 'reports.export'],
);

return [
    'Super Admin' => [],

    'Owner' => ['*'],

    'Sales Manager' => [
        'dashboard.view', 'farmers.*', 'enquiries.*', 'telecaller.queue', 'follow_ups.*', 'pipeline.*', 'territory.*',
        'customers.*', 'products.view', 'quotations.*', 'deals.*', 'orders.view', 'orders.create',
        'documents.view', 'documents.upload', 'documents.dashboard',
        'waivers.view', 'waivers.request', 'waivers.approve', 'waivers.approve_extension', 'readiness.view', 'delivery.view', 'delivery.complete',
        'targets.view_own', 'targets.view_team', 'targets.manage', 'reports.sales', 'reports.customers', 'reports.delivery', 'reports.documents', 'reports.export',
        'employees.view',
    ],

    'Salesman' => [
        'dashboard.view', 'farmers.view', 'farmers.create', 'farmers.update',
        'enquiries.view_own', 'enquiries.create', 'enquiries.update', 'enquiries.reopen_request',
        'follow_ups.*', 'pipeline.view', 'pipeline.move', 'customers.view', 'products.view',
        'quotations.view', 'quotations.create', 'deals.view', 'deals.create', 'deals.update', 'orders.view',
        'documents.view', 'documents.upload', 'readiness.view', 'delivery.view', 'targets.view_own',
    ],

    'Telecaller' => [
        'dashboard.view', 'farmers.view', 'farmers.create', 'enquiries.view_all', 'enquiries.create', 'enquiries.reopen_request',
        'telecaller.*', 'follow_ups.*',
    ],

    'Retail Employee' => $departmentEmployee('finance', ['view', 'update']),
    'Retail Manager' => $departmentManager('finance', 'finance'),
    'Accounts Employee' => $departmentEmployee('accounts', ['view', 'record_payment', 'verify_payment', 'clear_payment']),
    'Accounts Manager' => $departmentManager('accounts', 'accounts'),
    'Inventory Employee' => $departmentEmployee('inventory', ['view', 'inward', 'allocate']),
    'Inventory Manager' => [...$departmentManager('inventory', 'inventory'), 'products.*'],
    'RTO Employee' => $departmentEmployee('rto', ['view', 'update']),
    'RTO Manager' => $departmentManager('rto', 'rto'),
    'Insurance Employee' => $departmentEmployee('insurance', ['view', 'update']),
    'Insurance Manager' => $departmentManager('insurance', 'insurance'),
    'PDI Employee' => $departmentEmployee('pdi', ['view', 'inspect']),
    'PDI Manager' => $departmentManager('pdi', 'pdi'),
    'Delivery Employee' => $departmentEmployee('delivery', ['view', 'schedule', 'execute', 'complete']),
    'Delivery Manager' => $departmentManager('delivery', 'delivery'),
];
