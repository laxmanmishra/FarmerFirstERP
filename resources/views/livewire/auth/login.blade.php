<div>
    <div class="mb-8 lg:hidden">
        <span class="grid size-10 place-items-center rounded-lg bg-brand-900 text-accent-500"><x-ui.icon name="tractor" class="size-6" /></span>
    </div>

    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ __('Sign in') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('Use your Farmer First employee account.') }}</p>

    @if ($errors->has('email') && ! $errors->has('password'))
        <x-ui.alert tone="danger" class="mt-6">{{ $errors->first('email') }}</x-ui.alert>
    @endif

    <form wire:submit="login" class="mt-6 space-y-5" novalidate>
        <x-ui.field :label="__('Email or mobile')" for="email" required>
            <input id="email" type="text" wire:model="email" autocomplete="username" autofocus required
                @class(['form-control', 'form-control-invalid' => $errors->has('email')]) />
        </x-ui.field>

        <x-ui.input type="password" :label="__('Password')" wire:model="password" autocomplete="current-password" required />

        <x-ui.checkbox :label="__('Keep me signed in')" wire:model="remember" />

        <x-ui.button type="submit" size="lg" class="w-full" wire:target="login">{{ __('Sign in') }}</x-ui.button>
    </form>

    <p class="mt-8 text-xs text-slate-400">{{ __('Forgot your password? Ask your administrator to reset it.') }}</p>
</div>
