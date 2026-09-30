<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Files attached to an enquiry (exchange photos). Stored on the private disk and
 * served only through an authorised controller.
 */
#[Fillable(['enquiry_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'])]
class EnquiryAttachment extends Model
{
    public const KIND_EXCHANGE_PHOTO = 'exchange_photo';

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
