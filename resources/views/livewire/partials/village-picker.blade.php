{{-- Requires WithVillagePicker; pass $picker = villagePickerOptions() and optional $required. --}}
<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.select :label="__('District')" wire:model.live="pickDistrictId" name="pickDistrictId" :options="$picker['districts']" :placeholder="__('Select district…')" :required="$required ?? true" />
    <x-ui.select :label="__('Tehsil')" wire:model.live="pickTehsilId" name="pickTehsilId" :options="$picker['tehsils']" :placeholder="$pickDistrictId ? __('Select tehsil…') : __('Choose district first')" :disabled="! $pickDistrictId" :required="$required ?? true" />
    <x-ui.select :label="__('Village')" wire:model.live="village_id" name="village_id" :options="$picker['villages']" :placeholder="$pickTehsilId ? __('Select village…') : __('Choose tehsil first')" :disabled="! $pickTehsilId" :required="$required ?? true" />
</div>
