<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeJobCommand extends Command
{
    protected string $name = 'make:job';
    protected string $description = 'Create a new job class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Job name is required. Usage: nex make:job SendWelcomeEmail');
            return 1;
        }

        $dir = $this->app->basePath('app/Jobs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Job [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Jobs;

final class {$name}
{
    public function __construct(
        // Add your job constructor parameters here (serializable values only)
    ) {}

    public function handle(): void
    {
        // Job logic here
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created job: app/Jobs/{$name}.php");
        return 0;
    }
}
