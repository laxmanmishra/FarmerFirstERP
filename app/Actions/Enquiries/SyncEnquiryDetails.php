<?php

namespace App\Actions\Enquiries;

use App\Enums\DealType;
use App\Models\Enquiry;
use App\Models\EnquiryAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Replaces requirement lines, upserts the exchange tractor and stores exchange photos
 * on the private disk. Called inside the create/update transaction.
 */
class SyncEnquiryDetails
{
    /**
     * @param  list<UploadedFile>  $photos
     */
    public function sync(Enquiry $enquiry, EnquiryData $data, User $actor, array $photos = []): void
    {
        $enquiry->requirements()->delete();
        $enquiry->requirements()->createMany($data->requirements);

        if ($data->dealType === DealType::Exchange && $data->exchange !== null) {
            $enquiry->exchangeTractor()->updateOrCreate(['enquiry_id' => $enquiry->id], $data->exchange);
        } else {
            $enquiry->exchangeTractor()->delete();
        }

        foreach ($photos as $photo) {
            $enquiry->attachments()->create([
                'kind' => EnquiryAttachment::KIND_EXCHANGE_PHOTO,
                'disk' => 'local',
                'path' => $photo->store("enquiries/{$enquiry->id}", 'local'),
                'original_name' => mb_substr($photo->getClientOriginalName(), 0, 250),
                'mime_type' => (string) $photo->getMimeType(),
                'size_bytes' => (int) $photo->getSize(),
                'uploaded_by' => $actor->id,
            ]);
        }
    }
}
