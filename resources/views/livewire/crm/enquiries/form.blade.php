<div>
    <x-ui.page-header :title="$enquiryId ? __('Edit enquiry') : __('New enquiry')"
        :description="$enquiryId ? null : __('New enquiries enter the telecaller validation queue as Unverified.')"
        :breadcrumbs="[__('CRM') => null, __('Enquiries') => route('crm.enquiries.index'), ($enquiryId ? __('Edit') : __('New')) => null]" />

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3" novalidate>
        <div class="space-y-6 xl:col-span-2">
            {{-- Farmer --}}
            <x-ui.card :title="__('Farmer')">
                @if ($farmer)
                    <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg bg-slate-50 p-4">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $farmer->name }} <span class="text-xs font-normal text-slate-500">{{ $farmer->farmer_no }}</span></p>
                            <p class="text-sm text-slate-600">{{ $farmer->mobile }} · {{ $farmer->locationLabel() }}</p>
                            <p class="text-xs text-slate-500">{{ trans_choice(':count previous enquiry|:count previous enquiries', $farmer->enquiries_count) }}</p>
                        </div>
                        @unless ($enquiryId)
                            <x-ui.button variant="ghost" size="sm" wire:click="clearFarmer">{{ __('Change') }}</x-ui.button>
                        @endunless
                    </div>
                @elseif ($creatingFarmer)
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input :label="__('Farmer name')" wire:model="newFarmer.name" name="newFarmer.name" required />
                            <x-ui.input :label="__('Father / husband name')" wire:model="newFarmer.father_name" name="newFarmer.father_name" />
                            <x-ui.input type="tel" :label="__('Mobile')" wire:model="newFarmer.mobile" name="newFarmer.mobile" inputmode="numeric" maxlength="10" required />
                            <x-ui.input type="tel" :label="__('Alternate mobile')" wire:model="newFarmer.alternate_mobile" name="newFarmer.alternate_mobile" inputmode="numeric" maxlength="10" />
                        </div>
                        @include('livewire.partials.village-picker', ['picker' => $picker])

                        @error('duplicate_farmer')
                            <x-ui.alert tone="warning" :title="$message">
                                <ul class="mt-2 space-y-1">
                                    @foreach ($duplicateFarmers as $match)
                                        <li class="flex flex-wrap items-center gap-2">
                                            <span>{{ $match->farmer_no }} · {{ $match->name }} — {{ $match->mobile }}, {{ $match->locationLabel() }}</span>
                                            <x-ui.button size="xs" variant="secondary" wire:click="selectFarmer({{ $match->id }})">{{ __('Use this farmer') }}</x-ui.button>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="mt-3"><x-ui.checkbox :label="__('This is a different farmer — create anyway')" wire:model="confirmNewFarmer" /></div>
                            </x-ui.alert>
                        @enderror
                        <x-ui.button variant="ghost" size="sm" wire:click="clearFarmer">{{ __('Search existing farmers instead') }}</x-ui.button>
                    </div>
                @else
                    <div class="space-y-3">
                        <x-ui.search-input wire:model.live.debounce.300ms="farmerSearch" :placeholder="__('Search by mobile number or name…')" autofocus />
                        @error('farmerId')<p class="text-xs font-medium text-rose-600">{{ __('Select an existing farmer or add a new one.') }}</p>@enderror
                        @if ($farmerMatches->isNotEmpty())
                            <ul class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                                @foreach ($farmerMatches as $match)
                                    <li wire:key="match-{{ $match->id }}">
                                        <button type="button" wire:click="selectFarmer({{ $match->id }})" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-brand-50">
                                            <span>
                                                <span class="block text-sm font-medium text-slate-900">{{ $match->name }}</span>
                                                <span class="block text-xs text-slate-500">{{ $match->mobile }} · {{ $match->locationLabel() }}</span>
                                            </span>
                                            <x-ui.icon name="chevron-right" class="size-4 text-slate-400" />
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (mb_strlen(trim($farmerSearch)) >= 3)
                            <p class="text-sm text-slate-500">{{ __('No farmer found for ":term".', ['term' => $farmerSearch]) }}</p>
                        @endif
                        @can('farmers.create')
                            <x-ui.button variant="secondary" icon="plus" wire:click="startNewFarmer">{{ __('Add new farmer') }}</x-ui.button>
                        @endcan
                    </div>
                @endif
            </x-ui.card>

            {{-- Requirements --}}
            <x-ui.card :title="__('Requirement')" :description="__('One or more tractors or implements.')">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addRequirement">{{ __('Add line') }}</x-ui.button>
                </x-slot:actions>
                <div class="space-y-4">
                    @error('requirements')<p class="text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    @foreach ($requirements as $index => $line)
                        <div wire:key="req-{{ $index }}" class="rounded-lg border border-slate-200 p-4">
                            <div class="grid gap-4 sm:grid-cols-6">
                                <div class="sm:col-span-2">
                                    <x-ui.select :label="__('Type')" wire:model.live="requirements.{{ $index }}.requirement_type" name="requirements.{{ $index }}.requirement_type" :options="$productTypes" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-ui.select :label="__('Brand')" wire:model.live="requirements.{{ $index }}.brand_id" name="requirements.{{ $index }}.brand_id" :options="$brands" :placeholder="__('Any brand')" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-ui.input type="number" :label="__('Quantity')" wire:model="requirements.{{ $index }}.quantity" name="requirements.{{ $index }}.quantity" min="1" max="50" />
                                </div>
                                <div class="sm:col-span-3">
                                    <x-ui.select :label="__('Model')" wire:model.live="requirements.{{ $index }}.product_id" name="requirements.{{ $index }}.product_id" :placeholder="__('Not decided')"
                                        :options="$products->where('product_type.value', $line['requirement_type'])->when($line['brand_id'], fn ($c) => $c->where('brand_id', (int) $line['brand_id']))->mapWithKeys(fn ($p) => [$p->id => $p->brand->name.' '.$p->name.($p->hp ? ' ('.$p->hp.' HP)' : '')])" />
                                </div>
                                <div class="sm:col-span-3">
                                    <x-ui.select :label="__('Variant')" wire:model="requirements.{{ $index }}.product_variant_id" name="requirements.{{ $index }}.product_variant_id" :placeholder="__('Not decided')"
                                        :disabled="! $line['product_id']" :options="$variants->where('product_id', (int) $line['product_id'])->pluck('name', 'id')" />
                                </div>
                                <div class="sm:col-span-6">
                                    <x-ui.input :label="__('Notes')" wire:model="requirements.{{ $index }}.description" name="requirements.{{ $index }}.description" :placeholder="__('e.g. needs 4WD, loader, specific colour')" />
                                </div>
                            </div>
                            @if (count($requirements) > 1)
                                <div class="mt-3 flex justify-end">
                                    <x-ui.button variant="danger-ghost" size="xs" wire:click="removeRequirement({{ $index }})">{{ __('Remove line') }}</x-ui.button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            {{-- Exchange --}}
            @if ($deal_type === 'exchange')
                <x-ui.card :title="__('Old tractor for exchange')">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input :label="__('Brand')" wire:model="exchange.brand_name" name="exchange.brand_name" required />
                        <x-ui.input :label="__('Model')" wire:model="exchange.model_name" name="exchange.model_name" required />
                        <x-ui.input type="number" :label="__('Year of manufacture')" wire:model="exchange.manufacturing_year" name="exchange.manufacturing_year" />
                        <x-ui.input type="number" :label="__('Hours used')" wire:model="exchange.hours_used" name="exchange.hours_used" />
                        <x-ui.input :label="__('Registration no')" wire:model="exchange.registration_number" name="exchange.registration_number" class="uppercase" />
                        <x-ui.select :label="__('Condition')" wire:model="exchange.condition" name="exchange.condition" :placeholder="__('Select…')"
                            :options="['excellent' => __('Excellent'), 'good' => __('Good'), 'average' => __('Average'), 'poor' => __('Poor')]" />
                        <x-ui.input :label="__('Customer expected price (₹)')" wire:model="exchange.customer_expected_price" name="exchange.customer_expected_price" inputmode="numeric"
                            :hint="__('What the customer expects. The approved exchange value is set later by management.')" />
                        <div class="sm:col-span-2"><x-ui.input :label="__('Remarks')" wire:model="exchange.remarks" name="exchange.remarks" /></div>
                    </div>

                    <div class="mt-5">
                        <p class="text-sm font-medium text-slate-700">{{ __('Photos (optional)') }}</p>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach ($existingPhotos as $photo)
                                <img src="{{ route('crm.enquiries.attachments.show', $photo) }}" alt="{{ $photo->original_name }}" class="size-20 rounded-lg object-cover ring-1 ring-slate-200">
                            @endforeach
                            @foreach ($photos as $index => $photo)
                                <div wire:key="photo-{{ $index }}" class="relative">
                                    @if (method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-20 rounded-lg object-cover ring-1 ring-slate-200">
                                    @else
                                        <div class="grid size-20 place-items-center rounded-lg bg-slate-100 text-xs text-slate-500">{{ __('File') }}</div>
                                    @endif
                                    <button type="button" wire:click="removePhoto({{ $index }})" class="absolute -right-2 -top-2 grid size-6 place-items-center rounded-full bg-white text-slate-500 shadow ring-1 ring-slate-200 hover:text-rose-600">
                                        <x-ui.icon name="x" class="size-3" /><span class="sr-only">{{ __('Remove') }}</span>
                                    </button>
                                </div>
                            @endforeach
                            <label class="grid size-20 cursor-pointer place-items-center rounded-lg border-2 border-dashed border-slate-300 text-slate-400 hover:border-brand-400 hover:text-brand-600">
                                <x-ui.icon name="plus" class="size-6" />
                                <input type="file" wire:model="photos" multiple accept="image/*" capture="environment" class="sr-only">
                            </label>
                        </div>
                        <div wire:loading wire:target="photos" class="mt-2 text-xs text-slate-500">{{ __('Uploading…') }}</div>
                        @error('photos')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        @error('photos.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </x-ui.card>
            @endif
        </div>

        {{-- Side panel --}}
        <div class="space-y-6">
            <x-ui.card :title="__('Details')">
                <div class="space-y-4">
                    <x-ui.select :label="__('Source')" wire:model="source_code" name="source_code" :options="$sources" :placeholder="__('Select source…')" required />
                    <x-ui.select :label="__('Deal type')" wire:model.live="deal_type" name="deal_type" :options="$dealTypes" required />
                    <div>
                        <x-ui.input type="date" :label="__('Expected purchase date')" wire:model.live="expected_purchase_date" name="expected_purchase_date" min="{{ today()->toDateString() }}" required />
                        <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                            {{ __('Temperature') }}: @if ($temperature)<x-ui.temperature-badge :temperature="$temperature" />@else<span>—</span>@endif
                        </div>
                    </div>
                    <x-ui.input :label="__('Budget (₹)')" wire:model="budget" name="budget" inputmode="numeric" />
                    @if ($assignees->isNotEmpty() && ! $enquiryId)
                        <x-ui.select :label="__('Assign to salesman')" wire:model="assigneeId" name="assigneeId" :options="$assignees" :placeholder="__('Auto (territory)')" />
                    @endif
                    <x-ui.textarea :label="__('Remarks')" wire:model="remarks" name="remarks" rows="3" />
                </div>
            </x-ui.card>

            @error('duplicate_enquiry')
                <x-ui.card class="border-amber-300">
                    <x-ui.alert tone="warning" :title="__('Possible duplicate enquiry')">{{ $message }}</x-ui.alert>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($duplicateEnquiries as $match)
                            <li class="rounded-lg border border-slate-200 p-3">
                                <a href="{{ route('crm.enquiries.show', $match) }}" target="_blank" class="font-medium text-brand-700 underline">{{ $match->enquiry_no }}</a>
                                <span class="text-slate-500">· {{ $match->created_at->format('d M Y') }}</span>
                                <p class="text-slate-700">{{ $match->requirements->map->summary()->implode(', ') }}</p>
                                <p class="text-xs text-slate-500">{{ $match->currentStage()?->name }} · {{ $match->assignee?->name ?? __('Unassigned') }}</p>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-4">
                        <x-ui.textarea :label="__('Why is this a new enquiry?')" wire:model="duplicateOverrideReason" name="duplicateOverrideReason" rows="2" required
                            :hint="__('Recorded against the enquiry for review.')" />
                    </div>
                </x-ui.card>
            @enderror

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end xl:flex-col">
                <x-ui.button type="submit" size="lg" wire:target="save,photos" wire:loading.attr="disabled" class="w-full">
                    {{ $enquiryId ? __('Save changes') : ($duplicateEnquiryIds !== [] ? __('Confirm and create enquiry') : __('Create enquiry')) }}
                </x-ui.button>
                <x-ui.button variant="secondary" size="lg" class="w-full" :href="$enquiryId ? route('crm.enquiries.show', $enquiryId) : route('crm.enquiries.index')" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
