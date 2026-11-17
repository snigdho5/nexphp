<?php

declare(strict_types=1);

namespace Nex\Tests\Notifications;

use Nex\Database\Connection;
use Nex\Mail\LogMailer;
use Nex\Mail\Mailable;
use Nex\Notifications\Notifiable;
use Nex\Notifications\Notification;
use Nex\Notifications\NotificationSender;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserEntity
{
    use Notifiable;

    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
    ) {}
}

final class OrderShippedNotification extends Notification
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $trackingUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return (new Mailable())
            ->to($notifiable->email, $notifiable->name)
            ->subject("Order #{$this->orderId} Shipped")
            ->html("<p>Track here: {$this->trackingUrl}</p>");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->orderId,
            'tracking_url' => $this->trackingUrl,
        ];
    }
}

final class NotificationTest extends TestCase
{
    private Connection $connection;
    private LogMailer $mailer;
    private NotificationSender $sender;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $this->connection = new Connection($pdo, 'sqlite');
        $this->mailer = new LogMailer();
        $this->sender = new NotificationSender(
            mailer: $this->mailer,
            connection: $this->connection,
        );
        $this->sender->createTable();
    }

    public function test_send_notification_via_mail_and_database(): void
    {
        $user = new UserEntity(1, 'customer@example.com', 'John Doe');
        $notification = new OrderShippedNotification('ORD-999', 'https://track.example.com/999');

        $user->notify($notification, $this->sender);

        // Check mail
        $sentMails = $this->mailer->getSentMails();
        $this->assertCount(1, $sentMails);
        $this->assertSame('Order #ORD-999 Shipped', $sentMails[0]->getSubject());
        $this->assertSame([['email' => 'customer@example.com', 'name' => 'John Doe']], $sentMails[0]->getTo());

        // Check database
        $rows = $this->connection->select('SELECT * FROM nex_notifications');
        $this->assertCount(1, $rows);
        $this->assertSame('1', (string) $rows[0]['notifiable_id']);
        $this->assertSame(UserEntity::class, $rows[0]['notifiable_type']);
        $this->assertSame(OrderShippedNotification::class, $rows[0]['type']);

        $payload = json_decode((string) $rows[0]['data'], true);
        $this->assertSame('ORD-999', $payload['order_id']);
        $this->assertSame('https://track.example.com/999', $payload['tracking_url']);
    }

    public function test_unknown_channel_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $badNotification = new class extends Notification {
            public function via(object $notifiable): array
            {
                return ['unsupported_channel'];
            }
        };

        $user = new UserEntity(2, 'test@example.com', 'Test');
        $this->sender->send($user, $badNotification);
    }
}
