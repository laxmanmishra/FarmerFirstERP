@php use App\Support\Money; @endphp
<div>
    <div class="mb-6 overflow-hidden rounded-(--radius-card) border border-slate-200 bg-white shadow-(--shadow-card)">
        <div class="bg-linear-to-r from-brand-900 to-brand-700 px-6 py-5 text-white">
            <nav aria-label="Breadcrumb" class="mb-2 text-xs text-brand-200">
                <a href="{{ route('sales.customers.index') }}" wire:navigate class="hover:text-white">{{ __('Customers') }}</a> / {{ $customer->customer_no }}
            </nav>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="grid size-14 place-items-center rounded-full bg-white/15 text-lg font-semibold">
                        {{ \Illuminate\Support\Str::of($customer->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold">{{ $customer->name }}</h1>
                        <p class="text-sm text-brand-100">{{ $customer->customer_no }} · {{ $customer->mobile }} · {{ $customer->locationLabel() }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" icon="phone" href="tel:{{ $customer->mobile }}">{{ __('Call') }}</x-ui.button>
                    @can('customers.update')
                        <x-ui.button variant="secondary" icon="pencil" wire:click="editProfile">{{ __('Edit profile') }}</x-ui.button>
                    @endcan
                    @can('enquiries.create')
                        <x-ui.button icon="plus" :href="route('crm.enquiries.create', ['farmer' => $customer->farmer_id])" wire:navigate>{{ __('New enquiry') }}</x-ui.button>
                    @endcan
                </div>
            </div>
        </div>
        <div class="grid divide-y divide-slate-100 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
            @foreach ([[__('Enquiries'), $kpis['enquiries']], [__('Open quotations'), $kpis['openQuotations']], [__('Active deals'), $kpis['activeDeals']], [__('Approved business'), Money::format($kpis['approvedValue'])]] as [$label, $value])
                <div class="px-6 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="tabular mt-1 text-xl font-semibold text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @if ($customer->possibleDuplicateOf)
        <x-ui.alert tone="warning" class="mb-6" :title="__('Possible duplicate')">
            {{ __('This customer shares a mobile number with') }}
            <a href="{{ route('sales.customers.show', $customer->possibleDuplicateOf) }}" wire:navigate class="font-medium underline">{{ $customer->possibleDuplicateOf->customer_no }} · {{ $customer->possibleDuplicateOf->name }}</a>.
            {{ __('Check whether they are the same person.') }}
        </x-ui.alert>
    @endif

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="array_filter(['overview' => __('Overview'), 'enquiries' => __('Enquiries'), 'quotations' => __('Quotations'), 'deals' => __('Deals'),
        'orders' => auth()->user()->can('orders.view') ? __('Orders') : null, 'documents' => auth()->user()->can('documents.view') ? __('Documents') : null, 'timeline' => __('Timeline')])" />

    @if ($tab === 'overview')
        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.card :title="__('Profile')">
                <dl class="grid grid-cols-2 gap-4">
                    <x-ui.dl-item :label="__('Father / husband')">{{ $customer->father_name }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Alternate mobile')">{{ $customer->alternate_mobile }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('WhatsApp')">{{ $customer->whatsapp_number }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Email')">{{ $customer->email }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('PAN')">{{ $customer->pan }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Customer since')">{{ $customer->customer_since->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item class="col-span-2" :label="__('Address')">{{ collect([$customer->address, $customer->locationLabel(), $customer->pin_code])->filter()->implode(', ') }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Farmer record')"><a href="{{ route('crm.farmers.show', $customer->farmer_id) }}" wire:navigate class="text-brand-700 hover:underline">{{ $customer->farmer->farmer_no }}</a></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Branch')">{{ $customer->branch->name }}</x-ui.dl-item>
                </dl>
            </x-ui.card>
            <div class="space-y-6 xl:col-span-2">
                <x-ui.card :title="__('Deals')" :padding="false">
                    @include('livewire.sales.customers.partials.deals', ['deals' => $deals->take(5)])
                </x-ui.card>
                <x-ui.card :title="__('Recent enquiries')" :padding="false">
                    @include('livewire.sales.customers.partials.enquiries', ['enquiries' => $enquiries->take(5)])
                </x-ui.card>
            </div>
        </div>
    @elseif ($tab === 'enquiries')
        <x-ui.card :padding="false">@include('livewire.sales.customers.partials.enquiries', ['enquiries' => $enquiries])</x-ui.card>
    @elseif ($tab === 'quotations')
        <x-ui.card :padding="false">
            <ul class="divide-y divide-slate-100">
                @forelse ($quotations as $quotation)
                    <li><a href="{{ route('sales.quotations.show', $quotation) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50">
                        <span class="tabular text-sm font-medium">{{ $quotation->reference() }}</span>
                        <span class="text-xs text-slate-500">{{ $quotation->created_at->format('d M Y') }}</span>
                        <span class="tabular text-sm">{{ Money::format($quotation->net_amount) }}</span>
                        <x-ui.badge :tone="$quotation->status->tone()">{{ $quotation->status->label() }}</x-ui.badge>
                    </a></li>
                @empty
                    <li class="px-5 py-10"><x-ui.empty-state :title="__('No quotations')" icon="document-text" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    @elseif ($tab === 'deals')
        <x-ui.card :padding="false">@include('livewire.sales.customers.partials.deals', ['deals' => $deals])</x-ui.card>
    @elseif ($tab === 'orders')
        <x-ui.card :padding="false">
            <ul class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <li><a href="{{ route('sales.orders.show', $order) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50">
                        <span class="tabular text-sm font-medium">{{ $order->order_no }}</span>
                        <span class="text-xs text-slate-500">{{ $order->order_date->format('d M Y') }}</span>
                        <span class="tabular text-sm">{{ Money::format($order->order_value) }}</span>
                        <x-ui.stage-badge :stage="$order->stage" />
                    </a></li>
                @empty
                    <li class="px-5 py-10"><x-ui.empty-state :title="__('No orders')" icon="clipboard" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    @elseif ($tab === 'documents')
        <x-ui.card :padding="false" :title="__('Documents')" :description="__('Reusable documents (Aadhaar, PAN, land records…) are linked to new orders instead of being uploaded again.')">
            <ul class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <li><a href="{{ route('fulfilment.documents.show', $document) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50">
                        <span class="text-sm font-medium text-slate-900">{{ $document->type->name }} <span class="tabular text-xs font-normal text-slate-500">· {{ $document->document_no }} · v{{ $document->current_version }}</span></span>
                        <span class="text-xs text-slate-500">{{ $document->order?->order_no }}@if ($document->expiry_date) · {{ __('expires :d', ['d' => $document->expiry_date->format('d M Y')]) }}@endif</span>
                        <x-ui.badge :tone="$document->status->tone()">{{ $document->status->label() }}</x-ui.badge>
                    </a></li>
                @empty
                    <li class="px-5 py-10"><x-ui.empty-state :title="__('No documents yet')" icon="folder" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    @else
        <x-ui.card><x-ui.timeline :items="$timeline" /></x-ui.card>
    @endif

    <x-ui.drawer wire:model="showEdit" :title="__('Edit customer profile')" :description="__('Changes are recorded in the audit log.')">
        <form id="profile-form" wire:submit="saveProfile" class="space-y-4">
            <x-ui.input :label="__('Name')" wire:model="profile.name" name="profile.name" required />
            <x-ui.input :label="__('Father / husband name')" wire:model="profile.father_name" name="profile.father_name" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('Mobile')" wire:model="profile.mobile" name="profile.mobile" inputmode="numeric" maxlength="10" required />
                <x-ui.input :label="__('Alternate mobile')" wire:model="profile.alternate_mobile" name="profile.alternate_mobile" inputmode="numeric" maxlength="10" />
                <x-ui.input :label="__('WhatsApp')" wire:model="profile.whatsapp_number" name="profile.whatsapp_number" inputmode="numeric" maxlength="10" />
                <x-ui.input type="email" :label="__('Email')" wire:model="profile.email" name="profile.email" />
                <x-ui.input :label="__('PAN')" wire:model="profile.pan" name="profile.pan" maxlength="10" class="uppercase" />
                <x-ui.input :label="__('PIN code')" wire:model="profile.pin_code" name="profile.pin_code" inputmode="numeric" maxlength="6" />
            </div>
            <x-ui.textarea :label="__('Address')" wire:model="profile.address" name="profile.address" rows="2" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="profile-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
