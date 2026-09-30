<?php

namespace App\Notifications;

use App\Models\Enquiry;

class EnquiryAssigned extends ErpNotification
{
    public function __construct(public readonly Enquiry $enquiry) {}

    public function message(): string
    {
        return __('Enquiry :no (:farmer) has been assigned to you.', ['no' => $this->enquiry->enquiry_no, 'farmer' => $this->enquiry->farmer->name]);
    }

    public function url(): ?string
    {
        return route('crm.enquiries.show', $this->enquiry);
    }
}
