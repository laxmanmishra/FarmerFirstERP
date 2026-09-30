<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Format tokens: {prefix}, {branch}, {fy} (e.g. 2026-27), {yyyy}, {seq}.
 */
#[Fillable(['entity', 'name', 'prefix', 'format', 'padding', 'reset_policy', 'per_branch', 'is_active'])]
class NumberSeries extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    public const RESET_NEVER = 'never';

    public const RESET_FINANCIAL_YEAR = 'financial_year';

    protected $table = 'number_series';

    protected string $auditModule = 'number_series';

    protected function casts(): array
    {
        return ['padding' => 'integer', 'per_branch' => 'boolean'];
    }

    /**
     * @return HasMany<NumberSeriesCounter, $this>
     */
    public function counters(): HasMany
    {
        return $this->hasMany(NumberSeriesCounter::class);
    }
}
