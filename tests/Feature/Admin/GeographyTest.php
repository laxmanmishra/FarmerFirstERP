<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Geography\Index;
use App\Models\Tehsil;
use App\Models\Village;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeographyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_village_name_is_unique_within_its_tehsil(): void
    {
        $tehsil = Tehsil::query()->where('name', 'Sehore')->firstOrFail();

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->set('tab', 'villages')
            ->call('create')
            ->set('parent_id', $tehsil->id)
            ->set('name', 'Bilkisganj')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);
    }

    public function test_same_village_name_is_allowed_in_another_tehsil(): void
    {
        $otherTehsil = Tehsil::query()->where('name', 'Ashta')->firstOrFail();

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->set('tab', 'villages')
            ->call('create')
            ->set('parent_id', $otherTehsil->id)
            ->set('name', 'Bilkisganj')
            ->set('pin_code', '466001')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Village::query()->where('name', 'Bilkisganj')->count());
    }

    public function test_database_enforces_village_uniqueness(): void
    {
        $village = Village::query()->firstOrFail();

        $this->expectException(UniqueConstraintViolationException::class);

        Village::create(['tehsil_id' => $village->tehsil_id, 'name' => $village->name]);
    }

    public function test_deactivated_village_is_not_offered_by_the_api(): void
    {
        $village = Village::query()->firstOrFail();
        $village->update(['is_active' => false]);

        $this->actingAs($this->userWithRole('Owner'), 'sanctum')
            ->getJson(route('api.v1.geography.villages', $village->tehsil_id))
            ->assertOk()
            ->assertJsonMissing(['id' => $village->id]);
    }

    public function test_viewer_cannot_create_records(): void
    {
        Livewire::actingAs($this->userWithPermissions(['geography.view']))->test(Index::class)
            ->call('create')
            ->assertForbidden();
    }
}
