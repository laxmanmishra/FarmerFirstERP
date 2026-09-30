<?php

namespace App\Models;

use App\Enums\VerificationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable verification-history entry (SRS §194).
 */
#[Fillable(['document_id', 'document_version_id', 'action', 'remarks', 'user_id'])]
class DocumentVerification extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Verification history is immutable.'));
        static::deleting(fn () => throw new LogicException('Verification history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['action' => VerificationAction::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
