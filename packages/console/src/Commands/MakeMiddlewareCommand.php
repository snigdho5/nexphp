<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeMiddlewareCommand extends Command
{
    protected string $name = 'make:middleware';
    protected string $description = 'Create a new PSR-15 Middleware class';

    public function execute(array $args = []): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->output->error('Middleware name is required. Usage: nex make:middleware EnsureApiKey');
            return 1;
        }

        $dir = $this->app->basePath('app/Middlewares');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $filePath = $dir . "/{$name}.php";
        if (file_exists($filePath)) {
            $this->output->error("Middleware [{$name}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Middlewares;

use Nex\Contracts\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class {$name} implements MiddlewareInterface
{
    public function process(ServerRequestInterface \$request, RequestHandlerInterface \$handler): ResponseInterface
    {
        // Pre-processing logic before handler
        \$response = \$handler->handle(\$request);

        // Post-processing logic on response
        return \$response;
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created middleware: app/Middlewares/{$name}.php");
        return 0;
    }
}
