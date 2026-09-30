@props(['label', 'description' => null])

<label class="flex cursor-pointer items-start gap-3">
    <input type="checkbox" {{ $attributes->merge(['class' => 'mt-0.5 size-4 rounded border-slate-300 text-brand-700 accent-brand-700 focus:ring-brand-500']) }} />
    <span>
        <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($description)<span class="block text-xs text-slate-500">{{ $description }}</span>@endif
    </span>
</label>
