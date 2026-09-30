<?php

namespace App\Http\Resources;

use App\Models\Enquiry;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FollowUp
 */
class FollowUpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enquiry = $this->followable instanceof Enquiry ? $this->followable : null;

        return [
            'id' => $this->id,
            'type_code' => $this->type_code,
            'purpose' => $this->purpose,
            'due_at' => $this->due_at->toIso8601String(),
            'status' => $this->status->value,
            'is_overdue' => $this->isOverdue(),
            'outcome' => $this->outcome,
            'assignee' => $this->whenLoaded('assignee', fn () => ['id' => $this->assignee->id, 'name' => $this->assignee->name]),
            'enquiry' => $enquiry ? [
                'id' => $enquiry->id,
                'enquiry_no' => $enquiry->enquiry_no,
                'farmer_name' => $enquiry->relationLoaded('farmer') ? $enquiry->farmer->name : null,
                'farmer_mobile' => $enquiry->relationLoaded('farmer') ? $enquiry->farmer->mobile : null,
            ] : null,
        ];
    }
}
