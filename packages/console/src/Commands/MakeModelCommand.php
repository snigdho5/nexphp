<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new Active Record Model class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Model name is required. Usage: nex make:model Post');
            return 1;
        }

        $dir = $this->app->basePath('app/Models');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = "$dir/$name.php";
        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Nex\Database\Model;

class $name extends Model
{
    protected array \$fillable = [];
}

PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created model: app/Models/$name.php");

        return 0;
    }
}
