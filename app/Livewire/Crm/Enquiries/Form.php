<?php

namespace App\Livewire\Crm\Enquiries;

use App\Actions\Enquiries\CreateEnquiry;
use App\Actions\Enquiries\EnquiryData;
use App\Actions\Enquiries\UpdateEnquiry;
use App\Actions\Farmers\SaveFarmer;
use App\Enums\DealType;
use App\Enums\ProductType;
use App\Enums\Temperature;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithVillagePicker;
use App\Models\Brand;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\EnquiryAttachment;
use App\Models\Farmer;
use App\Models\LookupValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CrmDuplicateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Create / edit an enquiry (SRS §7–9). Mobile-friendly single page.
 */
class Form extends Component
{
    use InteractsWithUi, WithFileUploads, WithVillagePicker;

    #[Locked]
    public ?int $enquiryId = null;

    // Farmer selection
    public ?int $farmerId = null;

    public string $farmerSearch = '';

    public bool $creatingFarmer = false;

    /** @var array{name: string, father_name: string, mobile: string, alternate_mobile: string} */
    public array $newFarmer = ['name' => '', 'father_name' => '', 'mobile' => '', 'alternate_mobile' => ''];

    public bool $confirmNewFarmer = false;

    // Enquiry
    public string $source_code = '';

    public string $deal_type = 'new';

    public string $expected_purchase_date = '';

    public string $budget = '';

    public string $remarks = '';

    public ?int $assigneeId = null;

    /** @var list<array{requirement_type: string, brand_id: ?int, product_id: ?int, product_variant_id: ?int, quantity: int, description: string}> */
    public array $requirements = [];

    /** @var array<string, mixed> */
    public array $exchange = [];

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    // Duplicate review
    /** @var list<int> */
    public array $duplicateEnquiryIds = [];

    /** @var list<int> */
    public array $duplicateFarmerIds = [];

    public string $duplicateOverrideReason = '';

    public function mount(?Enquiry $enquiry = null): void
    {
        if ($enquiry?->exists) {
            $this->authorize('enquiries.update');
            abort_unless(Enquiry::query()->visibleTo(Auth::user())->whereKey($enquiry->id)->exists(), 404);

            $this->enquiryId = $enquiry->id;
            $this->farmerId = $enquiry->farmer_id;
            $this->source_code = $enquiry->source_code;
            $this->deal_type = $enquiry->deal_type->value;
            $this->expected_purchase_date = $enquiry->expected_purchase_date->toDateString();
            $this->budget = (string) $enquiry->budget;
            $this->remarks = (string) $enquiry->remarks;
            $this->requirements = $enquiry->requirements->map(fn ($line) => [
                'requirement_type' => $line->requirement_type->value,
                'brand_id' => $line->brand_id,
                'product_id' => $line->product_id,
                'product_variant_id' => $line->product_variant_id,
                'quantity' => $line->quantity,
                'description' => (string) $line->description,
            ])->all();
            $this->exchange = $enquiry->exchangeTractor?->only(['brand_name', 'model_name', 'manufacturing_year', 'hours_used', 'registration_number', 'condition', 'customer_expected_price', 'remarks']) ?? [];

            return;
        }

        $this->authorize('enquiries.create');

        if ($farmer = Farmer::query()->find(request()->integer('farmer'))) {
            $this->farmerId = $farmer->id;
        }

        $this->expected_purchase_date = today()->addDays(15)->toDateString();
        $this->addRequirement();
    }

    public function updatedFarmerSearch(): void
    {
        $this->farmerSearch = preg_replace('/[^\d\p{L} ]/u', '', $this->farmerSearch);
    }

    public function selectFarmer(int $farmerId): void
    {
        $this->farmerId = Farmer::query()->findOrFail($farmerId)->id;
        $this->creatingFarmer = false;
        $this->reset('duplicateEnquiryIds', 'duplicateOverrideReason');
    }

    public function startNewFarmer(): void
    {
        $this->authorize('farmers.create');
        $this->creatingFarmer = true;
        $this->farmerId = null;
        $this->newFarmer['mobile'] = ctype_digit($this->farmerSearch) ? $this->farmerSearch : '';
        $this->newFarmer['name'] = ctype_digit($this->farmerSearch) ? '' : $this->farmerSearch;
    }

    public function clearFarmer(): void
    {
        $this->reset('farmerId', 'creatingFarmer', 'duplicateEnquiryIds', 'duplicateFarmerIds', 'duplicateOverrideReason', 'confirmNewFarmer');
    }

    public function addRequirement(): void
    {
        if (count($this->requirements) < 10) {
            $this->requirements[] = ['requirement_type' => ProductType::Tractor->value, 'brand_id' => null, 'product_id' => null, 'product_variant_id' => null, 'quantity' => 1, 'description' => ''];
        }
    }

    public function removeRequirement(int $index): void
    {
        if (count($this->requirements) > 1) {
            unset($this->requirements[$index]);
            $this->requirements = array_values($this->requirements);
        }
    }

    public function updatedRequirements(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [null, null];

        if ($field === 'brand_id' || $field === 'requirement_type') {
            $this->requirements[$index]['product_id'] = null;
            $this->requirements[$index]['product_variant_id'] = null;
        }

        if ($field === 'product_id') {
            $this->requirements[$index]['product_variant_id'] = null;
        }
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function save(CreateEnquiry $create, UpdateEnquiry $update, SaveFarmer $saveFarmer): mixed
    {
        $this->authorize($this->enquiryId ? 'enquiries.update' : 'enquiries.create');
        $user = Auth::user();

        $validated = $this->validate([
            ...EnquiryData::rules(),
            'photos' => ['array', 'max:'.config('erp.crm.exchange_photo_max_count')],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('erp.crm.exchange_photo_max_kb')],
            'assigneeId' => ['nullable', Rule::exists('employees', 'id')->where('is_active', true)],
            ...$this->farmerRules(),
        ], attributes: [
            'requirements.*.requirement_type' => __('requirement type'),
            'requirements.*.quantity' => __('quantity'),
            'exchange.brand_name' => __('old tractor brand'),
            'exchange.model_name' => __('old tractor model'),
            'newFarmer.name' => __('farmer name'),
            'newFarmer.mobile' => __('mobile'),
            'village_id' => __('village'),
        ]);

        $data = EnquiryData::fromArray($validated);

        try {
            if ($this->enquiryId) {
                $enquiry = $update->handle($user, Enquiry::query()->findOrFail($this->enquiryId), $data, $this->photos);
                $this->toast(__('Enquiry updated.'));

                return $this->redirectRoute('crm.enquiries.show', $enquiry, navigate: true);
            }

            $enquiry = DB::transaction(function () use ($user, $create, $saveFarmer, $data): Enquiry {
                $farmer = $this->farmerId
                    ? Farmer::query()->findOrFail($this->farmerId)
                    : $saveFarmer->handle([
                        'name' => trim($this->newFarmer['name']),
                        'father_name' => trim($this->newFarmer['father_name']) ?: null,
                        'mobile' => $this->newFarmer['mobile'],
                        'alternate_mobile' => $this->newFarmer['alternate_mobile'] ?: null,
                        'village_id' => $this->village_id,
                    ], $user->workingBranch(), confirmedNotDuplicate: $this->confirmNewFarmer);

                return $create->handle(
                    $user,
                    $farmer,
                    $data,
                    $user->can('enquiries.assign') ? $this->assigneeId : null,
                    $this->duplicateOverrideReason,
                    $this->photos,
                );
            });
        } catch (BusinessRuleException $exception) {
            return $this->handleRuleFailure($exception);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('Enquiry :no created and queued for telecaller validation.', ['no' => $enquiry->enquiry_no])]);

        return $this->redirectRoute('crm.enquiries.show', $enquiry, navigate: true);
    }

    public function render(): mixed
    {
        $productIds = array_filter(array_column($this->requirements, 'product_id'));
        $farmer = $this->farmerId ? Farmer::query()->with('village.tehsil.district')->withCount('enquiries')->find($this->farmerId) : null;

        $temperature = null;

        try {
            $date = CarbonImmutable::parse($this->expected_purchase_date);
            $temperature = $date->isBefore(today()) ? null : Temperature::fromExpectedDate($date);
        } catch (\Throwable) {
        }

        return view('livewire.crm.enquiries.form', [
            'farmer' => $farmer,
            'farmerMatches' => $this->farmerMatches(),
            'temperature' => $temperature,
            'sources' => LookupValue::options(LookupValue::ENQUIRY_SOURCE),
            'dealTypes' => DealType::options(),
            'productTypes' => ProductType::options(),
            'brands' => Brand::query()->active()->orderByDesc('is_dealer_brand')->orderBy('name')->pluck('name', 'id'),
            'products' => Product::query()->active()->with('brand:id,name')->orderBy('name')->get(),
            'variants' => $productIds === [] ? collect() : ProductVariant::query()->active()->whereIn('product_id', $productIds)->orderBy('name')->get(),
            'assignees' => Auth::user()->can('enquiries.assign')
                ? Employee::query()->active()->whereHas('departments', fn ($query) => $query->where('code', 'SALES'))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'duplicateEnquiries' => $this->duplicateEnquiryIds === [] ? new Collection
                : Enquiry::query()->with(['farmer', 'assignee:id,name', 'validationStage', 'pipelineStage', 'requirements.product', 'requirements.brand'])->whereKey($this->duplicateEnquiryIds)->get(),
            'duplicateFarmers' => $this->duplicateFarmerIds === [] ? new Collection
                : Farmer::query()->with('village.tehsil.district')->whereKey($this->duplicateFarmerIds)->get(),
            'existingPhotos' => $this->enquiryId ? EnquiryAttachment::query()->where('enquiry_id', $this->enquiryId)->get() : collect(),
            'picker' => $this->villagePickerOptions(),
        ])->title($this->enquiryId ? __('Edit enquiry') : __('New enquiry'));
    }

    /**
     * @return array<string, mixed>
     */
    private function farmerRules(): array
    {
        if ($this->enquiryId || $this->farmerId) {
            return ['farmerId' => ['required', Rule::exists('farmers', 'id')]];
        }

        if (! $this->creatingFarmer) {
            return ['farmerId' => ['required']];
        }

        return [
            'newFarmer.name' => ['required', 'string', 'max:255'],
            'newFarmer.father_name' => ['nullable', 'string', 'max:255'],
            'newFarmer.mobile' => ['required', 'digits:10', 'regex:/^[6-9]/'],
            'newFarmer.alternate_mobile' => ['nullable', 'digits:10', 'different:newFarmer.mobile'],
            'village_id' => ['required', Rule::exists('villages', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return Collection<int, Farmer>
     */
    private function farmerMatches(): Collection
    {
        $term = trim($this->farmerSearch);

        if ($this->farmerId || mb_strlen($term) < 3) {
            return new Collection;
        }

        if (ctype_digit($term)) {
            return app(CrmDuplicateService::class)->farmers(strlen($term) === 10 ? $term : null)
                ->whenEmpty(fn () => Farmer::query()->with('village.tehsil.district')->where('mobile', 'like', $term.'%')->limit(8)->get());
        }

        return Farmer::query()->with('village.tehsil.district')->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%')->limit(8)->get();
    }

    private function handleRuleFailure(BusinessRuleException $exception): mixed
    {
        match ($exception->rule) {
            'duplicate_enquiry' => $this->duplicateEnquiryIds = $exception->context['enquiry_ids'],
            'duplicate_farmer' => $this->duplicateFarmerIds = $exception->context['farmer_ids'],
            default => null,
        };

        if (in_array($exception->rule, ['duplicate_enquiry', 'duplicate_farmer'], true)) {
            $this->addError($exception->rule, $exception->getMessage());
        } else {
            $this->toast($exception->getMessage(), 'error');
        }

        return null;
    }
}
