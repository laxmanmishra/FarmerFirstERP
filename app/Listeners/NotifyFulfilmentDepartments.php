<?php

namespace App\Listeners;

use App\Enums\RequirementState;
use App\Events\OrderBooked;
use App\Models\User;
use App\Notifications\FulfilmentTaskCreated;
use Illuminate\Support\Facades\Notification;

/**
 * Tells each department with a required task that a new order is waiting (SRS §23).
 */
class NotifyFulfilmentDepartments
{
    public function handle(OrderBooked $event): void
    {
        $order = $event->order->load(['customer:id,name', 'fulfilment.tasks.type']);

        foreach ($order->fulfilment->tasks->where('requirement_state', RequirementState::Required) as $task) {
            $recipients = User::withPermissionInBranch($task->type->update_permission, $order->branch_id, $event->actor)
                ->reject(fn (User $user) => $user->isSuperAdmin());

            Notification::send($recipients, new FulfilmentTaskCreated($order, $task));
        }
    }
}
