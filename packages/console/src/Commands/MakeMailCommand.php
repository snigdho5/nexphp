<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeMailCommand extends Command
{
    protected string $name = 'make:mail';
    protected string $description = 'Create a new Mailable class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Mail name is required. Usage: nex make:mail WelcomeMail');
            return 1;
        }

        $dir = $this->app->basePath('app/Mail');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Mail [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Mail;

use Nex\Mail\Mailable;

final class {$name} extends Mailable
{
    public function __construct(
        // Add constructor parameters
    ) {}

    public function build(): static
    {
        return \$this
            ->subject('Subject here')
            ->html('<p>HTML body here</p>')
            ->text('Text body here');
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created mail: app/Mail/{$name}.php");
        return 0;
    }
}
