<?php

namespace App\Notifications;

use App\Enums\ApprovalStatus;
use App\Models\ReopenRequest;

class ReopenDecided extends ErpNotification
{
    public function __construct(public readonly ReopenRequest $request) {}

    public function message(): string
    {
        return $this->request->status === ApprovalStatus::Approved
            ? __('Your request to reopen enquiry :no was approved.', ['no' => $this->request->enquiry->enquiry_no])
            : __('Your request to reopen enquiry :no was rejected: :remarks', ['no' => $this->request->enquiry->enquiry_no, 'remarks' => $this->request->decision_remarks]);
    }

    public function url(): ?string
    {
        return route('crm.enquiries.show', $this->request->enquiry);
    }
}
