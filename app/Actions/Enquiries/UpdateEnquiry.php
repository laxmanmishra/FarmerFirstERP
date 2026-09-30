<?php

namespace App\Actions\Enquiries;

use App\Enums\Temperature;
use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Edits an open enquiry's details. Status changes never happen here — they go
 * through the telecaller and pipeline actions. Temperature recalculates (SRS §8).
 */
class UpdateEnquiry
{
    public function __construct(private readonly SyncEnquiryDetails $details) {}

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function handle(User $actor, Enquiry $enquiry, EnquiryData $data, array $photos = []): Enquiry
    {
        if ($enquiry->isClosed()) {
            throw new BusinessRuleException(__('Closed enquiries cannot be edited. Reopen it first.'), 'enquiry_closed');
        }

        if ($data->expectedPurchaseDate->isBefore(today())) {
            throw new BusinessRuleException(__('The expected purchase date cannot be in the past.'), 'past_expected_date');
        }

        return DB::transaction(function () use ($actor, $enquiry, $data, $photos): Enquiry {
            $enquiry->update([
                'source_code' => $data->sourceCode,
                'deal_type' => $data->dealType,
                'expected_purchase_date' => $data->expectedPurchaseDate,
                'temperature' => Temperature::fromExpectedDate($data->expectedPurchaseDate),
                'budget' => $data->budget,
                'remarks' => $data->remarks,
                'village_id' => $data->villageId ?? $enquiry->village_id,
                'last_activity_at' => now(),
            ]);

            $this->details->sync($enquiry, $data, $actor, $photos);

            return $enquiry;
        });
    }
}
