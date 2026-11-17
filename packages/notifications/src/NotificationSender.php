<?php

declare(strict_types=1);

namespace Nex\Notifications;

use Nex\Database\Contracts\ConnectionInterface;
use Nex\Mail\Contracts\MailerInterface;
use Nex\Mail\Mailable;

/**
 * Sends a Notification to a notifiable entity through the declared channels.
 *
 * Usage:
 *   $sender = new NotificationSender(mailer: $mailer, connection: $db);
 *   $sender->send($user, new WelcomeNotification($user->name));
 */
final class NotificationSender
{
    public function __construct(
        private readonly ?MailerInterface $mailer = null,
        private readonly ?ConnectionInterface $connection = null,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        foreach ($channels as $channel) {
            match ($channel) {
                'mail'     => $this->sendMail($notifiable, $notification),
                'database' => $this->sendDatabase($notifiable, $notification),
                default    => throw new \InvalidArgumentException(
                    "Unknown notification channel [{$channel}]. " .
                    "Supported channels: 'mail', 'database'. " .
                    "Register custom channels by extending NotificationSender."
                ),
            };
        }
    }

    private function sendMail(object $notifiable, Notification $notification): void
    {
        if ($this->mailer === null) {
            throw new \RuntimeException(
                "Cannot send mail notification: no MailerInterface was provided to NotificationSender. " .
                "Pass a mailer in the constructor: new NotificationSender(mailer: \$mailer)."
            );
        }

        if (!method_exists($notification, 'toMail')) {
            throw new \RuntimeException(
                "Notification [" . $notification::class . "] uses the 'mail' channel but does not implement toMail(). " .
                "Add: public function toMail(object \$notifiable): Mailable { ... }"
            );
        }

        /** @var Mailable $mail */
        $mail = $notification->toMail($notifiable);
        $this->mailer->send($mail);
    }

    private function sendDatabase(object $notifiable, Notification $notification): void
    {
        if ($this->connection === null) {
            throw new \RuntimeException(
                "Cannot send database notification: no ConnectionInterface was provided to NotificationSender."
            );
        }

        if (!method_exists($notification, 'toDatabase')) {
            throw new \RuntimeException(
                "Notification [" . $notification::class . "] uses the 'database' channel but does not implement toDatabase(). " .
                "Add: public function toDatabase(object \$notifiable): array { ... }"
            );
        }

        $data = $notification->toDatabase($notifiable);
        $notifiableId = $notifiable->id ?? null;
        $notifiableType = $notifiable::class;

        $this->connection->statement(
            'INSERT INTO nex_notifications (id, notifiable_type, notifiable_id, type, data, read_at, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)',
            [
                bin2hex(random_bytes(16)),
                $notifiableType,
                $notifiableId,
                $notification::class,
                json_encode($data, JSON_THROW_ON_ERROR),
                time(),
            ],
        );
    }

    /** Ensure the notifications table exists (idempotent). */
    public function createTable(): void
    {
        if ($this->connection === null) {
            return;
        }

        $this->connection->statement(<<<SQL
            CREATE TABLE IF NOT EXISTS nex_notifications (
                id               TEXT NOT NULL PRIMARY KEY,
                notifiable_type  TEXT NOT NULL,
                notifiable_id    TEXT,
                type             TEXT NOT NULL,
                data             TEXT NOT NULL,
                read_at          INTEGER,
                created_at       INTEGER NOT NULL
            )
        SQL);
    }
}
