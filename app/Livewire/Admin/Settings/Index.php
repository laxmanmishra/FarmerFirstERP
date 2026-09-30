<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Branch;
use App\Models\Company;
use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Company profile, financial year and document number series (SRS v6.1 §8–9).
 */
#[Title('System Settings')]
class Index extends Component
{
    use InteractsWithUi;

    #[Url(except: 'company')]
    public string $tab = 'company';

    /** @var array<string, mixed> */
    public array $company = [];

    public bool $showSeriesForm = false;

    public ?int $seriesId = null;

    /** @var array<string, mixed> */
    public array $series = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->canAny(['settings.view', 'number_series.manage']), 403);

        $this->normaliseTab();

        if (Auth::user()->can('settings.view')) {
            $this->company = Company::current()?->only(['code', 'name', 'legal_name', 'gstin', 'pan', 'address', 'phone', 'email', 'financial_year_start_month']) ?? [];
        }
    }

    public function updatedTab(): void
    {
        $this->normaliseTab();
    }

    private function normaliseTab(): void
    {
        $allowed = array_keys(array_filter([
            'company' => Auth::user()->can('settings.view'),
            'numbering' => Auth::user()->can('number_series.manage'),
            'lists' => Auth::user()->can('settings.view'),
        ]));

        $this->tab = in_array($this->tab, $allowed, true) ? $this->tab : $allowed[0];
    }

    public function saveCompany(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'company.name' => ['required', 'string', 'max:255'],
            'company.legal_name' => ['nullable', 'string', 'max:255'],
            'company.gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'company.pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'company.address' => ['nullable', 'string', 'max:1000'],
            'company.phone' => ['nullable', 'string', 'max:20'],
            'company.email' => ['nullable', 'email:rfc', 'max:255'],
            'company.financial_year_start_month' => ['required', 'integer', 'between:1,12'],
        ], attributes: ['company.gstin' => 'GSTIN', 'company.pan' => 'PAN']);

        $attributes = array_map(fn ($value) => $value === '' ? null : $value, $validated['company']);
        Company::current()->update($attributes);

        $this->toast(__('Company settings saved.'));
    }

    public function editSeries(int $id): void
    {
        $this->authorize('number_series.manage');
        $record = NumberSeries::query()->findOrFail($id);

        $this->resetValidation();
        $this->seriesId = $record->id;
        $this->series = $record->only(['name', 'prefix', 'format', 'padding', 'reset_policy', 'per_branch', 'is_active']);
        $this->showSeriesForm = true;
    }

    public function saveSeries(): void
    {
        $this->authorize('number_series.manage');
        $record = NumberSeries::query()->findOrFail($this->seriesId);

        $validated = $this->validate([
            'series.name' => ['required', 'string', 'max:100'],
            'series.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'series.format' => ['required', 'string', 'max:100', 'regex:/\{seq\}/'],
            'series.padding' => ['required', 'integer', 'between:1,12'],
            'series.reset_policy' => ['required', Rule::in([NumberSeries::RESET_NEVER, NumberSeries::RESET_FINANCIAL_YEAR])],
            'series.per_branch' => ['boolean'],
            'series.is_active' => ['boolean'],
        ], [
            'series.format.regex' => __('The format must contain the {seq} token.'),
        ]);

        $attributes = $validated['series'];

        if ($attributes['per_branch'] && ! str_contains($attributes['format'], '{branch}')) {
            $this->addError('series.format', __('Branch-specific series must include the {branch} token so numbers stay unique.'));

            return;
        }

        if ($attributes['reset_policy'] === NumberSeries::RESET_FINANCIAL_YEAR && ! str_contains($attributes['format'], '{fy}') && ! str_contains($attributes['format'], '{yyyy}')) {
            $this->addError('series.format', __('Series that reset every financial year must include {fy} so numbers stay unique.'));

            return;
        }

        $record->update($attributes);
        $this->showSeriesForm = false;
        $this->toast(__('Number series updated. It applies to numbers issued from now on.'));
    }

    public function render(NumberSeriesService $numbers): mixed
    {
        $sampleBranch = Branch::query()->active()->find(Auth::user()->current_branch_id) ?? Branch::query()->active()->first();
        $seriesList = NumberSeries::query()->orderBy('name')->get();

        return view('livewire.admin.settings.index', [
            'seriesList' => $seriesList,
            'previews' => $seriesList->mapWithKeys(fn (NumberSeries $series) => [
                $series->id => $series->is_active ? $numbers->preview($series->entity, $sampleBranch) : null,
            ]),
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => now()->startOfYear()->month($month)->format('F')]),
            'canManageSettings' => Auth::user()->can('settings.manage'),
        ]);
    }
}
