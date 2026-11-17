<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Nex\Authorization\Gate;
use Nex\Database\Connection;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;
use Nex\Events\EventDispatcher;
use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Logging\StructuredLogger;
use Nex\Mail\LogMailer;
use Nex\Mail\Mailable;
use Nex\Notifications\Notifiable;
use Nex\Notifications\Notification;
use Nex\Notifications\NotificationSender;
use Nex\Queue\DatabaseQueue;
use Nex\Queue\QueueWorker;

echo "========================================================\n";
echo " NexPHP Reference Application: SaaS Business Workflow   \n";
echo "========================================================\n\n";

$app = Application::create(__DIR__);

// 1. Database setup
$pdo = new PDO('sqlite::memory:');
$db = new Connection($pdo, 'sqlite');
$schema = new Schema($db);

$schema->create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('role')->default('member');
    $table->timestamps();
});

$schema->create('invoices', function (Blueprint $table) {
    $table->id();
    $table->integer('user_id');
    $table->integer('amount_cents');
    $table->string('status')->default('pending');
    $table->timestamps();
});

$db->statement("INSERT INTO users (name, email, role, created_at, updated_at) VALUES ('Admin Jane', 'jane@saas.com', 'admin', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
$db->statement("INSERT INTO users (name, email, role, created_at, updated_at) VALUES ('Client Bob', 'bob@acme.com', 'member', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

// 2. Services: Logger, Events, Queue, Mail, Notifications, Authorization
$logger = new StructuredLogger();
$dispatcher = new EventDispatcher();
$queue = new DatabaseQueue($db);
$queue->createTables();
$mailer = new LogMailer();
$notificationSender = new NotificationSender(mailer: $mailer, connection: $db);
$notificationSender->createTable();

$gate = new Gate();
$gate->define('manage-invoices', fn (array $user) => ($user['role'] ?? '') === 'admin');

// 3. User Entity & Notification
class CustomerUser {
    use Notifiable;
    public function __construct(public int $id, public string $name, public string $email) {}
}

class InvoiceReadyNotification extends Notification {
    public function __construct(private int $amountCents) {}
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toMail(object $notifiable): Mailable {
        $amount = number_format($this->amountCents / 100, 2);
        return (new Mailable())
            ->to($notifiable->email, $notifiable->name)
            ->subject('Invoice Ready: $' . $amount)
            ->text("Hello {$notifiable->name}, your invoice for \${$amount} is ready.");
    }
    public function toDatabase(object $notifiable): array {
        return ['amount_cents' => $this->amountCents, 'status' => 'issued'];
    }
}

// 4. Background Job
class FinalizeInvoiceJob {
    public function __construct(public int $invoiceId) {}
    public function handle(): void {
        echo "  [Queue Worker] Finalizing invoice #{$this->invoiceId}...\n";
    }
}

// 5. Events & Listeners
class InvoiceCreatedEvent {
    public function __construct(public int $invoiceId, public int $userId, public int $amountCents) {}
}

$dispatcher->listen(InvoiceCreatedEvent::class, function (InvoiceCreatedEvent $event) use ($queue, $notificationSender, $db, $logger) {
    echo "  [Event] InvoiceCreatedEvent caught. Queueing finalization job...\n";
    $queue->push(new FinalizeInvoiceJob($event->invoiceId));

    $rows = $db->select('SELECT * FROM users WHERE id = ?', [$event->userId]);
    if (!empty($rows)) {
        $u = $rows[0];
        $customer = new CustomerUser((int)$u['id'], (string)$u['name'], (string)$u['email']);
        $customer->notify(new InvoiceReadyNotification($event->amountCents), $notificationSender);
        echo "  [Notification] Dispatched email & database notification to {$customer->email}.\n";
    }

    $logger->info('Invoice created and queued', [
        'invoice_id' => $event->invoiceId,
        'amount'     => $event->amountCents,
        'secret_key' => 'super_secret_token_123', // Will be automatically redacted!
    ]);
});

// 6. Simulate Business Request: Admin creates an invoice
$admin = $db->select("SELECT * FROM users WHERE email = 'jane@saas.com'")[0];
echo "Simulating request from: {$admin['name']} (Role: {$admin['role']})\n";

if (!$gate->forUser($admin)->allows('manage-invoices')) {
    echo "Access denied.\n";
    exit(1);
}

// Create invoice in DB
$db->statement(
    "INSERT INTO invoices (user_id, amount_cents, status, created_at, updated_at) VALUES (?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
    [2, 9900]
);
$invoiceId = 1;

// Dispatch event
$dispatcher->dispatch(new InvoiceCreatedEvent($invoiceId, 2, 9900));

// Process queue job
$worker = new QueueWorker($queue);
$processed = $worker->runOnce();
echo sprintf("  [Queue] Job processed: %s\n", $processed ? 'YES' : 'NO');

// Verify log redaction
$lastLog = json_decode($logger->getRecords()[0], true);
echo sprintf("  [Logging] Secret redaction check: %s\n", $lastLog['context']['secret_key']);

// Verify notification
$sentMails = $mailer->getSentMails();
echo sprintf("  [Mail] Sent %d email(s). Subject: '%s'\n", count($sentMails), $sentMails[0]->getSubject());

echo "\n[SUCCESS] Business SaaS workflow completed flawlessly.\n";
