<div>
    <x-ui.page-header :title="__('Telecaller')" :description="__('Claim an enquiry, call the farmer and record the outcome. Every attempt is kept.')"
        :breadcrumbs="[__('CRM') => null, __('Telecaller') => null]" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
        <x-ui.kpi-card :label="__('New, never called')" :value="$counts['new']" icon="inbox" tone="amber" />
        <x-ui.kpi-card :label="__('Callbacks due')" :value="$counts['due']" icon="clock" tone="rose" />
        <x-ui.kpi-card :label="__('Callbacks later')" :value="$counts['scheduled']" icon="calendar" tone="sky" />
        <x-ui.kpi-card :label="__('Claimed by me')" :value="$counts['mine']" icon="user" tone="brand" />
        <x-ui.kpi-card :label="__('My calls today')" :value="$counts['callsToday']" icon="phone" tone="slate" />
        <x-ui.kpi-card :label="__('Validated today')" :value="$counts['validatedToday']" icon="check-circle" tone="brand" />
    </div>

    <x-ui.tabs class="mb-4" :active="$tab" :tabs="['queue' => __('Queue'), 'callbacks' => __('Scheduled callbacks'), 'mine' => __('Claimed by me'), 'done' => __('My calls today')]" />

    <x-ui.table :paginator="$rows">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Enquiry no, farmer or mobile…')" />
        </x-slot:toolbar>

        @if ($tab === 'done')
            <x-slot:head>
                <x-ui.th>{{ __('Time') }}</x-ui.th>
                <x-ui.th>{{ __('Enquiry') }}</x-ui.th>
                <x-ui.th>{{ __('Outcome') }}</x-ui.th>
                <x-ui.th>{{ __('Remarks') }}</x-ui.th>
            </x-slot:head>
            @forelse ($rows as $call)
                <tr wire:key="call-{{ $call->id }}">
                    <x-ui.td class="tabular text-xs text-slate-500">{{ $call->called_at->format('H:i') }}</x-ui.td>
                    <x-ui.td>
                        <a href="{{ route('crm.enquiries.show', $call->enquiry_id) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $call->enquiry->enquiry_no }}</a>
                        <p class="text-xs text-slate-500">{{ $call->enquiry->farmer->name }} · {{ $call->enquiry->farmer->village->name }}</p>
                    </x-ui.td>
                    <x-ui.td><x-ui.stage-badge :stage="$call->outcome" /></x-ui.td>
                    <x-ui.td class="max-w-md truncate text-sm">{{ $call->remarks ?? '—' }}</x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="4" :title="__('No calls recorded today')" icon="phone" />
            @endforelse
        @else
            <x-slot:head>
                <x-ui.th>{{ __('Enquiry') }}</x-ui.th>
                <x-ui.th>{{ __('Farmer') }}</x-ui.th>
                <x-ui.th>{{ __('Requirement') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Calls') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @forelse ($rows as $enquiry)
                @php
                    $claimActive = $enquiry->claimed_by_employee_id && $enquiry->claimed_at && $enquiry->claimed_at->gte($expiredBefore);
                    $mine = $claimActive && $enquiry->claimed_by_employee_id === $employeeId;
                @endphp
                <tr wire:key="tq-{{ $enquiry->id }}" @class(['hover:bg-slate-50/70', 'bg-brand-50/50' => $mine])>
                    <x-ui.td>
                        <a href="{{ route('crm.enquiries.show', $enquiry) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $enquiry->enquiry_no }}</a>
                        <p class="text-xs text-slate-400">{{ $enquiry->created_at->diffForHumans() }}</p>
                    </x-ui.td>
                    <x-ui.td>
                        <p class="font-medium text-slate-800">{{ $enquiry->farmer->name }}</p>
                        <p class="tabular text-xs text-slate-500">{{ $enquiry->farmer->mobile }} · {{ $enquiry->farmer->village->name }}</p>
                    </x-ui.td>
                    <x-ui.td class="max-w-xs truncate text-sm">{{ $enquiry->requirements->map->summary()->implode(', ') }}</x-ui.td>
                    <x-ui.td>
                        <x-ui.stage-badge :stage="$enquiry->validationStage" />
                        @if ($enquiry->callback_at)
                            <p @class(['mt-1 text-xs', 'font-semibold text-rose-600' => $enquiry->callback_at->isPast(), 'text-slate-500' => $enquiry->callback_at->isFuture()])>
                                {{ __('Callback :time', ['time' => $enquiry->callback_at->format('d M, H:i')]) }}
                            </p>
                        @endif
                        @if ($claimActive && ! $mine)<p class="mt-1 text-xs text-slate-500">{{ __('With :name', ['name' => $enquiry->claimedBy?->name]) }}</p>@endif
                    </x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ $enquiry->call_attempts_count }}</x-ui.td>
                    <x-ui.td align="right">
                        <div class="flex justify-end gap-1 whitespace-nowrap">
                            @if ($mine)
                                @can('telecaller.validate')
                                    <x-ui.button size="xs" icon="phone" wire:click="openCall({{ $enquiry->id }})">{{ __('Record call') }}</x-ui.button>
                                @endcan
                                <x-ui.button variant="ghost" size="xs" wire:click="release({{ $enquiry->id }})">{{ __('Release') }}</x-ui.button>
                            @elseif (! $claimActive)
                                @can('telecaller.claim')
                                    <x-ui.button variant="secondary" size="xs" wire:click="claim({{ $enquiry->id }})">{{ __('Claim') }}</x-ui.button>
                                @endcan
                            @endif
                        </div>
                    </x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('Nothing waiting here')" icon="check-circle">{{ __('New enquiries appear here as soon as they are created.') }}</x-ui.empty-row>
            @endforelse
        @endif
    </x-ui.table>

    <x-ui.drawer wire:model="showCall" width="max-w-2xl" :title="$callEnquiry ? __('Call :name', ['name' => $callEnquiry->farmer->name]) : __('Call')"
        :description="$callEnquiry ? $callEnquiry->enquiry_no.' · '.$callEnquiry->farmer->locationLabel() : null">
        @if ($callEnquiry)
            <div class="space-y-6">
                <div class="flex flex-wrap gap-3">
                    <x-ui.button size="lg" icon="phone" href="tel:{{ $callEnquiry->farmer->mobile }}">{{ $callEnquiry->farmer->mobile }}</x-ui.button>
                    @if ($callEnquiry->farmer->alternate_mobile)
                        <x-ui.button variant="secondary" size="lg" icon="phone" href="tel:{{ $callEnquiry->farmer->alternate_mobile }}">{{ $callEnquiry->farmer->alternate_mobile }}</x-ui.button>
                    @endif
                </div>

                <div class="rounded-lg bg-slate-50 p-4 text-sm">
                    <p class="font-medium text-slate-800">{{ $callEnquiry->requirements->map->summary()->implode(', ') }}</p>
                    <p class="mt-1 text-slate-600">
                        {{ $callEnquiry->deal_type->label() }} · {{ __('expected :date', ['date' => $callEnquiry->expected_purchase_date->format('d M Y')]) }}
                        @if ($callEnquiry->budget) · {{ __('budget ₹:amount', ['amount' => number_format((float) $callEnquiry->budget)]) }}@endif
                    </p>
                    @if ($callEnquiry->exchangeTractor)
                        <p class="mt-1 text-slate-600">{{ __('Exchange: :tractor', ['tractor' => $callEnquiry->exchangeTractor->brand_name.' '.$callEnquiry->exchangeTractor->model_name]) }}</p>
                    @endif
                </div>

                <form id="call-form" wire:submit="recordCall" class="space-y-5">
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700">{{ __('Outcome') }} <span class="text-rose-500">*</span></legend>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($outcomes as $option)
                                <label wire:key="outcome-{{ $option->id }}" @class([
                                    'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition',
                                    'border-brand-500 bg-brand-50 ring-1 ring-brand-500' => (int) $outcomeId === $option->id,
                                    'border-slate-200 hover:border-slate-300' => (int) $outcomeId !== $option->id,
                                ])>
                                    <input type="radio" wire:model.live="outcomeId" value="{{ $option->id }}" class="sr-only">
                                    <x-ui.badge :tone="$option->color" dot>{{ $option->name }}</x-ui.badge>
                                </label>
                            @endforeach
                        </div>
                        @error('outcomeId')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </fieldset>

                    @if ($selectedOutcome && ! $selectedOutcome->is_final)
                        <x-ui.input type="datetime-local" :label="__('Call back at')" wire:model="callbackAt" name="callbackAt" :required="$selectedOutcome->requires_followup" />
                    @endif
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input type="number" step="0.5" :label="__('Duration (min)')" wire:model="durationMinutes" name="durationMinutes" min="0" />
                        <div class="sm:col-span-2 hidden sm:block"></div>
                    </div>
                    <x-ui.textarea :label="__('Remarks')" wire:model="remarks" name="remarks" rows="3" :required="(bool) $selectedOutcome?->requires_remark"
                        :hint="$selectedOutcome?->is_completion ? __('Valid enquiries move straight into the sales pipeline.') : null" />
                </form>

                @if ($callEnquiry->callAttempts->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700">{{ __('Previous calls') }}</p>
                        <ul class="space-y-2">
                            @foreach ($callEnquiry->callAttempts as $previous)
                                <li class="rounded-lg border border-slate-200 p-3 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <x-ui.stage-badge :stage="$previous->outcome" />
                                        <span class="text-xs text-slate-400">{{ $previous->called_at->format('d M, H:i') }} · {{ $previous->employee?->name }}</span>
                                    </div>
                                    @if ($previous->remarks)<p class="mt-1 text-slate-600">{{ $previous->remarks }}</p>@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            @if ($callEnquiry)
                <x-ui.button variant="ghost" wire:click="release({{ $callEnquiry->id }})">{{ __('Release without calling') }}</x-ui.button>
            @endif
            <x-ui.button type="submit" form="call-form" wire:target="recordCall">{{ __('Save call') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
