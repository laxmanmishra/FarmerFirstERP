<div x-data="{ dragging: null, over: null }">
    <x-ui.page-header :title="__('Sales Pipeline')" :description="$canMove ? __('Drag a card to move it. Won, lost and dropped ask for confirmation.') : __('Validated enquiries by stage.')"
        :breadcrumbs="[__('CRM') => null, __('Sales Pipeline') => null]" />

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Enquiry, farmer or mobile…')" />
        @if ($assignees->isNotEmpty())
            <select wire:model.live="assignee" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Salesman') }}">
                <option value="">{{ __('All salesmen') }}</option>
                @foreach ($assignees as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        @endif
        <select wire:model.live="temperature" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Temperature') }}">
            <option value="">{{ __('Any temperature') }}</option>
            @foreach (App\Enums\Temperature::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
        </select>
        <div class="flex flex-wrap gap-2 sm:ml-auto">
            @foreach ($finalStages as $stage)
                <span wire:key="final-count-{{ $stage->id }}" @if ($canMove) x-on:dragover.prevent="over = {{ $stage->id }}" x-on:dragleave="over = null" x-on:drop.prevent="$wire.drop(dragging, {{ $stage->id }}); over = null" @endif
                    :class="over === {{ $stage->id }} ? 'ring-2 ring-offset-1 ring-brand-500' : ''" class="rounded-full transition">
                    <x-ui.badge :tone="$stage->color" dot>{{ $stage->name }} · {{ __(':count this month', ['count' => $closedThisMonth[$stage->id] ?? 0]) }}</x-ui.badge>
                </span>
            @endforeach
        </div>
    </div>

    <div class="-mx-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="flex min-w-max gap-4">
            @foreach ($openStages as $stage)
                @php $column = $cards->get($stage->id, collect()); @endphp
                <section wire:key="col-{{ $stage->id }}" class="flex w-72 shrink-0 flex-col rounded-(--radius-card) bg-slate-100/80"
                    @if ($canMove) x-on:dragover.prevent="over = {{ $stage->id }}" x-on:dragleave.self="over = null" x-on:drop.prevent="$wire.drop(dragging, {{ $stage->id }}); over = null" @endif
                    :class="over === {{ $stage->id }} ? 'ring-2 ring-brand-500' : ''">
                    <header class="flex items-center justify-between px-3 py-2.5">
                        <x-ui.badge :tone="$stage->color" dot>{{ $stage->name }}</x-ui.badge>
                        <span class="tabular text-xs font-semibold text-slate-500">{{ $column->count() }}</span>
                    </header>
                    <div class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-2">
                        @foreach ($column->take($perColumn) as $enquiry)
                            <article wire:key="card-{{ $enquiry->id }}" @if ($canMove) draggable="true" x-on:dragstart="dragging = {{ $enquiry->id }}" x-on:dragend="dragging = null" @endif
                                :class="dragging === {{ $enquiry->id }} ? 'opacity-50' : ''"
                                class="rounded-lg border border-slate-200 bg-white p-3 shadow-xs {{ $canMove ? 'cursor-grab active:cursor-grabbing' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('crm.enquiries.show', $enquiry) }}" wire:navigate class="text-sm font-semibold text-slate-900 hover:text-brand-700">{{ $enquiry->farmer->name }}</a>
                                    <x-ui.temperature-badge :temperature="$enquiry->temperature" />
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-600">{{ $enquiry->requirements->map->summary()->implode(', ') }}</p>
                                <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                    <span>{{ $enquiry->farmer->village->name }}</span>
                                    <span class="tabular">{{ $enquiry->expected_purchase_date->format('d M') }}</span>
                                </div>
                                <div class="mt-1 flex items-center justify-between text-[11px] text-slate-400">
                                    <span class="tabular">{{ $enquiry->enquiry_no }}</span>
                                    <span>{{ $enquiry->assignee?->name ?? __('Unassigned') }}</span>
                                </div>
                            </article>
                        @endforeach
                        @if ($column->count() > $perColumn)
                            <a href="{{ route('crm.enquiries.index', ['view' => 'pipeline', 'stage' => $stage->id]) }}" wire:navigate class="rounded-lg py-2 text-center text-xs font-medium text-brand-700 hover:bg-white">
                                {{ __('+ :count more', ['count' => $column->count() - $perColumn]) }}
                            </a>
                        @endif
                        @if ($column->isEmpty())
                            <p class="py-6 text-center text-xs text-slate-400">{{ __('No enquiries') }}</p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    @if ($modal === 'move' && $movingStage && $movingEnquiry)
        <x-ui.modal wire:model="modal" :tone="$movingStage->is_rejection ? 'danger' : null"
            :title="__('Move :no to :stage', ['no' => $movingEnquiry->enquiry_no, 'stage' => $movingStage->name])"
            :description="$movingStage->is_final ? __('This closes the enquiry.') : null">
            <form id="move-form" wire:submit="confirmMove" class="space-y-4">
                @if ($movingStage->is_final && $movingStage->is_rejection)
                    <x-ui.select :label="__('Reason')" wire:model="closeReason" name="closeReason" :options="$closeReasons" :placeholder="__('Select reason…')" required />
                @endif
                <x-ui.textarea :label="__('Remarks')" wire:model="remarks" name="remarks" rows="3" :required="$movingStage->requires_remark" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="move-form" :variant="$movingStage->is_rejection ? 'danger' : 'primary'" wire:target="confirmMove">{{ __('Confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
