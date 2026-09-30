<?php

namespace App\Livewire\Admin\Workflows;

use App\Actions\Workflow\SaveWorkflowStage;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowStatusHistory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $definitionId;

    public bool $showStageForm = false;

    public ?int $stageId = null;

    /** @var array<string, mixed> */
    public array $stage = [];

    public bool $showTransitionForm = false;

    /** @var array<string, mixed> */
    public array $transition = [];

    public function mount(WorkflowDefinition $definition): void
    {
        $this->authorize('workflow.view');
        $this->definitionId = $definition->id;
    }

    public function createStage(): void
    {
        $this->authorize('workflow.configure');
        $this->resetValidation();
        $this->stageId = null;
        $this->stage = ['code' => '', 'name' => '', 'color' => 'slate', 'sla_hours' => null] + array_fill_keys(WorkflowStage::FLAGS, false);
        $this->showStageForm = true;
    }

    public function editStage(int $id): void
    {
        $this->authorize('workflow.configure');
        $record = $this->definition()->stages()->findOrFail($id);

        $this->resetValidation();
        $this->stageId = $record->id;
        $this->stage = $record->only(['code', 'name', 'color', 'sla_hours', ...WorkflowStage::FLAGS]);
        $this->showStageForm = true;
    }

    public function saveStage(SaveWorkflowStage $save): void
    {
        $this->authorize('workflow.configure');
        $definition = $this->definition();

        $validated = $this->validate([
            'stage.code' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('workflow_stages', 'code')->where('workflow_definition_id', $definition->id)->ignore($this->stageId)],
            'stage.name' => ['required', 'string', 'max:100'],
            'stage.color' => ['required', Rule::in(WorkflowStage::COLORS)],
            'stage.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            ...collect(WorkflowStage::FLAGS)->mapWithKeys(fn ($flag) => ["stage.{$flag}" => ['boolean']])->all(),
        ], ['stage.code.regex' => __('Use UPPER_CASE letters, digits and underscores, starting with a letter.')],
            ['stage.code' => __('code'), 'stage.name' => __('name')]);

        $attributes = $validated['stage'];
        $attributes['sla_hours'] = $attributes['sla_hours'] ?: null;

        if ($this->attempt(fn () => $save->handle($definition, $attributes, $this->stageId ? $definition->stages()->find($this->stageId) : null))) {
            $this->showStageForm = false;
            $this->toast(__('Stage saved.'));
        }
    }

    public function toggleStage(int $id, SaveWorkflowStage $save): void
    {
        $this->authorize('workflow.configure');

        if ($stage = $this->attempt(fn () => $save->toggleActive($this->definition()->stages()->findOrFail($id)))) {
            $this->toast($stage->is_active ? __('Stage activated.') : __('Stage deactivated. Existing records keep it in their history.'));
        }
    }

    public function deleteStage(int $id, SaveWorkflowStage $save): void
    {
        $this->authorize('workflow.configure');

        if ($this->attempt(fn () => $save->delete($this->definition()->stages()->findOrFail($id)) ?? true)) {
            $this->toast(__('Stage deleted.'));
        }
    }

    public function move(int $id, string $direction, SaveWorkflowStage $save): void
    {
        $this->authorize('workflow.configure');
        $ids = $this->definition()->stages()->pluck('id')->all();
        $index = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        $save->reorder($this->definition(), $ids);
    }

    public function toggleControlled(): void
    {
        $this->authorize('workflow.configure');
        $definition = $this->definition();

        if (! $definition->controlled_transitions && ! $definition->transitions()->active()->exists()) {
            $this->toast(__('Add at least one transition before enabling controlled transitions.'), 'error');

            return;
        }

        $definition->update(['controlled_transitions' => ! $definition->controlled_transitions]);
        $this->toast($definition->controlled_transitions ? __('Only configured transitions are now allowed.') : __('Any move between active stages is now allowed.'));
    }

    public function createTransition(): void
    {
        $this->authorize('workflow.configure');
        $this->resetValidation();
        $this->transition = ['from_stage_id' => '', 'to_stage_id' => '', 'allowed_roles' => [], 'requires_approval' => false, 'effective_from' => '', 'effective_to' => ''];
        $this->showTransitionForm = true;
    }

    public function saveTransition(): void
    {
        $this->authorize('workflow.configure');
        $definition = $this->definition();
        $stageIds = $definition->stages()->pluck('id')->all();

        $validated = $this->validate([
            'transition.from_stage_id' => ['nullable', Rule::in($stageIds)],
            'transition.to_stage_id' => ['required', Rule::in($stageIds), 'different:transition.from_stage_id'],
            'transition.allowed_roles' => ['array'],
            'transition.allowed_roles.*' => [Rule::exists('roles', 'name')],
            'transition.requires_approval' => ['boolean'],
            'transition.effective_from' => ['nullable', 'date'],
            'transition.effective_to' => ['nullable', 'date', 'after_or_equal:transition.effective_from'],
        ], attributes: ['transition.to_stage_id' => __('target stage'), 'transition.from_stage_id' => __('source stage')])['transition'];

        $definition->transitions()->create([
            'from_stage_id' => $validated['from_stage_id'] ?: null,
            'to_stage_id' => $validated['to_stage_id'],
            'allowed_roles' => $validated['allowed_roles'] ?: null,
            'requires_approval' => $validated['requires_approval'],
            'effective_from' => $validated['effective_from'] ?: null,
            'effective_to' => $validated['effective_to'] ?: null,
        ]);

        $this->showTransitionForm = false;
        $this->toast(__('Transition added.'));
    }

    public function toggleTransition(int $id): void
    {
        $this->authorize('workflow.configure');
        $transition = $this->definition()->transitions()->findOrFail($id);
        $transition->update(['is_active' => ! $transition->is_active]);
    }

    public function render(): mixed
    {
        $definition = $this->definition()->load(['stages', 'transitions.fromStage', 'transitions.toStage']);

        return view('livewire.admin.workflows.edit', [
            'definition' => $definition,
            'usage' => WorkflowStatusHistory::query()->where('workflow_definition_id', $definition->id)
                ->selectRaw('to_stage_id, count(*) as total')->groupBy('to_stage_id')->pluck('total', 'to_stage_id'),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'canConfigure' => auth()->user()->can('workflow.configure'),
            'flagLabels' => [
                'is_initial' => __('Initial'), 'is_final' => __('Final (closes the record)'), 'is_completion' => __('Completion (work genuinely done)'),
                'is_hold' => __('On hold'), 'is_rejection' => __('Rejection / loss'), 'blocks_delivery' => __('Blocks delivery'),
                'requires_remark' => __('Remark required'), 'requires_followup' => __('Next follow-up required'), 'requires_document' => __('Document required'),
            ],
        ])->title($definition->name);
    }

    private function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::query()->findOrFail($this->definitionId);
    }
}
