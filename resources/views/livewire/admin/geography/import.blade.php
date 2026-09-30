<div>
    <x-ui.page-header :title="__('Import geography')" :description="__('Bulk-load districts, tehsils and villages from Excel (.xlsx) or CSV. Nothing is saved unless every row is valid.')"
        :breadcrumbs="[__('Administration') => null, __('Geography') => route('admin.geography.index'), __('Import') => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="document-text" wire:click="downloadTemplate">{{ __('Download template') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if (! $batch || $batch->status === App\Models\ImportBatch::STATUS_IMPORTED)
                <x-ui.card :title="__('1. Upload file')">
                    <form wire:submit="validateFile" class="space-y-4">
                        <p class="text-sm text-slate-600">{{ __('Columns (first row): :columns. The state must already exist; districts and tehsils are created as needed; existing villages are skipped.', ['columns' => implode(', ', $columns)]) }}</p>
                        <input type="file" wire:model="file" accept=".csv,.xlsx" class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-800 hover:file:bg-brand-100" />
                        @error('file')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="file" class="text-xs text-slate-500">{{ __('Uploading…') }}</div>
                        <x-ui.button type="submit" wire:target="validateFile,file" :disabled="! $file">{{ __('Validate') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($batch)
                @if ($batch->status === App\Models\ImportBatch::STATUS_FAILED)
                    <x-ui.card :title="__('Error report')" :description="__(':errors of :total rows have problems. Fix the file and upload it again.', ['errors' => $batch->error_rows, 'total' => $batch->total_rows])">
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2 text-left">{{ __('Row') }}</th><th class="px-3 py-2 text-left">{{ __('Column') }}</th><th class="px-3 py-2 text-left">{{ __('Problem') }}</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($batch->errors ?? [] as $error)
                                        <tr><td class="tabular px-3 py-1.5">{{ $error['row'] }}</td><td class="px-3 py-1.5 font-mono text-xs">{{ $error['column'] }}</td><td class="px-3 py-1.5 text-rose-700">{{ $error['message'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4"><x-ui.button variant="secondary" wire:click="startOver">{{ __('Upload another file') }}</x-ui.button></div>
                    </x-ui.card>
                @elseif ($batch->status === App\Models\ImportBatch::STATUS_VALIDATED)
                    <x-ui.card :title="__('2. Preview')" :description="$batch->original_name">
                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-ui.kpi-card :label="__('Rows')" :value="number_format($batch->total_rows)" />
                            <x-ui.kpi-card :label="__('New villages')" :value="number_format($batch->summary['new_villages'] ?? 0)" tone="brand" />
                            <x-ui.kpi-card :label="__('Already exist')" :value="number_format($batch->summary['existing_villages'] ?? 0)" tone="slate" />
                            <x-ui.kpi-card :label="__('Tehsils affected')" :value="number_format($batch->summary['tehsils_touched'] ?? 0)" tone="sky" />
                        </div>
                        <div class="mt-5 flex gap-2">
                            <x-ui.button icon="check" wire:click="confirm" wire:confirm="{{ __('Import :count new villages now?', ['count' => $batch->summary['new_villages'] ?? 0]) }}">{{ __('3. Confirm import') }}</x-ui.button>
                            <x-ui.button variant="secondary" wire:click="startOver">{{ __('Cancel') }}</x-ui.button>
                        </div>
                    </x-ui.card>
                @else
                    <x-ui.alert tone="success" :title="__('Imported')">
                        {{ __('Created :d districts, :t tehsils and :v villages.', ['d' => $batch->summary['created']['districts'] ?? 0, 't' => $batch->summary['created']['tehsils'] ?? 0, 'v' => $batch->summary['created']['villages'] ?? 0]) }}
                    </x-ui.alert>
                @endif
            @endif
        </div>

        <x-ui.card :title="__('Import history')" :padding="false">
            <ul class="divide-y divide-slate-100">
                @forelse ($history as $item)
                    <li class="px-5 py-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-medium text-slate-800">{{ $item->original_name }}</span>
                            <x-ui.badge :tone="['imported' => 'green', 'validated' => 'amber', 'failed' => 'rose'][$item->status] ?? 'slate'">{{ ucfirst($item->status) }}</x-ui.badge>
                        </div>
                        <p class="text-xs text-slate-500">{{ $item->created_at->format('d M Y, H:i') }} · {{ $item->user?->name }} · {{ trans_choice(':count row|:count rows', $item->total_rows) }}</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('No imports yet.') }}</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
