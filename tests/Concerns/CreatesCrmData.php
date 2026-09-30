<?php

namespace Tests\Concerns;

use App\Actions\Enquiries\CreateEnquiry;
use App\Actions\Enquiries\EnquiryData;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Farmer;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;

/**
 * Builds CRM records through the real Actions so tests exercise the business rules.
 */
trait CreatesCrmData
{
    protected function crmUser(string $role, ?User $manager = null, string $department = 'SALES'): User
    {
        $user = $this->userWithRole($role);
        $employee = $this->employeeFor($user, ['name' => $user->name, 'reports_to_id' => $manager?->employee?->id]);
        $employee->departments()->attach(Department::query()->where('code', $department)->value('id'), ['is_primary' => true]);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeEnquiry(User $actor, ?Farmer $farmer = null, array $overrides = [], ?int $assigneeId = null, ?string $overrideReason = null): Enquiry
    {
        $this->actingAs($actor);
        $farmer ??= Farmer::factory()->create();

        return app(CreateEnquiry::class)->handle($actor, $farmer, EnquiryData::fromArray($overrides + [
            'source_code' => 'WALK_IN',
            'deal_type' => 'new',
            'expected_purchase_date' => today()->addDays(10)->toDateString(),
            'requirements' => [['requirement_type' => 'tractor', 'quantity' => 1]],
        ]), $assigneeId, $overrideReason);
    }

    protected function validationStage(string $code): WorkflowStage
    {
        return WorkflowStage::findByCode(WorkflowDefinition::ENQUIRY_VALIDATION, $code);
    }

    protected function pipelineStage(string $code): WorkflowStage
    {
        return WorkflowStage::findByCode(WorkflowDefinition::SALES_PIPELINE, $code);
    }
}
