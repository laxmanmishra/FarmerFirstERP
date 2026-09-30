<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'mobile', 'password', 'is_active', 'must_change_password', 'current_branch_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUserstamps, Notifiable;

    public const SUPER_ADMIN_ROLE = 'Super Admin';

    public const OWNER_ROLE = 'Owner';

    /**
     * In-memory defaults matching the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
        'failed_login_count' => 0,
    ];

    protected string $auditModule = 'users';

    /** @var list<string> */
    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'failed_login_count'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Emails are stored lower-case so login lookups are case-insensitive on every database.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    /**
     * @return HasMany<LoginHistory, $this>
     */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->latest('id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE);
    }

    /**
     * Whether the user may see data of every branch rather than only assigned ones.
     */
    public function hasAllBranchAccess(): bool
    {
        return $this->hasAnyRole([self::SUPER_ADMIN_ROLE, self::OWNER_ROLE]);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Branches this user may work in: all active branches for Super Admin/Owner,
     * otherwise the active branches of the linked employee.
     *
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(): Collection
    {
        if ($this->hasAllBranchAccess()) {
            return Branch::query()->active()->orderBy('name')->get();
        }

        return $this->employee?->branches()->where('branches.is_active', true)->orderBy('name')->get()
            ?? new Collection;
    }

    /**
     * The branch new records are created in: the selected branch if still accessible,
     * otherwise the first accessible branch (API/mobile users never pick one).
     */
    public function workingBranch(): ?Branch
    {
        $branches = $this->accessibleBranches();

        return $branches->firstWhere('id', $this->current_branch_id) ?? $branches->first();
    }

    public function canAccessBranch(Branch|int $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return $this->accessibleBranches()->contains('id', $branchId);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
