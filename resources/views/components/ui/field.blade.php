@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-medium text-slate-700">
            {{ $label }}@if ($required)<span class="ml-0.5 text-rose-500" aria-hidden="true">*</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($error && $errors->has($error))
        <p class="text-xs font-medium text-rose-600" role="alert">{{ $errors->first($error) }}</p>
    @elseif ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
