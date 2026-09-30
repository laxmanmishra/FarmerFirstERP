<div>
    <x-ui.page-header :title="__('Workflow Configuration')" :description="__('Statuses and transitions for every workflow. Add or rename steps without code changes; used statuses are deactivated, never deleted.')"
        :breadcrumbs="[__('Administration') => null, __('Workflow Configuration') => null]" />

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($definitions as $definition)
            <a wire:key="wf-{{ $definition->id }}" href="{{ route('admin.workflows.edit', $definition) }}" wire:navigate
                class="block rounded-(--radius-card) border border-slate-200 bg-white p-5 shadow-(--shadow-card) transition hover:border-brand-300 focus-ring">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $definition->name }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $definition->description }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <x-ui.badge>{{ \Illuminate\Support\Str::upper($definition->module) }}</x-ui.badge>
                        @if ($definition->controlled_transitions)<x-ui.badge tone="violet">{{ __('Controlled transitions') }}</x-ui.badge>@endif
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-1.5">
                    @foreach ($definition->stages as $stage)
                        <x-ui.badge :tone="$stage->color">{{ $stage->name }}</x-ui.badge>
                        @unless ($loop->last)<x-ui.icon name="chevron-right" class="size-3 text-slate-300" />@endunless
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ __(':active active of :total statuses', ['active' => $definition->active_stages_count, 'total' => $definition->stages_count]) }}</p>
            </a>
        @endforeach
    </div>

    <p class="mt-6 text-xs text-slate-500">{{ __('Department workflows (Finance, Accounts, RTO, Insurance, PDI, Delivery) appear here as those modules go live.') }}</p>
</div>
