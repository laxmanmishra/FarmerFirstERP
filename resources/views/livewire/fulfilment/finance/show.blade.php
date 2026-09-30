@php use App\Enums\FollowUpStatus; use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="$file->file_no" :description="$file->order->customer->name.' · '.$file->order->customer->mobile"
        :breadcrumbs="[__('Fulfilment') => null, __('Retail & Finance') => route('fulfilment.finance.index'), $file->file_no => null]">
        <x-slot:actions>
            @if ($editable)
                @can('finance.assign')<x-ui.button variant="secondary" icon="user" wire:click="open('assign')">{{ __('Assign') }}</x-ui.button>@endcan
                <x-ui.button icon="refresh" wire:click="open('status')">{{ __('Update status') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Finance status') }}</p>
            <div class="mt-2"><x-ui.stage-badge :stage="$file->stage" /></div>
            <p class="mt-1 text-xs text-slate-500">{{ $file->financer?->name ?? __('No financer yet') }} · {{ $file->responsible?->name ?? __('Unassigned') }}</p>
        </div>
        <x-ui.kpi-card :label="__('Loan requested')" :value="Money::format($file->loan_amount)" icon="banknotes" />
        <x-ui.kpi-card :label="__('Sanctioned')" :value="$file->sanctioned_amount !== null ? Money::format($file->sanctioned_amount) : '—'" icon="check-badge" tone="sky" />
        <x-ui.kpi-card :label="__('DO')" :value="$file->do_number ?? '—'" icon="document-text" :tone="$file->isDoExpired() ? 'rose' : 'amber'"
            :hint="$file->do_valid_until ? ($file->isDoExpired() ? __('expired :d', ['d' => $file->do_valid_until->format('d M Y')]) : __('valid until :d', ['d' => $file->do_valid_until->format('d M Y')])) : null" />
    </div>

    @if ($file->order->isCancelled())
        <x-ui.alert tone="danger" class="mb-6">{{ __('The order was cancelled; this file is closed.') }}</x-ui.alert>
    @endif

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="['details' => __('Loan & DO'), 'activity' => __('Follow-ups & queries').($pendingFollowUps + $openQueries > 0 ? ' ('.($pendingFollowUps + $openQueries).')' : ''), 'documents' => __('Documents'), 'timeline' => __('Timeline')]" />

    @if ($tab === 'details')
        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.card class="xl:col-span-2" :title="__('Loan & delivery order')" :description="__('Record what the financer reports. External financers never use the ERP.')">
                <form wire:submit="saveDetails" class="space-y-5">
                    <fieldset @disabled(! $editable) class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select :label="__('Financer')" wire:model.live="details.financer_id" name="details.financer_id" :options="$financers" :placeholder="__('Choose…')" />
                            <x-ui.select :label="__('Financer contact')" wire:model="details.financer_contact_id" name="details.financer_contact_id" :options="$contacts" :placeholder="__('None')" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.input :label="__('Sanctioned amount (₹)')" wire:model="details.sanctioned_amount" name="details.sanctioned_amount" inputmode="decimal" />
                            <x-ui.input :label="__('Down payment (₹)')" wire:model="details.down_payment" name="details.down_payment" inputmode="decimal" />
                            <x-ui.input :label="__('Loan account no.')" wire:model="details.loan_account_no" name="details.loan_account_no" />
                            <x-ui.input type="number" :label="__('Tenure (months)')" wire:model="details.tenure_months" name="details.tenure_months" />
                            <x-ui.input :label="__('Interest rate (%)')" wire:model="details.interest_rate" name="details.interest_rate" inputmode="decimal" />
                            <x-ui.input :label="__('EMI (₹)')" wire:model="details.emi_amount" name="details.emi_amount" inputmode="decimal" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-ui.input :label="__('DO number')" wire:model="details.do_number" name="details.do_number" />
                            <x-ui.input type="date" :label="__('DO date')" wire:model="details.do_date" name="details.do_date" />
                            <x-ui.input :label="__('DO amount (₹)')" wire:model="details.do_amount" name="details.do_amount" inputmode="decimal" />
                            <x-ui.input type="date" :label="__('DO valid until')" wire:model="details.do_valid_until" name="details.do_valid_until" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input :label="__('Disbursed amount (₹)')" wire:model="details.disbursed_amount" name="details.disbursed_amount" inputmode="decimal"
                                :hint="__('Accounts records the money itself as a finance-disbursement payment.')" />
                            <x-ui.input type="date" :label="__('Disbursed on')" wire:model="details.disbursed_on" name="details.disbursed_on" />
                        </div>
                        <x-ui.textarea :label="__('Remarks')" wire:model="details.remarks" name="details.remarks" rows="2" />
                    </fieldset>
                    @if ($editable)
                        <div class="flex justify-end"><x-ui.button type="submit" wire:target="saveDetails">{{ __('Save details') }}</x-ui.button></div>
                    @endif
                </form>
            </x-ui.card>
            <x-ui.card :title="__('Order')">
                <dl class="space-y-3">
                    <x-ui.dl-item :label="__('Order')"><a href="{{ route('sales.orders.show', $file->order) }}" wire:navigate class="font-medium text-brand-700 hover:underline">{{ $file->order->order_no }}</a> <x-ui.stage-badge :stage="$file->order->stage" /></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Customer')">{{ $file->order->customer->name }} · {{ $file->order->customer->customer_no }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Village')">{{ $file->order->customer->locationLabel() }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Order value')">{{ Money::format($file->order->order_value) }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Customer contribution')">{{ Money::format($file->order->customer_contribution) }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Expected delivery')">{{ $file->order->expected_delivery_date?->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Salesman')">{{ $file->order->primarySalesman?->name }}</x-ui.dl-item>
                    @if ($file->contact)
                        <x-ui.dl-item :label="__('Financer contact')">{{ $file->contact->name }} · <a href="tel:{{ $file->contact->mobile }}" class="tabular text-brand-700">{{ $file->contact->mobile }}</a></x-ui.dl-item>
                    @endif
                </dl>
            </x-ui.card>
        </div>
    @elseif ($tab === 'activity')
        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.card :title="__('Follow-ups')" :padding="false">
                <x-slot:actions>@if ($editable)<x-ui.button size="sm" icon="plus" wire:click="open('follow_up')">{{ __('Schedule') }}</x-ui.button>@endif</x-slot:actions>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($file->followUps as $followUp)
                        <li wire:key="fu-{{ $followUp->id }}" class="flex flex-wrap items-start justify-between gap-2 px-5 py-3">
                            <div>
                                <p class="font-medium text-slate-800">{{ $followUp->purpose }}</p>
                                <p @class(['text-xs', 'text-rose-600' => $followUp->isOverdue(), 'text-slate-500' => ! $followUp->isOverdue()])>
                                    {{ $followUpTypes[$followUp->type_code] ?? $followUp->type_code }} · {{ $followUp->due_at->format('d M, H:i') }} · {{ $followUp->assignee->name }}
                                </p>
                                @if ($followUp->outcome)<p class="mt-1 text-slate-600">{{ $followUp->outcome }} <span class="text-xs text-slate-400">— {{ $followUp->completedBy?->name }}</span></p>@endif
                            </div>
                            @if ($followUp->status === FollowUpStatus::Pending)
                                <x-ui.button size="sm" variant="secondary" wire:click="open('complete_follow_up', {{ $followUp->id }})">{{ __('Complete') }}</x-ui.button>
                            @else
                                <x-ui.badge :tone="$followUp->status === FollowUpStatus::Completed ? 'green' : 'slate'">{{ ucfirst($followUp->status->value) }}</x-ui.badge>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-slate-500">{{ __('No follow-ups yet.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('Financer queries')" :padding="false">
                <x-slot:actions>@if ($editable)<x-ui.button size="sm" icon="plus" wire:click="open('query')">{{ __('Record query') }}</x-ui.button>@endif</x-slot:actions>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($file->queries as $query)
                        <li wire:key="q-{{ $query->id }}" class="px-5 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-slate-800">{{ $query->subject }}</p>
                                    <p @class(['text-xs', 'text-rose-600' => $query->isOverdue(), 'text-slate-500' => ! $query->isOverdue()])>
                                        {{ $query->raised_by_party }} · {{ $query->created_at->format('d M Y') }}@if ($query->due_date) · {{ __('due :d', ['d' => $query->due_date->format('d M')]) }}@endif @if ($query->assignee) · {{ $query->assignee->name }}@endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-ui.badge :tone="$query->status->tone()">{{ $query->status->label() }}</x-ui.badge>
                                    @if ($editable && $query->status->isOpen())
                                        <x-ui.button size="sm" variant="ghost" wire:click="open('update_query', {{ $query->id }})">{{ __('Update') }}</x-ui.button>
                                    @endif
                                </div>
                            </div>
                            @if ($query->description)<p class="mt-1 text-slate-600">{{ $query->description }}</p>@endif
                            @if ($query->response)<p class="mt-1 rounded bg-slate-50 px-2 py-1 text-slate-700"><span class="text-xs font-medium text-slate-500">{{ __('Response') }}:</span> {{ $query->response }}</p>@endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-slate-500">{{ __('No queries from the financer.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    @elseif ($tab === 'documents')
        <livewire:fulfilment.documents.checklist :order-id="$file->order_id" :department-id="$departmentId" :key="'fin-docs-'.$file->id" />
    @else
        <x-ui.card><x-ui.timeline :items="$timeline" /></x-ui.card>
    @endif

    @if ($modal === 'status')
        <x-ui.modal wire:model="modal" :title="__('Update finance status')" :description="__('Current: :stage. The order\'s finance task follows automatically.', ['stage' => $file->stage->name])">
            <form id="fin-status-form" wire:submit="saveStatus" class="space-y-4">
                <x-ui.select :label="__('New status')" wire:model="form.stage_id" name="form.stage_id" :options="$targets->pluck('name', 'id')" :placeholder="__('Choose…')" required />
                <x-ui.textarea :label="__('Remarks')" wire:model="form.remarks" name="form.remarks" rows="2" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="fin-status-form" wire:target="saveStatus">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'assign')
        <x-ui.modal wire:model="modal" :title="__('Assign finance file')">
            <form id="fin-assign-form" wire:submit="saveAssignment">
                <x-ui.select :label="__('Responsible employee')" wire:model="form.employee_id" name="form.employee_id" :options="$employees" :placeholder="__('Unassigned')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="fin-assign-form" wire:target="saveAssignment">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'follow_up')
        <x-ui.modal wire:model="modal" :title="__('Schedule follow-up')">
            <form id="fin-fu-form" wire:submit="saveFollowUp" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select :label="__('Type')" wire:model="form.type_code" name="form.type_code" :options="$followUpTypes" />
                    <x-ui.input type="datetime-local" :label="__('Due')" wire:model="form.due_at" name="form.due_at" required />
                </div>
                <x-ui.select :label="__('Assigned to')" wire:model="form.employee_id" name="form.employee_id" :options="$employees" :placeholder="__('Choose…')" required />
                <x-ui.input :label="__('Purpose')" wire:model="form.purpose" name="form.purpose" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="fin-fu-form" wire:target="saveFollowUp">{{ __('Schedule') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'complete_follow_up')
        <x-ui.modal wire:model="modal" :title="__('Complete follow-up')">
            <form id="fin-fu-done" wire:submit="completeFollowUp">
                <x-ui.textarea :label="__('Outcome')" wire:model="form.outcome" name="form.outcome" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="fin-fu-done" wire:target="completeFollowUp">{{ __('Complete') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'query')
        <x-ui.modal wire:model="modal" :title="__('Record a financer query')">
            <form id="fin-q-form" wire:submit="saveQuery" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Raised by')" wire:model="form.party" name="form.party" required />
                    <x-ui.input type="date" :label="__('Due')" wire:model="form.due_date" name="form.due_date" />
                </div>
                <x-ui.input :label="__('Subject')" wire:model="form.subject" name="form.subject" required />
                <x-ui.textarea :label="__('Details')" wire:model="form.description" name="form.description" rows="2" />
                <x-ui.select :label="__('Handled by')" wire:model="form.employee_id" name="form.employee_id" :options="$employees" :placeholder="__('Unassigned')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="fin-q-form" wire:target="saveQuery">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'update_query' && $currentQuery)
        <x-ui.modal wire:model="modal" :title="__('Update query')" :description="$currentQuery->subject">
            <form id="fin-q-update" wire:submit="updateQuery" class="space-y-4">
                <x-ui.select :label="__('Status')" wire:model="form.status" name="form.status" :placeholder="__('Choose…')" required
                    :options="collect($currentQuery->status->next())->mapWithKeys(fn ($status) => [$status->value => $status->label()])" />
                <x-ui.textarea :label="__('Response')" wire:model="form.response" name="form.response" rows="3" :hint="__('Required to resolve.')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="fin-q-update" wire:target="updateQuery">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
