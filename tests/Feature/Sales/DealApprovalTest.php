<?php

namespace Tests\Feature\Sales;

use App\Actions\Deals\DealApprovalFlow;
use App\Actions\Quotations\QuotationLifecycle;
use App\Actions\Workflow\SaveWorkflowStage;
use App\Enums\DealDecision;
use App\Events\DealApproved;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Sales\Deals\Show;
use App\Models\Deal;
use App\Models\DealApproval;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Notifications\DealDecided;
use App\Notifications\DealSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * SRS §14: Deal Ready → Approve / Send Back / Reject with separation of duties.
 */
class DealApprovalTest extends TestCase
{
    use CreatesCrmData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
    }

    public function test_deal_cannot_be_submitted_until_ready(): void
    {
        $deal = $this->win($this->salesman, $this->validatedEnquiry($this->salesman));

        try {
            app(DealApprovalFlow::class)->submit($this->salesman, $deal);
            $this->fail('Incomplete deal must not be submitted.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('deal_not_ready', $exception->rule);
            $this->assertNotEmpty($exception->context['missing']);
        }

        $this->assertSame('DRAFT', $deal->fresh()->stage->code);
    }

    public function test_submission_notifies_approvers_and_locks_the_deal(): void
    {
        Notification::fake();
        $deal = $this->readyDeal();

        app(DealApprovalFlow::class)->submit($this->salesman, $deal);

        $deal->refresh();
        $this->assertSame('DEAL_READY', $deal->stage->code);
        Notification::assertSentTo($this->manager, DealSubmitted::class);
        Notification::assertNotSentTo($this->salesman, DealSubmitted::class);
        $this->assertSame(DealDecision::Submitted, $deal->approvals->sole()->action);
        $this->assertSame($deal->deal_value, $deal->approvals->sole()->snapshot['deal_value']);

        $this->expectException(BusinessRuleException::class);
        app(DealApprovalFlow::class)->updateTerms($deal, $this->terms());
    }

    public function test_manager_approves_and_order_event_is_raised(): void
    {
        Event::fake([DealApproved::class]);
        Notification::fake();
        $deal = $this->readyDeal();
        app(DealApprovalFlow::class)->submit($this->salesman, $deal);

        app(DealApprovalFlow::class)->decide($this->manager, $deal->fresh(), DealDecision::Approved, 'Go ahead');

        $deal->refresh();
        $this->assertSame('APPROVED', $deal->stage->code);
        $this->assertSame($this->manager->id, $deal->approved_by);
        Event::assertDispatched(DealApproved::class, fn (DealApproved $event) => $event->deal->is($deal));
        Notification::assertSentTo($this->salesman, DealDecided::class);
        $this->assertSame(['submitted', 'approved'], $deal->approvals()->reorder('id')->pluck('action')->map->value->all());
    }

    public function test_salesman_and_submitter_cannot_decide(): void
    {
        $owner = $this->crmUser('Owner');
        $deal = $this->readyDeal();
        app(DealApprovalFlow::class)->submit($owner, $deal);

        foreach ([$this->salesman, $owner] as $actor) {
            try {
                app(DealApprovalFlow::class)->decide($actor, $deal->fresh(), DealDecision::Approved, null);
                $this->fail('Separation of duties not enforced.');
            } catch (BusinessRuleException $exception) {
                $this->assertContains($exception->rule, ['not_approver', 'self_approval']);
            }
        }

        app(DealApprovalFlow::class)->decide($this->manager, $deal->fresh(), DealDecision::Approved, null);
        $this->assertSame('APPROVED', $deal->fresh()->stage->code);
    }

    public function test_send_back_requires_remarks_and_reopens_editing(): void
    {
        $deal = $this->readyDeal();
        app(DealApprovalFlow::class)->submit($this->salesman, $deal);

        try {
            app(DealApprovalFlow::class)->decide($this->manager, $deal->fresh(), DealDecision::SentBack, '');
            $this->fail('Remarks should be required.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('reason_required', $exception->rule);
        }

        app(DealApprovalFlow::class)->decide($this->manager, $deal->fresh(), DealDecision::SentBack, 'Booking amount too low');

        $deal->refresh();
        $this->assertSame('SENT_BACK', $deal->stage->code);
        $this->assertTrue($deal->isEditable());

        app(DealApprovalFlow::class)->updateTerms($deal, $this->terms(['booking_amount' => '100000']));
        app(DealApprovalFlow::class)->submit($this->salesman, $deal->fresh());
        $this->assertSame('DEAL_READY', $deal->fresh()->stage->code);
    }

    public function test_reject_closes_the_deal(): void
    {
        $deal = $this->readyDeal();
        app(DealApprovalFlow::class)->submit($this->salesman, $deal);

        app(DealApprovalFlow::class)->decide($this->manager, $deal->fresh(), DealDecision::Rejected, 'Credit risk');

        $deal->refresh();
        $this->assertSame('REJECTED', $deal->stage->code);
        $this->assertNotNull($deal->closed_at);
    }

    public function test_approval_sets_the_internally_approved_exchange_value(): void
    {
        $enquiry = $this->validatedEnquiry($this->salesman, overrides: ['deal_type' => 'exchange', 'exchange' => ['brand_name' => 'Eicher', 'model_name' => '380', 'customer_expected_price' => '250000']]);
        $quotation = $this->quote($this->salesman, $enquiry, exchange: '210000');
        app(QuotationLifecycle::class)->issue($this->salesman, $quotation);
        app(QuotationLifecycle::class)->decide($this->salesman, $quotation->fresh(), true, null);
        $deal = $this->win($this->salesman, $enquiry);
        app(DealApprovalFlow::class)->updateTerms($deal, $this->terms());
        app(DealApprovalFlow::class)->submit($this->salesman, $deal->fresh());

        app(DealApprovalFlow::class)->decide($this->manager, $deal->fresh(), DealDecision::Approved, null);

        $exchange = $enquiry->fresh()->exchangeTractor;
        $this->assertSame('250000.00', $exchange->customer_expected_price);
        $this->assertSame('210000.00', $exchange->approved_exchange_value);
    }

    public function test_finance_and_booking_limits_are_validated(): void
    {
        $deal = $this->readyDeal();

        try {
            app(DealApprovalFlow::class)->updateTerms($deal, $this->terms(['finance_required' => true, 'finance_amount' => '9999999']));
            $this->fail('Finance above deal value should be refused.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('finance_exceeds_value', $exception->rule);
        }

        $this->expectException(BusinessRuleException::class);
        app(DealApprovalFlow::class)->updateTerms($deal, $this->terms(['finance_required' => true, 'finance_amount' => '900000', 'booking_amount' => '20000']));
    }

    public function test_approval_history_is_immutable_and_system_stages_are_protected(): void
    {
        $deal = $this->readyDeal();
        app(DealApprovalFlow::class)->submit($this->salesman, $deal);

        try {
            app(SaveWorkflowStage::class)->toggleActive(WorkflowStage::findByCode(WorkflowDefinition::DEAL, Deal::STAGE_APPROVED));
            $this->fail('System stage must stay active.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('stage_system', $exception->rule);
        }

        $this->expectException(LogicException::class);
        DealApproval::query()->first()->update(['remarks' => 'tampered']);
    }

    public function test_deal_screen_flow_and_visibility(): void
    {
        $deal = $this->readyDeal();

        Livewire::actingAs($this->salesman)->test(Show::class, ['deal' => $deal])
            ->assertSee(__('Mark Deal Ready'))
            ->call('submit')
            ->assertDispatched('toast', type: 'success')
            ->assertDontSeeHtml("openDecision('approved')");

        Livewire::actingAs($this->manager)->test(Show::class, ['deal' => $deal])
            ->call('openDecision', 'approved')
            ->call('decide')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('APPROVED', $deal->fresh()->stage->code);
        $this->actingAs($this->crmUser('Salesman'))->get(route('sales.deals.show', $deal))->assertNotFound();
        $this->actingAs($this->salesman)->get(route('sales.deal-approvals.index'))->assertForbidden();
    }

    private function readyDeal(): Deal
    {
        $enquiry = $this->validatedEnquiry($this->salesman);
        $this->acceptedQuotation($this->salesman, $enquiry);
        $deal = $this->win($this->salesman, $enquiry);
        app(DealApprovalFlow::class)->updateTerms($deal, $this->terms());

        return $deal->fresh(['stage']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function terms(array $overrides = []): array
    {
        return $overrides + [
            'expected_delivery_date' => today()->addWeek()->toDateString(),
            'booking_amount' => '25000', 'finance_required' => false, 'finance_amount' => '0',
            'rto_required' => true, 'insurance_required' => true, 'pdi_required' => true, 'remarks' => null,
        ];
    }
}
