<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeProviderCommand extends Command
{
    protected string $name = 'make:provider';
    protected string $description = 'Create a new Service Provider class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Provider name is required. Usage: nex make:provider AppServiceProvider');
            return 1;
        }

        $dir = $this->app->basePath('app/Providers');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Provider [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Providers;

use Nex\Foundation\Application;

final class {$name}
{
    public function register(Application \$app): void
    {
        // Bind interfaces and singletons into DI container
    }

    public function boot(Application \$app): void
    {
        // Bootstrap application services
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created provider: app/Providers/{$name}.php");
        return 0;
    }
}
