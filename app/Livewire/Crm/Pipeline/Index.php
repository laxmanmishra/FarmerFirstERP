<?php

namespace App\Livewire\Crm\Pipeline;

use App\Actions\Pipeline\MoveEnquiryStage;
use App\Enums\Temperature;
use App\Livewire\Concerns\InteractsWithUi;
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

/**
 * Kanban of validated, open enquiries by pipeline stage (SRS §11). Dragging requires
 * pipeline.move; moves to stages needing a reason or remark open a confirmation.
 */
#[Title('Sales Pipeline')]
class Index extends Component
{
    use InteractsWithUi;

    private const CARDS_PER_COLUMN = 50;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $assignee = '';

    #[Url(except: '')]
    public string $temperature = '';

    public mixed $modal = null;

    public ?int $movingEnquiryId = null;

    public ?int $movingStageId = null;

    public string $remarks = '';

    public string $closeReason = '';

    public function mount(): void
    {
        $this->authorize('pipeline.view');
    }

    /**
     * Called by the drag-and-drop handler.
     */
    public function drop(int $enquiryId, int $stageId, MoveEnquiryStage $move): void
    {
        $this->authorize('pipeline.move');
        $enquiry = $this->visible()->findOrFail($enquiryId);
        $stage = WorkflowStage::query()->ofDefinition(WorkflowDefinition::SALES_PIPELINE)->findOrFail($stageId);

        if ($enquiry->pipeline_stage_id === $stage->id) {
            return;
        }

        if ($stage->is_final || $stage->requires_remark || $stage->requires_followup) {
            $this->reset('remarks', 'closeReason');
            $this->resetValidation();
            $this->movingEnquiryId = $enquiry->id;
            $this->movingStageId = $stage->id;
            $this->modal = 'move';

            return;
        }

        if ($this->attempt(fn () => $move->handle(Auth::user(), $enquiry, $stage))) {
            $this->toast(__(':no moved to :stage.', ['no' => $enquiry->enquiry_no, 'stage' => $stage->name]));
        }
    }

    public function confirmMove(MoveEnquiryStage $move): void
    {
        $this->authorize('pipeline.move');
        $this->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        $enquiry = $this->visible()->findOrFail($this->movingEnquiryId);
        $stage = WorkflowStage::query()->ofDefinition(WorkflowDefinition::SALES_PIPELINE)->findOrFail($this->movingStageId);

        if ($this->attempt(fn () => $move->handle(Auth::user(), $enquiry, $stage, $this->remarks ?: null, $this->closeReason ?: null))) {
            $this->modal = null;
            $this->toast(__(':no moved to :stage.', ['no' => $enquiry->enquiry_no, 'stage' => $stage->name]));
        }
    }

    public function render(): mixed
    {
        $user = Auth::user();
        $stages = WorkflowStage::query()->ofDefinition(WorkflowDefinition::SALES_PIPELINE)->active()->orderBy('sequence')->get();
        $openStages = $stages->where('is_final', false);

        $cards = $this->visible()
            ->whereNull('closed_at')
            ->whereNotNull('pipeline_stage_id')
            ->with(['farmer.village', 'assignee:id,name', 'requirements.product', 'requirements.brand'])
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('enquiry_no', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('farmer', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%')->orWhere('mobile', 'like', '%'.trim($this->search).'%'))))
            ->when($this->assignee !== '', fn (Builder $query) => $query->where('assigned_employee_id', $this->assignee))
            ->when(Temperature::tryFrom($this->temperature), fn (Builder $query, Temperature $temperature) => $query->where('temperature', $temperature))
            ->orderBy('expected_purchase_date')
            ->get()
            ->groupBy('pipeline_stage_id');

        $closedThisMonth = $this->visible()->where('closed_at', '>=', now()->startOfMonth())->whereNotNull('pipeline_stage_id')
            ->selectRaw('pipeline_stage_id, count(*) as total')->groupBy('pipeline_stage_id')->pluck('total', 'pipeline_stage_id');

        return view('livewire.crm.pipeline.index', [
            'openStages' => $openStages,
            'finalStages' => $stages->where('is_final', true),
            'cards' => $cards,
            'perColumn' => self::CARDS_PER_COLUMN,
            'closedThisMonth' => $closedThisMonth,
            'canMove' => $user->can('pipeline.move'),
            'assignees' => $user->canAny(['enquiries.view_team', 'enquiries.view_all'])
                ? Employee::query()->active()->whereHas('departments', fn (Builder $query) => $query->where('code', 'SALES'))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'movingStage' => $this->movingStageId ? $stages->firstWhere('id', $this->movingStageId) : null,
            'movingEnquiry' => $this->movingEnquiryId ? Enquiry::query()->find($this->movingEnquiryId) : null,
            'closeReasons' => LookupValue::options(LookupValue::CLOSE_REASON),
        ]);
    }

    /**
     * @return Builder<Enquiry>
     */
    private function visible(): Builder
    {
        return Enquiry::query()->visibleTo(Auth::user());
    }
}
