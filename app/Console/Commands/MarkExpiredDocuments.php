<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Enums\VerificationAction;
use App\Models\Document;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily expiry job (DOCUMENT_REQUIREMENTS §5.6). Requirements linked to an expired
 * document stop being satisfied because their status is derived from the document.
 */
#[Signature('documents:mark-expired')]
#[Description('Mark documents past their expiry date as expired')]
class MarkExpiredDocuments extends Command
{
    public function handle(): int
    {
        $count = 0;

        Document::query()
            ->with('currentVersion')
            ->whereIn('status', [DocumentStatus::Uploaded, DocumentStatus::UnderVerification, DocumentStatus::Verified])
            ->whereDate('expiry_date', '<', today())
            ->chunkById(200, function ($documents) use (&$count): void {
                foreach ($documents as $document) {
                    DB::transaction(function () use ($document): void {
                        $document->update(['status' => DocumentStatus::Expired, 'expired_at' => now()]);
                        $document->verifications()->create([
                            'document_version_id' => $document->currentVersion?->id,
                            'action' => VerificationAction::Expired,
                            'remarks' => __('Expired on :date', ['date' => $document->expiry_date->format('d M Y')]),
                        ]);
                    });
                    $count++;
                }
            });

        $this->info("Marked {$count} documents as expired.");

        return self::SUCCESS;
    }
}
