<?php

namespace App\Http\Resources;

use App\Models\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowStage
 */
class StageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'color' => $this->color,
            'is_final' => $this->is_final,
            'is_completion' => $this->is_completion,
        ];
    }
}
