@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false, 'options' => [], 'placeholder' => null])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', (string) $name));
@endphp

<x-ui.field :label="$label" :for="$id" :error="$name" :hint="$hint" :required="$required">
    <select id="{{ $id }}" {{ $attributes->class(['form-control pr-8', 'form-control-invalid' => $name && $errors->has($name)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-ui.field>
