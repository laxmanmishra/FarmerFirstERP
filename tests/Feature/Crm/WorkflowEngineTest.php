<?php

namespace Tests\Feature\Crm;

use App\Actions\Pipeline\MoveEnquiryStage;
use App\Actions\Telecaller\ClaimEnquiry;
use App\Actions\Telecaller\RecordCallAttempt;
use App\Actions\Workflow\SaveWorkflowStage;
use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStatusHistory;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

/**
 * Configurable statuses (SRS §68–70, §76–78, §89): new steps without code changes,
 * controlled transitions by role, immutable history, integrity guards.
 */
class WorkflowEngineTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_admin_added_stage_is_immediately_usable_without_code_changes(): void
    {
        $definition = WorkflowDefinition::byCode(WorkflowDefinition::SALES_PIPELINE);
        $demo = app(SaveWorkflowStage::class)->handle($definition, ['code' => 'DEMO_GIVEN', 'name' => 'Demo given', 'color' => 'violet', 'requires_remark' => true]);
        $enquiry = $this->validated();

        try {
            app(MoveEnquiryStage::class)->handle($this->salesman(), $enquiry, $demo);
            $this->fail('Remark flag should be enforced.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('workflow_remark_required', $exception->rule);
        }

        app(MoveEnquiryStage::class)->handle($this->salesman(), $enquiry->fresh(), $demo, 'Demo at farm, liked it');
        $this->assertSame('DEMO_GIVEN', $enquiry->fresh()->pipelineStage->code);
    }

    public function test_controlled_transitions_restrict_moves_by_configuration_and_role(): void
    {
        $definition = WorkflowDefinition::byCode(WorkflowDefinition::SALES_PIPELINE);
        $validated = $this->pipelineStage('VALIDATED');
        $definition->transitions()->create(['from_stage_id' => $validated->id, 'to_stage_id' => $this->pipelineStage('CONTACTED')->id]);
        $definition->transitions()->create(['from_stage_id' => $validated->id, 'to_stage_id' => $this->pipelineStage('DROPPED')->id, 'allowed_roles' => ['Sales Manager']]);
        $definition->update(['controlled_transitions' => true]);

        $targets = app(WorkflowService::class)->availableTargets($validated, $this->salesman())->pluck('code')->all();
        $this->assertSame(['CONTACTED'], $targets);

        $manager = $this->crmUser('Sales Manager');
        $this->assertEqualsCanonicalizing(['CONTACTED', 'DROPPED'], app(WorkflowService::class)->availableTargets($validated, $manager)->pluck('code')->all());

        $this->expectException(BusinessRuleException::class);
        app(MoveEnquiryStage::class)->handle($this->salesman(), $this->validated(), $this->pipelineStage('NEGOTIATION'));
    }

    public function test_inactive_stage_cannot_be_entered_and_used_stage_cannot_be_deleted(): void
    {
        $negotiation = $this->pipelineStage('NEGOTIATION');
        $enquiry = $this->validated();
        app(MoveEnquiryStage::class)->handle($this->salesman(), $enquiry, $negotiation);

        try {
            app(SaveWorkflowStage::class)->delete($negotiation);
            $this->fail('A used stage must not be deletable.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('stage_in_use', $exception->rule);
        }

        app(SaveWorkflowStage::class)->toggleActive($negotiation);
        $this->assertSame('NEGOTIATION', $enquiry->fresh()->pipelineStage->code, 'History and current records keep the inactive stage.');

        $this->expectException(BusinessRuleException::class);
        app(MoveEnquiryStage::class)->handle($this->salesman(), $this->validated(), $negotiation->fresh());
    }

    public function test_workflow_keeps_an_initial_and_a_completion_stage(): void
    {
        $save = app(SaveWorkflowStage::class);

        try {
            $save->toggleActive($this->pipelineStage('VALIDATED'));
            $this->fail('Last initial stage must stay active.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('workflow_needs_initial', $exception->rule);
        }

        $this->expectException(BusinessRuleException::class);
        $save->toggleActive($this->pipelineStage('WON'));
    }

    public function test_used_stage_code_is_locked(): void
    {
        $this->validated();
        $stage = $this->pipelineStage('VALIDATED');

        $this->expectException(BusinessRuleException::class);
        app(SaveWorkflowStage::class)->handle($stage->definition, ['code' => 'RENAMED', 'name' => 'Renamed', 'color' => 'sky', 'is_initial' => true], $stage);
    }

    public function test_status_history_is_immutable(): void
    {
        $this->validated();

        $this->expectException(LogicException::class);
        WorkflowStatusHistory::query()->firstOrFail()->delete();
    }

    private function salesman(): User
    {
        return once(fn () => $this->crmUser('Salesman'));
    }

    private function validated(): Enquiry
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->salesman());
        app(ClaimEnquiry::class)->handle($telecaller->employee, $enquiry);
        app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('VALID'), 'ok');

        return $enquiry->fresh();
    }
}
