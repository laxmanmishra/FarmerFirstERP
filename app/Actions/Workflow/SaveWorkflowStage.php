<?php

namespace App\Actions\Workflow;

use App\Exceptions\BusinessRuleException;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;

/**
 * Adds or edits a configurable stage without code changes (SRS §69, §84).
 *
 * Integrity rules that hold whatever the configuration:
 * - a used stage keeps its code (history refers to it) and can only be deactivated;
 * - every workflow keeps at least one active initial stage and one active
 *   final + completion stage, because the application relies on both.
 */
class SaveWorkflowStage
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(WorkflowDefinition $definition, array $attributes, ?WorkflowStage $stage = null): WorkflowStage
    {
        return DB::transaction(function () use ($definition, $attributes, $stage): WorkflowStage {
            if ($stage !== null && $stage->code !== $attributes['code'] && $stage->isUsed()) {
                throw new BusinessRuleException(__('The code of a stage that has been used cannot change.'), 'stage_code_locked');
            }

            if ($attributes['is_completion'] ?? false) {
                $attributes['is_final'] = true;
            }

            $stage = $stage
                ? tap($stage)->update($attributes)
                : $definition->stages()->create($attributes + ['sequence' => ((int) $definition->stages()->max('sequence')) + 10]);

            $this->assertIntegrity($definition);

            return $stage;
        });
    }

    public function toggleActive(WorkflowStage $stage): WorkflowStage
    {
        return DB::transaction(function () use ($stage): WorkflowStage {
            $stage->update(['is_active' => ! $stage->is_active]);
            $this->assertIntegrity($stage->definition);

            return $stage;
        });
    }

    /**
     * Deletes a stage that has never been used; used stages must be deactivated instead.
     */
    public function delete(WorkflowStage $stage): void
    {
        if ($stage->isUsed()) {
            throw new BusinessRuleException(__('This stage has been used and can only be deactivated, so history stays intact.'), 'stage_in_use');
        }

        DB::transaction(function () use ($stage): void {
            $definition = $stage->definition;
            $stage->delete();
            $this->assertIntegrity($definition);
        });
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(WorkflowDefinition $definition, array $orderedIds): void
    {
        DB::transaction(function () use ($definition, $orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                $definition->stages()->whereKey($id)->update(['sequence' => ($position + 1) * 10]);
            }
        });
    }

    private function assertIntegrity(WorkflowDefinition $definition): void
    {
        $active = $definition->stages()->active();

        if (! (clone $active)->where('is_initial', true)->exists()) {
            throw new BusinessRuleException(__('The workflow needs at least one active initial stage.'), 'workflow_needs_initial');
        }

        if (! (clone $active)->where('is_final', true)->where('is_completion', true)->exists()) {
            throw new BusinessRuleException(__('The workflow needs at least one active final stage marked as completion.'), 'workflow_needs_completion');
        }
    }
}
