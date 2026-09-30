<?php

namespace App\Livewire\Admin\Geography;

use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\District;
use App\Models\State;
use App\Models\Tehsil;
use App\Models\Village;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Territory masters: State → District → Tehsil → Village (SRS §6).
 */
#[Title('Geography')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    /**
     * level => [model, parent foreign key, parent model]
     *
     * @var array<string, array{0: class-string<Model>, 1: ?string, 2: ?class-string<Model>}>
     */
    private const LEVELS = [
        'states' => [State::class, null, null],
        'districts' => [District::class, 'state_id', State::class],
        'tehsils' => [Tehsil::class, 'district_id', District::class],
        'villages' => [Village::class, 'tehsil_id', Tehsil::class],
    ];

    /** @var list<string> */
    protected array $sortable = ['name', 'code'];

    #[Url(except: 'districts')]
    public string $tab = 'districts';

    #[Url(except: '')]
    public string $stateFilter = '';

    #[Url(except: '')]
    public string $districtFilter = '';

    #[Url(except: '')]
    public string $tehsilFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $parent_id = null;

    public string $name = '';

    public string $code = '';

    public string $pin_code = '';

    public function mount(): void
    {
        $this->authorize('geography.view');
        $this->normaliseTab();
    }

    public function updatedTab(): void
    {
        $this->normaliseTab();
        $this->showForm = false;
        $this->resetPage();
    }

    public function updatedStateFilter(): void
    {
        $this->reset('districtFilter', 'tehsilFilter');
        $this->resetPage();
    }

    public function updatedDistrictFilter(): void
    {
        $this->reset('tehsilFilter');
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('geography.manage');
        $this->resetForm();
        $this->parent_id = match ($this->tab) {
            'districts' => $this->stateFilter !== '' ? (int) $this->stateFilter : null,
            'tehsils' => $this->districtFilter !== '' ? (int) $this->districtFilter : null,
            'villages' => $this->tehsilFilter !== '' ? (int) $this->tehsilFilter : null,
            default => null,
        };
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('geography.manage');
        [$model, $parentKey] = self::LEVELS[$this->tab];
        $record = $model::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $record->id;
        $this->name = $record->name;
        $this->code = (string) $record->code;
        $this->parent_id = $parentKey ? $record->{$parentKey} : null;
        $this->pin_code = $record instanceof Village ? (string) $record->pin_code : '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('geography.manage');
        [$model, $parentKey, $parentModel] = self::LEVELS[$this->tab];
        $table = (new $model)->getTable();

        $nameUnique = Rule::unique($table, 'name')->ignore($this->editingId);

        if ($parentKey) {
            $nameUnique->where($parentKey, $this->parent_id);
        }

        $rules = [
            'name' => ['required', 'string', 'max:150', $nameUnique],
            'code' => [$this->tab === 'states' ? 'required' : 'nullable', 'string', 'max:20', Rule::unique($table, 'code')->ignore($this->editingId)],
        ];

        if ($parentKey) {
            $rules['parent_id'] = ['required', Rule::exists((new $parentModel)->getTable(), 'id')];
        }

        if ($this->tab === 'villages') {
            $rules['pin_code'] = ['nullable', 'digits:6'];
        }

        $validated = $this->validate($rules, [
            'name.unique' => __('This name already exists under the selected parent.'),
        ], ['parent_id' => __('parent')]);

        $attributes = ['name' => trim($validated['name']), 'code' => $validated['code'] ?: null];

        if ($parentKey) {
            $attributes[$parentKey] = $validated['parent_id'];
        }

        if ($this->tab === 'villages') {
            $attributes['pin_code'] = $validated['pin_code'] ?: null;
        }

        $this->editingId
            ? $model::query()->findOrFail($this->editingId)->update($attributes)
            : $model::create($attributes);

        $this->showForm = false;
        $this->toast(__('Saved.'));
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('geography.manage');
        [$model] = self::LEVELS[$this->tab];
        $record = $model::query()->findOrFail($id);
        $record->update(['is_active' => ! $record->is_active]);

        $this->toast($record->is_active ? __('Activated.') : __('Deactivated. It will no longer be offered in forms.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.geography.index', [
            'records' => $this->applySorting($this->recordsQuery(), 'name', 'asc')->paginate($this->perPage),
            'states' => State::query()->orderBy('name')->pluck('name', 'id'),
            'districts' => District::query()->when($this->stateFilter !== '', fn ($query) => $query->where('state_id', $this->stateFilter))->orderBy('name')->pluck('name', 'id'),
            'tehsils' => Tehsil::query()->when($this->districtFilter !== '', fn ($query) => $query->where('district_id', $this->districtFilter))->orderBy('name')->pluck('name', 'id'),
            'parentOptions' => match ($this->tab) {
                'districts' => State::query()->active()->orderBy('name')->pluck('name', 'id'),
                'tehsils' => District::query()->active()->orderBy('name')->pluck('name', 'id'),
                'villages' => Tehsil::query()->active()->with('district:id,name')->orderBy('name')->get()->mapWithKeys(fn (Tehsil $tehsil) => [$tehsil->id => "{$tehsil->name} ({$tehsil->district->name})"]),
                default => collect(),
            },
        ]);
    }

    private function recordsQuery(): mixed
    {
        $term = $this->searchTerm();

        $query = match ($this->tab) {
            'states' => State::query()->withCount('districts'),
            'districts' => District::query()->with('state:id,name')->withCount('tehsils')
                ->when($this->stateFilter !== '', fn ($query) => $query->where('state_id', $this->stateFilter)),
            'tehsils' => Tehsil::query()->with('district:id,name')->withCount('villages')
                ->when($this->districtFilter !== '', fn ($query) => $query->where('district_id', $this->districtFilter))
                ->when($this->stateFilter !== '' && $this->districtFilter === '', fn ($query) => $query->whereHas('district', fn ($query) => $query->where('state_id', $this->stateFilter))),
            'villages' => Village::query()->with('tehsil.district:id,name')
                ->when($this->tehsilFilter !== '', fn ($query) => $query->where('tehsil_id', $this->tehsilFilter))
                ->when($this->districtFilter !== '' && $this->tehsilFilter === '', fn ($query) => $query->whereHas('tehsil', fn ($query) => $query->where('district_id', $this->districtFilter))),
        };

        return $query->when($term, fn ($query, string $term) => $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term)));
    }

    private function normaliseTab(): void
    {
        $this->tab = array_key_exists($this->tab, self::LEVELS) ? $this->tab : 'districts';
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'parent_id', 'name', 'code', 'pin_code');
        $this->resetValidation();
    }
}
