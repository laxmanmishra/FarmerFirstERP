<?php

namespace App\Livewire\Crm\Enquiries;

use App\Enums\Temperature;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\LookupValue;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Enquiries')]
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['enquiry_no', 'expected_purchase_date', 'created_at', 'last_activity_at'];

    #[Url(except: 'open')]
    public string $view = 'open';

    #[Url(except: '')]
    public string $stage = '';

    #[Url(except: '')]
    public string $temperature = '';

    #[Url(except: '')]
    public string $assignee = '';

    #[Url(except: '')]
    public string $source = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->canAny(['enquiries.view_own', 'enquiries.view_team', 'enquiries.view_all']), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'stage', 'temperature', 'assignee', 'source'], true)) {
            $this->resetPage();
        }
    }

    public function render(): mixed
    {
        $user = Auth::user();

        $query = Enquiry::query()
            ->visibleTo($user)
            ->with(['farmer.village', 'assignee:id,name', 'validationStage', 'pipelineStage', 'requirements.product', 'requirements.brand'])
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('enquiry_no', 'like', $term)
                ->orWhereHas('farmer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('alternate_mobile', 'like', $term))))
            ->when($this->view === 'open', fn (Builder $query) => $query->whereNull('closed_at'))
            ->when($this->view === 'validation', fn (Builder $query) => $query->whereNull('pipeline_stage_id')->whereNull('closed_at'))
            ->when($this->view === 'pipeline', fn (Builder $query) => $query->whereNotNull('pipeline_stage_id')->whereNull('closed_at'))
            ->when($this->view === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
            ->when($this->stage !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('pipeline_stage_id', $this->stage)
                ->orWhere(fn (Builder $query) => $query->whereNull('pipeline_stage_id')->where('validation_stage_id', $this->stage))))
            ->when(Temperature::tryFrom($this->temperature), fn (Builder $query, Temperature $temperature) => $query->where('temperature', $temperature))
            ->when($this->assignee === 'none', fn (Builder $query) => $query->whereNull('assigned_employee_id'))
            ->when(ctype_digit($this->assignee), fn (Builder $query) => $query->where('assigned_employee_id', $this->assignee))
            ->when($this->source !== '', fn (Builder $query) => $query->where('source_code', $this->source));

        return view('livewire.crm.enquiries.index', [
            'enquiries' => $this->applySorting($query, 'id', 'desc')->paginate($this->perPage),
            'stages' => WorkflowStage::query()->with('definition:id,code')
                ->whereHas('definition', fn (Builder $query) => $query->whereIn('code', [WorkflowDefinition::ENQUIRY_VALIDATION, WorkflowDefinition::SALES_PIPELINE]))
                ->orderBy('workflow_definition_id')->orderBy('sequence')->get(),
            'assignees' => $user->canAny(['enquiries.view_team', 'enquiries.view_all'])
                ? Employee::query()->active()->whereHas('departments', fn (Builder $query) => $query->where('code', 'SALES'))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'sources' => LookupValue::options(LookupValue::ENQUIRY_SOURCE),
        ]);
    }
}
