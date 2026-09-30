<?php

namespace App\Notifications;

use App\Models\Document;

class DocumentRejected extends ErpNotification
{
    public function __construct(public readonly Document $document, public readonly string $reason)
    {
        $this->priority = 'high';
    }

    public function message(): string
    {
        return __(':type :no was rejected: :reason', ['type' => $this->document->type->name, 'no' => $this->document->document_no, 'reason' => $this->reason]);
    }

    public function url(): ?string
    {
        return route('fulfilment.documents.show', $this->document);
    }
}
