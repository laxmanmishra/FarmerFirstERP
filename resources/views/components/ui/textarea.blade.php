@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false, 'rows' => 3])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', (string) $name));
@endphp

<x-ui.field :label="$label" :for="$id" :error="$name" :hint="$hint" :required="$required">
    <textarea id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->class(['form-control', 'form-control-invalid' => $name && $errors->has($name)]) }}></textarea>
</x-ui.field>
