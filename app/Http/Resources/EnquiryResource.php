<?php

namespace App\Http\Resources;

use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enquiry
 */
class EnquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enquiry_no' => $this->enquiry_no,
            'farmer' => FarmerResource::make($this->whenLoaded('farmer')),
            'deal_type' => $this->deal_type->value,
            'source_code' => $this->source_code,
            'expected_purchase_date' => $this->expected_purchase_date->toDateString(),
            'temperature' => $this->temperature->value,
            'budget' => $this->budget,
            'remarks' => $this->remarks,
            'validation_stage' => StageResource::make($this->whenLoaded('validationStage')),
            'pipeline_stage' => StageResource::make($this->whenLoaded('pipelineStage')),
            'is_closed' => $this->isClosed(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'requirements' => $this->whenLoaded('requirements', fn () => $this->requirements->map(fn ($line) => [
                'requirement_type' => $line->requirement_type->value,
                'brand_id' => $line->brand_id,
                'product_id' => $line->product_id,
                'product_variant_id' => $line->product_variant_id,
                'quantity' => $line->quantity,
                'description' => $line->description,
                'summary' => $line->summary(),
            ])),
            'exchange' => $this->whenLoaded('exchangeTractor', fn () => $this->exchangeTractor?->only([
                'brand_name', 'model_name', 'manufacturing_year', 'hours_used', 'registration_number', 'condition', 'customer_expected_price', 'approved_exchange_value',
            ])),
            'created_at' => $this->created_at->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
        ];
    }
}
