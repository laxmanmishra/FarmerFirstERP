@php use App\Enums\RequirementState; use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="$order->order_no" :description="$order->customer->name.' · '.$order->customer->customer_no"
        :breadcrumbs="[__('Sales') => null, __('Orders') => route('sales.orders.index'), $order->order_no => null]">
        <x-slot:actions>
            @if (! $order->stage->is_final)
                @can('orders.cancel')
                    <x-ui.button variant="danger-ghost" icon="ban" wire:click="openCancel">{{ __('Cancel order') }}</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($order->isCancelled())
        <x-ui.alert tone="danger" class="mb-6" :title="__('Cancelled')">
            {{ __('Cancelled by :name on :date: :reason', ['name' => $order->cancelledBy?->name, 'date' => $order->cancelled_at->format('d M Y'), 'reason' => $order->cancellation_reason]) }}
        </x-ui.alert>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Status') }}</p>
            <div class="mt-2"><x-ui.stage-badge :stage="$order->stage" /></div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Booked :date', ['date' => $order->order_date->format('d M Y')]) }}</p>
        </div>
        <x-ui.kpi-card :label="__('Order value')" :value="Money::format($order->order_value)" icon="banknotes" />
        <x-ui.kpi-card :label="__('Customer contribution')" :value="Money::format($order->customer_contribution)" icon="calculator" tone="sky"
            :hint="$order->finance_required ? __('Finance :v', ['v' => Money::format($order->finance_amount)]) : __('Cash deal')" />
        <x-ui.kpi-card :label="__('Documents')" :value="$documentsSatisfied.' / '.$documentsNeeded" icon="folder" :tone="$documentsSatisfied < $documentsNeeded ? 'amber' : 'brand'"
            :hint="__('satisfied')" />
    </div>

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="['overview' => __('Overview'), 'fulfilment' => __('Fulfilment tasks'), 'documents' => __('Documents'), 'timeline' => __('Timeline')]" />

    @if ($tab === 'overview')
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-ui.table>
                    <x-slot:toolbar>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ __('Booked commercial terms') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Frozen from the approved deal; later changes need a new approval.') }}</p>
                        </div>
                    </x-slot:toolbar>
                    <x-slot:head>
                        <x-ui.th>{{ __('Item') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('Qty') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('Rate') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('Discount') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('Total') }}</x-ui.th>
                    </x-slot:head>
                    @foreach ($order->items as $item)
                        <tr wire:key="oi-{{ $item->id }}">
                            <x-ui.td>{{ $item->description }} <span class="text-xs text-slate-400">· {{ $item->line_type->label() }}</span></x-ui.td>
                            <x-ui.td align="right" class="tabular">{{ $item->quantity }}</x-ui.td>
                            <x-ui.td align="right" class="tabular">{{ Money::format($item->unit_price) }}</x-ui.td>
                            <x-ui.td align="right" class="tabular">{{ Money::compare($item->discount_amount, 0) > 0 ? '−'.Money::format($item->discount_amount) : '—' }}</x-ui.td>
                            <x-ui.td align="right" class="tabular font-medium">{{ Money::format($item->line_total) }}</x-ui.td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50/60 text-sm">
                        <td colspan="4" class="px-4 py-2 text-right text-slate-500">{{ __('Exchange value') }}</td>
                        <td class="tabular px-4 py-2 text-right">−{{ Money::format($order->exchange_value) }}</td>
                    </tr>
                    <tr class="bg-slate-50/60 font-semibold">
                        <td colspan="4" class="px-4 py-2 text-right">{{ __('Order value') }}</td>
                        <td class="tabular px-4 py-2 text-right">{{ Money::format($order->order_value) }}</td>
                    </tr>
                </x-ui.table>
            </div>
            <x-ui.card :title="__('Details')">
                <dl class="space-y-3">
                    <x-ui.dl-item :label="__('Customer')"><a href="{{ route('sales.customers.show', $order->customer) }}" wire:navigate class="font-medium text-brand-700 hover:underline">{{ $order->customer->customer_no }} · {{ $order->customer->name }}</a></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Mobile')">{{ $order->customer->mobile }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Deal')">
                        @can('deals.view')<a href="{{ route('sales.deals.show', $order->deal_id) }}" wire:navigate class="text-brand-700 hover:underline">{{ $order->deal->deal_no }}</a>@else{{ $order->deal->deal_no }}@endcan
                    </x-ui.dl-item>
                    <x-ui.dl-item :label="__('Fulfilment')">{{ $order->fulfilment?->fulfilment_no }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Primary salesman')">{{ $order->primarySalesman?->name }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Expected delivery')">{{ $order->expected_delivery_date?->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Booking amount')">{{ Money::format($order->booking_amount) }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Branch')">{{ $order->branch->name }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Village')">{{ $order->customer->locationLabel() }}</x-ui.dl-item>
                </dl>
            </x-ui.card>
        </div>
    @elseif ($tab === 'fulfilment')
        <x-ui.table>
            <x-slot:toolbar>
                <p class="text-sm text-slate-600">{{ __('Departments work in parallel. Requirement state and progress are tracked separately: a waived task is never counted as completed.') }}</p>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th>{{ __('Task') }}</x-ui.th>
                <x-ui.th>{{ __('Requirement') }}</x-ui.th>
                <x-ui.th>{{ __('Progress') }}</x-ui.th>
                <x-ui.th>{{ __('Responsible') }}</x-ui.th>
                <x-ui.th>{{ __('Updated') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @foreach ($tasks as $task)
                <tr wire:key="task-{{ $task->id }}" @class(['hover:bg-slate-50/70', 'opacity-60' => $task->requirement_state === RequirementState::NotRequired])>
                    <x-ui.td>
                        <p class="font-medium text-slate-900">{{ $task->type->name }}</p>
                        <p class="text-xs text-slate-500">{{ $task->department->name }} @if ($task->blocks_delivery)· <span class="text-amber-700">{{ __('blocks delivery') }}</span>@endif</p>
                    </x-ui.td>
                    <x-ui.td>
                        <x-ui.badge :tone="$task->requirement_state->tone()">{{ $task->requirement_state->label() }}</x-ui.badge>
                        @if ($task->requirement_remarks)<p class="mt-1 max-w-xs text-xs text-slate-500">{{ $task->requirement_remarks }}</p>@endif
                    </x-ui.td>
                    <x-ui.td><x-ui.stage-badge :stage="$task->stage" /></x-ui.td>
                    <x-ui.td class="text-sm">{{ $task->responsible?->name ?? '—' }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-xs text-slate-500">{{ $task->updated_at->diffForHumans() }}</x-ui.td>
                    <x-ui.td align="right" class="whitespace-nowrap">
                        @unless ($order->isCancelled())
                            @if ($workable[$task->id] && $task->requirement_state->isApplicable() && ! $task->stage->is_final)
                                <x-ui.button size="sm" variant="secondary" wire:click="openTask({{ $task->id }}, 'status')">{{ __('Update') }}</x-ui.button>
                            @endif
                            @if ($workable[$task->id])
                                <x-ui.button size="sm" variant="ghost" wire:click="openTask({{ $task->id }}, 'assign')">{{ __('Assign') }}</x-ui.button>
                            @endif
                            @can('orders.create')
                                @if (! $task->stage->is_completion && $task->requirement_state !== RequirementState::Waived)
                                    <x-ui.button size="sm" variant="ghost" wire:click="openTask({{ $task->id }}, 'requirement')">{{ __('Change requirement') }}</x-ui.button>
                                @endif
                            @endcan
                        @endunless
                    </x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @elseif ($tab === 'documents')
        <livewire:fulfilment.documents.checklist :order-id="$order->id" :key="'checklist-'.$order->id" />
    @else
        <x-ui.card><x-ui.timeline :items="$timeline" /></x-ui.card>
    @endif

    @if ($modal === 'status' && $currentTask)
        <x-ui.modal wire:model="modal" :title="__('Update :task', ['task' => $currentTask->type->name])" :description="__('Current status: :stage', ['stage' => $currentTask->stage->name])">
            <form id="task-status-form" wire:submit="saveStatus" class="space-y-4">
                <x-ui.select :label="__('New status')" wire:model="form.stage_id" name="form.stage_id" :options="$targets->pluck('name', 'id')" :placeholder="__('Choose…')" required />
                <x-ui.textarea :label="__('Remarks')" wire:model="form.remarks" name="form.remarks" rows="2" :hint="__('Required for some statuses, such as On hold.')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="task-status-form" wire:target="saveStatus">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'assign' && $currentTask)
        <x-ui.modal wire:model="modal" :title="__('Assign :task', ['task' => $currentTask->type->name])" :description="__('Only active employees of :department can be responsible.', ['department' => $currentTask->department->name])">
            <form id="task-assign-form" wire:submit="saveAssignment">
                <x-ui.select :label="__('Responsible employee')" wire:model="form.employee_id" name="form.employee_id" :options="$employees" :placeholder="__('Unassigned')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="task-assign-form" wire:target="saveAssignment">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'requirement' && $currentTask)
        <x-ui.modal wire:model="modal" :title="__('Change requirement: :task', ['task' => $currentTask->type->name])"
            :description="__('Document requirements of this task follow the change. To defer required work, request a waiver instead.')">
            <form id="task-requirement-form" wire:submit="saveRequirement" class="space-y-4">
                <x-ui.select :label="__('Requirement')" wire:model="form.state" name="form.state" required
                    :options="[RequirementState::Required->value => RequirementState::Required->label(), RequirementState::Conditional->value => RequirementState::Conditional->label(), RequirementState::NotRequired->value => RequirementState::NotRequired->label()]" />
                <x-ui.textarea :label="__('Reason')" wire:model="form.reason" name="form.reason" rows="2" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="task-requirement-form" wire:target="saveRequirement">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'cancel')
        <x-ui.modal wire:model="modal" tone="danger" :title="__('Cancel order :no', ['no' => $order->order_no])"
            :description="__('Open tasks are cancelled. The order, its documents and history are kept.')">
            <form id="cancel-form" wire:submit="cancelOrder">
                <x-ui.textarea :label="__('Reason')" wire:model="form.reason" name="form.reason" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Keep order') }}</x-ui.button>
                <x-ui.button type="submit" form="cancel-form" variant="danger" wire:target="cancelOrder">{{ __('Cancel order') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
