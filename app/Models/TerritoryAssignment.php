<?php

namespace App\Models;

use App\Enums\TerritoryLevel;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salesman territory (SRS §6). `primary_scope` is maintained here so the unique
 * index enforces one active primary salesman per area.
 */
#[Fillable(['employee_id', 'level', 'district_id', 'tehsil_id', 'village_id', 'is_primary', 'is_active', 'effective_from', 'effective_to', 'end_reason'])]
class TerritoryAssignment extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    protected string $auditModule = 'territory';

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            $assignment->primary_scope = $assignment->is_primary && $assignment->is_active
                ? $assignment->level->value.':'.$assignment->areaId()
                : null;
        });
    }

    protected function casts(): array
    {
        return [
            'level' => TerritoryLevel::class,
            'is_primary' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function areaId(): ?int
    {
        return $this->{$this->level->column()};
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Tehsil, $this>
     */
    public function tehsil(): BelongsTo
    {
        return $this->belongsTo(Tehsil::class);
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function areaLabel(): string
    {
        return match ($this->level) {
            TerritoryLevel::District => $this->district?->name,
            TerritoryLevel::Tehsil => $this->tehsil?->name.' ('.$this->tehsil?->district?->name.')',
            TerritoryLevel::Village => $this->village?->name.' ('.$this->village?->tehsil?->name.')',
        } ?? '—';
    }
}
