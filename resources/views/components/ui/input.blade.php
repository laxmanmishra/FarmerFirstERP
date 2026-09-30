@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false, 'type' => 'text'])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', (string) $name));
@endphp

<x-ui.field :label="$label" :for="$id" :error="$name" :hint="$hint" :required="$required">
    <input type="{{ $type }}" id="{{ $id }}" @if ($required) required @endif
        {{ $attributes->class(['form-control', 'form-control-invalid' => $name && $errors->has($name)]) }}
        @if ($name && $errors->has($name)) aria-invalid="true" @endif />
</x-ui.field>
