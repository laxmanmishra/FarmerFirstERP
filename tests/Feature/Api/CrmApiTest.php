<?php

namespace Tests\Feature\Api;

use App\Actions\FollowUps\ScheduleFollowUp;
use App\Models\Farmer;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

/**
 * Mobile/field API (SRS v6.1 §10–11): same rules as the web UI.
 */
class CrmApiTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_salesman_creates_farmer_and_enquiry_through_the_api(): void
    {
        $salesman = $this->crmUser('Salesman');
        $token = $salesman->createToken('phone')->plainTextToken;
        $village = Village::query()->firstOrFail();

        $farmerId = $this->withToken($token)->postJson(route('api.v1.farmers.store'), ['name' => 'Ramdeen', 'mobile' => '9123400001', 'village_id' => $village->id])
            ->assertCreated()->assertJsonPath('data.farmer_no', fn ($no) => str_starts_with($no, 'FRM'))->json('data.id');

        $this->withToken($token)->postJson(route('api.v1.enquiries.store'), [
            'farmer_id' => $farmerId, 'source_code' => 'FIELD_VISIT', 'deal_type' => 'new',
            'expected_purchase_date' => today()->addDay()->toDateString(),
            'requirements' => [['requirement_type' => 'tractor', 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.temperature', 'extra_hot')
            ->assertJsonPath('data.validation_stage.code', 'UNVERIFIED')
            ->assertJsonPath('data.assignee.id', $salesman->employee->id);
    }

    public function test_duplicate_farmer_returns_business_rule_error_with_matches(): void
    {
        $existing = Farmer::factory()->create(['mobile' => '9123400002']);
        $token = $this->crmUser('Salesman')->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson(route('api.v1.farmers.store'), ['name' => 'Someone', 'mobile' => '9123400002', 'village_id' => $existing->village_id])
            ->assertStatus(422)
            ->assertJsonPath('type', 'business_rule_error')
            ->assertJsonPath('context.farmer_ids', [$existing->id]);

        $this->withToken($token)->postJson(route('api.v1.farmers.store'), ['name' => 'Someone', 'mobile' => '9123400002', 'village_id' => $existing->village_id, 'confirm_not_duplicate' => true])
            ->assertCreated();
    }

    public function test_duplicate_check_endpoint_and_blocked_create(): void
    {
        $salesman = $this->crmUser('Salesman');
        $first = $this->makeEnquiry($salesman);
        $token = $salesman->createToken('phone')->plainTextToken;
        $payload = ['farmer_id' => $first->farmer_id, 'deal_type' => 'new', 'expected_purchase_date' => today()->addDays(12)->toDateString()];

        $this->withToken($token)->postJson(route('api.v1.enquiries.duplicates'), $payload)
            ->assertOk()->assertJsonPath('data.0.id', $first->id);

        $this->withToken($token)->postJson(route('api.v1.enquiries.store'), $payload + ['source_code' => 'PHONE', 'requirements' => [['requirement_type' => 'tractor', 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonPath('context.enquiry_ids', [$first->id]);
    }

    public function test_validation_errors_use_the_envelope(): void
    {
        $token = $this->crmUser('Salesman')->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson(route('api.v1.enquiries.store'), ['expected_purchase_date' => today()->subDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonPath('type', 'validation_error')
            ->assertJsonValidationErrors(['farmer_id', 'source_code', 'deal_type', 'expected_purchase_date', 'requirements']);
    }

    public function test_listing_only_returns_visible_enquiries(): void
    {
        $mine = $this->crmUser('Salesman');
        $this->makeEnquiry($mine);
        $this->makeEnquiry($this->crmUser('Salesman'));

        $this->withToken($mine->createToken('phone')->plainTextToken)->getJson(route('api.v1.enquiries.index'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_follow_ups_are_listed_and_completed(): void
    {
        $salesman = $this->crmUser('Salesman');
        $followUp = app(ScheduleFollowUp::class)->handle($salesman, $this->makeEnquiry($salesman), $salesman->employee, 'CALL', now()->addHour(), 'Call');
        $token = $salesman->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.follow-ups.index'))->assertOk()->assertJsonPath('data.0.id', $followUp->id);
        $this->withToken($token)->postJson(route('api.v1.follow-ups.complete', $followUp), ['outcome' => 'Spoke, will visit'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
    }
}
