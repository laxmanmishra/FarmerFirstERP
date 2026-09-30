<?php

namespace Tests\Feature\Crm;

use App\Actions\Enquiries\EnquiryData;
use App\Actions\Enquiries\UpdateEnquiry;
use App\Actions\Territory\AssignTerritory;
use App\Enums\Temperature;
use App\Enums\TerritoryLevel;
use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Village;
use App\Models\WorkflowStatusHistory;
use App\Notifications\EnquiryAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

class EnquiryLifecycleTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_new_enquiry_is_unverified_numbered_and_has_history(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->makeEnquiry($salesman, overrides: ['expected_purchase_date' => today()->addDays(3)->toDateString()]);

        $this->assertMatchesRegularExpression('#^ENQ/\d{4}-\d{2}/00001$#', $enquiry->enquiry_no);
        $this->assertSame('UNVERIFIED', $enquiry->validationStage->code);
        $this->assertNull($enquiry->pipeline_stage_id);
        $this->assertSame(Temperature::Hot, $enquiry->temperature);
        $this->assertSame($salesman->employee->id, $enquiry->created_by_employee_id);

        $history = WorkflowStatusHistory::query()->where('subject_id', $enquiry->id)->sole();
        $this->assertNull($history->from_stage_id);
        $this->assertSame('created', $history->meta['event']);
    }

    public function test_salesman_created_enquiry_is_assigned_to_the_salesman(): void
    {
        $salesman = $this->crmUser('Salesman');

        $this->assertSame($salesman->employee->id, $this->makeEnquiry($salesman)->assigned_employee_id);
    }

    public function test_telecaller_created_enquiry_goes_to_territory_primary_salesman_and_notifies(): void
    {
        Notification::fake();
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $salesman = $this->crmUser('Salesman');
        $village = Village::query()->with('tehsil')->firstOrFail();
        app(AssignTerritory::class)->handle($salesman->employee, TerritoryLevel::District, $village->tehsil->district_id, true, today());

        $enquiry = $this->makeEnquiry($telecaller, Farmer::factory()->create(['village_id' => $village->id]));

        $this->assertSame($salesman->employee->id, $enquiry->assigned_employee_id);
        Notification::assertSentTo($salesman, EnquiryAssigned::class);
    }

    public function test_enquiry_without_territory_stays_unassigned(): void
    {
        $this->assertNull($this->makeEnquiry($this->crmUser('Telecaller', department: 'TELECALLING'))->assigned_employee_id);
    }

    public function test_past_expected_date_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->makeEnquiry($this->crmUser('Salesman'), overrides: ['expected_purchase_date' => today()->subDay()->toDateString()]);
    }

    public function test_duplicate_open_enquiry_is_blocked_until_confirmed_with_a_reason(): void
    {
        $salesman = $this->crmUser('Salesman');
        $farmer = Farmer::factory()->create();
        $product = Product::factory()->create();
        $line = ['requirements' => [['requirement_type' => 'tractor', 'product_id' => $product->id, 'quantity' => 1]]];
        $first = $this->makeEnquiry($salesman, $farmer, $line);

        try {
            $this->makeEnquiry($salesman, $farmer, $line);
            $this->fail('Duplicate enquiry was not detected.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('duplicate_enquiry', $exception->rule);
            $this->assertSame([$first->id], $exception->context['enquiry_ids']);
        }

        $second = $this->makeEnquiry($salesman, $farmer, $line, overrideReason: 'Second tractor for his son');

        $this->assertSame('Second tractor for his son', $second->duplicate_override_reason);
        $this->assertSame(2, Enquiry::query()->count());
    }

    public function test_same_mobile_on_another_farmer_record_counts_as_duplicate(): void
    {
        $salesman = $this->crmUser('Salesman');
        $this->makeEnquiry($salesman, Farmer::factory()->create(['mobile' => '9876500001']));

        $this->expectException(BusinessRuleException::class);
        $this->makeEnquiry($salesman, Farmer::factory()->create(['mobile' => '9800000000', 'alternate_mobile' => '9876500001']));
    }

    public function test_different_product_or_deal_type_is_not_a_duplicate(): void
    {
        $salesman = $this->crmUser('Salesman');
        $farmer = Farmer::factory()->create();
        [$a, $b] = Product::factory()->count(2)->create();

        $this->makeEnquiry($salesman, $farmer, ['requirements' => [['requirement_type' => 'tractor', 'product_id' => $a->id, 'quantity' => 1]]]);
        $this->makeEnquiry($salesman, $farmer, ['requirements' => [['requirement_type' => 'tractor', 'product_id' => $b->id, 'quantity' => 1]]]);
        $this->makeEnquiry($salesman, $farmer, ['deal_type' => 'exchange', 'exchange' => ['brand_name' => 'Eicher', 'model_name' => '380'],
            'requirements' => [['requirement_type' => 'tractor', 'product_id' => $a->id, 'quantity' => 1]]]);

        $this->assertSame(3, Enquiry::query()->count());
    }

    public function test_exchange_details_and_multiple_requirement_lines_are_stored(): void
    {
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'), overrides: [
            'deal_type' => 'exchange',
            'exchange' => ['brand_name' => 'Eicher', 'model_name' => '380', 'customer_expected_price' => '250000'],
            'requirements' => [
                ['requirement_type' => 'tractor', 'quantity' => 1],
                ['requirement_type' => 'implement', 'quantity' => 2, 'description' => 'Rotavator'],
            ],
        ]);

        $this->assertCount(2, $enquiry->requirements);
        $this->assertSame('250000.00', $enquiry->exchangeTractor->customer_expected_price);
        $this->assertNull($enquiry->exchangeTractor->approved_exchange_value);
    }

    public function test_editing_recalculates_temperature_and_is_refused_once_closed(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->makeEnquiry($salesman);
        $data = EnquiryData::fromArray([
            'source_code' => 'PHONE', 'deal_type' => 'new', 'expected_purchase_date' => today()->toDateString(),
            'requirements' => [['requirement_type' => 'tractor', 'quantity' => 1]],
        ]);

        app(UpdateEnquiry::class)->handle($salesman, $enquiry, $data);
        $this->assertSame(Temperature::ExtraHot, $enquiry->fresh()->temperature);

        $enquiry->forceFill(['closed_at' => now()])->save();
        $this->expectException(BusinessRuleException::class);
        app(UpdateEnquiry::class)->handle($salesman, $enquiry->fresh(), $data);
    }

    public function test_nightly_command_refreshes_temperature_as_dates_approach(): void
    {
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'), overrides: ['expected_purchase_date' => today()->addDays(20)->toDateString()]);
        $this->assertSame(Temperature::Cold, $enquiry->temperature);

        $this->travel(15)->days();
        $this->artisan('crm:refresh-temperatures')->assertSuccessful();

        $this->assertSame(Temperature::Hot, $enquiry->fresh()->temperature);
    }
}
