<?php

namespace App\Livewire\Fulfilment\Documents;

use App\Enums\DocumentStatus;
use App\Enums\RequirementState;
use App\Enums\RequirementStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Document Center (SRS §205–217): live documentation dashboard whose every counter
 * drills down to the exact requirement rows, the repository and the verification queue.
 */
class Index extends Component
{
    use WithDataTable;

    public const TABS = ['dashboard', 'requirements', 'repository', 'verification'];

    #[Url(except: '')]
    public string $tab = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $employee = '';

    #[Url(except: '')]
    public string $age = '';

    #[Url(except: false)]
    public bool $blocking = false;

    #[Url(except: false)]
    public bool $overdue = false;

    public function mount(): void
    {
        $this->authorize('documents.view');
        $this->normaliseTab();
    }

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->normaliseTab();
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'type', 'department', 'employee', 'age', 'blocking', 'overdue', 'search');
        $this->resetPage();
    }

    public function render(): mixed
    {
        $data = match ($this->tab) {
            'dashboard' => $this->dashboard(),
            'requirements' => ['requirements' => $this->requirementsQuery()->paginate($this->perPage)],
            'repository' => ['documents' => $this->repositoryQuery()->paginate($this->perPage)],
            default => ['documents' => $this->verificationQuery()->paginate($this->perPage)],
        };

        return view('livewire.fulfilment.documents.index', $data + [
            'tabs' => $this->allowedTabs(),
            'types' => DocumentType::query()->orderBy('sort_order')->pluck('name', 'id'),
            'departments' => Department::query()->whereHas('employees')->orWhereIn('id', DocumentRequirement::query()->select('department_id'))->orderBy('sort_order')->pluck('name', 'id'),
            'statuses' => collect(RequirementStatus::cases())->mapWithKeys(fn (RequirementStatus $status) => [$status->value => $status->label()]),
            'ageing' => DocumentRequirement::AGEING_BUCKETS,
            'hasFilters' => $this->status !== '' || $this->type !== '' || $this->department !== '' || $this->employee !== '' || $this->age !== '' || $this->blocking || $this->overdue || $this->search !== '',
        ])->title(__('Documents'));
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(): array
    {
        $base = fn (): Builder => DocumentRequirement::query()->visibleTo(Auth::user())->onOpenOrders();
        $columns = RequirementStatus::dashboardColumns();

        $byStatus = [];
        $matrix = [];

        foreach ($columns as $status) {
            $counts = $base()->withStatus($status)->groupBy('document_type_id')->selectRaw('document_type_id, count(*) as aggregate')->pluck('aggregate', 'document_type_id');
            $byStatus[$status->value] = (int) $counts->sum();

            foreach ($counts as $typeId => $count) {
                $matrix[$typeId][$status->value] = (int) $count;
            }
        }

        $unsatisfied = fn (): Builder => $base()->unsatisfied();

        return [
            'byStatus' => $byStatus,
            'columns' => $columns,
            'matrix' => $matrix,
            'blockingCount' => $base()->blockingDelivery()->count(),
            'overdueCount' => $unsatisfied()->whereDate('due_date', '<', today())->count(),
            'byDepartment' => Department::query()->orderBy('sort_order')->get(['id', 'name'])->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'open' => $unsatisfied()->where('department_id', $department->id)->count(),
                'blocking' => $base()->blockingDelivery()->where('department_id', $department->id)->count(),
                'overdue' => $unsatisfied()->where('department_id', $department->id)->whereDate('due_date', '<', today())->count(),
            ])->filter(fn (array $row) => $row['open'] > 0)->values(),
            'byEmployee' => $this->employeeLoad($unsatisfied()),
            'ageingCounts' => collect(DocumentRequirement::AGEING_BUCKETS)->map(fn (array $range) => $unsatisfied()->agedBetween($range[0], $range[1])->count()),
        ];
    }

    /**
     * @param  Builder<DocumentRequirement>  $query
     * @return Collection<int, array{id: string, name: string, open: int, overdue: int}>
     */
    private function employeeLoad(Builder $query): Collection
    {
        $rows = $query->groupBy('responsible_employee_id')
            ->selectRaw('responsible_employee_id, count(*) as aggregate, sum(case when due_date < ? then 1 else 0 end) as overdue', [today()->toDateString()])
            ->get();
        $names = Employee::query()->whereKey($rows->pluck('responsible_employee_id')->filter())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'id' => $row->responsible_employee_id ? (string) $row->responsible_employee_id : 'none',
            'name' => $row->responsible_employee_id ? ($names[$row->responsible_employee_id] ?? '—') : __('Unassigned'),
            'open' => (int) $row->aggregate,
            'overdue' => (int) $row->overdue,
        ])->sortByDesc('open')->values();
    }

    /**
     * @return Builder<DocumentRequirement>
     */
    private function requirementsQuery(): Builder
    {
        $status = RequirementStatus::tryFrom($this->status);
        $bucket = DocumentRequirement::AGEING_BUCKETS[$this->age] ?? null;

        return DocumentRequirement::query()->visibleTo(Auth::user())->onOpenOrders()
            ->with(['order:id,order_no,customer_id', 'order.customer:id,name,mobile', 'documentType', 'department:id,name', 'responsible:id,name', 'document.type'])
            ->when($status, fn (Builder $query, RequirementStatus $status) => $query->withStatus($status), fn (Builder $query) => $query->where('requirement_state', '!=', RequirementState::NotRequired))
            ->when(ctype_digit($this->type), fn (Builder $query) => $query->where('document_type_id', $this->type))
            ->when(ctype_digit($this->department), fn (Builder $query) => $query->where('department_id', $this->department))
            ->when($this->employee === 'none', fn (Builder $query) => $query->whereNull('responsible_employee_id'))
            ->when(ctype_digit($this->employee), fn (Builder $query) => $query->where('responsible_employee_id', $this->employee))
            ->when($this->blocking, fn (Builder $query) => $query->blockingDelivery())
            ->when($this->overdue, fn (Builder $query) => $query->unsatisfied()->whereDate('due_date', '<', today()))
            ->when($bucket, fn (Builder $query) => $query->unsatisfied()->agedBetween($bucket[0], $bucket[1]))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->whereHas('order', fn (Builder $query) => $query
                ->where('order_no', 'like', $term)
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term))))
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * @return Builder<Document>
     */
    private function repositoryQuery(): Builder
    {
        return Document::query()->visibleTo(Auth::user())
            ->with(['type', 'customer:id,name,customer_no', 'order:id,order_no', 'currentVersion.uploader:id,name'])
            ->when(ctype_digit($this->type), fn (Builder $query) => $query->where('document_type_id', $this->type))
            ->when(DocumentStatus::tryFrom($this->status), fn (Builder $query, DocumentStatus $status) => $query->where('status', $status))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('document_no', 'like', $term)
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('customer_no', 'like', $term))))
            ->latest('id');
    }

    /**
     * @return Builder<Document>
     */
    private function verificationQuery(): Builder
    {
        return $this->repositoryQuery()->awaitingVerificationBy(Auth::user())->reorder()->oldest('updated_at');
    }

    /**
     * @return array<string, string>
     */
    private function allowedTabs(): array
    {
        return array_filter([
            'dashboard' => Auth::user()->can('documents.dashboard') ? __('Dashboard') : null,
            'requirements' => __('Requirements'),
            'repository' => __('Repository'),
            'verification' => Auth::user()->can('documents.verify') ? __('Verification queue') : null,
        ]);
    }

    private function normaliseTab(): void
    {
        $allowed = array_keys($this->allowedTabs());
        $this->tab = in_array($this->tab, $allowed, true) ? $this->tab : $allowed[0];
    }
}
