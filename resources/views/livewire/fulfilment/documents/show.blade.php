@php use App\Enums\DocumentStatus; @endphp
<div>
    <x-ui.page-header :title="$document->type->name.' · '.$document->document_no" :description="$document->customer->name.' · '.$document->customer->customer_no"
        :breadcrumbs="[__('Fulfilment') => null, __('Documents') => route('fulfilment.documents.index', ['tab' => 'repository']), $document->document_no => null]">
        <x-slot:actions>
            @if ($canViewFile && $document->currentVersion)
                <x-ui.button variant="secondary" icon="eye" href="{{ route('fulfilment.documents.file', $document) }}" target="_blank">{{ __('Open') }}</x-ui.button>
                <x-ui.button variant="secondary" icon="arrow-down" href="{{ route('fulfilment.documents.file', ['document' => $document, 'download' => 1]) }}">{{ __('Download') }}</x-ui.button>
            @endif
            @can('documents.upload')
                <x-ui.button variant="secondary" icon="plus" wire:click="open('version')">{{ __('New version') }}</x-ui.button>
            @endcan
            @if ($canVerify && $document->status->awaitsVerification())
                @if ($document->status === DocumentStatus::Uploaded)
                    <x-ui.button variant="ghost" wire:click="startVerification">{{ __('Start review') }}</x-ui.button>
                @endif
                <x-ui.button variant="danger-ghost" wire:click="open('reject')">{{ __('Reject') }}</x-ui.button>
                <x-ui.button icon="check" wire:click="verify">{{ __('Verify') }}</x-ui.button>
            @elseif ($canVerify && $document->status === DocumentStatus::Verified)
                <x-ui.button variant="danger-ghost" wire:click="open('reject')">{{ __('Reject') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $canViewFile)
        <x-ui.alert tone="warning" class="mb-6">{{ __('This is a sensitive document. You can see its details but not open the file.') }}</x-ui.alert>
    @endif
    @if ($document->status === DocumentStatus::Rejected)
        <x-ui.alert tone="danger" class="mb-6" :title="__('Rejected')">{{ $document->rejection_reason }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card :title="__('Details')">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <x-ui.dl-item :label="__('Status')"><x-ui.badge :tone="$document->status->tone()">{{ $document->status->label() }}</x-ui.badge></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Document number')">{{ $document->type->isSensitive() ? $document->maskedReference() : $document->reference_no }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Level')">{{ $document->type->level->label() }}@if ($document->type->is_reusable) · {{ __('reusable') }}@endif</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Issue date')">{{ $document->issue_date?->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Expiry date')"><span @class(['text-rose-700 font-medium' => $document->isExpired()])>{{ $document->expiry_date?->format('d M Y') }}</span></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Verified')">{{ $document->verified_at ? $document->verified_at->format('d M Y, H:i') : null }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Customer')"><a href="{{ route('sales.customers.show', $document->customer_id) }}" wire:navigate class="text-brand-700 hover:underline">{{ $document->customer->name }}</a></x-ui.dl-item>
                    <x-ui.dl-item :label="__('First uploaded for')">
                        @if ($document->order)<a href="{{ route('sales.orders.show', $document->order_id) }}" wire:navigate class="text-brand-700 hover:underline">{{ $document->order->order_no }}</a>@endif
                    </x-ui.dl-item>
                    <x-ui.dl-item :label="__('Department')">{{ $document->department?->name }}</x-ui.dl-item>
                    @if ($document->remarks)<x-ui.dl-item class="sm:col-span-3" :label="__('Remarks')">{{ $document->remarks }}</x-ui.dl-item>@endif
                </dl>
            </x-ui.card>

            <x-ui.table>
                <x-slot:toolbar><p class="text-sm font-semibold text-slate-900">{{ __('Versions') }}</p><p class="text-xs text-slate-500">{{ __('Older versions are kept and never overwritten.') }}</p></x-slot:toolbar>
                <x-slot:head>
                    <x-ui.th>{{ __('Version') }}</x-ui.th>
                    <x-ui.th>{{ __('File') }}</x-ui.th>
                    <x-ui.th>{{ __('Uploaded by') }}</x-ui.th>
                    <x-ui.th>{{ __('Date') }}</x-ui.th>
                    <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
                </x-slot:head>
                @foreach ($document->versions as $version)
                    <tr wire:key="v-{{ $version->id }}">
                        <x-ui.td class="tabular font-medium">v{{ $version->version }} @if ($version->version === $document->current_version)<x-ui.badge tone="brand">{{ __('current') }}</x-ui.badge>@endif</x-ui.td>
                        <x-ui.td class="text-sm">{{ $version->original_name }} <span class="block text-xs text-slate-500">{{ $version->humanSize() }} · {{ $version->mime_type }}</span></x-ui.td>
                        <x-ui.td class="text-sm">{{ $version->uploader?->name }}</x-ui.td>
                        <x-ui.td class="whitespace-nowrap text-sm">{{ $version->created_at->format('d M Y, H:i') }}</x-ui.td>
                        <x-ui.td align="right">
                            @if ($canViewFile)
                                <a href="{{ route('fulfilment.documents.file', ['document' => $document, 'version' => $version, 'download' => 1]) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Download') }}</a>
                            @endif
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($accessLogs !== null)
                <x-ui.card :title="__('Access log')" :description="__('Every view and download of this document’s files.')" :padding="false">
                    <ul class="divide-y divide-slate-100 text-sm">
                        @forelse ($accessLogs as $log)
                            <li class="flex flex-wrap justify-between gap-2 px-5 py-2.5">
                                <span>{{ $log->user?->name ?? __('Unknown') }} · {{ $log->action === 'download' ? __('downloaded') : __('viewed') }} v{{ $log->version?->version }}</span>
                                <span class="text-xs text-slate-500">{{ $log->created_at->format('d M Y, H:i') }} · {{ $log->ip_address }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-500">{{ __('Nobody has opened this document yet.') }}</li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Used for')" :description="__('Requirements this one document satisfies.')" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($document->requirements as $requirement)
                        <li><a href="{{ route('sales.orders.show', ['order' => $requirement->order_id, 'tab' => 'documents']) }}" wire:navigate class="flex items-center justify-between px-5 py-2.5 text-sm hover:bg-slate-50">
                            <span class="tabular">{{ $requirement->order->order_no }}</span>
                            <span class="text-xs text-slate-500">{{ $requirement->department->name }}</span>
                        </a></li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('Not linked to any requirement.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('Verification history')" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($document->verifications as $entry)
                        <li class="px-5 py-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <x-ui.badge :tone="$entry->action->tone()">{{ $entry->action->label() }}</x-ui.badge>
                                <span class="text-xs text-slate-400">{{ $entry->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $entry->user?->name ?? __('System') }}@if ($entry->version) · v{{ $entry->version->version }}@endif</p>
                            @if ($entry->remarks)<p class="mt-1 text-slate-700">{{ $entry->remarks }}</p>@endif
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-500">{{ $document->type->verification_required ? __('Not verified yet.') : __('This type does not need verification.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>

    @if ($modal === 'version')
        <x-ui.modal wire:model="modal" :title="__('Upload a new version')" :description="__('The current file stays in the history. The new version must be verified again.')">
            <form id="version-form" wire:submit="uploadVersion" class="space-y-4">
                <div>
                    <label for="version-file" class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('File') }} <span class="text-rose-600">*</span></label>
                    <input id="version-file" type="file" wire:model="file" accept="{{ collect($document->type->extensions())->map(fn ($e) => '.'.$e)->implode(',') }}"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-800 hover:file:bg-brand-100" />
                    @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Document number')" wire:model="meta.reference_no" name="meta.reference_no" :hint="__('Leave empty to keep the current one.')" />
                    <x-ui.input type="date" :label="__('Issue date')" wire:model="meta.issue_date" name="meta.issue_date" />
                    @if ($document->type->expiry_applicable)
                        <x-ui.input type="date" :label="__('Expiry date')" wire:model="meta.expiry_date" name="meta.expiry_date" required />
                    @endif
                </div>
                <x-ui.textarea :label="__('Remarks')" wire:model="meta.remarks" name="meta.remarks" rows="2" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="version-form" wire:target="uploadVersion,file">{{ __('Upload') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'reject')
        <x-ui.modal wire:model="modal" tone="danger" :title="__('Reject document')" :description="__('The uploader is notified with your reason.')">
            <form id="doc-reject-form" wire:submit="reject">
                <x-ui.textarea :label="__('Reason')" wire:model="reason" name="reason" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="doc-reject-form" variant="danger" wire:target="reject">{{ __('Reject') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
