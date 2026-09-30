<div>
    <x-ui.page-header :title="__('Users')" :description="__('Login accounts, roles and access status.')"
        :breadcrumbs="[__('Administration') => null, __('Users') => null]">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <x-ui.button icon="plus" wire:click="create">{{ __('New user') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table :paginator="$users">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, email or mobile…')" />
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                    <option value="locked">{{ __('Locked') }}</option>
                </select>
                <select wire:model.live="role" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Role') }}">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roleOptions as $roleName)<option value="{{ $roleName }}">{{ $roleName }}</option>@endforeach
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th sortable="name" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('User') }}</x-ui.th>
            <x-ui.th>{{ __('Roles') }}</x-ui.th>
            <x-ui.th>{{ __('Employee') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th sortable="last_login_at" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Last sign-in') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}" class="hover:bg-slate-50/70">
                <x-ui.td>
                    <p class="font-medium text-slate-900">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500">{{ $user->email }}@if ($user->mobile) · {{ $user->mobile }}@endif</p>
                </x-ui.td>
                <x-ui.td>
                    <div class="flex max-w-xs flex-wrap gap-1">
                        @forelse ($user->roles as $userRole)<x-ui.badge tone="brand">{{ $userRole->name }}</x-ui.badge>@empty<span class="text-slate-400">—</span>@endforelse
                    </div>
                </x-ui.td>
                <x-ui.td class="text-xs text-slate-500">{{ $user->employee?->employee_code ?? '—' }}</x-ui.td>
                <x-ui.td>
                    <div class="flex flex-wrap gap-1">
                        <x-ui.active-badge :active="$user->is_active" />
                        @if ($user->isLocked())<x-ui.badge tone="rose">{{ __('Locked') }}</x-ui.badge>@endif
                        @if ($user->must_change_password)<x-ui.badge tone="amber">{{ __('Password change due') }}</x-ui.badge>@endif
                    </div>
                </x-ui.td>
                <x-ui.td class="tabular whitespace-nowrap text-xs text-slate-500">{{ $user->last_login_at?->format('d M Y, H:i') ?? __('Never') }}</x-ui.td>
                <x-ui.td align="right">
                    <div class="flex justify-end gap-1 whitespace-nowrap">
                        @can('update', $user)
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</x-ui.button>
                        @endcan
                        @can('resetPassword', $user)
                            <x-ui.button variant="ghost" size="xs" icon="key" wire:click="resetPassword({{ $user->id }})"
                                wire:confirm="{{ __('Issue a new temporary password for :name? Their current sessions will be signed out.', ['name' => $user->name]) }}">{{ __('Reset') }}</x-ui.button>
                            @if ($user->isLocked())
                                <x-ui.button variant="ghost" size="xs" icon="lock" wire:click="unlock({{ $user->id }})">{{ __('Unlock') }}</x-ui.button>
                            @endif
                        @endcan
                        @can('deactivate', $user)
                            <x-ui.button :variant="$user->is_active ? 'danger-ghost' : 'ghost'" size="xs" :icon="$user->is_active ? 'ban' : 'check'" wire:click="confirmStatusChange({{ $user->id }})">
                                {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                            </x-ui.button>
                        @endcan
                    </div>
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('No users match these filters')" icon="user-circle" />
        @endforelse
    </x-ui.table>

    {{-- Create / edit --}}
    <x-ui.drawer wire:model="showForm" :title="$editingId ? __('Edit user') : __('New user')"
        :description="$editingId ? null : __('A temporary password is generated; the user must change it at first sign-in.')">
        <form id="user-form" wire:submit="save" class="space-y-5">
            <x-ui.input :label="__('Full name')" wire:model="name" required />
            <x-ui.input type="email" :label="__('Email')" wire:model="email" required autocomplete="off" />
            <x-ui.input :label="__('Mobile')" wire:model="mobile" inputmode="numeric" maxlength="10" :hint="__('10-digit mobile, used as an alternative sign-in.')" />

            @if ($canAssignRoles)
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">{{ __('Roles') }}</legend>
                    <p class="text-xs text-slate-500">{{ __('A user may hold several roles; permissions combine.') }}</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($roleOptions as $roleName)
                            @if ($roleName !== App\Models\User::SUPER_ADMIN_ROLE || auth()->user()->isSuperAdmin())
                                <x-ui.checkbox :label="$roleName" wire:model="roles" value="{{ $roleName }}" />
                            @endif
                        @endforeach
                    </div>
                    @error('roles')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </fieldset>
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="user-form" wire:target="save">{{ $editingId ? __('Save changes') : __('Create user') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    {{-- Activate / deactivate with mandatory reason --}}
    <x-ui.modal wire:model="showStatusModal" :tone="$statusUser?->is_active ? 'danger' : null"
        :title="$statusUser?->is_active ? __('Deactivate :name?', ['name' => $statusUser?->name]) : __('Activate :name?', ['name' => $statusUser?->name])"
        :description="$statusUser?->is_active ? __('The user is signed out everywhere immediately and cannot sign in until reactivated.') : __('The user will be able to sign in again.')">
        <x-ui.textarea :label="__('Reason')" wire:model="statusReason" name="statusReason" required :hint="__('Recorded in the audit log.')" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button :variant="$statusUser?->is_active ? 'danger' : 'primary'" wire:click="changeStatus">{{ $statusUser?->is_active ? __('Deactivate') : __('Activate') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- One-time temporary password --}}
    <x-ui.modal wire:model="showPasswordModal" :title="__('Temporary password for :name', ['name' => $revealedFor])"
        :description="__('Share it securely. It is shown only once and must be changed at first sign-in.')">
        <div x-data="{ copied: false }" class="flex items-center gap-2">
            <code class="tabular flex-1 rounded-lg bg-slate-100 px-3 py-2 font-mono text-base tracking-wider text-slate-900">{{ $revealedPassword }}</code>
            <x-ui.button variant="secondary" x-on:click="navigator.clipboard.writeText(@js($revealedPassword)); copied = true">
                <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
            </x-ui.button>
        </div>
        <x-slot:footer>
            <x-ui.button wire:click="closePasswordModal">{{ __('Done') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
