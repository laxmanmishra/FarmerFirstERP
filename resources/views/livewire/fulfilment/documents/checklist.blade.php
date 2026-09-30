@php use App\Enums\DocumentStatus; use App\Enums\RequirementStatus; $cancelled = $order->isCancelled(); @endphp
<div class="space-y-6">
    @forelse ($groups as $department => $requirements)
        <x-ui.table wire:key="dept-{{ \Illuminate\Support\Str::slug($department) }}">
            <x-slot:toolbar>
                <p class="text-sm font-semibold text-slate-900">{{ $department }}</p>
                <p class="text-xs text-slate-500">{{ __(':done of :total satisfied', ['done' => $requirements->filter->isSatisfied()->count(), 'total' => $requirements->count()]) }}</p>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th>{{ __('Document') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th>{{ __('Linked file') }}</x-ui.th>
                <x-ui.th>{{ __('Responsible') }}</x-ui.th>
                <x-ui.th>{{ __('Due') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @foreach ($requirements as $requirement)
                @php $status = $requirement->status(); $document = $requirement->document; @endphp
                <tr wire:key="req-{{ $requirement->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td>
                        <p class="flex items-center gap-1.5 font-medium text-slate-900">
                            {{ $requirement->documentType->name }}
                            @if ($requirement->documentType->isSensitive())<x-ui.icon name="lock" class="size-3.5 text-slate-400" title="{{ __('Sensitive') }}" />@endif
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $requirement->documentType->level->label() }}@if ($requirement->documentType->is_reusable) · {{ __('reusable') }}@endif
                            @if ($requirement->blocks_delivery) · <span class="text-amber-700">{{ __('blocks delivery') }}</span>@endif
                        </p>
                    </x-ui.td>
                    <x-ui.td class="whitespace-nowrap">
                        <x-ui.badge :tone="$status->tone()">{{ $status->label() }}</x-ui.badge>
                        @if ($requirement->isSatisfied())<x-ui.icon name="check-circle" class="ml-1 inline size-4 text-emerald-600" title="{{ __('Satisfied') }}" />@endif
                        @if ($status === RequirementStatus::Rejected && $document->rejection_reason)<p class="mt-1 max-w-xs text-xs text-rose-700">{{ $document->rejection_reason }}</p>@endif
                    </x-ui.td>
                    <x-ui.td class="text-sm">
                        @if ($document)
                            <a href="{{ route('fulfilment.documents.show', $document) }}" wire:navigate class="tabular font-medium text-brand-700 hover:underline">{{ $document->document_no }}</a>
                            <p class="text-xs text-slate-500">v{{ $document->current_version }} · {{ $document->currentVersion?->uploader?->name }}@if ($document->expiry_date) · {{ __('expires :d', ['d' => $document->expiry_date->format('d M Y')]) }}@endif</p>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td class="text-sm">{{ $requirement->responsible?->name ?? __('Unassigned') }}</x-ui.td>
                    <x-ui.td @class(['whitespace-nowrap text-sm', 'font-medium text-rose-700' => $requirement->isOverdue()])>{{ $requirement->due_date?->format('d M Y') ?? '—' }}</x-ui.td>
                    <x-ui.td align="right" class="whitespace-nowrap">
                        @unless ($cancelled)
                            @if ($document && $canVerify[$requirement->id] && $document->status->awaitsVerification())
                                @if ($document->status === DocumentStatus::Uploaded)
                                    <x-ui.button size="sm" variant="ghost" wire:click="startVerification({{ $requirement->id }})">{{ __('Start review') }}</x-ui.button>
                                @endif
                                <x-ui.button size="sm" icon="check" wire:click="verify({{ $requirement->id }})" wire:target="verify({{ $requirement->id }})">{{ __('Verify') }}</x-ui.button>
                                <x-ui.button size="sm" variant="danger-ghost" wire:click="open({{ $requirement->id }}, 'reject')">{{ __('Reject') }}</x-ui.button>
                            @endif
                            @can('documents.upload')
                                @if ($reusable[$requirement->id] > 0)
                                    <x-ui.button size="sm" variant="secondary" icon="refresh" wire:click="open({{ $requirement->id }}, 'link')">{{ __('Use existing (:n)', ['n' => $reusable[$requirement->id]]) }}</x-ui.button>
                                @endif
                                @if ($requirement->requirement_state->isApplicable() && (! $document || in_array($document->status, [DocumentStatus::Rejected, DocumentStatus::Expired], true)))
                                    <x-ui.button size="sm" variant="secondary" icon="plus" wire:click="open({{ $requirement->id }}, 'upload')">{{ $document ? __('Upload new version') : __('Upload') }}</x-ui.button>
                                @endif
                            @endcan
                        @endunless
                    </x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @empty
        <x-ui.card><x-ui.empty-state :title="__('No documents required')" icon="folder" /></x-ui.card>
    @endforelse

    @if ($notRequired->isNotEmpty())
        <details class="rounded-(--radius-card) border border-slate-200 bg-white px-4 py-3 text-sm shadow-(--shadow-card)">
            <summary class="cursor-pointer font-medium text-slate-700">{{ __(':count documents not required for this order', ['count' => $notRequired->count()]) }}</summary>
            <ul class="mt-2 grid gap-1 text-slate-500 sm:grid-cols-2">
                @foreach ($notRequired as $requirement)
                    <li>{{ $requirement->documentType->name }} · {{ $requirement->department->name }}</li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($modal === 'upload' && $current)
        <x-ui.modal wire:model="modal" :title="__('Upload :type', ['type' => $current->documentType->name])"
            :description="__('Allowed: :ext, up to :size MB. Stored privately; every version is kept.', ['ext' => strtoupper(implode(', ', $current->documentType->extensions())), 'size' => round($current->documentType->max_size_kb / 1024, 1)])">
            <form id="upload-form" wire:submit="upload" class="space-y-4">
                <div>
                    <label for="doc-file" class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('File') }} <span class="text-rose-600">*</span></label>
                    <input id="doc-file" type="file" wire:model="file" accept="{{ collect($current->documentType->extensions())->map(fn ($e) => '.'.$e)->implode(',') }}" capture="environment"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-800 hover:file:bg-brand-100" />
                    <p wire:loading wire:target="file" class="mt-1 text-xs text-slate-500">{{ __('Uploading…') }}</p>
                    @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Document number')" wire:model="meta.reference_no" name="meta.reference_no" :hint="$current->documentType->isSensitive() ? __('Stored encrypted and shown masked.') : null" />
                    <x-ui.input type="date" :label="__('Issue date')" wire:model="meta.issue_date" name="meta.issue_date" />
                    @if ($current->documentType->expiry_applicable)
                        <x-ui.input type="date" :label="__('Expiry date')" wire:model="meta.expiry_date" name="meta.expiry_date" required />
                    @endif
                </div>
                <x-ui.textarea :label="__('Remarks')" wire:model="meta.remarks" name="meta.remarks" rows="2" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="upload-form" wire:target="upload,file">{{ __('Upload') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'link' && $current)
        <x-ui.modal wire:model="modal" width="max-w-2xl" :title="__('Use an existing :type', ['type' => $current->documentType->name])"
            :description="__('Linking reuses the document already in the repository instead of uploading a copy.')">
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                @forelse ($candidates as $candidate)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="tabular text-sm font-medium text-slate-900">{{ $candidate->document_no }} · v{{ $candidate->current_version }}</p>
                            <p class="text-xs text-slate-500">{{ __('Uploaded :date', ['date' => $candidate->created_at->format('d M Y')]) }}@if ($candidate->maskedReference()) · {{ $candidate->maskedReference() }}@endif</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.badge :tone="$candidate->status->tone()">{{ $candidate->status->label() }}</x-ui.badge>
                            <x-ui.button size="sm" wire:click="link({{ $candidate->id }})" wire:target="link({{ $candidate->id }})">{{ __('Use this') }}</x-ui.button>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">{{ __('No usable documents found.') }}</li>
                @endforelse
            </ul>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Close') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'reject' && $current)
        <x-ui.modal wire:model="modal" tone="danger" :title="__('Reject :type', ['type' => $current->documentType->name])" :description="__('The uploader is notified with your reason.')">
            <form id="reject-form" wire:submit="reject">
                <x-ui.textarea :label="__('Reason')" wire:model="reason" name="reason" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="reject-form" variant="danger" wire:target="reject">{{ __('Reject') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
