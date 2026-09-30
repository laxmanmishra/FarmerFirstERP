<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'legal_name', 'gstin', 'pan', 'address', 'phone', 'email', 'financial_year_start_month'])]
class Company extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    protected string $auditModule = 'settings';

    protected function casts(): array
    {
        return ['financial_year_start_month' => 'integer'];
    }

    /**
     * The single operating company of this installation.
     */
    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
