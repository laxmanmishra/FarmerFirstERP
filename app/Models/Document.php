<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical document in the central repository (SRS §185). Uploaded once, linked to any
 * number of requirements; files are versioned and old versions are kept (SRS §195).
 */
#[Fillable([
    'document_no', 'document_type_id', 'customer_id', 'deal_id', 'order_id', 'department_id', 'status', 'reference_no',
    'issue_date', 'expiry_date', 'current_version', 'remarks', 'uploaded_by', 'verified_by', 'verified_at', 'rejection_reason', 'expired_at',
])]
class Document extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'documents';

    /** @var list<string> */
    protected array $auditExclude = ['reference_no'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'reference_no' => 'encrypted',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'current_version' => 'integer',
            'verified_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * Expired by date even if the nightly job has not yet flagged it.
     */
    public function isExpired(): bool
    {
        return $this->status === DocumentStatus::Expired || ($this->expiry_date !== null && $this->expiry_date->lt(today()));
    }

    /**
     * Existence and verification are separate from satisfying a requirement (INV-09);
     * this answers "can the document be relied on".
     */
    public function isUsable(): bool
    {
        if ($this->isExpired() || $this->status === DocumentStatus::Rejected) {
            return false;
        }

        return $this->status === DocumentStatus::Verified || ! $this->type->verification_required;
    }

    public function maskedReference(): ?string
    {
        if ($this->reference_no === null || $this->reference_no === '') {
            return null;
        }

        return str_repeat('•', max(0, mb_strlen($this->reference_no) - 4)).mb_substr($this->reference_no, -4);
    }

    /**
     * Sensitive files open only for holders of documents.view_sensitive, the type's
     * verifiers and the person who uploaded the current version (SRS §197).
     */
    public function canViewFile(User $user): bool
    {
        if (! $this->type->isSensitive()) {
            return true;
        }

        return $user->can('documents.view_sensitive') || $user->can($this->type->verification_permission)
            || $this->currentVersion?->uploaded_by === $user->id;
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    /**
     * The newest version is always the current one (versions only ever increase).
     *
     * @return HasOne<DocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version', 'max');
    }

    /**
     * @return HasMany<DocumentVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(DocumentVerification::class)->latest('id');
    }

    /**
     * @return HasMany<DocumentRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    /**
     * @return HasMany<DocumentAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('customer_id', Customer::query()->visibleTo($user)->select('customers.id'))
            ->orWhereIn('order_id', Order::query()->visibleTo($user)->select('orders.id')));
    }

    /**
     * The user's verification queue: awaiting verification, of a type whose verification
     * permission they hold, and not uploaded by them.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeAwaitingVerificationBy(Builder $query, User $user): void
    {
        $permissions = DocumentType::query()->where('verification_required', true)->distinct()->pluck('verification_permission')
            ->filter(fn (string $permission) => $user->can($permission))->values();

        $query->whereIn('status', [DocumentStatus::Uploaded, DocumentStatus::UnderVerification])
            ->whereHas('type', fn (Builder $query) => $query->where('verification_required', true)->whereIn('verification_permission', $permissions))
            ->where(fn (Builder $query) => $query->whereNull('uploaded_by')->orWhere('uploaded_by', '!=', $user->id));
    }

    /**
     * Not rejected and not expired (by flag or by date).
     *
     * @param  Builder<Document>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNotIn('status', [DocumentStatus::Rejected, DocumentStatus::Expired])
            ->where(fn (Builder $query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()));
    }
}
