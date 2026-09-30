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
