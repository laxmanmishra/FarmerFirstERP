<?php

namespace App\Livewire\Crm\Territory;

use App\Actions\Territory\AssignTerritory;
use App\Enums\TerritoryLevel;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithVillagePicker;
use App\Models\Employee;
use App\Models\TerritoryAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Salesman territory assignment (SRS §6). One active primary salesman per area.
 */
#[Title('Territory')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable, WithVillagePicker;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $employee = '';

    public bool $showForm = false;

    public ?int $employee_id = null;

    public string $level = 'district';

    public bool $is_primary = true;

    public string $effective_from = '';

    public bool $replaceExisting = false;

    public ?string $conflict = null;

    public mixed $modal = null;

    public ?int $endingId = null;

    public string $endReason = '';

    public function mount(): void
    {
        $this->authorize('territory.view');
    }

    public function create(): void
    {
        $this->authorize('territory.assign');
        $this->reset('employee_id', 'is_primary', 'replaceExisting', 'conflict', 'village_id', 'pickDistrictId', 'pickTehsilId');
        $this->resetValidation();
        $this->level = TerritoryLevel::District->value;
        $this->effective_from = today()->toDateString();
        $this->showForm = true;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['level', 'pickDistrictId', 'pickTehsilId', 'village_id', 'is_primary'], true)) {
            $this->conflict = null;
            $this->replaceExisting = false;
        }
    }

    public function save(AssignTerritory $assign): void
    {
        $this->authorize('territory.assign');
        $level = TerritoryLevel::tryFrom($this->level) ?? TerritoryLevel::District;

        $this->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('is_active', true)],
            'level' => ['required', Rule::enum(TerritoryLevel::class)],
            'effective_from' => ['required', 'date'],
            'pickDistrictId' => ['required', Rule::exists('districts', 'id')],
            'pickTehsilId' => [$level !== TerritoryLevel::District ? 'required' : 'nullable', Rule::exists('tehsils', 'id')],
            'village_id' => [$level === TerritoryLevel::Village ? 'required' : 'nullable', Rule::exists('villages', 'id')],
        ], attributes: ['employee_id' => __('salesman'), 'pickDistrictId' => __('district'), 'pickTehsilId' => __('tehsil'), 'village_id' => __('village')]);

        $areaId = match ($level) {
            TerritoryLevel::District => $this->pickDistrictId,
            TerritoryLevel::Tehsil => $this->pickTehsilId,
            TerritoryLevel::Village => $this->village_id,
        };

        try {
            $assign->handle(Employee::query()->findOrFail($this->employee_id), $level, $areaId, $this->is_primary, CarbonImmutable::parse($this->effective_from), $this->replaceExisting);
        } catch (BusinessRuleException $exception) {
            if ($exception->rule === 'territory_primary_exists') {
                $this->conflict = $exception->getMessage();

                return;
            }

            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->showForm = false;
        $this->toast(__('Territory assigned.'));
    }

    public function confirmEnd(int $assignmentId): void
    {
        $this->authorize('territory.assign');
        $this->endingId = TerritoryAssignment::query()->active()->findOrFail($assignmentId)->id;
        $this->endReason = '';
        $this->resetValidation();
        $this->modal = 'end';
    }

    public function end(AssignTerritory $assign): void
    {
        $this->authorize('territory.assign');
        $this->validate(['endReason' => ['required', 'string', 'min:3', 'max:500']], attributes: ['endReason' => __('reason')]);

        if ($this->attempt(fn () => $assign->end(TerritoryAssignment::query()->findOrFail($this->endingId), $this->endReason))) {
            $this->modal = null;
            $this->toast(__('Assignment ended.'));
        }
    }

    public function render(): mixed
    {
        $assignments = TerritoryAssignment::query()
            ->with(['employee:id,name,employee_code', 'district', 'tehsil.district', 'village.tehsil'])
            ->when($this->status === 'active', fn (Builder $query) => $query->active())
            ->when($this->status === 'ended', fn (Builder $query) => $query->where('is_active', false))
            ->when($this->employee !== '', fn (Builder $query) => $query->where('employee_id', $this->employee))
            ->orderByDesc('is_active')->orderBy('level')->latest('id')
            ->paginate($this->perPage);

        return view('livewire.crm.territory.index', [
            'assignments' => $assignments,
            'salesmen' => Employee::query()->active()->whereHas('departments', fn (Builder $query) => $query->where('code', 'SALES'))->orderBy('name')->pluck('name', 'id'),
            'levels' => collect(TerritoryLevel::cases())->mapWithKeys(fn (TerritoryLevel $level) => [$level->value => $level->label()]),
            'picker' => $this->villagePickerOptions(),
        ]);
    }
}
