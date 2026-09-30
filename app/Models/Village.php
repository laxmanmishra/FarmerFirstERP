<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tehsil_id', 'code', 'name', 'pin_code', 'is_active'])]
class Village extends Model
{
    use Auditable, HasActiveFlag, HasFactory, HasUserstamps;

    protected string $auditModule = 'geography';

    /**
     * @return BelongsTo<Tehsil, $this>
     */
    public function tehsil(): BelongsTo
    {
        return $this->belongsTo(Tehsil::class);
    }
}
