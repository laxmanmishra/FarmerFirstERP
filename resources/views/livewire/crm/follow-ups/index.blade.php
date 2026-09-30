<div>
    <x-ui.page-header :title="__('Follow-ups')" :description="__('Scheduled calls, visits and demos across your enquiries.')"
        :breadcrumbs="[__('CRM') => null, __('Follow-ups') => null]" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi-card :label="__('Due today')" :value="$counts['today']" icon="calendar" tone="amber" wire:click="$set('tab', 'today')" class="cursor-pointer" />
        <x-ui.kpi-card :label="__('Overdue')" :value="$counts['overdue']" icon="exclamation" tone="rose" wire:click="$set('tab', 'overdue')" class="cursor-pointer" />
        <x-ui.kpi-card :label="__('Upcoming')" :value="$counts['upcoming']" icon="clock" tone="sky" wire:click="$set('tab', 'upcoming')" class="cursor-pointer" />
        <x-ui.kpi-card :label="__('Completed today')" :value="$counts['completedToday']" icon="check-circle" tone="brand" wire:click="$set('tab', 'completed')" class="cursor-pointer" />
    </div>

    <x-ui.tabs class="mb-4" :active="$tab" :tabs="['today' => __('Today'), 'overdue' => __('Overdue'), 'upcoming' => __('Upcoming'), 'completed' => __('Completed')]" />

    <x-ui.table :paginator="$followUps">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Purpose, enquiry, farmer…')" />
            @if ($canSeeTeam)
                <select wire:model.live="scope" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Scope') }}">
                    <option value="">{{ __('My team') }}</option>
                    <option value="mine">{{ __('Only mine') }}</option>
                </select>
            @endif
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th>{{ __('Due') }}</x-ui.th>
            <x-ui.th>{{ __('Follow-up') }}</x-ui.th>
            <x-ui.th>{{ __('Enquiry / farmer') }}</x-ui.th>
            <x-ui.th>{{ __('Assigned to') }}</x-ui.th>
            <x-ui.th>{{ $tab === 'completed' ? __('Outcome') : __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @forelse ($followUps as $followUp)
            @php $enquiry = $followUp->followable instanceof App\Models\Enquiry ? $followUp->followable : null; @endphp
            <tr wire:key="fu-{{ $followUp->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="whitespace-nowrap">
                    <p @class(['tabular text-sm font-medium', 'text-rose-600' => $followUp->isOverdue(), 'text-slate-800' => ! $followUp->isOverdue()])>{{ $followUp->due_at->format('d M, H:i') }}</p>
                    <p class="text-xs text-slate-400">{{ $followUp->due_at->diffForHumans() }}</p>
                </x-ui.td>
                <x-ui.td>
                    <p class="text-sm font-medium text-slate-800">{{ $followUp->purpose }}</p>
                    <p class="text-xs text-slate-500">{{ $types[$followUp->type_code] ?? App\Models\LookupValue::label(App\Models\LookupValue::FOLLOW_UP_TYPE, $followUp->type_code) }}</p>
                </x-ui.td>
                <x-ui.td>
                    @if ($enquiry)
                        <a href="{{ route('crm.enquiries.show', $enquiry) }}" wire:navigate class="text-sm font-medium text-slate-900 hover:text-brand-700">{{ $enquiry->enquiry_no }}</a>
                        <p class="text-xs text-slate-500">{{ $enquiry->farmer->name }} · <a href="tel:{{ $enquiry->farmer->mobile }}" class="tabular hover:text-brand-700">{{ $enquiry->farmer->mobile }}</a></p>
                    @elseif ($followUp->followable instanceof App\Models\Concerns\Followable)
                        <a href="{{ $followUp->followable->followUpUrl() }}" wire:navigate class="text-sm font-medium text-slate-900 hover:text-brand-700">{{ $followUp->followable->followUpSubject() }}</a>
                    @endif
                </x-ui.td>
                <x-ui.td class="text-sm">{{ $followUp->assignee->name }}</x-ui.td>
                <x-ui.td class="max-w-xs">
                    @if ($tab === 'completed')
                        <x-ui.badge :tone="$followUp->status === App\Enums\FollowUpStatus::Completed ? 'green' : 'slate'">{{ $followUp->status->label() }}</x-ui.badge>
                        <p class="mt-1 truncate text-xs text-slate-500">{{ $followUp->outcome }}</p>
                    @elseif ($followUp->isOverdue())
                        <x-ui.badge tone="rose">{{ __('Overdue') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="amber">{{ __('Pending') }}</x-ui.badge>
                    @endif
                </x-ui.td>
                <x-ui.td align="right">
                    @if ($followUp->status === App\Enums\FollowUpStatus::Pending)
                        @can('follow_ups.manage')
                            <div class="flex justify-end gap-1 whitespace-nowrap">
                                <x-ui.button size="xs" icon="check" wire:click="open('complete', {{ $followUp->id }})">{{ __('Complete') }}</x-ui.button>
                                <x-ui.button variant="ghost" size="xs" wire:click="open('cancel', {{ $followUp->id }})">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        @endcan
                    @endif
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('No follow-ups here')" icon="calendar" />
        @endforelse
    </x-ui.table>

    @if (in_array($modal, ['complete', 'cancel'], true))
        <x-ui.modal wire:model="modal" :title="$modal === 'complete' ? __('Complete follow-up') : __('Cancel follow-up')">
            <form id="fu-form" wire:submit="submit" class="space-y-4">
                <x-ui.textarea :label="$modal === 'complete' ? __('Outcome') : __('Reason')" wire:model="outcome" name="outcome" rows="3" required />
                @if ($modal === 'complete')
                    <x-ui.checkbox :label="__('Schedule the next follow-up')" wire:model.live="scheduleNext" />
                    @if ($scheduleNext)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select :label="__('Type')" wire:model="nextType" name="nextType" :options="$types" />
                            <x-ui.input type="datetime-local" :label="__('Due')" wire:model="nextDueAt" name="nextDueAt" />
                        </div>
                        <x-ui.input :label="__('Purpose')" wire:model="nextPurpose" name="nextPurpose" />
                    @endif
                @endif
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Close') }}</x-ui.button>
                <x-ui.button type="submit" form="fu-form" :variant="$modal === 'cancel' ? 'danger' : 'primary'" wire:target="submit">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
