@props(['tone' => 'slate', 'dot' => false])

@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'brand' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
    ];
    $dots = ['slate' => 'bg-slate-400', 'brand' => 'bg-brand-500', 'green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'sky' => 'bg-sky-500', 'violet' => 'bg-violet-500'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($tones[$tone] ?? $tones['slate'])]) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $dots[$tone] ?? $dots['slate'] }}"></span>@endif
    {{ $slot }}
</span>
