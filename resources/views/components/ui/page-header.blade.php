@props(['title', 'description' => null, 'breadcrumbs' => []])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($breadcrumbs !== [])
            <nav aria-label="Breadcrumb" class="mb-1.5">
                <ol class="flex flex-wrap items-center gap-1 text-xs text-slate-500">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1">
                            @if (! $loop->first)<x-ui.icon name="chevron-right" class="size-3 text-slate-400" />@endif
                            @if (is_string($url))
                                <a href="{{ $url }}" wire:navigate class="hover:text-brand-700">{{ $label }}</a>
                            @else
                                <span class="text-slate-700">{{ is_int($label) ? $url : $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($description)<p class="mt-1 text-sm text-slate-500">{{ $description }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
