<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('farmers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', 'digits:10', 'regex:/^[6-9]/'],
            'alternate_mobile' => ['nullable', 'digits:10', 'different:mobile'],
            'whatsapp_number' => ['nullable', 'digits:10'],
            'village_id' => ['required', Rule::exists('villages', 'id')->where('is_active', true)],
            'address' => ['nullable', 'string', 'max:500'],
            'pin_code' => ['nullable', 'digits:6'],
            'land_acres' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'confirm_not_duplicate' => ['boolean'],
        ];
    }
}
