<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new Controller class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Controller name is required. Usage: nex make:controller UserController');
            return 1;
        }

        $dir = $this->app->basePath('app/Controllers');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Controller [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use Nex\Http\Responses\JsonResponse;
use Nex\Routing\Attributes\Get;
use Nex\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class {$name}
{
    #[Get('/example')]
    public function index(ServerRequestInterface \$request): ResponseInterface
    {
        return new JsonResponse([
            'status' => 'ok',
            'data'   => [],
        ]);
    }

    #[Post('/example')]
    public function store(ServerRequestInterface \$request): ResponseInterface
    {
        return new JsonResponse([
            'status'  => 'created',
            'message' => 'Resource created successfully',
        ], 201);
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created controller: app/Controllers/{$name}.php");
        return 0;
    }
}
