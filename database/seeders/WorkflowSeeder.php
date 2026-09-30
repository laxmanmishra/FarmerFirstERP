<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;

/**
 * Initial workflow configuration (docs/STATUS_TRANSITIONS.md). Additive and
 * idempotent: existing stages are never overwritten, so admin changes survive.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->definition(WorkflowDefinition::ENQUIRY_VALIDATION, 'crm', 'Enquiry validation', 'Telecaller verification outcomes (SRS §10).', [
            // code, name, color, flags
            ['UNVERIFIED', 'Unverified', 'amber', ['is_initial']],
            ['CALLBACK', 'Callback', 'sky', ['requires_followup']],
            ['NO_ANSWER', 'No answer', 'slate', []],
            ['VALID', 'Valid', 'green', ['is_final', 'is_completion']],
            ['INVALID', 'Invalid', 'rose', ['is_final', 'is_rejection', 'requires_remark']],
            ['NOT_INTERESTED', 'Not interested', 'rose', ['is_final', 'is_rejection', 'requires_remark']],
            ['DUPLICATE', 'Duplicate', 'slate', ['is_final', 'is_rejection', 'requires_remark']],
            ['WRONG_NUMBER', 'Wrong number', 'rose', ['is_final', 'is_rejection']],
            ['CUSTOMER_NOT_KNOWN', 'Customer not known', 'rose', ['is_final', 'is_rejection']],
        ]);

        $this->definition(WorkflowDefinition::SALES_PIPELINE, 'crm', 'Sales pipeline', 'Validated enquiry to WON / LOST / DROPPED (SRS §11).', [
            ['VALIDATED', 'New / Validated', 'sky', ['is_initial']],
            ['CONTACTED', 'Contacted', 'sky', []],
            ['FOLLOW_UP_REQUIRED', 'Follow-up required', 'amber', []],
            ['CUSTOMER_INTERESTED', 'Customer interested', 'brand', []],
            ['PRODUCT_DISCUSSION', 'Product discussion', 'brand', []],
            ['QUOTATION_REQUIRED', 'Quotation required', 'violet', []],
            ['QUOTATION_GIVEN', 'Quotation given', 'violet', []],
            ['NEGOTIATION', 'Negotiation', 'amber', []],
            ['PURCHASE_DECISION_PENDING', 'Purchase decision pending', 'amber', []],
            ['WON', 'Won', 'green', ['is_final', 'is_completion']],
            ['LOST', 'Lost', 'rose', ['is_final', 'is_rejection', 'requires_remark']],
            ['DROPPED', 'Dropped', 'slate', ['is_final', 'is_rejection', 'requires_remark']],
        ]);

        // Deal approval stages are referenced by code (SRS §14: Approve / Send Back / Reject), hence is_system.
        $this->definition(WorkflowDefinition::DEAL, 'sales', 'Deal approval', 'Deal Ready → Manager/Owner review (SRS §14).', [
            ['DRAFT', 'Draft', 'slate', ['is_initial', 'is_system']],
            ['DEAL_READY', 'Deal ready — awaiting approval', 'amber', ['is_system']],
            ['SENT_BACK', 'Sent back', 'violet', ['requires_remark', 'is_system']],
            ['APPROVED', 'Approved', 'green', ['is_final', 'is_completion', 'is_system']],
            ['REJECTED', 'Rejected', 'rose', ['is_final', 'is_rejection', 'requires_remark', 'is_system']],
        ]);

        // Order stages are moved by business events (booking, fulfilment, delivery), not by hand — hence is_system.
        $this->definition(WorkflowDefinition::ORDER, 'orders', 'Order / booking', 'Order lifecycle from booking to completion (SRS §15).', [
            ['BOOKED', 'Booked', 'sky', ['is_initial', 'is_system']],
            ['IN_FULFILMENT', 'In fulfilment', 'brand', ['is_system']],
            ['READY_FOR_DELIVERY', 'Ready for delivery', 'green', ['is_system']],
            ['DELIVERED', 'Delivered', 'green', ['is_system']],
            ['COMPLETED', 'Completed', 'green', ['is_final', 'is_completion', 'is_system']],
            ['CANCELLED', 'Cancelled', 'rose', ['is_final', 'is_rejection', 'requires_remark', 'is_system']],
        ]);

        // Mirrors what the external financer reports (SRS §57). Readiness uses the completion flag.
        $this->definition(WorkflowDefinition::FINANCE, 'finance', 'Retail & Finance file', 'Finance file status per external financer (SRS §57, §67).', [
            ['FILE_CREATED', 'File created', 'slate', ['is_initial']],
            ['FINANCER_REFERRAL', 'Referred to financer', 'sky', []],
            ['FI_PENDING', 'Field investigation pending', 'amber', []],
            ['FI_SCHEDULED', 'Field investigation scheduled', 'amber', []],
            ['FI_DONE', 'Field investigation done', 'sky', []],
            ['CREDIT_APPROVAL_PENDING', 'Credit approval pending', 'amber', []],
            ['CREDIT_APPROVED', 'Credit approved', 'green', []],
            ['CREDIT_REJECTED', 'Credit rejected', 'rose', ['is_rejection', 'requires_remark']],
            ['QUOTATION_PENDING', 'Financer quotation pending', 'amber', []],
            ['QUOTATION_RECEIVED', 'Financer quotation received', 'sky', []],
            ['DO_PENDING', 'DO pending', 'amber', []],
            ['DO_RECEIVED', 'DO received', 'green', []],
            ['DO_EXPIRED', 'DO expired', 'rose', []],
            ['DISBURSEMENT_PENDING', 'Disbursement pending', 'amber', []],
            ['DISBURSED', 'Disbursed', 'green', []],
            ['ON_HOLD', 'On hold', 'slate', ['is_hold', 'requires_remark']],
            ['CANCELLED', 'Cancelled', 'rose', ['is_final', 'is_rejection', 'requires_remark', 'is_system']],
            ['FINANCE_COMPLETED', 'Finance completed', 'green', ['is_final', 'is_completion']],
        ]);

        // Payment position is computed from payments; completion is refused while money is short.
        $this->definition(WorkflowDefinition::ACCOUNTS, 'accounts', 'Accounts file', 'Accounts clearance of an order (SRS §91–113).', [
            ['PAYMENT_PENDING', 'Payment pending', 'amber', ['is_initial']],
            ['ADVANCE_RECEIVED', 'Advance received', 'sky', []],
            ['PART_PAYMENT_RECEIVED', 'Part payment received', 'sky', []],
            ['VERIFICATION_PENDING', 'Verification pending', 'amber', []],
            ['PAYMENT_SHORT', 'Payment short', 'rose', ['is_system']],
            ['PAYMENT_RETURNED', 'Payment returned', 'rose', []],
            ['ON_HOLD', 'On hold', 'slate', ['is_hold', 'requires_remark']],
            ['PAYMENT_CLEARED', 'Payment cleared', 'green', ['is_completion']],
            ['ACCOUNTS_COMPLETED', 'Accounts completed', 'green', ['is_final', 'is_completion']],
        ]);

        // Generic department task progress until each department gets its own workflow (Phases 5–6).
        $this->definition(WorkflowDefinition::FULFILMENT_TASK, 'fulfilment', 'Fulfilment task', 'Operational status of a department task (SRS §23).', [
            ['PENDING', 'Pending', 'amber', ['is_initial', 'is_system']],
            ['IN_PROGRESS', 'In progress', 'brand', []],
            ['ON_HOLD', 'On hold', 'slate', ['is_hold', 'requires_remark']],
            ['COMPLETED', 'Completed', 'green', ['is_final', 'is_completion']],
            ['CANCELLED', 'Cancelled', 'rose', ['is_final', 'is_rejection', 'is_system']],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: list<string>}>  $stages
     */
    private function definition(string $code, string $module, string $name, string $description, array $stages): void
    {
        $definition = WorkflowDefinition::query()->firstOrCreate(['code' => $code], [
            'module' => $module,
            'name' => $name,
            'description' => $description,
        ]);

        foreach ($stages as $index => [$stageCode, $stageName, $color, $flags]) {
            WorkflowStage::query()->firstOrCreate(
                ['workflow_definition_id' => $definition->id, 'code' => $stageCode],
                ['name' => $stageName, 'color' => $color, 'sequence' => ($index + 1) * 10, ...array_fill_keys($flags, true)],
            );
        }
    }
}
