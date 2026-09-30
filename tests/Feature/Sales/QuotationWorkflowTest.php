<?php

namespace Tests\Feature\Sales;

use App\Actions\Quotations\QuotationLifecycle;
use App\Enums\QuotationStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Sales\Quotations\Form;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Quotation;
use App\Notifications\QuotationDiscountApprovalRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * SRS v6.1 §4: versioning, role discount limits with approval, issue/accept/decline.
 */
class QuotationWorkflowTest extends TestCase
{
    use CreatesCrmData, CreatesSalesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_quotation_within_limit_is_draft_numbered_and_issuable(): void
    {
        $salesman = $this->crmUser('Salesman');
        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman));

        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertMatchesRegularExpression('#^QT/\d{4}-\d{2}/00001$#', $quotation->quotation_no);
        $this->assertSame(1, $quotation->version);

        app(QuotationLifecycle::class)->issue($salesman, $quotation);
        $this->assertSame(QuotationStatus::Issued, $quotation->fresh()->status);
    }

    public function test_quotation_requires_a_validated_enquiry(): void
    {
        $salesman = $this->crmUser('Salesman');

        $this->expectException(BusinessRuleException::class);
        $this->quote($salesman, $this->makeEnquiry($salesman));
    }

    public function test_discount_above_limit_needs_approval_by_someone_else_with_enough_authority(): void
    {
        Notification::fake();
        $manager = $this->crmUser('Sales Manager');
        $salesman = $this->crmUser('Salesman', $manager);
        $bigDiscount = [['line_type' => 'product', 'description' => 'Tractor', 'quantity' => 1, 'unit_price' => '800000', 'discount_amount' => '30000']]; // 3.75%

        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman), $bigDiscount);

        $this->assertSame(QuotationStatus::PendingApproval, $quotation->status);
        Notification::assertSentTo($manager, QuotationDiscountApprovalRequested::class);

        try {
            app(QuotationLifecycle::class)->issue($salesman, $quotation);
            $this->fail('A pending discount must block issuing.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('quotation_not_issuable', $exception->rule);
        }

        app(QuotationLifecycle::class)->approveDiscount($manager, $quotation, 'Festival offer');
        $quotation->refresh();
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertSame($manager->id, $quotation->discount_approved_by);

        app(QuotationLifecycle::class)->issue($salesman, $quotation);
        $this->assertSame(QuotationStatus::Issued, $quotation->fresh()->status);
    }

    public function test_approver_limit_and_self_approval_are_enforced(): void
    {
        $manager = $this->crmUser('Sales Manager');
        $salesman = $this->crmUser('Salesman', $manager);
        $huge = [['line_type' => 'product', 'description' => 'Tractor', 'quantity' => 1, 'unit_price' => '800000', 'discount_amount' => '80000']]; // 10%, above manager's 5%
        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman), $huge);

        try {
            app(QuotationLifecycle::class)->approveDiscount($manager, $quotation, null);
            $this->fail('Manager limit should be enforced.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('discount_above_limit', $exception->rule);
        }

        $ownManagerQuote = $this->quote($manager, $this->validatedEnquiry($manager), $huge);
        $this->expectException(BusinessRuleException::class);
        app(QuotationLifecycle::class)->approveDiscount($manager, $ownManagerQuote, null);
    }

    public function test_owner_approves_large_discount(): void
    {
        $salesman = $this->crmUser('Salesman');
        $owner = $this->crmUser('Owner');
        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman), [['line_type' => 'product', 'description' => 'T', 'quantity' => 1, 'unit_price' => '800000', 'discount_amount' => '80000']]);

        app(QuotationLifecycle::class)->approveDiscount($owner, $quotation, 'OK');

        $this->assertSame(QuotationStatus::Draft, $quotation->fresh()->status);
    }

    public function test_revision_creates_next_version_and_supersedes_previous(): void
    {
        $salesman = $this->crmUser('Salesman');
        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman));
        app(QuotationLifecycle::class)->issue($salesman, $quotation);

        $revision = app(QuotationLifecycle::class)->revise($salesman, $quotation->fresh(['items']));

        $this->assertSame(2, $revision->version);
        $this->assertSame($quotation->quotation_no, $revision->quotation_no);
        $this->assertSame(QuotationStatus::Draft, $revision->status);
        $this->assertSame(QuotationStatus::Superseded, $quotation->fresh()->status);
        $this->assertCount(2, $revision->items);

        $this->expectException(BusinessRuleException::class);
        app(QuotationLifecycle::class)->revise($salesman, $quotation->fresh(['items']));
    }

    public function test_accepting_supersedes_other_quotations_and_accepted_cannot_be_revised(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->validatedEnquiry($salesman);
        $other = $this->quote($salesman, $enquiry);
        $accepted = $this->acceptedQuotation($salesman, $enquiry);

        $this->assertSame(QuotationStatus::Superseded, $other->fresh()->status);
        $this->assertSame(QuotationStatus::Accepted, $accepted->status);

        $this->expectException(BusinessRuleException::class);
        app(QuotationLifecycle::class)->revise($salesman, $accepted->load('items'));
    }

    public function test_expired_quotation_cannot_be_accepted_and_decline_needs_issued_state(): void
    {
        $salesman = $this->crmUser('Salesman');
        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman));
        app(QuotationLifecycle::class)->issue($salesman, $quotation);

        $this->travel(20)->days();

        try {
            app(QuotationLifecycle::class)->decide($salesman, $quotation->fresh(), true, null);
            $this->fail('Expired quotation should not be accepted.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('quotation_expired', $exception->rule);
        }

        app(QuotationLifecycle::class)->decide($salesman, $quotation->fresh(), false, 'Bought elsewhere');
        $this->assertSame(QuotationStatus::Declined, $quotation->fresh()->status);
    }

    public function test_form_fills_price_from_price_master_and_saves(): void
    {
        $salesman = $this->crmUser('Salesman');
        $product = Product::factory()->create(['name' => '575 DI']);
        ProductPrice::create(['product_id' => $product->id, 'price' => '785000', 'tax_percent' => '12', 'effective_from' => today()->subMonth()]);
        $enquiry = $this->validatedEnquiry($salesman, overrides: ['requirements' => [['requirement_type' => 'tractor', 'product_id' => $product->id, 'quantity' => 1]]]);

        $this->get(route('sales.quotations.create', ['enquiry' => $enquiry->id]))->assertOk()->assertSee('785000');

        Livewire::withQueryParams(['enquiry' => $enquiry->id])->actingAs($salesman)->test(Form::class)
            ->assertSet('lines.0.unit_price', '785000.00')
            ->assertSet('lines.0.tax_percent', '12.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame('879200.00', Quotation::query()->sole()->net_amount);
    }

    public function test_print_view_is_protected(): void
    {
        $salesman = $this->crmUser('Salesman');
        $quotation = $this->quote($salesman, $this->validatedEnquiry($salesman));

        $this->actingAs($salesman)->get(route('sales.quotations.print', $quotation))->assertOk()->assertSee($quotation->quotation_no);
        $this->actingAs($this->crmUser('Salesman'))->get(route('sales.quotations.print', $quotation))->assertNotFound();
    }
}
