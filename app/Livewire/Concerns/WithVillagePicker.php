<?php

namespace App\Livewire\Concerns;

use App\Models\District;
use App\Models\Tehsil;
use App\Models\Village;
use Illuminate\Support\Collection;

/**
 * Cascading District → Tehsil → Village selection. The view partial
 * `livewire.partials.village-picker` renders the three selects.
 */
trait WithVillagePicker
{
    public ?int $pickDistrictId = null;

    public ?int $pickTehsilId = null;

    public ?int $village_id = null;

    public function updatedPickDistrictId(): void
    {
        $this->pickTehsilId = null;
        $this->village_id = null;
    }

    public function updatedPickTehsilId(): void
    {
        $this->village_id = null;
    }

    protected function presetVillage(?int $villageId): void
    {
        $village = $villageId ? Village::query()->with('tehsil')->find($villageId) : null;

        $this->village_id = $village?->id;
        $this->pickTehsilId = $village?->tehsil_id;
        $this->pickDistrictId = $village?->tehsil->district_id;
    }

    /**
     * @return array{districts: Collection<int, string>, tehsils: Collection<int, string>, villages: Collection<int, string>}
     */
    protected function villagePickerOptions(): array
    {
        return [
            'districts' => District::query()->active()->orderBy('name')->pluck('name', 'id'),
            'tehsils' => $this->pickDistrictId
                ? Tehsil::query()->active()->where('district_id', $this->pickDistrictId)->orderBy('name')->pluck('name', 'id')
                : collect(),
            'villages' => $this->pickTehsilId
                ? Village::query()->active()->where('tehsil_id', $this->pickTehsilId)->orderBy('name')->pluck('name', 'id')
                : collect(),
        ];
    }
}
