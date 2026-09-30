<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <x-ui.alert class="flex-1">{{ __('Financers and their contacts never get an ERP login. Retail staff record their decisions on the finance file.') }}</x-ui.alert>
        <x-ui.button icon="plus" wire:click="editFinancer">{{ __('New financer') }}</x-ui.button>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($financers as $financer)
            <x-ui.card wire:key="fin-{{ $financer->id }}" :padding="false">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $financer->name }}</p>
                        <p class="font-mono text-xs text-slate-400">{{ $financer->code }} · {{ App\Models\Financer::TYPES[$financer->type] ?? $financer->type }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.active-badge :active="$financer->is_active" />
                        <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="editFinancer({{ $financer->id }})">{{ __('Edit') }}</x-ui.button>
                    </div>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($financer->contacts as $contact)
                        <li class="flex items-center justify-between gap-2 px-5 py-2.5">
                            <div>
                                <p @class(['font-medium', 'text-slate-800' => $contact->is_active, 'text-slate-400 line-through' => ! $contact->is_active])>{{ $contact->name }}</p>
                                <p class="text-xs text-slate-500">{{ collect([$contact->designation, $contact->area, $contact->mobile])->filter()->implode(' · ') }}</p>
                            </div>
                            <x-ui.button size="sm" variant="ghost" wire:click="editContact({{ $financer->id }}, {{ $contact->id }})">{{ __('Edit') }}</x-ui.button>
                        </li>
                    @endforeach
                    <li class="px-5 py-2.5"><x-ui.button size="sm" variant="secondary" icon="plus" wire:click="editContact({{ $financer->id }})">{{ __('Add contact') }}</x-ui.button></li>
                </ul>
            </x-ui.card>
        @empty
            <x-ui.card class="lg:col-span-2"><x-ui.empty-state :title="__('No financers yet')" icon="banknotes" /></x-ui.card>
        @endforelse
    </div>

    @if ($drawer === 'financer')
        <x-ui.drawer wire:model="drawer" :title="$editingId ? __('Edit financer') : __('New financer')">
            <form id="financer-form" wire:submit="saveFinancer" class="space-y-4">
                <x-ui.input :label="__('Code')" wire:model="form.code" name="form.code" class="font-mono uppercase" required />
                <x-ui.input :label="__('Name')" wire:model="form.name" name="form.name" required />
                <x-ui.select :label="__('Type')" wire:model="form.type" name="form.type" :options="App\Models\Financer::TYPES" />
                <x-ui.checkbox :label="__('Active')" wire:model="form.is_active" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="financer-form" wire:target="saveFinancer">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @elseif ($drawer === 'contact')
        <x-ui.drawer wire:model="drawer" :title="$editingId ? __('Edit contact') : __('New contact')">
            <form id="contact-form" wire:submit="saveContact" class="space-y-4">
                <x-ui.input :label="__('Name')" wire:model="form.name" name="form.name" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Designation')" wire:model="form.designation" name="form.designation" />
                    <x-ui.input :label="__('Mobile')" wire:model="form.mobile" name="form.mobile" inputmode="numeric" maxlength="10" required />
                    <x-ui.input type="email" :label="__('Email')" wire:model="form.email" name="form.email" />
                    <x-ui.input :label="__('Area / branch')" wire:model="form.area" name="form.area" />
                </div>
                <x-ui.checkbox :label="__('Active')" wire:model="form.is_active" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="contact-form" wire:target="saveContact">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @endif
</div>
