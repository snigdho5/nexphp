<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeNotificationCommand extends Command
{
    protected string $name = 'make:notification';
    protected string $description = 'Create a new Notification class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Notification name is required. Usage: nex make:notification WelcomeNotification');
            return 1;
        }

        $dir = $this->app->basePath('app/Notifications');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Notification [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Notifications;

use Nex\Mail\Mailable;
use Nex\Notifications\Notification;

final class {$name} extends Notification
{
    public function __construct(
        // Add notification data
    ) {}

    /**
     * @return list<string>
     */
    public function via(object \$notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object \$notifiable): Mailable
    {
        return (new Mailable())
            ->subject('Notification Subject')
            ->html('<p>Notification body</p>');
    }

    public function toDatabase(object \$notifiable): array
    {
        return [
            'message' => 'Notification message',
        ];
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created notification: app/Notifications/{$name}.php");
        return 0;
    }
}
