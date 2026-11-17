<?php

declare(strict_types=1);

namespace Nex\Notifications;

/**
 * Notifiable trait — mix into any entity that can receive notifications.
 *
 * Usage:
 *   class User {
 *       use Notifiable;
 *   }
 *   $user->notify(new WelcomeNotification('Jane'), $sender);
 */
trait Notifiable
{
    public function notify(Notification $notification, NotificationSender $sender): void
    {
        $sender->send($this, $notification);
    }
}
