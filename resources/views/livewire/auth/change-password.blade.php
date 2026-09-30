<div class="max-w-xl">
    <x-ui.page-header :title="__('Change password')" :description="__('Choose a strong password that you do not use anywhere else.')" />

    @if ($forced)
        <x-ui.alert tone="warning" class="mb-6" :title="__('Password change required')">
            {{ __('Your password was set by an administrator. Set your own password to continue.') }}
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form wire:submit="save" class="space-y-5">
            <x-ui.input type="password" :label="__('Current password')" wire:model="current_password" autocomplete="current-password" required />
            <x-ui.input type="password" :label="__('New password')" wire:model="password" autocomplete="new-password" required
                :hint="app()->isProduction() ? __('At least 10 characters with upper and lower case letters and a number.') : __('At least 8 characters with letters and a number.')" />
            <x-ui.input type="password" :label="__('Confirm new password')" wire:model="password_confirmation" autocomplete="new-password" required />

            <div class="flex justify-end">
                <x-ui.button type="submit" wire:target="save">{{ __('Update password') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
