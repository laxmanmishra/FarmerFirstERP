<?php

namespace App\Http\Requests\Api;

use App\Actions\Enquiries\EnquiryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Same rules as the web enquiry form (EnquiryData::rules()).
 */
class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('enquiries.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'farmer_id' => ['required', Rule::exists('farmers', 'id')],
            'assigned_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('is_active', true)],
            'duplicate_override_reason' => ['nullable', 'string', 'max:500'],
            ...EnquiryData::rules(),
        ];
    }
}
