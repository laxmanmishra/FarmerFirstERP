<?php

namespace App\Notifications;

use App\Models\ReopenRequest;

class ReopenRequested extends ErpNotification
{
    public function __construct(public readonly ReopenRequest $request) {}

    public function message(): string
    {
        return __(':user asked to reopen enquiry :no: :reason', [
            'user' => $this->request->requester->name,
            'no' => $this->request->enquiry->enquiry_no,
            'reason' => $this->request->reason,
        ]);
    }

    public function url(): ?string
    {
        return route('crm.reopen-requests.index');
    }
}
