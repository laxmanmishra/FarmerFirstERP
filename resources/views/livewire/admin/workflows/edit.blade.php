<div>
    <x-ui.page-header :title="$definition->name" :description="$definition->description"
        :breadcrumbs="[__('Administration') => null, __('Workflow Configuration') => route('admin.workflows.index'), $definition->name => null]">
        <x-slot:actions>
            @if ($canConfigure)
                <x-ui.button icon="plus" wire:click="createStage">{{ __('Add stage') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Preview --}}
    <x-ui.card class="mb-6" :title="__('Preview')" :description="__('Active stages in display order. Final stages close the record.')">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($definition->stages->where('is_active', true)->where('is_final', false) as $stage)
                <x-ui.badge :tone="$stage->color">{{ $stage->name }}</x-ui.badge>
                @unless ($loop->last)<x-ui.icon name="chevron-right" class="size-3 text-slate-300" />@endunless
            @endforeach
            <span class="mx-2 text-slate-300">|</span>
            @foreach ($definition->stages->where('is_active', true)->where('is_final', true) as $stage)
                <x-ui.badge :tone="$stage->color" dot>{{ $stage->name }}</x-ui.badge>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.table class="mb-6">
        <x-slot:head>
            <x-ui.th>{{ __('Order') }}</x-ui.th>
            <x-ui.th>{{ __('Stage') }}</x-ui.th>
            <x-ui.th>{{ __('Behaviour') }}</x-ui.th>
            <x-ui.th align="right">{{ __('SLA') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Used') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @foreach ($definition->stages as $stage)
            <tr wire:key="stage-{{ $stage->id }}" @class(['hover:bg-slate-50/70', 'opacity-60' => ! $stage->is_active])>
                <x-ui.td class="whitespace-nowrap">
                    @if ($canConfigure)
                        <div class="flex gap-0.5">
                            <button type="button" wire:click="move({{ $stage->id }}, 'up')" @disabled($loop->first) class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" aria-label="{{ __('Move up') }}"><x-ui.icon name="arrow-up" class="size-3.5" /></button>
                            <button type="button" wire:click="move({{ $stage->id }}, 'down')" @disabled($loop->last) class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" aria-label="{{ __('Move down') }}"><x-ui.icon name="arrow-down" class="size-3.5" /></button>
                        </div>
                    @endif
                </x-ui.td>
                <x-ui.td>
                    <x-ui.badge :tone="$stage->color" dot>{{ $stage->name }}</x-ui.badge>
                    <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $stage->code }}</p>
                </x-ui.td>
                <x-ui.td>
                    <div class="flex max-w-md flex-wrap gap-1">
                        @foreach (App\Models\WorkflowStage::FLAGS as $flag)
                            @if ($stage->{$flag})<x-ui.badge tone="slate">{{ \Illuminate\Support\Str::before($flagLabels[$flag], ' (') }}</x-ui.badge>@endif
                        @endforeach
                    </div>
                </x-ui.td>
                <x-ui.td align="right" class="tabular text-xs text-slate-500">{{ $stage->sla_hours ? $stage->sla_hours.'h' : '—' }}</x-ui.td>
                <x-ui.td align="right" class="tabular text-xs">{{ number_format($usage[$stage->id] ?? 0) }}</x-ui.td>
                <x-ui.td><x-ui.active-badge :active="$stage->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    @if ($canConfigure)
                        <div class="flex justify-end gap-1 whitespace-nowrap">
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="editStage({{ $stage->id }})">{{ __('Edit') }}</x-ui.button>
                            <x-ui.button variant="ghost" size="xs" wire:click="toggleStage({{ $stage->id }})">{{ $stage->is_active ? __('Deactivate') : __('Activate') }}</x-ui.button>
                            @if (($usage[$stage->id] ?? 0) === 0)
                                <x-ui.button variant="danger-ghost" size="xs" wire:click="deleteStage({{ $stage->id }})" wire:confirm="{{ __('Delete :name? It has never been used.', ['name' => $stage->name]) }}">{{ __('Delete') }}</x-ui.button>
                            @endif
                        </div>
                    @endif
                </x-ui.td>
            </tr>
        @endforeach
    </x-ui.table>

    {{-- Transitions --}}
    <x-ui.card :title="__('Transitions')" :padding="false"
        :description="$definition->controlled_transitions ? __('Controlled: only the transitions below are allowed.') : __('Uncontrolled: any move between active open stages is allowed. Configure transitions and switch on control to restrict moves by role.')">
        <x-slot:actions>
            @if ($canConfigure)
                <x-ui.button variant="secondary" size="sm" wire:click="toggleControlled">{{ $definition->controlled_transitions ? __('Allow any move') : __('Enforce transitions') }}</x-ui.button>
                <x-ui.button size="sm" icon="plus" wire:click="createTransition">{{ __('Add transition') }}</x-ui.button>
            @endif
        </x-slot:actions>
        <ul class="divide-y divide-slate-100">
            @forelse ($definition->transitions as $transition)
                <li wire:key="tr-{{ $transition->id }}" @class(['flex flex-wrap items-center gap-3 px-5 py-3 text-sm', 'opacity-50' => ! $transition->is_active])>
                    @if ($transition->fromStage)<x-ui.stage-badge :stage="$transition->fromStage" />@else<x-ui.badge>{{ __('Any stage') }}</x-ui.badge>@endif
                    <x-ui.icon name="chevron-right" class="size-4 text-slate-400" />
                    <x-ui.stage-badge :stage="$transition->toStage" />
                    <span class="text-xs text-slate-500">{{ $transition->allowed_roles ? implode(', ', $transition->allowed_roles) : __('All roles') }}</span>
                    @if ($transition->requires_approval)<x-ui.badge tone="amber">{{ __('Approval required') }}</x-ui.badge>@endif
                    @if ($transition->effective_from || $transition->effective_to)
                        <span class="text-xs text-slate-400">{{ $transition->effective_from?->format('d M Y') ?? '…' }} – {{ $transition->effective_to?->format('d M Y') ?? '…' }}</span>
                    @endif
                    @if ($canConfigure)
                        <x-ui.button class="ml-auto" variant="ghost" size="xs" wire:click="toggleTransition({{ $transition->id }})">{{ $transition->is_active ? __('Disable') : __('Enable') }}</x-ui.button>
                    @endif
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('No transitions configured.') }}</li>
            @endforelse
        </ul>
    </x-ui.card>

    <x-ui.drawer wire:model="showStageForm" :title="$stageId ? __('Edit stage') : __('Add stage')"
        :description="__('Stages are data: the application relies on their behaviour flags, not their names.')">
        <form id="stage-form" wire:submit="saveStage" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('Code')" wire:model="stage.code" name="stage.code" required class="font-mono uppercase" :hint="__('Stable identifier, e.g. LEGAL_VERIFICATION.')" />
                <x-ui.input :label="__('Display name')" wire:model="stage.name" name="stage.name" required />
                <x-ui.select :label="__('Colour')" wire:model="stage.color" name="stage.color" :options="array_combine(App\Models\WorkflowStage::COLORS, array_map('ucfirst', App\Models\WorkflowStage::COLORS))" />
                <x-ui.input type="number" :label="__('SLA (hours)')" wire:model="stage.sla_hours" name="stage.sla_hours" min="1" :hint="__('Ageing threshold for escalation.')" />
            </div>
            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-slate-700">{{ __('Behaviour') }}</legend>
                @foreach (App\Models\WorkflowStage::FLAGS as $flag)
                    <x-ui.checkbox :label="$flagLabels[$flag]" wire:model="stage.{{ $flag }}" />
                @endforeach
            </fieldset>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="stage-form" wire:target="saveStage">{{ __('Save stage') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.drawer wire:model="showTransitionForm" :title="__('Add transition')">
        <form id="transition-form" wire:submit="saveTransition" class="space-y-5">
            <x-ui.select :label="__('From')" wire:model="transition.from_stage_id" name="transition.from_stage_id" :placeholder="__('Any stage')" :options="$definition->stages->pluck('name', 'id')" />
            <x-ui.select :label="__('To')" wire:model="transition.to_stage_id" name="transition.to_stage_id" :placeholder="__('Select…')" :options="$definition->stages->pluck('name', 'id')" required />
            <fieldset>
                <legend class="text-sm font-medium text-slate-700">{{ __('Allowed roles') }}</legend>
                <p class="text-xs text-slate-500">{{ __('Leave empty to allow every role with access to the module.') }}</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $role)<x-ui.checkbox :label="$role" wire:model="transition.allowed_roles" value="{{ $role }}" />@endforeach
                </div>
            </fieldset>
            <x-ui.checkbox :label="__('Requires approval')" :description="__('Only reachable through an approval process such as a reopen request.')" wire:model="transition.requires_approval" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input type="date" :label="__('Effective from')" wire:model="transition.effective_from" name="transition.effective_from" />
                <x-ui.input type="date" :label="__('Effective to')" wire:model="transition.effective_to" name="transition.effective_to" />
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="transition-form">{{ __('Add') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
