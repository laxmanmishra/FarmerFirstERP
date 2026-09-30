<?php

namespace Tests\Feature\Sales;

use App\Actions\Deals\ConvertWonEnquiry;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Farmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * SRS §12 / master prompt Test 6: WON → duplicate check → CUSTOMER_ID → Deal.
 */
class CustomerConversionTest extends TestCase
{
    use CreatesCrmData, CreatesSalesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_won_enquiry_creates_customer_and_draft_deal_with_primary_salesman(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->validatedEnquiry($salesman);

        $deal = $this->win($salesman, $enquiry);

        $customer = $enquiry->fresh()->customer;
        $this->assertMatchesRegularExpression('/^CUS\d{6}$/', $customer->customer_no);
        $this->assertSame($enquiry->farmer_id, $customer->farmer_id);
        $this->assertSame('DRAFT', $deal->stage->code);
        $this->assertSame($customer->id, $deal->customer_id);
        $this->assertSame($salesman->employee->id, $deal->primary_salesman_employee_id);
        $this->assertNull($customer->possible_duplicate_of_id);
    }

    public function test_conversion_is_idempotent(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->validatedEnquiry($salesman);
        $deal = $this->win($salesman, $enquiry);

        $again = app(ConvertWonEnquiry::class)->handle($enquiry->fresh(), $salesman);

        $this->assertTrue($again->is($deal));
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, Deal::query()->count());
    }

    public function test_repeat_buyer_is_linked_not_duplicated(): void
    {
        $salesman = $this->crmUser('Salesman');
        $farmer = Farmer::factory()->create();
        $this->win($salesman, $this->validatedEnquiry($salesman, $farmer));

        $second = $this->validatedEnquiry($salesman, $farmer, ['deal_type' => 'exchange', 'exchange' => ['brand_name' => 'Eicher', 'model_name' => '380']]);
        $this->win($salesman, $second);

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, Customer::query()->first()->deals()->count());
    }

    public function test_other_farmer_with_same_mobile_is_flagged_as_possible_duplicate(): void
    {
        $salesman = $this->crmUser('Salesman');
        $this->win($salesman, $this->validatedEnquiry($salesman, Farmer::factory()->create(['mobile' => '9811100001'])));
        $existing = Customer::query()->sole();

        $this->win($salesman, $this->validatedEnquiry($salesman, Farmer::factory()->create(['mobile' => '9811100002', 'alternate_mobile' => '9811100001'])));

        $this->assertSame(2, Customer::query()->count());
        $this->assertSame($existing->id, Customer::query()->latest('id')->first()->possible_duplicate_of_id);
    }

    public function test_accepted_quotation_before_winning_is_applied_to_the_deal(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->validatedEnquiry($salesman);
        $quotation = $this->acceptedQuotation($salesman, $enquiry, finance: '500000');

        $deal = $this->win($salesman, $enquiry);

        $this->assertSame($quotation->id, $deal->quotation_id);
        $this->assertSame($quotation->net_amount, $deal->deal_value);
        $this->assertTrue($deal->finance_required);
        $this->assertSame('411000.00', $deal->customer_contribution);
        $this->assertCount(2, $deal->items);
    }
}
