<div>
    <x-ui.page-header :title="$farmer->name" :description="$farmer->farmer_no.' · '.$farmer->locationLabel()"
        :breadcrumbs="[__('CRM') => null, __('Farmers') => route('crm.farmers.index'), $farmer->farmer_no => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="phone" href="tel:{{ $farmer->mobile }}">{{ $farmer->mobile }}</x-ui.button>
            @can('enquiries.create')
                <x-ui.button icon="plus" :href="route('crm.enquiries.create', ['farmer' => $farmer->id])" wire:navigate>{{ __('New enquiry') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-ui.card :title="__('Profile')">
            <dl class="grid grid-cols-2 gap-4">
                <x-ui.dl-item :label="__('Father / husband')">{{ $farmer->father_name }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Alternate mobile')">{{ $farmer->alternate_mobile }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('WhatsApp')">{{ $farmer->whatsapp_number }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Land (acres)')">{{ $farmer->land_acres }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Occupation')">{{ $farmer->occupation }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('PIN code')">{{ $farmer->pin_code }}</x-ui.dl-item>
                <x-ui.dl-item class="col-span-2" :label="__('Address')">{{ $farmer->address }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Branch')">{{ $farmer->branch->name }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Added')">{{ $farmer->created_at->format('d M Y') }}@if ($farmer->creator) · {{ $farmer->creator->name }}@endif</x-ui.dl-item>
                @if ($farmer->remarks)<x-ui.dl-item class="col-span-2" :label="__('Remarks')">{{ $farmer->remarks }}</x-ui.dl-item>@endif
            </dl>
        </x-ui.card>

        <x-ui.card class="xl:col-span-2" :title="__('Enquiries')" :padding="false"
            :description="$hiddenCount > 0 ? trans_choice(':count more enquiry belongs to other salesmen.|:count more enquiries belong to other salesmen.', $hiddenCount) : null">
            <ul class="divide-y divide-slate-100">
                @forelse ($enquiries as $enquiry)
                    <li wire:key="enq-{{ $enquiry->id }}">
                        <a href="{{ route('crm.enquiries.show', $enquiry) }}" wire:navigate class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-slate-50">
                            <span class="tabular text-xs font-semibold text-slate-500">{{ $enquiry->enquiry_no }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm text-slate-800">{{ $enquiry->requirements->map->summary()->implode(', ') }}</span>
                            <x-ui.temperature-badge :temperature="$enquiry->temperature" />
                            <x-ui.stage-badge :stage="$enquiry->currentStage()" />
                            <span class="text-xs text-slate-500">{{ $enquiry->assignee?->name ?? __('Unassigned') }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10"><x-ui.empty-state :title="__('No enquiries yet')" icon="inbox" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
