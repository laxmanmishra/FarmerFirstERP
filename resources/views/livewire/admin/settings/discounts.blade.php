<div class="max-w-3xl">
    <x-ui.alert class="mb-4">{{ __('A user may give a discount up to the most generous limit among their roles, and within the amount cap if one is set. Larger discounts need approval. Super Admin is unlimited.') }}</x-ui.alert>
    <form wire:submit="save">
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Role') }}</x-ui.th>
                <x-ui.th>{{ __('Maximum discount %') }}</x-ui.th>
                <x-ui.th>{{ __('Maximum amount (₹)') }}</x-ui.th>
            </x-slot:head>
            @foreach ($roles as $role)
                <tr wire:key="dl-{{ $role->id }}">
                    <x-ui.td class="font-medium text-slate-900">{{ $role->name }}</x-ui.td>
                    <x-ui.td><input type="text" inputmode="decimal" wire:model="limits.{{ $role->id }}.max_percent" @disabled(! $canManage) class="form-control w-28" aria-label="{{ __('Maximum discount % for :role', ['role' => $role->name]) }}"></x-ui.td>
                    <x-ui.td><input type="text" inputmode="decimal" wire:model="limits.{{ $role->id }}.max_amount" @disabled(! $canManage) placeholder="{{ __('No cap') }}" class="form-control w-40" aria-label="{{ __('Maximum amount for :role', ['role' => $role->name]) }}"></x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
        @error('limits.*')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
        @if ($canManage)
            <div class="mt-4 flex justify-end"><x-ui.button type="submit">{{ __('Save limits') }}</x-ui.button></div>
        @endif
    </form>
</div>
