<?php

namespace Tests\Feature\Crm;

use App\Actions\Telecaller\ClaimEnquiry;
use App\Actions\Telecaller\RecordCallAttempt;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Crm\Telecaller\Index;
use App\Models\CallAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

class TelecallerValidationTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_only_one_telecaller_can_hold_a_claim(): void
    {
        $first = $this->crmUser('Telecaller', department: 'TELECALLING');
        $second = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));

        app(ClaimEnquiry::class)->handle($first->employee, $enquiry);

        try {
            app(ClaimEnquiry::class)->handle($second->employee, $enquiry);
            $this->fail('Second claim should be refused.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('already_claimed', $exception->rule);
        }

        $this->assertSame($first->employee->id, $enquiry->fresh()->claimed_by_employee_id);
    }

    public function test_expired_claim_can_be_taken_over(): void
    {
        $first = $this->crmUser('Telecaller', department: 'TELECALLING');
        $second = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));
        app(ClaimEnquiry::class)->handle($first->employee, $enquiry);

        $this->travel(config('erp.crm.claim_timeout_minutes') + 1)->minutes();
        app(ClaimEnquiry::class)->handle($second->employee, $enquiry);

        $this->assertSame($second->employee->id, $enquiry->fresh()->claimed_by_employee_id);
    }

    public function test_call_cannot_be_recorded_without_holding_the_claim(): void
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));

        $this->expectException(BusinessRuleException::class);
        app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('VALID'), 'ok');
    }

    public function test_valid_outcome_moves_enquiry_into_the_pipeline(): void
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));
        app(ClaimEnquiry::class)->handle($telecaller->employee, $enquiry);

        app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('VALID'), 'Confirmed', 120);

        $enquiry->refresh();
        $this->assertSame('VALID', $enquiry->validationStage->code);
        $this->assertSame('VALIDATED', $enquiry->pipelineStage->code);
        $this->assertSame($telecaller->employee->id, $enquiry->validated_by_employee_id);
        $this->assertNull($enquiry->claimed_by_employee_id);
        $this->assertFalse($enquiry->isClosed());
        $this->assertSame(1, CallAttempt::query()->where('enquiry_id', $enquiry->id)->count());
    }

    public function test_invalid_outcome_requires_a_remark_and_closes_the_enquiry(): void
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));
        app(ClaimEnquiry::class)->handle($telecaller->employee, $enquiry);

        try {
            app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('INVALID'), null);
            $this->fail('A remark should be required.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('workflow_remark_required', $exception->rule);
        }

        $this->assertSame(0, CallAttempt::query()->count());

        app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('INVALID'), 'Fake enquiry, no land');

        $enquiry->refresh();
        $this->assertTrue($enquiry->isClosed());
        $this->assertSame('Fake enquiry, no land', $enquiry->close_remarks);
        $this->assertNull($enquiry->pipeline_stage_id);
    }

    public function test_callback_requires_a_future_callback_time_and_keeps_the_enquiry_queued(): void
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));
        app(ClaimEnquiry::class)->handle($telecaller->employee, $enquiry);

        try {
            app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('CALLBACK'), 'Busy');
            $this->fail('A callback time should be required.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('workflow_followup_required', $exception->rule);
        }

        app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('CALLBACK'), 'Busy', 30, now()->addHours(3));

        $enquiry->refresh();
        $this->assertTrue($enquiry->isAwaitingValidation());
        $this->assertTrue($enquiry->callback_at->isFuture());
        $this->assertNull($enquiry->claimed_by_employee_id);
    }

    public function test_every_call_is_a_separate_immutable_record(): void
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));

        foreach (range(1, 3) as $attempt) {
            app(ClaimEnquiry::class)->handle($telecaller->employee, $enquiry->fresh());
            app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('NO_ANSWER'), "Attempt {$attempt}");
        }

        $this->assertSame(3, CallAttempt::query()->where('enquiry_id', $enquiry->id)->count());

        $this->expectException(LogicException::class);
        CallAttempt::query()->first()->update(['remarks' => 'rewritten']);
    }

    public function test_telecaller_screen_claims_and_validates(): void
    {
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));

        Livewire::actingAs($telecaller)->test(Index::class)
            ->assertSee($enquiry->enquiry_no)
            ->call('claim', $enquiry->id)
            ->assertSet('showCall', true)
            ->set('outcomeId', $this->validationStage('VALID')->id)
            ->set('remarks', 'Interested, wants demo')
            ->call('recordCall')
            ->assertHasNoErrors()
            ->assertSet('showCall', false);

        $this->assertNotNull($enquiry->fresh()->pipeline_stage_id);
    }
}
