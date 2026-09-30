<div>
    <x-ui.page-header :title="__('Good :part, :name', ['part' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => \Illuminate\Support\Str::before($user->name, ' ')])"
        :description="__('Here is what is happening across Farmer First today.')" />

    @if ($crm !== [])
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Sales & CRM') }}</h2>
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($crm as $kpi)
                <x-ui.kpi-card :label="$kpi['label']" :value="number_format($kpi['value'])" :icon="$kpi['icon']" :href="$kpi['href']" :tone="$kpi['tone']" />
            @endforeach
        </div>
    @endif

    @if ($organisation !== [])
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Organisation') }}</h2>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($organisation as $kpi)
                <x-ui.kpi-card :label="$kpi['label']" :value="number_format($kpi['value'])" :icon="$kpi['icon']" :href="$kpi['href']" :tone="$kpi['tone']" />
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card :title="__('Operational dashboards')" :description="__('KPIs switch on automatically as each module goes live.')">
                <ol class="grid gap-3 sm:grid-cols-2">
                    @foreach ([

                        ['Orders & Documents', __('Orders, fulfilment tasks, central document centre'), 4],
                        ['Fulfilment', __('Retail & Finance, Accounts, Inventory'), 5],
                        ['Compliance', __('RTO, Insurance, PDI / Workshop'), 6],
                        ['Readiness & Delivery', __('Waivers, readiness engine, delivery & handover'), 7],
                        ['Management', __('Targets, achievements, control tower, reports'), 9],
                    ] as [$module, $scope, $phase])
                        <li class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-md bg-slate-100 text-xs font-semibold text-slate-500">P{{ $phase }}</span>
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $module }}</p>
                                <p class="text-xs text-slate-500">{{ $scope }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>

            @can('audit.view')
                <x-ui.card :title="__('Recent activity')" :padding="false">
                    <x-slot:actions>
                        <x-ui.button variant="ghost" size="sm" :href="route('admin.audit-logs.index')" wire:navigate>{{ __('View all') }}</x-ui.button>
                    </x-slot:actions>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($recentActivity as $entry)
                            <li class="flex items-start gap-3 px-5 py-3">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-400"></span>
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="text-slate-700">
                                        <span class="font-medium text-slate-900">{{ $entry->user?->name ?? __('System') }}</span>
                                        {{ str_replace('_', ' ', $entry->event) }}
                                        <span class="text-slate-500">{{ \Illuminate\Support\Str::headline($entry->module) }}</span>
                                        @if ($entry->auditable_id)<span class="text-slate-400">#{{ $entry->auditable_id }}</span>@endif
                                    </p>
                                    <p class="text-xs text-slate-400">{{ $entry->created_at->diffForHumans() }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-8"><x-ui.empty-state :title="__('No activity yet')" icon="clock" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endcan
        </div>

        <x-ui.card :title="__('My access')">
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Roles') }}</dt>
                    <dd class="mt-1 flex flex-wrap gap-1.5">
                        @foreach ($user->getRoleNames() as $role)<x-ui.badge tone="brand">{{ $role }}</x-ui.badge>@endforeach
                    </dd>
                </div>
                @if ($user->employee)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Departments') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($user->employee->departments as $department)
                                <x-ui.badge>{{ $department->name }}</x-ui.badge>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </dd>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Designation') }}</dt>
                            <dd class="mt-1 text-slate-800">{{ $user->employee->designation?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Reports to') }}</dt>
                            <dd class="mt-1 text-slate-800">{{ $user->employee->manager?->name ?? '—' }}</dd>
                        </div>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Branches') }}</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->accessibleBranches()->pluck('name')->implode(', ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Last sign-in') }}</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->last_login_at?->format('d M Y, H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</div>
