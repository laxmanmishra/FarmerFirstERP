@php
    $eventTones = ['created' => 'green', 'updated' => 'sky', 'deleted' => 'rose', 'deactivated' => 'rose', 'login' => 'brand', 'logout' => 'slate', 'account_locked' => 'rose'];
    $format = function (mixed $value): string {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    };
@endphp
<div>
    <x-ui.page-header :title="__('Audit Logs')" :description="__('Append-only trail of every material change, approval and sign-in. Entries cannot be edited or deleted.')"
        :breadcrumbs="[__('Administration') => null, __('Audit Logs') => null]" />

    <x-ui.table :paginator="$logs">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search reason, request id or IP…')" />
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="module" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Module') }}">
                    <option value="">{{ __('All modules') }}</option>
                    @foreach ($modules as $option)<option value="{{ $option }}">{{ \Illuminate\Support\Str::headline($option) }}</option>@endforeach
                </select>
                <select wire:model.live="event" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Event') }}">
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($events as $option)<option value="{{ $option }}">{{ \Illuminate\Support\Str::headline($option) }}</option>@endforeach
                </select>
                <select wire:model.live="userId" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('User') }}">
                    <option value="">{{ __('All users') }}</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                <input type="date" wire:model.live="from" class="form-control h-9 w-auto py-1" aria-label="{{ __('From') }}" />
                <input type="date" wire:model.live="to" class="form-control h-9 w-auto py-1" aria-label="{{ __('To') }}" />
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th>{{ __('When') }}</x-ui.th>
            <x-ui.th>{{ __('User') }}</x-ui.th>
            <x-ui.th>{{ __('Event') }}</x-ui.th>
            <x-ui.th>{{ __('Record') }}</x-ui.th>
            <x-ui.th>{{ __('Changes') }}</x-ui.th>
            <x-ui.th>{{ __('IP') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Details') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($logs as $log)
            <tr wire:key="audit-{{ $log->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="tabular whitespace-nowrap text-xs text-slate-500">{{ $log->created_at->format('d M Y, H:i:s') }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap">{{ $log->user?->name ?? __('System') }}</x-ui.td>
                <x-ui.td><x-ui.badge :tone="$eventTones[$log->event] ?? 'slate'">{{ \Illuminate\Support\Str::headline($log->event) }}</x-ui.badge></x-ui.td>
                <x-ui.td class="whitespace-nowrap text-xs">
                    <span class="font-medium text-slate-700">{{ \Illuminate\Support\Str::headline($log->module) }}</span>
                    @if ($log->auditable_id)<span class="text-slate-400">#{{ $log->auditable_id }}</span>@endif
                </x-ui.td>
                <x-ui.td class="max-w-sm truncate text-xs text-slate-500">
                    {{ collect(array_keys(($log->new_values ?? []) + ($log->old_values ?? [])))->implode(', ') ?: ($log->reason ?? '—') }}
                </x-ui.td>
                <x-ui.td class="tabular text-xs text-slate-400">{{ $log->ip_address ?? '—' }}</x-ui.td>
                <x-ui.td align="right"><x-ui.button variant="ghost" size="xs" icon="eye" wire:click="view({{ $log->id }})">{{ __('View') }}</x-ui.button></x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('No audit entries match these filters')" icon="shield" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showDetail" width="max-w-2xl" :title="__('Audit entry #:id', ['id' => $detail?->id])"
        :description="$detail ? $detail->created_at->format('d M Y, H:i:s').' · '.($detail->user?->name ?? __('System')) : null">
        @if ($detail)
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs text-slate-500">{{ __('Event') }}</dt><dd class="font-medium">{{ \Illuminate\Support\Str::headline($detail->event) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Module') }}</dt><dd class="font-medium">{{ \Illuminate\Support\Str::headline($detail->module) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Record') }}</dt><dd>{{ $detail->auditable_type ? class_basename($detail->auditable_type).' #'.$detail->auditable_id : '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('IP address') }}</dt><dd class="tabular">{{ $detail->ip_address ?? '—' }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">{{ __('Request id') }}</dt><dd class="font-mono text-xs">{{ $detail->request_id ?? '—' }}</dd></div>
                @if ($detail->reason)
                    <div class="col-span-2"><dt class="text-xs text-slate-500">{{ __('Reason') }}</dt><dd class="rounded-lg bg-amber-50 p-2 text-amber-900">{{ $detail->reason }}</dd></div>
                @endif
            </dl>

            @php $keys = array_unique(array_merge(array_keys($detail->old_values ?? []), array_keys($detail->new_values ?? []))); @endphp
            @if ($keys !== [])
                <div class="mt-6 overflow-hidden rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2 text-left">{{ __('Field') }}</th><th class="px-3 py-2 text-left">{{ __('Before') }}</th><th class="px-3 py-2 text-left">{{ __('After') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($keys as $key)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-700">{{ $key }}</td>
                                    <td class="break-all px-3 py-2 text-rose-700">{{ array_key_exists($key, $detail->old_values ?? []) ? $format($detail->old_values[$key]) : '—' }}</td>
                                    <td class="break-all px-3 py-2 text-emerald-700">{{ array_key_exists($key, $detail->new_values ?? []) ? $format($detail->new_values[$key]) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-ui.drawer>
</div>
