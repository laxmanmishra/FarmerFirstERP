<?php

namespace App\Console\Commands;

use App\Enums\Temperature;
use App\Models\Enquiry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Temperature depends on "days until expected purchase", so it changes every day
 * even when nobody edits the enquiry (SRS §8). Only open enquiries are refreshed.
 */
#[Signature('crm:refresh-temperatures')]
#[Description('Recalculate enquiry temperature from the expected purchase date')]
class RefreshEnquiryTemperatures extends Command
{
    public function handle(): int
    {
        $updated = 0;

        Enquiry::query()->open()->select(['id', 'expected_purchase_date', 'temperature'])->chunkById(500, function ($enquiries) use (&$updated): void {
            foreach ($enquiries as $enquiry) {
                $temperature = Temperature::fromExpectedDate($enquiry->expected_purchase_date);

                if ($temperature !== $enquiry->temperature) {
                    Enquiry::query()->whereKey($enquiry->id)->update(['temperature' => $temperature]);
                    $updated++;
                }
            }
        });

        $this->info("Updated {$updated} enquiries.");

        return self::SUCCESS;
    }
}
