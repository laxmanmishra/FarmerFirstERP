<div>
    <x-ui.page-header :title="__('Reopen Requests')" :description="__('Requests from salesmen and telecallers to reopen closed enquiries.')"
        :breadcrumbs="[__('CRM') => null, __('Reopen Requests') => null]" />

    <x-ui.tabs class="mb-4" model="status" :active="$status" :tabs="['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')]" />

    <x-ui.table :paginator="$requests">
        <x-slot:head>
            <x-ui.th>{{ __('Requested') }}</x-ui.th>
            <x-ui.th>{{ __('Enquiry') }}</x-ui.th>
            <x-ui.th>{{ __('Closed as') }}</x-ui.th>
            <x-ui.th>{{ __('Reason') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @forelse ($requests as $request)
            <tr wire:key="rr-{{ $request->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="whitespace-nowrap">
                    <p class="text-sm text-slate-800">{{ $request->requester->name }}</p>
                    <p class="text-xs text-slate-400">{{ $request->created_at->format('d M Y, H:i') }}</p>
                </x-ui.td>
                <x-ui.td>
                    <a href="{{ route('crm.enquiries.show', $request->enquiry) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $request->enquiry->enquiry_no }}</a>
                    <p class="text-xs text-slate-500">{{ $request->enquiry->farmer->name }}</p>
                </x-ui.td>
                <x-ui.td><x-ui.stage-badge :stage="$request->enquiry->currentStage()" /></x-ui.td>
                <x-ui.td class="max-w-sm text-sm">{{ $request->reason }}</x-ui.td>
                <x-ui.td>
                    <x-ui.badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui.badge>
                    @if ($request->decider)<p class="mt-1 text-xs text-slate-500">{{ $request->decider->name }}@if ($request->decision_remarks): {{ $request->decision_remarks }}@endif</p>@endif
                </x-ui.td>
                <x-ui.td align="right">
                    @if ($request->status === App\Enums\ApprovalStatus::Pending && $request->requested_by !== auth()->id())
                        <div class="flex justify-end gap-1 whitespace-nowrap">
                            <x-ui.button size="xs" icon="check" wire:click="decide({{ $request->id }}, 'approve')">{{ __('Approve') }}</x-ui.button>
                            <x-ui.button variant="danger-ghost" size="xs" wire:click="decide({{ $request->id }}, 'reject')">{{ __('Reject') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('No requests')" icon="refresh" />
        @endforelse
    </x-ui.table>

    @if (in_array($modal, ['approve', 'reject'], true))
        <x-ui.modal wire:model="modal" :tone="$modal === 'reject' ? 'danger' : null" :title="$modal === 'approve' ? __('Approve and reopen') : __('Reject request')">
            <form id="decision-form" wire:submit="submit">
                <x-ui.textarea :label="__('Remarks')" wire:model="remarks" name="remarks" rows="3" :required="$modal === 'reject'" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="decision-form" :variant="$modal === 'reject' ? 'danger' : 'primary'">{{ $modal === 'approve' ? __('Approve') : __('Reject') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
