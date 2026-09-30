<div>
    <x-ui.page-header :title="__('Roles & Permissions')" :description="__('Users can hold several roles; their permissions combine.')"
        :breadcrumbs="[__('Administration') => null, __('Roles & Permissions') => null]">
        <x-slot:actions>
            @can('roles.manage')
                <x-ui.button icon="plus" wire:click="create">{{ __('New role') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            <div wire:key="role-{{ $role->id }}" class="flex flex-col rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $role->name }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if ($role->name === App\Models\User::SUPER_ADMIN_ROLE)
                                {{ __('Bypasses all permission checks') }}
                            @else
                                {{ trans_choice(':count permission|:count permissions', $role->permissions_count) }}
                            @endif
                        </p>
                    </div>
                    @if (in_array($role->name, $defaultRoles, true))
                        <x-ui.badge>{{ __('Default') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="violet">{{ __('Custom') }}</x-ui.badge>
                    @endif
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                    <a href="{{ route('admin.users.index', ['role' => $role->name]) }}" wire:navigate class="text-xs font-medium text-slate-600 hover:text-brand-700">
                        {{ trans_choice(':count user|:count users', $role->users_count) }}
                    </a>
                    <div class="flex gap-1">
                        @if ($role->name !== App\Models\User::SUPER_ADMIN_ROLE)
                            <x-ui.button variant="ghost" size="xs" :icon="auth()->user()->can('roles.manage') ? 'pencil' : 'eye'" :href="route('admin.roles.edit', $role)" wire:navigate>
                                {{ auth()->user()->can('roles.manage') ? __('Permissions') : __('View') }}
                            </x-ui.button>
                        @endif
                        @can('roles.manage')
                            @unless (in_array($role->name, $defaultRoles, true))
                                <x-ui.button variant="danger-ghost" size="xs" wire:click="delete({{ $role->id }})" wire:confirm="{{ __('Delete the :name role?', ['name' => $role->name]) }}">{{ __('Delete') }}</x-ui.button>
                            @endunless
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-ui.modal wire:model="showForm" :title="__('New role')" :description="__('You will set its permissions on the next screen.')">
        <form id="role-form" wire:submit="save" class="space-y-4">
            <x-ui.input :label="__('Role name')" wire:model="name" required />
            <x-ui.select :label="__('Start from permissions of')" wire:model="copyFromId" :placeholder="__('No permissions')"
                :options="$roles->reject(fn ($role) => $role->name === App\Models\User::SUPER_ADMIN_ROLE)->pluck('name', 'id')" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="role-form" wire:target="save">{{ __('Create role') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
