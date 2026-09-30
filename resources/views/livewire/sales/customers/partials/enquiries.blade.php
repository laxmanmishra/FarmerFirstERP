<ul class="divide-y divide-slate-100">
    @forelse ($enquiries as $enquiry)
        <li><a href="{{ route('crm.enquiries.show', $enquiry) }}" wire:navigate class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-slate-50">
            <span class="tabular text-xs font-semibold text-slate-500">{{ $enquiry->enquiry_no }}</span>
            <span class="min-w-0 flex-1 truncate text-sm text-slate-800">{{ $enquiry->requirements->map->summary()->implode(', ') }}</span>
            <x-ui.stage-badge :stage="$enquiry->currentStage()" />
            <span class="text-xs text-slate-500">{{ $enquiry->created_at->format('d M Y') }}</span>
        </a></li>
    @empty
        <li class="px-5 py-8"><x-ui.empty-state :title="__('No enquiries')" icon="inbox" /></li>
    @endforelse
</ul>
