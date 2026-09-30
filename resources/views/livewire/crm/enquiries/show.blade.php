<div>
    <x-ui.page-header :title="$enquiry->enquiry_no" :description="$enquiry->farmer->name.' · '.$enquiry->farmer->locationLabel()"
        :breadcrumbs="[__('CRM') => null, __('Enquiries') => route('crm.enquiries.index'), $enquiry->enquiry_no => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="phone" href="tel:{{ $enquiry->farmer->mobile }}">{{ __('Call') }}</x-ui.button>
            @if (! $enquiry->isClosed())
                @can('enquiries.update')
                    <x-ui.button variant="secondary" icon="pencil" :href="route('crm.enquiries.edit', $enquiry)" wire:navigate>{{ __('Edit') }}</x-ui.button>
                @endcan
                @can('follow_ups.manage')
                    <x-ui.button variant="secondary" icon="calendar" wire:click="openModal('follow-up')">{{ __('Follow-up') }}</x-ui.button>
                @endcan
                @can('enquiries.assign')
                    <x-ui.button variant="secondary" icon="user" wire:click="openModal('assign')">{{ __('Assign') }}</x-ui.button>
                @endcan
            @elseif (! $enquiry->pipelineStage?->is_completion)
                @can('enquiries.reopen')
                    <x-ui.button icon="refresh" wire:click="openModal('reopen')">{{ __('Reopen') }}</x-ui.button>
                @elsecan('enquiries.reopen_request')
                    <x-ui.button icon="refresh" wire:click="openModal('request-reopen')" :disabled="$hasPendingReopen">{{ $hasPendingReopen ? __('Reopen requested') : __('Request reopen') }}</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Status strip --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Validation status') }}</p>
            <div class="mt-2"><x-ui.stage-badge :stage="$enquiry->validationStage" /></div>
            @if ($enquiry->claimedBy && $enquiry->isAwaitingValidation())
                <p class="mt-1 text-xs text-slate-500">{{ __('Claimed by :name', ['name' => $enquiry->claimedBy->name]) }}</p>
            @elseif ($enquiry->callback_at)
                <p class="mt-1 text-xs text-slate-500">{{ __('Callback :time', ['time' => $enquiry->callback_at->format('d M, H:i')]) }}</p>
            @endif
        </div>
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Pipeline') }}</p>
            <div class="mt-2"><x-ui.stage-badge :stage="$enquiry->pipelineStage" /></div>
            @if ($enquiry->isClosed())
                <p class="mt-1 text-xs text-slate-500">{{ __('Closed :date', ['date' => $enquiry->closed_at->format('d M Y')]) }}@if ($closeReasonLabel) · {{ $closeReasonLabel }}@endif</p>
            @endif
        </div>
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Expected purchase') }}</p>
            <p class="tabular mt-1 text-sm font-semibold text-slate-900">{{ $enquiry->expected_purchase_date->format('d M Y') }}</p>
            <x-ui.temperature-badge :temperature="$enquiry->temperature" class="mt-1" />
        </div>
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Salesman') }}</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $enquiry->assignee?->name ?? __('Unassigned') }}</p>
            <p class="text-xs text-slate-500">{{ __('Created by :name', ['name' => $enquiry->creatorEmployee?->name ?? '—']) }}</p>
        </div>
    </div>

    {{-- Pipeline quick moves --}}
    @if ($stageOptions->isNotEmpty())
        <x-ui.card class="mb-6" :title="__('Move in pipeline')">
            <div class="flex flex-wrap gap-2">
                @foreach ($stageOptions as $option)
                    <x-ui.button :variant="$option->is_final ? ($option->is_completion ? 'primary' : 'danger-ghost') : 'secondary'" size="sm" wire:click="openModal('stage', {{ $option->id }})">
                        {{ $option->name }}
                    </x-ui.button>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card :title="__('Requirement')">
                <ul class="divide-y divide-slate-100">
                    @foreach ($enquiry->requirements as $line)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0">
                            <span class="text-sm font-medium text-slate-800">{{ $line->summary() }}</span>
                            <span class="flex items-center gap-2">
                                <x-ui.badge>{{ $line->requirement_type->label() }}</x-ui.badge>
                                @if ($line->description)<span class="text-xs text-slate-500">{{ $line->description }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-4">
                    <x-ui.dl-item :label="__('Deal type')">{{ $enquiry->deal_type->label() }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Source')">{{ $sourceLabel }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Budget')">{{ $enquiry->budget ? '₹'.number_format((float) $enquiry->budget) : '' }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Branch')">{{ $enquiry->branch->name }}</x-ui.dl-item>
                    @if ($enquiry->remarks)<x-ui.dl-item class="col-span-full" :label="__('Remarks')">{{ $enquiry->remarks }}</x-ui.dl-item>@endif
                    @if ($enquiry->duplicate_override_reason)
                        <x-ui.dl-item class="col-span-full" :label="__('Created despite possible duplicate')"><span class="text-amber-800">{{ $enquiry->duplicate_override_reason }}</span></x-ui.dl-item>
                    @endif
                </dl>
            </x-ui.card>

            @if ($enquiry->exchangeTractor)
                @php $old = $enquiry->exchangeTractor; @endphp
                <x-ui.card :title="__('Exchange tractor')">
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <x-ui.dl-item :label="__('Tractor')">{{ $old->brand_name }} {{ $old->model_name }}</x-ui.dl-item>
                        <x-ui.dl-item :label="__('Year')">{{ $old->manufacturing_year }}</x-ui.dl-item>
                        <x-ui.dl-item :label="__('Hours')">{{ $old->hours_used ? number_format($old->hours_used) : '' }}</x-ui.dl-item>
                        <x-ui.dl-item :label="__('Registration')">{{ $old->registration_number }}</x-ui.dl-item>
                        <x-ui.dl-item :label="__('Condition')">{{ $old->condition ? ucfirst($old->condition) : '' }}</x-ui.dl-item>
                        <x-ui.dl-item :label="__('Customer expects')">{{ $old->customer_expected_price ? '₹'.number_format((float) $old->customer_expected_price) : '' }}</x-ui.dl-item>
                        <x-ui.dl-item :label="__('Approved value')">{{ $old->approved_exchange_value ? '₹'.number_format((float) $old->approved_exchange_value) : __('Not yet approved') }}</x-ui.dl-item>
                        @if ($old->remarks)<x-ui.dl-item class="col-span-full" :label="__('Remarks')">{{ $old->remarks }}</x-ui.dl-item>@endif
                    </dl>
                    @if ($enquiry->attachments->isNotEmpty())
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($enquiry->attachments as $photo)
                                <a href="{{ route('crm.enquiries.attachments.show', $photo) }}" target="_blank">
                                    <img src="{{ route('crm.enquiries.attachments.show', $photo) }}" alt="{{ $photo->original_name }}" class="size-24 rounded-lg object-cover ring-1 ring-slate-200 hover:ring-brand-400">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endif

            <x-ui.card :title="__('Activity timeline')">
                <x-ui.timeline :items="$timeline" />
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Farmer')">
                <p class="font-semibold text-slate-900"><a href="{{ route('crm.farmers.show', $enquiry->farmer) }}" wire:navigate class="hover:text-brand-700">{{ $enquiry->farmer->name }}</a></p>
                <p class="text-sm text-slate-600">{{ $enquiry->farmer->farmer_no }}</p>
                <dl class="mt-4 space-y-3">
                    <x-ui.dl-item :label="__('Mobile')"><a href="tel:{{ $enquiry->farmer->mobile }}" class="tabular hover:text-brand-700">{{ $enquiry->farmer->mobile }}</a></x-ui.dl-item>
                    @if ($enquiry->farmer->whatsapp_number)
                        <x-ui.dl-item :label="__('WhatsApp')"><a href="https://wa.me/91{{ $enquiry->farmer->whatsapp_number }}" target="_blank" rel="noopener" class="tabular hover:text-brand-700">{{ $enquiry->farmer->whatsapp_number }}</a></x-ui.dl-item>
                    @endif
                    <x-ui.dl-item :label="__('Village')">{{ $enquiry->village->name }}</x-ui.dl-item>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Follow-ups')" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($enquiry->followUps->where('status', App\Enums\FollowUpStatus::Pending) as $followUp)
                        <li wire:key="fu-{{ $followUp->id }}" class="px-5 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-slate-800">{{ $followUp->purpose }}</p>
                                    <p @class(['text-xs', 'font-semibold text-rose-600' => $followUp->isOverdue(), 'text-slate-500' => ! $followUp->isOverdue()])>
                                        {{ $followUp->due_at->format('d M, H:i') }} · {{ App\Models\LookupValue::label(App\Models\LookupValue::FOLLOW_UP_TYPE, $followUp->type_code) }} · {{ $followUp->assignee->name }}
                                        @if ($followUp->isOverdue()) · {{ __('Overdue') }}@endif
                                    </p>
                                </div>
                                @can('follow_ups.manage')
                                    <x-ui.button variant="ghost" size="xs" icon="check" wire:click="openModal('complete-follow-up', {{ $followUp->id }})">{{ __('Done') }}</x-ui.button>
                                @endcan
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('No pending follow-ups.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>

    {{-- Modals: one open at a time, driven by $modal --}}
    @if ($modal === 'assign')
        <x-ui.modal wire:model="modal" :title="__('Assign enquiry')">
            <form id="assign-form" wire:submit="assign" class="space-y-4">
                <x-ui.select :label="__('Salesman')" wire:model="assignTo" name="assignTo" :options="$employees" :placeholder="__('Unassigned')" />
                <x-ui.textarea :label="__('Reason')" wire:model="assignReason" name="assignReason" required rows="2" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeModal">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="assign-form" wire:target="assign">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if (in_array($modal, ['follow-up', 'complete-follow-up'], true))
        <x-ui.modal wire:model="modal" :title="$modal === 'follow-up' ? __('Schedule follow-up') : __('Complete follow-up')">
            <form id="follow-up-form" wire:submit="{{ $modal === 'follow-up' ? 'scheduleFollowUp' : 'completeFollowUp' }}" class="space-y-4">
                @if ($modal === 'complete-follow-up')
                    <x-ui.textarea :label="__('Outcome')" wire:model="followUpOutcome" name="followUpOutcome" required rows="3" />
                    <x-ui.checkbox :label="__('Schedule the next follow-up')" wire:model.live="scheduleNext" />
                @endif
                @if ($modal === 'follow-up' || $scheduleNext)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select :label="__('Type')" wire:model="followUpType" name="followUpType" :options="$followUpTypes" required />
                        <x-ui.input type="datetime-local" :label="__('Due')" wire:model="followUpDueAt" name="followUpDueAt" required />
                    </div>
                    <x-ui.input :label="__('Purpose')" wire:model="followUpPurpose" name="followUpPurpose" required />
                    @if ($modal === 'follow-up')
                        <x-ui.select :label="__('Assigned to')" wire:model="followUpAssignee" name="followUpAssignee" :options="$employees" required />
                    @endif
                @endif
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeModal">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="follow-up-form">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($modal === 'stage' && $targetStage)
        <x-ui.modal wire:model="modal" :tone="$targetStage->is_rejection ? 'danger' : null"
            :title="__('Move to :stage', ['stage' => $targetStage->name])"
            :description="$targetStage->is_final ? __('This closes the enquiry.') : null">
            <form id="stage-form" wire:submit="moveStage" class="space-y-4">
                @if ($targetStage->is_final && $targetStage->is_rejection)
                    <x-ui.select :label="__('Reason')" wire:model="closeReason" name="closeReason" :options="$closeReasons" :placeholder="__('Select reason…')" required />
                @endif
                <x-ui.textarea :label="__('Remarks')" wire:model="stageRemarks" name="stageRemarks" rows="3" :required="$targetStage->requires_remark" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeModal">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="stage-form" :variant="$targetStage->is_rejection ? 'danger' : 'primary'" wire:target="moveStage">{{ __('Confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if (in_array($modal, ['reopen', 'request-reopen'], true))
        <x-ui.modal wire:model="modal" :title="$modal === 'reopen' ? __('Reopen enquiry') : __('Request to reopen')"
            :description="$modal === 'reopen' ? __('The enquiry returns to its last open stage.') : __('A manager must approve the request.')">
            <form id="reopen-form" wire:submit="{{ $modal === 'reopen' ? 'reopen' : 'requestReopen' }}">
                <x-ui.textarea :label="__('Reason')" wire:model="reopenReason" name="reopenReason" required rows="3" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeModal">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="reopen-form">{{ $modal === 'reopen' ? __('Reopen') : __('Send request') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
