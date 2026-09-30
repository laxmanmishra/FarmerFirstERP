<div>
    <x-ui.page-header :title="__('Enquiries')" :description="__('Every enquiry from creation through validation to WON, LOST or DROPPED.')"
        :breadcrumbs="[__('CRM') => null, __('Enquiries') => null]">
        <x-slot:actions>
            @can('enquiries.create')
                <x-ui.button icon="plus" :href="route('crm.enquiries.create')" wire:navigate>{{ __('New enquiry') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-4" model="view" :active="$view"
        :tabs="['open' => __('All open'), 'validation' => __('Awaiting validation'), 'pipeline' => __('In pipeline'), 'closed' => __('Closed'), 'all' => __('Everything')]" />

    <x-ui.table :paginator="$enquiries">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Enquiry no, farmer name or mobile…')" />
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="stage" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($stages->groupBy(fn ($stage) => $stage->definition->code) as $definition => $group)
                        <optgroup label="{{ $definition === App\Models\WorkflowDefinition::ENQUIRY_VALIDATION ? __('Validation status') : __('Pipeline') }}">
                            @foreach ($group as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <select wire:model.live="temperature" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Temperature') }}">
                    <option value="">{{ __('Any temperature') }}</option>
                    @foreach (App\Enums\Temperature::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                </select>
                @if ($assignees->isNotEmpty())
                    <select wire:model.live="assignee" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Salesman') }}">
                        <option value="">{{ __('Any salesman') }}</option>
                        <option value="none">{{ __('Unassigned') }}</option>
                        @foreach ($assignees as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                @endif
                <select wire:model.live="source" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Source') }}">
                    <option value="">{{ __('Any source') }}</option>
                    @foreach ($sources as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th sortable="enquiry_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Enquiry') }}</x-ui.th>
            <x-ui.th>{{ __('Farmer') }}</x-ui.th>
            <x-ui.th>{{ __('Requirement') }}</x-ui.th>
            <x-ui.th sortable="expected_purchase_date" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Expected') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th>{{ __('Salesman') }}</x-ui.th>
            <x-ui.th sortable="last_activity_at" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Last activity') }}</x-ui.th>
        </x-slot:head>

        @forelse ($enquiries as $enquiry)
            <tr wire:key="enquiry-{{ $enquiry->id }}" class="cursor-pointer hover:bg-slate-50/70" x-on:click="Livewire.navigate('{{ route('crm.enquiries.show', $enquiry) }}')">
                <x-ui.td>
                    <a href="{{ route('crm.enquiries.show', $enquiry) }}" wire:navigate class="tabular text-sm font-semibold text-slate-900 hover:text-brand-700" x-on:click.stop>{{ $enquiry->enquiry_no }}</a>
                    <p class="text-xs text-slate-400">{{ $enquiry->created_at->format('d M Y') }}</p>
                </x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-800">{{ $enquiry->farmer->name }}</p>
                    <p class="text-xs text-slate-500">{{ $enquiry->farmer->mobile }} · {{ $enquiry->farmer->village->name }}</p>
                </x-ui.td>
                <x-ui.td class="max-w-xs">
                    <p class="truncate text-sm">{{ $enquiry->requirements->map->summary()->implode(', ') }}</p>
                    @if ($enquiry->deal_type === App\Enums\DealType::Exchange)<x-ui.badge tone="violet" class="mt-1">{{ __('Exchange') }}</x-ui.badge>@endif
                </x-ui.td>
                <x-ui.td class="whitespace-nowrap">
                    <p class="tabular text-sm">{{ $enquiry->expected_purchase_date->format('d M Y') }}</p>
                    <x-ui.temperature-badge :temperature="$enquiry->temperature" class="mt-1" />
                </x-ui.td>
                <x-ui.td><x-ui.stage-badge :stage="$enquiry->currentStage()" /></x-ui.td>
                <x-ui.td class="text-sm">{{ $enquiry->assignee?->name ?? __('Unassigned') }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap text-xs text-slate-500">{{ $enquiry->last_activity_at?->diffForHumans() ?? '—' }}</x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('No enquiries match these filters')" icon="inbox" />
        @endforelse
    </x-ui.table>
</div>
