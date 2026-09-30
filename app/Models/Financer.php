<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * External bank / NBFC (SRS §65). Financers never get ERP logins (INV-08); Retail
 * staff record their decisions on the finance file.
 */
#[Fillable(['code', 'name', 'type', 'is_active'])]
class Financer extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    public const TYPES = ['bank' => 'Bank', 'nbfc' => 'NBFC', 'cooperative' => 'Co-operative', 'other' => 'Other'];

    protected string $auditModule = 'finance';

    /**
     * @return HasMany<FinancerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(FinancerContact::class)->orderBy('name');
    }
}
