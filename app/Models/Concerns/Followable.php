<?php

namespace App\Models\Concerns;

/**
 * A record other than an enquiry that follow-ups can be attached to (e.g. a finance file).
 */
interface Followable
{
    /**
     * Short label for reminders and lists, e.g. "FIN/2026-27/00001 · Ramesh Kumar".
     */
    public function followUpSubject(): string;

    public function followUpUrl(): string;

    /**
     * Permission that lets a manager act on other employees' follow-ups of this record.
     */
    public function followUpManagerPermission(): string;

    public function followUpBranchId(): int;
}
