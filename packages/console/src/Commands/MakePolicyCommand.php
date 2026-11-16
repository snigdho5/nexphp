<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakePolicyCommand extends Command
{
    protected string $name = 'make:policy';
    protected string $description = 'Create a new Authorization Policy class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Policy name is required. Usage: nex make:policy PostPolicy');
            return 1;
        }

        $dir = $this->app->basePath('app/Policies');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = "$dir/$name.php";
        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Policies;

use Nex\Auth\Contracts\AuthenticatableInterface;

class $name
{
    public function view(AuthenticatableInterface \$user, object \$model): bool
    {
        return true;
    }

    public function update(AuthenticatableInterface \$user, object \$model): bool
    {
        return \$user->getAuthIdentifier() === \$model->user_id;
    }

    public function delete(AuthenticatableInterface \$user, object \$model): bool
    {
        return \$user->getAuthIdentifier() === \$model->user_id;
    }
}

PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created policy: app/Policies/$name.php");

        return 0;
    }
}
