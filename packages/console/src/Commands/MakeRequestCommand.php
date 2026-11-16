<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeRequestCommand extends Command
{
    protected string $name = 'make:request';
    protected string $description = 'Create a new FormRequest validation class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Request name is required. Usage: nex make:request CreateUserRequest');
            return 1;
        }

        $dir = $this->app->basePath('app/Requests');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = "$dir/$name.php";
        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Requests;

use Nex\Validation\FormRequest;

class $name extends FormRequest
{
    public function rules(): array
    {
        return [
            // Define rules e.g. 'title' => 'required|string|max:100',
        ];
    }
}

PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created request: app/Requests/$name.php");

        return 0;
    }
}
