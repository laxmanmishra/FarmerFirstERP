@php
    use App\Enums\DocumentStatus;
    use App\Enums\RequirementStatus;
    $drill = fn (array $filters) => route('fulfilment.documents.index', ['tab' => 'requirements'] + $filters);
@endphp
<div>
    <x-ui.page-header :title="__('Document Center')" :description="__('One repository for every customer and order document. Counters are live and open the exact records.')"
        :breadcrumbs="[__('Fulfilment') => null, __('Documents') => null]" />

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="$tabs" />

    @if ($tab === 'dashboard')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.kpi-card :label="__('Blocking delivery')" :value="$blockingCount" icon="ban" tone="rose" :href="$drill(['blocking' => 1])" :hint="__('required, not yet satisfied')" />
            <x-ui.kpi-card :label="__('Overdue')" :value="$overdueCount" icon="clock" tone="amber" :href="$drill(['overdue' => 1])" :hint="__('past due date')" />
            <x-ui.kpi-card :label="__('Pending upload')" :value="$byStatus['pending']" icon="folder" tone="slate" :href="$drill(['status' => 'pending'])" />
            <x-ui.kpi-card :label="__('Awaiting verification')" :value="$byStatus['uploaded'] + $byStatus['under_verification']" icon="check-badge" tone="sky"
                :href="auth()->user()->can('documents.verify') ? route('fulfilment.documents.index', ['tab' => 'verification']) : $drill(['status' => 'uploaded'])" />
        </div>

        <x-ui.table class="mb-6">
            <x-slot:toolbar>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ __('Requirements by document type') }}</p>
                    <p class="text-xs text-slate-500">{{ __('Open orders only. Click a number to see the records.') }}</p>
                </div>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th>{{ __('Document type') }}</x-ui.th>
                @foreach ($columns as $column)
                    <x-ui.th align="right">{{ $column->label() }}</x-ui.th>
                @endforeach
            </x-slot:head>
            <tr class="bg-slate-50/60 font-semibold">
                <x-ui.td>{{ __('All types') }}</x-ui.td>
                @foreach ($columns as $column)
                    <x-ui.td align="right" class="tabular">
                        <a href="{{ $drill(['status' => $column->value]) }}" wire:navigate class="hover:text-brand-700 hover:underline">{{ $byStatus[$column->value] }}</a>
                    </x-ui.td>
                @endforeach
            </tr>
            @forelse ($types->only(array_keys($matrix)) as $typeId => $typeName)
                <tr wire:key="m-{{ $typeId }}" class="hover:bg-slate-50/70">
                    <x-ui.td class="font-medium text-slate-800">{{ $typeName }}</x-ui.td>
                    @foreach ($columns as $column)
                        @php $count = $matrix[$typeId][$column->value] ?? 0; @endphp
                        <x-ui.td align="right" class="tabular">
                            @if ($count > 0)
                                <a href="{{ $drill(['status' => $column->value, 'type' => $typeId]) }}" wire:navigate @class(['font-medium hover:underline', 'text-rose-700' => in_array($column, [RequirementStatus::Rejected, RequirementStatus::Expired], true), 'text-brand-700' => ! in_array($column, [RequirementStatus::Rejected, RequirementStatus::Expired], true)])>{{ $count }}</a>
                            @else
                                <span class="text-slate-300">0</span>
                            @endif
                        </x-ui.td>
                    @endforeach
                </tr>
            @empty
                <x-ui.empty-row :colspan="count($columns) + 1" :title="__('No document requirements on open orders')" icon="folder" />
            @endforelse
        </x-ui.table>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.card :title="__('By department')" :padding="false">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500"><tr>
                        <th class="px-5 py-2 text-left font-medium">{{ __('Department') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Open') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Blocking') }}</th>
                        <th class="px-5 py-2 text-right font-medium">{{ __('Overdue') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byDepartment as $row)
                            <tr wire:key="d-{{ $row['id'] }}">
                                <td class="px-5 py-2">{{ $row['name'] }}</td>
                                <td class="tabular px-3 py-2 text-right"><a href="{{ $drill(['department' => $row['id']]) }}" wire:navigate class="text-brand-700 hover:underline">{{ $row['open'] }}</a></td>
                                <td class="tabular px-3 py-2 text-right"><a href="{{ $drill(['department' => $row['id'], 'blocking' => 1]) }}" wire:navigate class="hover:underline">{{ $row['blocking'] }}</a></td>
                                <td class="tabular px-5 py-2 text-right"><a href="{{ $drill(['department' => $row['id'], 'overdue' => 1]) }}" wire:navigate @class(['hover:underline', 'font-medium text-rose-700' => $row['overdue'] > 0])>{{ $row['overdue'] }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">{{ __('Nothing open.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>

            <x-ui.card :title="__('By responsible employee')" :padding="false">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500"><tr>
                        <th class="px-5 py-2 text-left font-medium">{{ __('Employee') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Open') }}</th>
                        <th class="px-5 py-2 text-right font-medium">{{ __('Overdue') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byEmployee as $row)
                            <tr wire:key="e-{{ $row['id'] }}">
                                <td class="px-5 py-2">{{ $row['name'] }}</td>
                                <td class="tabular px-3 py-2 text-right"><a href="{{ $drill(['employee' => $row['id']]) }}" wire:navigate class="text-brand-700 hover:underline">{{ $row['open'] }}</a></td>
                                <td @class(['tabular px-5 py-2 text-right', 'font-medium text-rose-700' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-6 text-center text-slate-500">{{ __('Nothing open.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>

            <x-ui.card :title="__('Ageing of open requirements')" :description="__('Days since the requirement was raised.')">
                @php $max = max(1, $ageingCounts->max()); @endphp
                <ul class="space-y-3">
                    @foreach ($ageingCounts as $bucket => $count)
                        <li>
                            <a href="{{ $drill(['age' => $bucket]) }}" wire:navigate class="group block">
                                <div class="mb-1 flex justify-between text-sm"><span class="text-slate-600 group-hover:text-brand-700">{{ __(':range days', ['range' => $bucket]) }}</span><span class="tabular font-medium">{{ $count }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div @class(['h-full rounded-full', 'bg-rose-500' => in_array($bucket, ['8-15', '15+'], true), 'bg-brand-600' => ! in_array($bucket, ['8-15', '15+'], true)]) style="width: {{ (int) round($count / $max * 100) }}%"></div></div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    @elseif ($tab === 'requirements')
        <x-ui.table :paginator="$requirements">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Order no, customer or mobile…')" />
                    <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <select wire:model.live="type" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Document type') }}">
                        <option value="">{{ __('Any type') }}</option>
                        @foreach ($types as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <select wire:model.live="department" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Department') }}">
                        <option value="">{{ __('Any department') }}</option>
                        @foreach ($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <x-ui.checkbox :label="__('Blocking delivery')" wire:model.live="blocking" />
                    <x-ui.checkbox :label="__('Overdue')" wire:model.live="overdue" />
                </div>
                @if ($hasFilters)
                    <x-ui.button size="sm" variant="ghost" icon="x" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th>{{ __('Order') }}</x-ui.th>
                <x-ui.th>{{ __('Document') }}</x-ui.th>
                <x-ui.th>{{ __('Department') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th>{{ __('Responsible') }}</x-ui.th>
                <x-ui.th>{{ __('Age') }}</x-ui.th>
                <x-ui.th>{{ __('Due') }}</x-ui.th>
            </x-slot:head>
            @forelse ($requirements as $requirement)
                @php $rowStatus = $requirement->status(); @endphp
                <tr wire:key="r-{{ $requirement->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td>
                        <a href="{{ route('sales.orders.show', ['order' => $requirement->order_id, 'tab' => 'documents']) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $requirement->order->order_no }}</a>
                        <p class="text-xs text-slate-500">{{ $requirement->order->customer->name }} · {{ $requirement->order->customer->mobile }}</p>
                    </x-ui.td>
                    <x-ui.td>
                        <p class="font-medium text-slate-800">{{ $requirement->documentType->name }}</p>
                        @if ($requirement->blocks_delivery)<p class="text-xs text-amber-700">{{ __('blocks delivery') }}</p>@endif
                    </x-ui.td>
                    <x-ui.td class="text-sm">{{ $requirement->department->name }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap"><x-ui.badge :tone="$rowStatus->tone()">{{ $rowStatus->label() }}</x-ui.badge></x-ui.td>
                    <x-ui.td class="text-sm">{{ $requirement->responsible?->name ?? __('Unassigned') }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-sm">{{ trans_choice(':count day|:count days', (int) $requirement->created_at->startOfDay()->diffInDays(today()), ['count' => (int) $requirement->created_at->startOfDay()->diffInDays(today())]) }}</x-ui.td>
                    <x-ui.td @class(['whitespace-nowrap text-sm', 'font-medium text-rose-700' => $requirement->isOverdue()])>{{ $requirement->due_date?->format('d M Y') ?? '—' }}</x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="7" :title="__('No matching requirements')" icon="folder" />
            @endforelse
        </x-ui.table>
    @else
        <x-ui.table :paginator="$documents">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Document no, customer or mobile…')" />
                    <select wire:model.live="type" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Document type') }}">
                        <option value="">{{ __('Any type') }}</option>
                        @foreach ($types as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    @if ($tab === 'repository')
                        <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                            <option value="">{{ __('Any status') }}</option>
                            @foreach (DocumentStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                        </select>
                    @endif
                </div>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th>{{ __('Document') }}</x-ui.th>
                <x-ui.th>{{ __('Type') }}</x-ui.th>
                <x-ui.th>{{ __('Customer') }}</x-ui.th>
                <x-ui.th>{{ __('Order') }}</x-ui.th>
                <x-ui.th>{{ __('Uploaded') }}</x-ui.th>
                <x-ui.th>{{ __('Expiry') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
            </x-slot:head>
            @forelse ($documents as $document)
                <tr wire:key="doc-{{ $document->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td><a href="{{ route('fulfilment.documents.show', $document) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $document->document_no }}</a>
                        <p class="text-xs text-slate-500">v{{ $document->current_version }}</p></x-ui.td>
                    <x-ui.td class="text-sm">{{ $document->type->name }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $document->customer->name }} <span class="block text-xs text-slate-500">{{ $document->customer->customer_no }}</span></x-ui.td>
                    <x-ui.td class="tabular text-sm">{{ $document->order?->order_no ?? '—' }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-sm">{{ $document->updated_at->format('d M Y') }} <span class="block text-xs text-slate-500">{{ $document->currentVersion?->uploader?->name }}</span></x-ui.td>
                    <x-ui.td @class(['whitespace-nowrap text-sm', 'text-rose-700' => $document->isExpired()])>{{ $document->expiry_date?->format('d M Y') ?? '—' }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap"><x-ui.badge :tone="$document->status->tone()">{{ $document->status->label() }}</x-ui.badge></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="7" :title="$tab === 'verification' ? __('Nothing waiting for your verification') : __('No documents found')" icon="folder" />
            @endforelse
        </x-ui.table>
    @endif
</div>
