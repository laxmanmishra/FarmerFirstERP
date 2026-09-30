<ul class="divide-y divide-slate-100">
    @forelse ($deals as $deal)
        <li><a href="{{ route('sales.deals.show', $deal) }}" wire:navigate class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-slate-50">
            <span class="tabular text-sm font-semibold text-slate-900">{{ $deal->deal_no }}</span>
            <span class="tabular flex-1 text-sm">{{ App\Support\Money::format($deal->deal_value) }}</span>
            <span class="text-xs text-slate-500">{{ $deal->primarySalesman?->name }}</span>
            <x-ui.stage-badge :stage="$deal->stage" />
        </a></li>
    @empty
        <li class="px-5 py-8"><x-ui.empty-state :title="__('No deals yet')" icon="handshake" /></li>
    @endforelse
</ul>
