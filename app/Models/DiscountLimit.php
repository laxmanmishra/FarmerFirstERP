<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Maximum discount a role may give without approval (SRS v6.1 §4).
 * A user's limit is the most generous of their roles; roles without a row have none.
 */
#[Fillable(['role_id', 'max_percent', 'max_amount'])]
class DiscountLimit extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'settings';

    protected function casts(): array
    {
        return ['max_percent' => 'decimal:2', 'max_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function allows(User $user, string $discountAmount, string $discountPercent): bool
    {
        if ($user->isSuperAdmin() || Money::compare($discountAmount, 0) === 0) {
            return true;
        }

        return static::query()->whereIn('role_id', $user->roles->pluck('id'))->get()
            ->contains(fn (self $limit) => Money::compare($discountPercent, $limit->max_percent) <= 0
                && ($limit->max_amount === null || Money::compare($discountAmount, $limit->max_amount) <= 0));
    }
}
