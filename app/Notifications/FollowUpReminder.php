<?php

namespace App\Notifications;

use App\Models\Enquiry;
use App\Models\FollowUp;

class FollowUpReminder extends ErpNotification
{
    public function __construct(public readonly FollowUp $followUp, public readonly bool $overdue = false)
    {
        $this->priority = $overdue ? 'high' : 'normal';
    }

    public function message(): string
    {
        $subject = $this->followUp->followable instanceof Enquiry
            ? $this->followUp->followable->enquiry_no.' · '.$this->followUp->followable->farmer->name
            : __('record');

        return $this->overdue
            ? __('Overdue follow-up (:subject): :purpose', ['subject' => $subject, 'purpose' => $this->followUp->purpose])
            : __('Follow-up due at :time (:subject): :purpose', ['time' => $this->followUp->due_at->format('H:i'), 'subject' => $subject, 'purpose' => $this->followUp->purpose]);
    }

    public function url(): ?string
    {
        return $this->followUp->followable instanceof Enquiry
            ? route('crm.enquiries.show', $this->followUp->followable)
            : route('crm.follow-ups.index');
    }
}
