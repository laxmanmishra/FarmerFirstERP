<div>
    <x-ui.page-header :title="$role->name" :description="trans_choice('Assigned to :count user|Assigned to :count users', $role->users_count)"
        :breadcrumbs="[__('Administration') => null, __('Roles & Permissions') => route('admin.roles.index'), $role->name => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.roles.index')" wire:navigate>{{ __('Back') }}</x-ui.button>
            @if ($canManage)
                <x-ui.button icon="check" wire:click="save">{{ __('Save permissions') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert class="mb-6">
        {{ __('Record-level scope still applies: "view own" limits a user to records they own, "view team" adds their reporting team, and branch access comes from the employee record.') }}
    </x-ui.alert>

    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($modules as $module => $definition)
            @php
                $modulePermissions = array_map(fn ($action) => "{$module}.{$action}", $definition['actions']);
                $selectedCount = count(array_intersect($modulePermissions, $permissions));
            @endphp
            <section wire:key="module-{{ $module }}" class="rounded-(--radius-card) border border-slate-200 bg-white shadow-(--shadow-card)">
                <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ $definition['label'] }}</h3>
                        <p class="text-xs text-slate-500">{{ $selectedCount }} / {{ count($modulePermissions) }}</p>
                    </div>
                    @if ($canManage)
                        <x-ui.button variant="ghost" size="xs" wire:click="toggleModule('{{ $module }}')">
                            {{ $selectedCount === count($modulePermissions) ? __('Clear') : __('Select all') }}
                        </x-ui.button>
                    @endif
                </header>
                <div class="grid grid-cols-2 gap-x-4 gap-y-2 p-4">
                    @foreach ($modulePermissions as $permission)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" value="{{ $permission }}" wire:model="permissions" @disabled(! $canManage)
                                class="size-4 rounded border-slate-300 accent-brand-700" />
                            {{ App\Support\PermissionRegistry::label($permission) }}
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
