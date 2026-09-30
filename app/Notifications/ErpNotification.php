<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for in-app notifications (SRS v6.0 D). Stored on the database channel with a
 * uniform payload the notification bell renders: message, url, type, priority.
 * Email/SMS/WhatsApp channels are added by later phases without changing callers.
 */
abstract class ErpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $priority = 'normal';

    abstract public function message(): string;

    abstract public function url(): ?string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, message: string, url: ?string, priority: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => class_basename(static::class),
            'message' => $this->message(),
            'url' => $this->url(),
            'priority' => $this->priority,
        ];
    }
}
