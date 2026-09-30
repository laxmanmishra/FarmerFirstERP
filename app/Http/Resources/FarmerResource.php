<?php

namespace App\Http\Resources;

use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Farmer
 */
class FarmerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farmer_no' => $this->farmer_no,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'mobile' => $this->mobile,
            'alternate_mobile' => $this->alternate_mobile,
            'whatsapp_number' => $this->whatsapp_number,
            'village' => $this->whenLoaded('village', fn () => [
                'id' => $this->village->id,
                'name' => $this->village->name,
                'tehsil' => $this->village->relationLoaded('tehsil') ? $this->village->tehsil->name : null,
                'district' => $this->village->relationLoaded('tehsil') && $this->village->tehsil->relationLoaded('district') ? $this->village->tehsil->district->name : null,
            ]),
            'land_acres' => $this->land_acres,
            'enquiries_count' => $this->whenCounted('enquiries'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
