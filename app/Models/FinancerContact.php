<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Field officer / branch contact of a financer (SRS §66).
 */
#[Fillable(['financer_id', 'name', 'designation', 'mobile', 'email', 'area', 'is_active'])]
class FinancerContact extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    protected string $auditModule = 'finance';

    /**
     * @return BelongsTo<Financer, $this>
     */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }
}
