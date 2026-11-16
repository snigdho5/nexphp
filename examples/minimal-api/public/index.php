<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Nex\Foundation\Application;

$app = Application::create(dirname(__DIR__));

$app->get('/', fn() => [
    'framework' => 'NexPHP',
    'version' => Application::VERSION,
    'description' => 'Less code. More power. Zero magic.',
    'status' => 'healthy',
]);

$app->get('/hello/{name}', function (string $name) {
    return [
        'greeting' => "Hello, $name! Welcome to NexPHP.",
        'timestamp' => time(),
    ];
});

$app->run();
