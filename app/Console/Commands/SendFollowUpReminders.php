<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Notifications\FollowUpReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reminds assignees shortly before a follow-up is due and alerts once when it becomes
 * overdue. The reminded_at / overdue_notified_at stamps make each alert idempotent.
 */
#[Signature('crm:follow-up-reminders')]
#[Description('Send due-soon and overdue follow-up notifications')]
class SendFollowUpReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;
        $lead = now()->addMinutes(config('erp.crm.follow_up_reminder_minutes'));

        FollowUp::query()->pending()->whereNull('reminded_at')->whereBetween('due_at', [now(), $lead])
            ->with(['assignee.user', 'followable'])->chunkById(200, function ($followUps) use (&$sent): void {
                foreach ($followUps as $followUp) {
                    $followUp->assignee->user?->notify(new FollowUpReminder($followUp));
                    FollowUp::query()->whereKey($followUp->id)->update(['reminded_at' => now()]);
                    $sent++;
                }
            });

        FollowUp::query()->overdue()->whereNull('overdue_notified_at')
            ->with(['assignee.user', 'followable'])->chunkById(200, function ($followUps) use (&$sent): void {
                foreach ($followUps as $followUp) {
                    $followUp->assignee->user?->notify(new FollowUpReminder($followUp, overdue: true));
                    FollowUp::query()->whereKey($followUp->id)->update(['overdue_notified_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} follow-up notifications.");

        return self::SUCCESS;
    }
}
