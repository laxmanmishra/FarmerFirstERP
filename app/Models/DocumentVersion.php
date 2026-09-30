<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use LogicException;

/**
 * One uploaded file of a document. Versions are immutable and never removed (SRS §195).
 */
#[Fillable(['document_id', 'version', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum', 'uploaded_by', 'remarks'])]
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Document versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Document versions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'size_bytes' => 'integer'];
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, 1);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
