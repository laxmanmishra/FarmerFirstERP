<?php

/*
|--------------------------------------------------------------------------
| Sidebar Navigation
|--------------------------------------------------------------------------
|
| Every item declares the route it opens and the permission(s) needed (any
| of). Items are hidden until their route exists, so modules appear in the
| menu automatically as they ship. Sections without visible items are hidden.
|
*/

return [
    ['label' => null, 'items' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
    ]],

    ['label' => 'CRM', 'icon' => 'users', 'items' => [
        ['label' => 'Farmers', 'route' => 'crm.farmers.index', 'icon' => 'user', 'permission' => 'farmers.view'],
        ['label' => 'Enquiries', 'route' => 'crm.enquiries.index', 'icon' => 'inbox', 'permission' => ['enquiries.view_own', 'enquiries.view_team', 'enquiries.view_all']],
        ['label' => 'Telecaller', 'route' => 'crm.telecaller.index', 'icon' => 'phone', 'permission' => 'telecaller.queue'],
        ['label' => 'Follow-ups', 'route' => 'crm.follow-ups.index', 'icon' => 'calendar', 'permission' => 'follow_ups.view'],
        ['label' => 'Sales Pipeline', 'route' => 'crm.pipeline.index', 'icon' => 'columns', 'permission' => 'pipeline.view'],
    ]],

    ['label' => 'Sales', 'icon' => 'briefcase', 'items' => [
        ['label' => 'Customers', 'route' => 'sales.customers.index', 'icon' => 'identification', 'permission' => 'customers.view'],
        ['label' => 'Quotations', 'route' => 'sales.quotations.index', 'icon' => 'document-text', 'permission' => 'quotations.view'],
        ['label' => 'Deals', 'route' => 'sales.deals.index', 'icon' => 'handshake', 'permission' => 'deals.view'],
        ['label' => 'Deal Approvals', 'route' => 'sales.deal-approvals.index', 'icon' => 'check-badge', 'permission' => 'deals.approve'],
        ['label' => 'Orders', 'route' => 'sales.orders.index', 'icon' => 'clipboard', 'permission' => 'orders.view'],
    ]],

    ['label' => 'Fulfilment', 'icon' => 'truck', 'items' => [
        ['label' => 'Documents', 'route' => 'fulfilment.documents.index', 'icon' => 'folder', 'permission' => 'documents.view'],
        ['label' => 'Finance', 'route' => 'fulfilment.finance.index', 'icon' => 'banknotes', 'permission' => 'finance.view'],
        ['label' => 'Accounts', 'route' => 'fulfilment.accounts.index', 'icon' => 'calculator', 'permission' => 'accounts.view'],
        ['label' => 'Inventory', 'route' => 'fulfilment.inventory.index', 'icon' => 'cube', 'permission' => 'inventory.view'],
        ['label' => 'RTO', 'route' => 'fulfilment.rto.index', 'icon' => 'building', 'permission' => 'rto.view'],
        ['label' => 'Insurance', 'route' => 'fulfilment.insurance.index', 'icon' => 'shield', 'permission' => 'insurance.view'],
        ['label' => 'PDI', 'route' => 'fulfilment.pdi.index', 'icon' => 'wrench', 'permission' => 'pdi.view'],
        ['label' => 'Waivers', 'route' => 'fulfilment.waivers.index', 'icon' => 'flag', 'permission' => 'waivers.view'],
        ['label' => 'Delivery Readiness', 'route' => 'fulfilment.readiness.index', 'icon' => 'signal', 'permission' => 'readiness.view'],
        ['label' => 'Delivery', 'route' => 'fulfilment.delivery.index', 'icon' => 'truck', 'permission' => 'delivery.view'],
    ]],

    ['label' => 'Targets', 'icon' => 'chart', 'items' => [
        ['label' => 'Sales Targets', 'route' => 'targets.index', 'icon' => 'target', 'permission' => ['targets.view_own', 'targets.view_team', 'targets.view_all']],
        ['label' => 'Achievements', 'route' => 'targets.achievements', 'icon' => 'trophy', 'permission' => ['targets.view_own', 'targets.view_team', 'targets.view_all']],
    ]],

    ['label' => 'Reports', 'icon' => 'chart', 'items' => [
        ['label' => 'Sales Reports', 'route' => 'reports.sales', 'icon' => 'chart', 'permission' => 'reports.sales'],
        ['label' => 'Customer Reports', 'route' => 'reports.customers', 'icon' => 'chart', 'permission' => 'reports.customers'],
        ['label' => 'Finance Reports', 'route' => 'reports.finance', 'icon' => 'chart', 'permission' => 'reports.finance'],
        ['label' => 'Accounts Reports', 'route' => 'reports.accounts', 'icon' => 'chart', 'permission' => 'reports.accounts'],
        ['label' => 'Inventory Reports', 'route' => 'reports.inventory', 'icon' => 'chart', 'permission' => 'reports.inventory'],
        ['label' => 'RTO Reports', 'route' => 'reports.rto', 'icon' => 'chart', 'permission' => 'reports.rto'],
        ['label' => 'Insurance Reports', 'route' => 'reports.insurance', 'icon' => 'chart', 'permission' => 'reports.insurance'],
        ['label' => 'PDI Reports', 'route' => 'reports.pdi', 'icon' => 'chart', 'permission' => 'reports.pdi'],
        ['label' => 'Delivery Reports', 'route' => 'reports.delivery', 'icon' => 'chart', 'permission' => 'reports.delivery'],
        ['label' => 'Document Reports', 'route' => 'reports.documents', 'icon' => 'chart', 'permission' => 'reports.documents'],
    ]],

    ['label' => 'Administration', 'icon' => 'cog', 'items' => [
        ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user-circle', 'permission' => 'users.view'],
        ['label' => 'Employees', 'route' => 'admin.employees.index', 'icon' => 'users', 'permission' => 'employees.view'],
        ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'icon' => 'key', 'permission' => 'roles.view'],
        ['label' => 'Departments', 'route' => 'admin.departments.index', 'icon' => 'squares', 'permission' => 'departments.view'],
        ['label' => 'Branches', 'route' => 'admin.branches.index', 'icon' => 'building', 'permission' => 'branches.view'],
        ['label' => 'Geography', 'route' => 'admin.geography.index', 'icon' => 'map', 'permission' => 'geography.view'],
        ['label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'cube', 'permission' => 'products.manage'],
        ['label' => 'Workflow Configuration', 'route' => 'admin.workflows.index', 'icon' => 'flow', 'permission' => 'workflow.view'],
        ['label' => 'Document Configuration', 'route' => 'admin.document-types.index', 'icon' => 'folder', 'permission' => 'documents.configure'],
        ['label' => 'System Settings', 'route' => 'admin.settings.index', 'icon' => 'cog', 'permission' => ['settings.view', 'number_series.manage']],
        ['label' => 'Audit Logs', 'route' => 'admin.audit-logs.index', 'icon' => 'shield', 'permission' => 'audit.view'],
    ]],
];
