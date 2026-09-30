<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\VerificationAction;
use App\Exceptions\BusinessRuleException;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentRejected;
use Illuminate\Support\Facades\DB;

/**
 * Uploaded → Under Verification → Verified / Rejected (SRS §194). Only holders of the
 * type's verification permission decide, never the person who uploaded the version.
 */
class DocumentVerificationFlow
{
    public function canVerify(User $user, Document $document): bool
    {
        return $user->can($document->type->verification_permission)
            && $document->currentVersion?->uploaded_by !== $user->id;
    }

    public function start(User $actor, Document $document): Document
    {
        $this->assertVerifier($actor, $document);

        if ($document->status !== DocumentStatus::Uploaded) {
            throw new BusinessRuleException(__('Only a newly uploaded document can be taken up for verification.'), 'document_not_uploaded');
        }

        return $this->apply($actor, $document, DocumentStatus::UnderVerification, VerificationAction::Started, null);
    }

    public function verify(User $actor, Document $document, ?string $remarks): Document
    {
        $this->assertVerifier($actor, $document);

        if (! $document->status->awaitsVerification()) {
            throw new BusinessRuleException(__('This document is not awaiting verification.'), 'document_not_pending');
        }

        if ($document->isExpired()) {
            throw new BusinessRuleException(__('The document has expired; ask for a renewed copy.'), 'document_expired');
        }

        return $this->apply($actor, $document, DocumentStatus::Verified, VerificationAction::Verified, $remarks, [
            'verified_by' => $actor->id, 'verified_at' => now(), 'rejection_reason' => null,
        ]);
    }

    public function reject(User $actor, Document $document, string $reason): Document
    {
        $this->assertVerifier($actor, $document);

        if (! in_array($document->status, [DocumentStatus::Uploaded, DocumentStatus::UnderVerification, DocumentStatus::Verified], true)) {
            throw new BusinessRuleException(__('This document cannot be rejected in its current state.'), 'document_not_rejectable');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give the reason for rejecting the document.'), 'reason_required');
        }

        $document = $this->apply($actor, $document, DocumentStatus::Rejected, VerificationAction::Rejected, $reason, [
            'rejection_reason' => $reason, 'verified_by' => null, 'verified_at' => null,
        ]);

        if (($uploader = $document->currentVersion?->uploader) !== null && ! $uploader->is($actor)) {
            $uploader->notify(new DocumentRejected($document, $reason));
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function apply(User $actor, Document $document, DocumentStatus $status, VerificationAction $action, ?string $remarks, array $attributes = []): Document
    {
        DB::transaction(function () use ($actor, $document, $status, $action, $remarks, $attributes): void {
            $document->update(['status' => $status, ...$attributes]);
            $document->verifications()->create([
                'document_version_id' => $document->currentVersion?->id,
                'action' => $action,
                'remarks' => $remarks,
                'user_id' => $actor->id,
            ]);
        });

        return $document;
    }

    private function assertVerifier(User $actor, Document $document): void
    {
        $document->loadMissing(['type', 'currentVersion.uploader']);

        if (! $actor->can($document->type->verification_permission)) {
            throw new BusinessRuleException(__('You are not allowed to verify :type documents.', ['type' => $document->type->name]), 'not_verifier');
        }

        if ($document->currentVersion?->uploaded_by === $actor->id) {
            throw new BusinessRuleException(__('You uploaded this version, so someone else must verify it.'), 'self_verification');
        }
    }
}
