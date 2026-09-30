<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Import history (SRS v6.1 §12): file, user, counts, row errors and result.
 */
#[Fillable(['type', 'original_name', 'stored_path', 'status', 'total_rows', 'error_rows', 'errors', 'summary', 'user_id', 'imported_at'])]
class ImportBatch extends Model
{
    public const STATUS_VALIDATED = 'validated';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IMPORTED = 'imported';

    public const TYPE_GEOGRAPHY = 'geography';

    protected function casts(): array
    {
        return ['errors' => 'array', 'summary' => 'array', 'imported_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
