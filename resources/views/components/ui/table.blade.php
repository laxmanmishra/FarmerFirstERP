@props(['paginator' => null])

{{-- Data table shell: optional toolbar slot, head slot, body slot and pagination. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-(--radius-card) border border-slate-200 bg-white shadow-(--shadow-card)']) }}>
    @isset($toolbar)
        <div class="flex flex-col gap-3 border-b border-slate-100 p-3 sm:flex-row sm:items-center sm:justify-between">{{ $toolbar }}</div>
    @endisset

    <div class="relative overflow-x-auto">
        <div wire:loading.delay class="absolute inset-x-0 top-0 h-0.5 animate-pulse bg-brand-500"></div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50/80">
                <tr>{{ $head }}</tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if ($paginator && $paginator->hasPages())
        <div class="border-t border-slate-100 px-4 py-3">{{ $paginator->links() }}</div>
    @elseif ($paginator)
        <div class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">
            {{ trans_choice(':count record|:count records', $paginator->total(), ['count' => number_format($paginator->total())]) }}
        </div>
    @endif
</div>
