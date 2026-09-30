@php use App\Enums\DocumentLevel; use App\Enums\TaskCondition; @endphp
<div>
    <x-ui.page-header :title="__('Document Configuration')" :description="__('Document types, which documents each department needs, and the fulfilment tasks created for every order.')"
        :breadcrumbs="[__('Administration') => null, __('Document Configuration') => null]">
        <x-slot:actions>
            @if ($tab === 'types')<x-ui.button icon="plus" wire:click="edit('type')">{{ __('New document type') }}</x-ui.button>@endif
            @if ($tab === 'rules')<x-ui.button icon="plus" wire:click="edit('rule')">{{ __('New rule') }}</x-ui.button>@endif
            @if ($tab === 'tasks')<x-ui.button icon="plus" wire:click="edit('task')">{{ __('New task type') }}</x-ui.button>@endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="['types' => __('Document types'), 'rules' => __('Requirement rules'), 'tasks' => __('Fulfilment tasks'), 'options' => __('Options')]" />

    @if ($tab === 'types')
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Document type') }}</x-ui.th>
                <x-ui.th>{{ __('Level') }}</x-ui.th>
                <x-ui.th>{{ __('Behaviour') }}</x-ui.th>
                <x-ui.th>{{ __('Verified by') }}</x-ui.th>
                <x-ui.th>{{ __('Files') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @foreach ($types as $type)
                <tr wire:key="t-{{ $type->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td><p class="font-medium text-slate-900">{{ $type->name }}</p><p class="font-mono text-xs text-slate-400">{{ $type->code }} · {{ $type->category }}</p></x-ui.td>
                    <x-ui.td class="text-sm">{{ $type->level->label() }}</x-ui.td>
                    <x-ui.td>
                        <div class="flex flex-wrap gap-1">
                            @if ($type->is_reusable)<x-ui.badge tone="brand">{{ __('Reusable') }}</x-ui.badge>@endif
                            @if ($type->expiry_applicable)<x-ui.badge tone="amber">{{ __('Expires') }}</x-ui.badge>@endif
                            @if ($type->isSensitive())<x-ui.badge tone="rose">{{ __('Sensitive') }}</x-ui.badge>@endif
                        </div>
                    </x-ui.td>
                    <x-ui.td class="text-xs">{{ $type->verification_required ? $type->verification_permission : __('No verification') }}</x-ui.td>
                    <x-ui.td class="text-xs text-slate-500">{{ $type->allowed_extensions }} · {{ round($type->max_size_kb / 1024, 1) }} MB</x-ui.td>
                    <x-ui.td><x-ui.active-badge :active="$type->is_active" /></x-ui.td>
                    <x-ui.td align="right"><x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="edit('type', {{ $type->id }})">{{ __('Edit') }}</x-ui.button></x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @elseif ($tab === 'rules')
        <x-ui.alert class="mb-4">{{ __('A rule without a task applies to every order. A rule tied to a task follows that task: when the task is not required, the document is not required either.') }}</x-ui.alert>
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Department') }}</x-ui.th>
                <x-ui.th>{{ __('Document') }}</x-ui.th>
                <x-ui.th>{{ __('Needed when') }}</x-ui.th>
                <x-ui.th>{{ __('Blocks delivery') }}</x-ui.th>
                <x-ui.th>{{ __('Due') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @foreach ($rules as $rule)
                <tr wire:key="r-{{ $rule->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td class="text-sm">{{ $rule->department->name }}</x-ui.td>
                    <x-ui.td class="font-medium text-slate-900">{{ $rule->documentType->name }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $rule->taskType ? __(':task is required', ['task' => $rule->taskType->name]) : __('Every order') }}</x-ui.td>
                    <x-ui.td>{!! $rule->blocks_delivery ? '<span class="text-amber-700 text-sm font-medium">'.e(__('Yes')).'</span>' : '<span class="text-slate-400 text-sm">'.e(__('No')).'</span>' !!}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $rule->due_offset_days !== null ? trans_choice(':count day after booking|:count days after booking', $rule->due_offset_days, ['count' => $rule->due_offset_days]) : '—' }}</x-ui.td>
                    <x-ui.td><x-ui.active-badge :active="$rule->is_active" /></x-ui.td>
                    <x-ui.td align="right"><x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="edit('rule', {{ $rule->id }})">{{ __('Edit') }}</x-ui.button></x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @elseif ($tab === 'tasks')
        <x-ui.alert class="mb-4">{{ __('Every new order gets one task per active type. The condition reads the approved deal: a task whose condition is not met is created as Not Required.') }}</x-ui.alert>
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Task') }}</x-ui.th>
                <x-ui.th>{{ __('Department') }}</x-ui.th>
                <x-ui.th>{{ __('Required when') }}</x-ui.th>
                <x-ui.th>{{ __('Blocks delivery') }}</x-ui.th>
                <x-ui.th>{{ __('Worked by') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @foreach ($tasks as $task)
                <tr wire:key="k-{{ $task->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td><p class="font-medium text-slate-900">{{ $task->name }}</p><p class="font-mono text-xs text-slate-400">{{ $task->code }}</p></x-ui.td>
                    <x-ui.td class="text-sm">{{ $task->department->name }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $task->condition->label() }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $task->blocks_delivery ? __('Yes') : __('No') }}</x-ui.td>
                    <x-ui.td class="font-mono text-xs">{{ $task->update_permission }}</x-ui.td>
                    <x-ui.td><x-ui.active-badge :active="$task->is_active" /></x-ui.td>
                    <x-ui.td align="right"><x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="edit('task', {{ $task->id }})">{{ __('Edit') }}</x-ui.button></x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @else
        <x-ui.card class="max-w-2xl">
            <form wire:submit="saveOptions" class="space-y-4">
                <x-ui.checkbox :label="__('Link existing documents automatically')" wire:model="autoLink"
                    :description="__('When an order is booked, verified reusable customer documents (Aadhaar, PAN, …) already in the repository are linked to the new requirements. Otherwise staff choose “Use existing” themselves.')" />
                <div class="flex justify-end"><x-ui.button type="submit">{{ __('Save options') }}</x-ui.button></div>
            </form>
        </x-ui.card>
    @endif

    @if ($drawer === 'type')
        <x-ui.drawer wire:model="drawer" :title="$editingId ? __('Edit document type') : __('New document type')" :description="__('Applies to uploads from now on. Existing documents keep their history.')">
            <form id="type-form" wire:submit="saveType" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Code')" wire:model="form.code" name="form.code" class="font-mono uppercase" :disabled="(bool) $editingId" :required="! $editingId" :hint="$editingId ? __('Codes cannot change.') : __('Capitals, digits and _')" />
                    <x-ui.input :label="__('Name')" wire:model="form.name" name="form.name" required />
                    <x-ui.select :label="__('Category')" wire:model="form.category" name="form.category" :options="collect(App\Models\DocumentType::CATEGORIES)->mapWithKeys(fn ($c) => [$c => ucfirst($c)])" />
                    <x-ui.select :label="__('Level')" wire:model="form.level" name="form.level" :options="collect(DocumentLevel::cases())->mapWithKeys(fn ($l) => [$l->value => $l->label()])" />
                    <x-ui.select :label="__('Verification permission')" wire:model="form.verification_permission" name="form.verification_permission" :options="$permissions" />
                    <x-ui.select :label="__('Default department')" wire:model="form.default_department_id" name="form.default_department_id" :options="$departments" :placeholder="__('None')" />
                    <x-ui.input :label="__('Allowed extensions')" wire:model="form.allowed_extensions" name="form.allowed_extensions" />
                    <x-ui.input type="number" :label="__('Maximum size (KB)')" wire:model="form.max_size_kb" name="form.max_size_kb" />
                    <x-ui.input type="number" :label="__('Sort order')" wire:model="form.sort_order" name="form.sort_order" />
                    <x-ui.select :label="__('Sensitivity')" wire:model="form.sensitivity" name="form.sensitivity" :options="['normal' => __('Normal'), 'sensitive' => __('Sensitive — restricted file access, access audited')]" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.checkbox :label="__('Reusable across orders')" wire:model="form.is_reusable" />
                    <x-ui.checkbox :label="__('Has an expiry date')" wire:model="form.expiry_applicable" />
                    <x-ui.checkbox :label="__('Needs verification')" wire:model="form.verification_required" />
                    <x-ui.checkbox :label="__('Active')" wire:model="form.is_active" />
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="type-form" wire:target="saveType">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @elseif ($drawer === 'rule')
        <x-ui.drawer wire:model="drawer" :title="$editingId ? __('Edit requirement rule') : __('New requirement rule')" :description="__('One rule per document type and department.')">
            <form id="rule-form" wire:submit="saveRule" class="space-y-4">
                <x-ui.select :label="__('Document type')" wire:model="form.document_type_id" name="form.document_type_id" :options="$typeOptions" :placeholder="__('Choose…')" required />
                <x-ui.select :label="__('Department')" wire:model="form.department_id" name="form.department_id" :options="$departments" :placeholder="__('Choose…')" required />
                <x-ui.select :label="__('Needed when this task is required')" wire:model="form.fulfilment_task_type_id" name="form.fulfilment_task_type_id" :options="$taskOptions" :placeholder="__('Every order')" />
                <x-ui.input type="number" :label="__('Due (days after booking)')" wire:model="form.due_offset_days" name="form.due_offset_days" />
                <x-ui.checkbox :label="__('Blocks delivery until satisfied')" wire:model="form.blocks_delivery" />
                <x-ui.checkbox :label="__('Active')" wire:model="form.is_active" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="rule-form" wire:target="saveRule">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @elseif ($drawer === 'task')
        <x-ui.drawer wire:model="drawer" :title="$editingId ? __('Edit fulfilment task') : __('New fulfilment task')">
            <form id="task-form" wire:submit="saveTask" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Code')" wire:model="form.code" name="form.code" class="font-mono uppercase" :disabled="(bool) $editingId" :required="! $editingId" />
                    <x-ui.input :label="__('Name')" wire:model="form.name" name="form.name" required />
                </div>
                <x-ui.select :label="__('Department')" wire:model="form.department_id" name="form.department_id" :options="$departments" :placeholder="__('Choose…')" required />
                <x-ui.select :label="__('Required when')" wire:model="form.condition" name="form.condition" :options="collect(TaskCondition::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])" />
                <x-ui.select :label="__('Permission to work the task')" wire:model="form.update_permission" name="form.update_permission" :options="$permissions" :placeholder="__('Choose…')" required />
                <x-ui.input type="number" :label="__('Sort order')" wire:model="form.sort_order" name="form.sort_order" />
                <x-ui.checkbox :label="__('Blocks delivery until completed')" wire:model="form.blocks_delivery" />
                <x-ui.checkbox :label="__('Active')" wire:model="form.is_active" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="task-form" wire:target="saveTask">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @endif
</div>
