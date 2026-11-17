<?php

declare(strict_types=1);

namespace Nex\Notifications;

use Nex\Mail\Contracts\MailerInterface;
use Nex\Mail\Mailable;
use Nex\Database\Contracts\ConnectionInterface;

/**
 * Base notification class.
 *
 * Usage:
 *   class WelcomeNotification extends Notification {
 *       public function __construct(private readonly string $name) {}
 *
 *       public function via(object $notifiable): array { return ['mail', 'database']; }
 *
 *       public function toMail(object $notifiable): Mailable {
 *           return (new Mailable())->subject("Welcome, {$this->name}!")->html("<p>Hello {$this->name}</p>");
 *       }
 *
 *       public function toDatabase(object $notifiable): array {
 *           return ['message' => "Welcome, {$this->name}!"];
 *       }
 *   }
 */
abstract class Notification
{
    /**
     * Which channels to use. Must be implemented by concrete notifications.
     * @return list<string>
     */
    abstract public function via(object $notifiable): array;
}
