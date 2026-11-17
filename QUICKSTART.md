# NexPHP Quickstart Guide

This guide gets you up and running with a new **NexPHP** project in under 3 minutes.

---

## 1. Prerequisites

- **PHP 8.3** or higher (PHP 8.4 and 8.5 fully supported)
- **Composer 2.x**
- Extensions: `pdo_sqlite` (for default SQLite database), `curl`

---

## 2. Option A: Create a Full Application Skeleton (Recommended)

Generate a clean, production-ready application layout using the `nex new` command:

```bash
# 1. Clone NexPHP (or install via composer)
git clone git@github.com:snigdho5/nexphp.git
cd nexphp
composer install

# 2. Scaffold your new project
php bin/nex new my-app

# 3. Enter your project directory
cd my-app
composer install

# 4. Start local development server
php ../bin/nex serve
```

Your server starts immediately at `http://127.0.0.1:8000`.

### What `nex new` creates for you:

```text
my-app/
├── app/
│   ├── Controllers/     # Attribute-routed controllers (#[Get], #[Post])
│   ├── Models/          # Active Record models
│   ├── Middlewares/     # PSR-15 middleware classes
│   ├── Jobs/            # Queue background jobs
│   ├── Events/          # In-process events
│   └── Listeners/       # Event listeners
├── config/              # App configuration
├── database/migrations/ # Timestamped schema migrations
├── public/index.php     # Public web entrypoint
├── routes/api.php       # HTTP routes
├── storage/logs/        # Structured JSON logs
├── tests/               # PHPUnit test suite
├── .env.example         # Environment template
└── composer.json        # Autoload & dependencies
```

---

## 3. Option B: Single-File Micro-Application (Rapid Prototyping)

If you want to build a lightweight micro-service or prototype an API in a single file:

1. Create `composer.json`:
```json
{
    "require": {
        "php": ">=8.3",
        "nexphp/nexphp": "*"
    }
}
```

2. Create `index.php`:
```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Http\ServerRequestFactory;
use Nex\Http\ResponseEmitter;

// 1. Boot Application
$app = Application::create(__DIR__);

// 2. Define Routes
$app->get('/', function () {
    return new JsonResponse([
        'framework' => 'NexPHP',
        'status'    => 'running',
        'message'   => 'Less code. More power. Zero magic.'
    ]);
});

$app->get('/api/users/{id}', function (ServerRequest $request, array $params) {
    return new JsonResponse([
        'user_id' => (int) $params['id'],
        'status'  => 'active'
    ]);
});

// 3. Handle request and emit response
$request = ServerRequestFactory::fromGlobals();
$response = $app->handle($request);
(new ResponseEmitter())->emit($response);
```

3. Run with PHP's built-in server:
```bash
php -S 127.0.0.1:8000 index.php
```

---

## 4. Daily Workflow & Scaffolding Commands

Use `bin/nex` to scaffold application components with zero boilerplate:

### Code Generation
```bash
php bin/nex make:controller UserController    # Creates app/Controllers/UserController.php
php bin/nex make:model User                   # Creates app/Models/User.php
php bin/nex make:request CreateUserRequest    # Auto-validated FormRequest
php bin/nex make:migration create_users_table # Timestamped migration file
php bin/nex make:middleware EnsureApiKey      # PSR-15 Middleware
php bin/nex make:job SendWelcomeEmail         # Background Queue Job
php bin/nex make:event UserRegistered         # In-process Event
php bin/nex make:listener SendWelcomeNotice   # Event Listener
php bin/nex make:mail WelcomeMail             # Mailable class
php bin/nex make:notification NewOrderNotice  # Multi-channel Notification
```

### Database & Migrations
```bash
php bin/nex migrate                           # Run pending migrations
php bin/nex migrate:rollback                  # Rollback the last migration batch
```

### Queue & Background Workers
```bash
php bin/nex queue:work                        # Start queue worker daemon
php bin/nex queue:failed                      # View failed jobs table
php bin/nex queue:retry <job-id>              # Re-queue failed job
```

### Scheduler & Testing
```bash
php bin/nex schedule:run                      # Execute due scheduled tasks
php bin/nex test                              # Run test suite with PHPUnit
```

### High-Throughput Persistent Worker Mode (~200,000+ req/sec)
```bash
php bin/nex worker:run                        # Run persistent worker loop
php bin/nex runtime:diagnostics               # Memory & runtime diagnostics
```

---

## 5. Further Reading

- [AI Vibe Coding Guide](file:///Users/snigdho/dev/php-projects/nexphp/docs/ai-vibe-coding-guide.md) — How to prompt AI models (Cursor, Copilot, ChatGPT) for NexPHP.
- [Architecture & Dual Runtimes](file:///Users/snigdho/dev/php-projects/nexphp/docs/architecture-and-runtimes.md) — Persistent worker safety & Fibers.
- [CLI Reference](file:///Users/snigdho/dev/php-projects/nexphp/docs/cli-reference.md) — Guide to all 23 CLI commands.
- [Production & Security Guide](file:///Users/snigdho/dev/php-projects/nexphp/docs/security-and-production.md) — Deployment & security checklist.
