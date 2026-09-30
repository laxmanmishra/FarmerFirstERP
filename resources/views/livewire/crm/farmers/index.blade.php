<div>
    <x-ui.page-header :title="__('Farmers')" :description="__('Farmer master shared by enquiries, customers and orders.')"
        :breadcrumbs="[__('CRM') => null, __('Farmers') => null]">
        <x-slot:actions>
            @can('farmers.create')
                <x-ui.button icon="plus" wire:click="create">{{ __('New farmer') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table :paginator="$farmers">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, mobile or farmer no…')" />
            <select wire:model.live="district" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('District') }}">
                <option value="">{{ __('All districts') }}</option>
                @foreach ($picker['districts'] as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
            </select>
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="farmer_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Farmer no') }}</x-ui.th>
            <x-ui.th sortable="name" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Farmer') }}</x-ui.th>
            <x-ui.th>{{ __('Mobile') }}</x-ui.th>
            <x-ui.th>{{ __('Location') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Land (acres)') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Enquiries') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($farmers as $farmer)
            <tr wire:key="farmer-{{ $farmer->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="tabular text-xs font-medium text-slate-500">{{ $farmer->farmer_no }}</x-ui.td>
                <x-ui.td>
                    <a href="{{ route('crm.farmers.show', $farmer) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $farmer->name }}</a>
                    @if ($farmer->father_name)<p class="text-xs text-slate-500">{{ __('S/o or W/o') }} {{ $farmer->father_name }}</p>@endif
                </x-ui.td>
                <x-ui.td class="tabular">
                    <a href="tel:{{ $farmer->mobile }}" class="hover:text-brand-700">{{ $farmer->mobile }}</a>
                    @if ($farmer->alternate_mobile)<p class="text-xs text-slate-400">{{ $farmer->alternate_mobile }}</p>@endif
                </x-ui.td>
                <x-ui.td class="text-sm">{{ $farmer->locationLabel() }}</x-ui.td>
                <x-ui.td align="right" class="tabular">{{ $farmer->land_acres ?? '—' }}</x-ui.td>
                <x-ui.td align="right" class="tabular">{{ $farmer->enquiries_count }}</x-ui.td>
                <x-ui.td align="right">
                    <div class="flex justify-end gap-1 whitespace-nowrap">
                        @can('enquiries.create')
                            <x-ui.button variant="ghost" size="xs" icon="plus" :href="route('crm.enquiries.create', ['farmer' => $farmer->id])" wire:navigate>{{ __('Enquiry') }}</x-ui.button>
                        @endcan
                        @can('farmers.update')
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $farmer->id }})">{{ __('Edit') }}</x-ui.button>
                        @endcan
                    </div>
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('No farmers found')" icon="user" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" width="max-w-2xl" :title="$editingId ? __('Edit farmer') : __('New farmer')"
        :description="__('The system checks existing farmers by mobile number and by name within the village.')">
        <form id="farmer-form" wire:submit="save" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input :label="__('Farmer name')" wire:model="name" required />
                <x-ui.input :label="__('Father / husband name')" wire:model="father_name" />
                <x-ui.input type="tel" :label="__('Mobile')" wire:model.live.debounce.400ms="mobile" inputmode="numeric" maxlength="10" required />
                <x-ui.input type="tel" :label="__('Alternate mobile')" wire:model="alternate_mobile" inputmode="numeric" maxlength="10" />
                <x-ui.input type="tel" :label="__('WhatsApp number')" wire:model="whatsapp_number" inputmode="numeric" maxlength="10" />
                <x-ui.input :label="__('Land holding (acres)')" wire:model="land_acres" inputmode="decimal" />
            </div>

            @if ($duplicates->isNotEmpty())
                <x-ui.alert tone="warning" :title="__('Possible duplicate farmer')">
                    <ul class="mt-2 space-y-1">
                        @foreach ($duplicates as $match)
                            <li wire:key="dup-{{ $match->id }}">
                                <a href="{{ route('crm.farmers.show', $match) }}" target="_blank" class="font-medium underline">{{ $match->farmer_no }} · {{ $match->name }}</a>
                                — {{ $match->mobile }}, {{ $match->locationLabel() }} ({{ trans_choice(':count enquiry|:count enquiries', $match->enquiries_count) }})
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-3"><x-ui.checkbox :label="__('This is a different farmer — create anyway')" wire:model="confirmNotDuplicate" /></div>
                </x-ui.alert>
            @endif

            @include('livewire.partials.village-picker', ['picker' => $picker])
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2"><x-ui.input :label="__('Address / landmark')" wire:model="address" /></div>
                <x-ui.input :label="__('PIN code')" wire:model="pin_code" inputmode="numeric" maxlength="6" />
            </div>
            <x-ui.input :label="__('Occupation')" wire:model="occupation" />
            <x-ui.textarea :label="__('Remarks')" wire:model="remarks" rows="2" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="farmer-form" wire:target="save">{{ __('Save farmer') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
