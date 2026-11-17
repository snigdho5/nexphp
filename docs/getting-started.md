# Getting Started with NexPHP

Welcome to NexPHP — the next-generation PHP framework engineered for modern runtimes, developer ergonomics, and AI "vibe coding".

---

## 1. Quickstart (Under 5 Minutes)

### Option A: Create a New Project with CLI
```bash
# Clone the repository
git clone git@github.com:snigdho5/nexphp.git
cd nexphp
composer install

# Scaffold your new application
php bin/nex new my-api
cd my-api
php bin/nex serve
```
Your server will start at `http://127.0.0.1:8000`.

---

## 2. Single-File Micro-Application

NexPHP lets you prototype an entire application in a single file without unnecessary boilerplate:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Http\ServerRequestFactory;
use Nex\Http\ResponseEmitter;

$app = Application::create(__DIR__);

// Define routes
$app->get('/', function () {
    return new JsonResponse([
        'framework' => 'NexPHP',
        'version'   => '1.0.0',
        'message'   => 'Less code. More power. Zero magic.',
    ]);
});

$app->get('/api/users/{id}', function (ServerRequest $request, array $params) {
    return new JsonResponse([
        'user_id' => (int) $params['id'],
        'role'    => 'engineer',
    ]);
});

// Run application
$request = ServerRequestFactory::fromGlobals();
$response = $app->handle($request);
(new ResponseEmitter())->emit($response);
```

---

## 3. Persistent Worker Mode

For high throughput (~200,000+ req/sec), run in persistent worker mode:

```bash
php bin/nex worker:run
```

NexPHP keeps the framework in memory and cleanly isolates request-scoped state between invocations to ensure zero memory leaks.
