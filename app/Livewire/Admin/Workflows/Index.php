<?php

namespace App\Livewire\Admin\Workflows;

use App\Models\WorkflowDefinition;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Workflow Configuration')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('workflow.view');
    }

    public function render(): mixed
    {
        return view('livewire.admin.workflows.index', [
            'definitions' => WorkflowDefinition::query()
                ->withCount(['stages', 'stages as active_stages_count' => fn ($query) => $query->where('is_active', true)])
                ->with(['stages' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('module')->orderBy('name')->get(),
        ]);
    }
}
