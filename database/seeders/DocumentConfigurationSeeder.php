<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DocumentRequirementRule;
use App\Models\DocumentType;
use App\Models\FulfilmentTaskType;
use Illuminate\Database\Seeder;

/**
 * Initial fulfilment tasks, Document Type Master and requirement rules
 * (docs/DOCUMENT_REQUIREMENTS.md). Additive: existing rows are never overwritten.
 *
 * Delivery-blocking defaults (open question 4.3, to confirm at sign-off):
 * Finance, Accounts, Inventory, Insurance and PDI block delivery; RTO and the
 * Delivery task itself do not.
 */
class DocumentConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->pluck('id', 'code');

        $tasks = [
            // code => [name, department, condition, blocks delivery, permission to work it, driven by]
            'FINANCE' => ['Retail & Finance', 'RETAIL_FINANCE', 'finance_required', true, 'finance.update', FulfilmentTaskType::DRIVEN_BY_FINANCE_FILE],
            'ACCOUNTS' => ['Accounts clearance', 'ACCOUNTS', 'always', true, 'accounts.clear_payment', FulfilmentTaskType::DRIVEN_BY_ACCOUNT_FILE],
            'INVENTORY' => ['Unit allocation', 'INVENTORY', 'always', true, 'inventory.allocate', FulfilmentTaskType::DRIVEN_BY_ALLOCATION],
            'RTO' => ['RTO registration', 'RTO', 'rto_required', false, 'rto.update', null],
            'INSURANCE' => ['Insurance', 'INSURANCE', 'insurance_required', true, 'insurance.update', null],
            'PDI' => ['PDI inspection', 'PDI', 'pdi_required', true, 'pdi.inspect', null],
            'DELIVERY' => ['Delivery', 'DELIVERY', 'always', false, 'delivery.execute', null],
        ];

        foreach (array_keys($tasks) as $index => $code) {
            [$name, $department, $condition, $blocks, $permission, $drivenBy] = $tasks[$code];
            FulfilmentTaskType::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'department_id' => $departments[$department],
                'condition' => $condition,
                'blocks_delivery' => $blocks,
                'update_permission' => $permission,
                'driven_by' => $drivenBy,
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        $types = [
            // code => [name, category, level, reusable, expiry, verify, sensitive, default department]
            'AADHAAR' => ['Aadhaar Card', 'identity', 'customer', true, false, true, true, 'SALES'],
            'PAN' => ['PAN Card', 'identity', 'customer', true, false, true, true, 'RETAIL_FINANCE'],
            'ADDRESS_PROOF' => ['Address Proof', 'address', 'customer', true, false, true, false, 'SALES'],
            'PHOTO' => ['Customer Photograph', 'identity', 'customer', true, false, false, false, 'SALES'],
            'LAND_DOC' => ['Land Document', 'finance', 'customer', true, false, true, true, 'RETAIL_FINANCE'],
            'BANK_DOC' => ['Bank Statement / Passbook', 'finance', 'customer', true, false, true, true, 'RETAIL_FINANCE'],
            'FIN_APPLICATION' => ['Finance Application', 'finance', 'order', false, false, true, false, 'RETAIL_FINANCE'],
            'CREDIT_APPROVAL' => ['Credit Approval Letter', 'finance', 'order', false, false, true, false, 'RETAIL_FINANCE'],
            'FIN_QUOTATION' => ['Financer Quotation', 'finance', 'order', false, false, false, false, 'RETAIL_FINANCE'],
            'DO' => ['Delivery Order (DO)', 'finance', 'order', false, true, true, false, 'RETAIL_FINANCE'],
            'PAYMENT_PROOF' => ['Payment Proof', 'payment', 'department', false, false, true, false, 'ACCOUNTS'],
            'INVOICE' => ['Invoice / Retail Invoice', 'sales', 'order', false, false, false, false, 'ACCOUNTS'],
            'INS_PROPOSAL' => ['Insurance Proposal', 'insurance', 'order', false, false, false, false, 'INSURANCE'],
            'INS_POLICY' => ['Insurance Policy', 'insurance', 'unit', true, true, true, false, 'INSURANCE'],
            'RTO_APPLICATION' => ['RTO Application', 'registration', 'order', false, false, true, false, 'RTO'],
            'RC' => ['Registration Certificate', 'registration', 'unit', true, false, true, false, 'RTO'],
            'PDI_REPORT' => ['PDI Checklist Report', 'inspection', 'unit', false, false, true, false, 'PDI'],
            'HANDOVER_FORM' => ['Customer Handover Form', 'delivery', 'order', false, false, false, false, 'DELIVERY'],
            'DELIVERY_RECEIPT' => ['Delivery Receipt', 'delivery', 'order', false, false, false, false, 'DELIVERY'],
        ];

        foreach (array_keys($types) as $index => $code) {
            [$name, $category, $level, $reusable, $expiry, $verify, $sensitive, $department] = $types[$code];
            DocumentType::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'level' => $level,
                'is_reusable' => $reusable,
                'expiry_applicable' => $expiry,
                'verification_required' => $verify,
                'sensitivity' => $sensitive ? 'sensitive' : 'normal',
                'default_department_id' => $departments[$department],
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        $typeIds = DocumentType::query()->pluck('id', 'code');
        $taskIds = FulfilmentTaskType::query()->pluck('id', 'code');

        $rules = [
            // [document type, department, task type (null = every order), blocks delivery, due days after booking]
            ['AADHAAR', 'SALES', null, true, 2],
            ['PHOTO', 'SALES', null, false, 3],
            ['PAN', 'RETAIL_FINANCE', 'FINANCE', false, 3],
            ['LAND_DOC', 'RETAIL_FINANCE', 'FINANCE', false, 5],
            ['BANK_DOC', 'RETAIL_FINANCE', 'FINANCE', false, 5],
            ['FIN_APPLICATION', 'RETAIL_FINANCE', 'FINANCE', false, 3],
            ['CREDIT_APPROVAL', 'RETAIL_FINANCE', 'FINANCE', false, 10],
            ['DO', 'RETAIL_FINANCE', 'FINANCE', true, 14],
            ['PAYMENT_PROOF', 'ACCOUNTS', 'ACCOUNTS', false, 7],
            ['INVOICE', 'ACCOUNTS', 'ACCOUNTS', true, 7],
            ['AADHAAR', 'RTO', 'RTO', false, 7],
            ['ADDRESS_PROOF', 'RTO', 'RTO', false, 7],
            ['INVOICE', 'RTO', 'RTO', false, 10],
            ['INS_POLICY', 'RTO', 'RTO', false, 10],
            ['RTO_APPLICATION', 'RTO', 'RTO', false, 14],
            ['INS_PROPOSAL', 'INSURANCE', 'INSURANCE', false, 5],
            ['INS_POLICY', 'INSURANCE', 'INSURANCE', true, 7],
            ['PDI_REPORT', 'PDI', 'PDI', true, 7],
            ['HANDOVER_FORM', 'DELIVERY', 'DELIVERY', false, null],
        ];

        foreach ($rules as [$type, $department, $task, $blocks, $dueDays]) {
            DocumentRequirementRule::query()->firstOrCreate(
                ['document_type_id' => $typeIds[$type], 'department_id' => $departments[$department]],
                ['fulfilment_task_type_id' => $task ? $taskIds[$task] : null, 'blocks_delivery' => $blocks, 'due_offset_days' => $dueDays],
            );
        }
    }
}
