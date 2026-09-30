<?php

namespace App\Notifications;

use App\Models\FulfilmentTask;
use App\Models\Order;

class FulfilmentTaskCreated extends ErpNotification
{
    public function __construct(public readonly Order $order, public readonly FulfilmentTask $task) {}

    public function message(): string
    {
        return __('New order :no (:customer) needs :task.', [
            'no' => $this->order->order_no,
            'customer' => $this->order->customer->name,
            'task' => mb_strtolower($this->task->type->name),
        ]);
    }

    public function url(): ?string
    {
        return route('sales.orders.show', $this->order);
    }
}
