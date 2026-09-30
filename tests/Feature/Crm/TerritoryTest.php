<?php

namespace Tests\Feature\Crm;

use App\Actions\Territory\AssignTerritory;
use App\Enums\TerritoryLevel;
use App\Exceptions\BusinessRuleException;
use App\Models\TerritoryAssignment;
use App\Models\Village;
use App\Services\TerritoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

class TerritoryTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_one_primary_salesman_per_area(): void
    {
        $village = Village::query()->firstOrFail();
        $first = $this->crmUser('Salesman');
        $second = $this->crmUser('Salesman');
        app(AssignTerritory::class)->handle($first->employee, TerritoryLevel::Village, $village->id, true, today());

        try {
            app(AssignTerritory::class)->handle($second->employee, TerritoryLevel::Village, $village->id, true, today());
            $this->fail('Second primary should be refused.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('territory_primary_exists', $exception->rule);
        }

        app(AssignTerritory::class)->handle($second->employee, TerritoryLevel::Village, $village->id, false, today());
        $this->assertSame(2, TerritoryAssignment::query()->active()->count());
    }

    public function test_database_refuses_a_second_active_primary(): void
    {
        $village = Village::query()->firstOrFail();
        app(AssignTerritory::class)->handle($this->crmUser('Salesman')->employee, TerritoryLevel::Village, $village->id, true, today());

        $this->expectException(UniqueConstraintViolationException::class);
        TerritoryAssignment::create([
            'employee_id' => $this->crmUser('Salesman')->employee->id, 'level' => TerritoryLevel::Village,
            'village_id' => $village->id, 'is_primary' => true, 'is_active' => true, 'effective_from' => today(),
        ]);
    }

    public function test_replacing_the_primary_ends_the_old_assignment_and_keeps_history(): void
    {
        $village = Village::query()->firstOrFail();
        $old = app(AssignTerritory::class)->handle($this->crmUser('Salesman')->employee, TerritoryLevel::Village, $village->id, true, today()->subMonth());
        $new = $this->crmUser('Salesman');

        app(AssignTerritory::class)->handle($new->employee, TerritoryLevel::Village, $village->id, true, today(), replaceExisting: true);

        $old->refresh();
        $this->assertFalse($old->is_active);
        $this->assertNull($old->primary_scope);
        $this->assertSame(today()->subDay()->toDateString(), $old->effective_to->toDateString());
        $this->assertSame($new->employee->id, app(TerritoryService::class)->primarySalesmanFor($village)->id);
    }

    public function test_most_specific_assignment_wins(): void
    {
        $village = Village::query()->with('tehsil')->firstOrFail();
        [$districtMan, $tehsilMan, $villageMan] = [$this->crmUser('Salesman'), $this->crmUser('Salesman'), $this->crmUser('Salesman')];
        $assign = app(AssignTerritory::class);
        $territory = app(TerritoryService::class);

        $assign->handle($districtMan->employee, TerritoryLevel::District, $village->tehsil->district_id, true, today());
        $this->assertSame($districtMan->employee->id, $territory->primarySalesmanFor($village)->id);

        $assign->handle($tehsilMan->employee, TerritoryLevel::Tehsil, $village->tehsil_id, true, today());
        $this->assertSame($tehsilMan->employee->id, $territory->primarySalesmanFor($village)->id);

        $assign->handle($villageMan->employee, TerritoryLevel::Village, $village->id, true, today());
        $this->assertSame($villageMan->employee->id, $territory->primarySalesmanFor($village)->id);
    }

    public function test_inactive_salesman_is_skipped(): void
    {
        $village = Village::query()->with('tehsil')->firstOrFail();
        $salesman = $this->crmUser('Salesman');
        app(AssignTerritory::class)->handle($salesman->employee, TerritoryLevel::District, $village->tehsil->district_id, true, today());
        $salesman->employee->update(['is_active' => false]);

        $this->assertNull(app(TerritoryService::class)->primarySalesmanFor($village));
    }
}
