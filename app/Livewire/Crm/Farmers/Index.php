<?php

namespace App\Livewire\Crm\Farmers;

use App\Actions\Farmers\SaveFarmer;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithVillagePicker;
use App\Models\Farmer;
use App\Services\CrmDuplicateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Farmers')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable, WithVillagePicker;

    /** @var list<string> */
    protected array $sortable = ['farmer_no', 'name', 'created_at'];

    #[Url(except: '')]
    public string $district = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $father_name = '';

    public string $mobile = '';

    public string $alternate_mobile = '';

    public string $whatsapp_number = '';

    public string $address = '';

    public string $pin_code = '';

    public string $land_acres = '';

    public string $occupation = '';

    public string $remarks = '';

    /** @var list<int> */
    public array $duplicateIds = [];

    public bool $confirmNotDuplicate = false;

    public function mount(): void
    {
        $this->authorize('farmers.view');

        if (request()->boolean('new') && Auth::user()->can('farmers.create')) {
            $this->create();
            $this->mobile = (string) preg_replace('/\D/', '', (string) request('mobile'));
        }
    }

    public function create(): void
    {
        $this->authorize('farmers.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $farmerId): void
    {
        $this->authorize('farmers.update');
        $farmer = Farmer::query()->findOrFail($farmerId);

        $this->resetForm();
        $this->editingId = $farmer->id;
        $this->fill($farmer->only(['name', 'mobile']));

        foreach (['father_name', 'alternate_mobile', 'whatsapp_number', 'address', 'pin_code', 'land_acres', 'occupation', 'remarks'] as $field) {
            $this->{$field} = (string) $farmer->{$field};
        }

        $this->presetVillage($farmer->village_id);
        $this->showForm = true;
    }

    /**
     * Live duplicate hint while typing the mobile number.
     */
    public function updatedMobile(): void
    {
        $this->confirmNotDuplicate = false;
        $this->duplicateIds = strlen($this->mobile) === 10
            ? app(CrmDuplicateService::class)->farmers($this->mobile, exceptId: $this->editingId)->pluck('id')->all()
            : [];
    }

    public function save(SaveFarmer $saveFarmer): mixed
    {
        $this->authorize($this->editingId ? 'farmers.update' : 'farmers.create');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', 'digits:10', 'regex:/^[6-9]/'],
            'alternate_mobile' => ['nullable', 'digits:10', 'different:mobile'],
            'whatsapp_number' => ['nullable', 'digits:10'],
            'village_id' => ['required', Rule::exists('villages', 'id')->where('is_active', true)],
            'address' => ['nullable', 'string', 'max:500'],
            'pin_code' => ['nullable', 'digits:6'],
            'land_acres' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['mobile.regex' => __('Enter a valid Indian mobile number.')], ['village_id' => __('village')]);

        $attributes = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        try {
            $farmer = $saveFarmer->handle(
                $attributes,
                Auth::user()->workingBranch(),
                $this->editingId ? Farmer::query()->findOrFail($this->editingId) : null,
                $this->confirmNotDuplicate,
            );
        } catch (BusinessRuleException $exception) {
            if ($exception->rule === 'duplicate_farmer') {
                $this->duplicateIds = $exception->context['farmer_ids'];
                $this->addError('mobile', $exception->getMessage());

                return null;
            }

            $this->toast($exception->getMessage(), 'error');

            return null;
        }

        $this->showForm = false;
        $this->toast($this->editingId ? __('Farmer updated.') : __('Farmer :no created.', ['no' => $farmer->farmer_no]));

        return null;
    }

    public function render(): mixed
    {
        $user = Auth::user();

        $query = Farmer::query()
            ->with('village.tehsil.district')
            ->withCount('enquiries')
            ->when(! $user->hasAllBranchAccess(), fn (Builder $query) => $query->whereIn('branch_id', $user->accessibleBranches()->pluck('id')))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)->orWhere('farmer_no', 'like', $term)
                ->orWhere('mobile', 'like', $term)->orWhere('alternate_mobile', 'like', $term)))
            ->when($this->district !== '', fn (Builder $query) => $query->whereHas('village.tehsil', fn (Builder $query) => $query->where('district_id', $this->district)));

        return view('livewire.crm.farmers.index', [
            'farmers' => $this->applySorting($query, 'id', 'desc')->paginate($this->perPage),
            'picker' => $this->villagePickerOptions(),
            'duplicates' => $this->duplicateIds === [] ? new Collection : Farmer::query()->with('village.tehsil.district')->withCount('enquiries')->whereKey($this->duplicateIds)->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'father_name', 'mobile', 'alternate_mobile', 'whatsapp_number', 'address', 'pin_code',
            'land_acres', 'occupation', 'remarks', 'duplicateIds', 'confirmNotDuplicate', 'village_id', 'pickDistrictId', 'pickTehsilId');
        $this->resetValidation();
    }
}
