<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'description', 'is_operational', 'sort_order', 'is_active'])]
class Department extends Model
{
    use Auditable, HasActiveFlag, HasFactory, HasUserstamps;

    protected function casts(): array
    {
        return ['is_operational' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)->withPivot('is_primary', 'is_head')->withTimestamps();
    }
}
