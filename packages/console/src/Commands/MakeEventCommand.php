<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeEventCommand extends Command
{
    protected string $name = 'make:event';
    protected string $description = 'Create a new event class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Event name is required. Usage: nex make:event UserRegistered');
            return 1;
        }

        $dir = $this->app->basePath('app/Events');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Event [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Events;

final class {$name}
{
    public function __construct(
        // Add event data as readonly properties
    ) {}
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created event: app/Events/{$name}.php");
        return 0;
    }
}
