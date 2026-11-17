<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeListenerCommand extends Command
{
    protected string $name = 'make:listener';
    protected string $description = 'Create a new event listener class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Listener name is required. Usage: nex make:listener SendWelcomeEmailListener');
            return 1;
        }

        $dir = $this->app->basePath('app/Listeners');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Listener [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Listeners;

use Nex\Events\Contracts\ListenerInterface;

final class {$name} implements ListenerInterface
{
    public function handle(object \$event): void
    {
        // Handle the event
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created listener: app/Listeners/{$name}.php");
        return 0;
    }
}
