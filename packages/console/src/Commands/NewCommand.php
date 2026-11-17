<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class NewCommand extends Command
{
    protected string $name = 'new';
    protected string $description = 'Create a new NexPHP application skeleton';

    public function execute(array $args = []): int
    {
        $projectName = $args[0] ?? null;
        if (!$projectName) {
            $this->output->error('Project name is required. Usage: nex new my-app');
            return 1;
        }

        $targetDir = getcwd() . DIRECTORY_SEPARATOR . $projectName;
        if (is_dir($targetDir)) {
            $this->output->error("Directory [{$projectName}] already exists.");
            return 1;
        }

        $this->output->info("Creating a new NexPHP application in [{$projectName}]...");

        // Create directory structure
        $dirs = [
            $targetDir . '/app/Controllers',
            $targetDir . '/app/Models',
            $targetDir . '/app/Middlewares',
            $targetDir . '/app/Jobs',
            $targetDir . '/app/Events',
            $targetDir . '/app/Listeners',
            $targetDir . '/config',
            $targetDir . '/database/migrations',
            $targetDir . '/public',
            $targetDir . '/routes',
            $targetDir . '/storage/logs',
            $targetDir . '/tests',
        ];

        foreach ($dirs as $dir) {
            mkdir($dir, 0755, recursive: true);
        }

        // 1. composer.json
        $composerJson = <<<JSON
{
    "name": "app/{$projectName}",
    "description": "NexPHP Application",
    "type": "project",
    "require": {
        "php": ">=8.3",
        "nexphp/nexphp": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\\\": "tests/"
        }
    }
}
JSON;
        file_put_contents($targetDir . '/composer.json', $composerJson);

        // 2. public/index.php
        $indexPhp = <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nex\Foundation\Application;
use Nex\Http\ServerRequestFactory;
use Nex\Http\ResponseEmitter;

$app = Application::create(dirname(__DIR__));

// Load routes
require_once dirname(__DIR__) . '/routes/api.php';

$request = ServerRequestFactory::fromGlobals();
$response = $app->handle($request);

(new ResponseEmitter())->emit($response);
PHP;
        file_put_contents($targetDir . '/public/index.php', $indexPhp);

        // 3. routes/api.php
        $routesPhp = <<<'PHP'
<?php

declare(strict_types=1);

use Nex\Http\Responses\JsonResponse;

/** @var Nex\Foundation\Application $app */

$app->get('/', function () {
    return new JsonResponse([
        'framework' => 'NexPHP',
        'status'    => 'running',
        'message'   => 'Less code. More power. Zero magic.',
    ]);
});

$app->get('/api/health', function () {
    return new JsonResponse([
        'status' => 'healthy',
        'time'   => time(),
    ]);
});
PHP;
        file_put_contents($targetDir . '/routes/api.php', $routesPhp);

        // 4. .env.example
        $envExample = <<<'ENV'
APP_NAME=NexPHP
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

QUEUE_CONNECTION=database
MAIL_MAILER=log
ENV;
        file_put_contents($targetDir . '/.env.example', $envExample);
        file_put_contents($targetDir . '/.env', $envExample);

        // 5. .gitignore
        $gitignore = <<<'GIT'
/vendor/
/storage/logs/*
!/storage/logs/.gitkeep
.env
database/*.sqlite
GIT;
        file_put_contents($targetDir . '/.gitignore', $gitignore);
        file_put_contents($targetDir . '/storage/logs/.gitkeep', '');

        // 6. phpunit.xml
        $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Application">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
        file_put_contents($targetDir . '/phpunit.xml', $phpunitXml);

        // 7. tests/ExampleTest.php
        $exampleTest = <<<'PHP'
<?php

declare(strict_types=1);

namespace Tests;

use Nex\Foundation\Application;
use Nex\Http\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function test_health_check_returns_ok(): void
    {
        $app = Application::create(dirname(__DIR__));
        require dirname(__DIR__) . '/routes/api.php';

        $request = new ServerRequest('GET', '/api/health');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('healthy', $data['status']);
    }
}
PHP;
        file_put_contents($targetDir . '/tests/ExampleTest.php', $exampleTest);

        $this->output->success("Application created in [{$projectName}].");
        $this->output->writeln();
        $this->output->writeln("Get started with:");
        $this->output->writeln("  cd {$projectName}");
        $this->output->writeln("  composer install");
        $this->output->writeln("  php bin/nex serve");
        $this->output->writeln();

        return 0;
    }
}
