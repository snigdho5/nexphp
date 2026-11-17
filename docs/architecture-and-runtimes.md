# Architecture & Dual-Runtime Guide

NexPHP is architected from the core for **Dual-Runtime Readiness**: a neutral framework core that runs with zero changes in both classic PHP-FPM environments and high-speed persistent workers.

---

## 1. The Dual-Runtime Model

```
                    ┌────────────────────────┐
                    │      HTTP Request      │
                    └───────────┬────────────┘
                                │
               ┌────────────────┴────────────────┐
               ▼                                 ▼
    ┌─────────────────────┐           ┌─────────────────────┐
    │   Classic PHP-FPM   │           │  Persistent Worker  │
    │  (Shared Nothing)   │           │ (FrankenPHP / CLI)  │
    └──────────┬──────────┘           └──────────┬──────────┘
               │                                 │
               └────────────────┬────────────────┘
                                ▼
                    ┌────────────────────────┐
                    │  Application::handle() │
                    └───────────┬────────────┘
                                ▼
                    ┌────────────────────────┐
                    │   Middleware Pipeline  │
                    └───────────┬────────────┘
                                ▼
                    ┌────────────────────────┐
                    │   Router & Action      │
                    └───────────┬────────────┘
                                ▼
                    ┌────────────────────────┐
                    │ Request-Scope Cleanup  │
                    │   $app->forgetScoped() │
                    └────────────────────────┘
```

---

## 2. Request-Scope Isolation

In persistent worker mode, PHP does **not** terminate the process between HTTP requests. Without proper isolation, user identity, database transaction states, or authentication tokens could leak across requests.

NexPHP solves this systematically:
- **`singleton()`**: Services shared across the worker's entire lifetime (Database connection pool, Config repository, Router, Logger).
- **`scoped()`**: Services bound strictly to the current HTTP request (Current User, `ServerRequestInterface`, CSRF token, FormRequests).
- **`forgetScoped()`**: Automatically executed by `Application::handle()` at the conclusion of every request, resetting all request-scoped bindings.

---

## 3. Cooperative Concurrency with Fibers

NexPHP leverages PHP 8.1+ Fibers for non-blocking cooperative concurrency:

```php
use Nex\Concurrency\Scheduler;
use Nex\HttpClient\HttpClient;

$scheduler = new Scheduler();
$http = new HttpClient();

// Runs concurrent HTTP requests in parallel using curl_multi with Fiber suspension
$results = $scheduler->all([
    'stripe' => fn () => $http->get('https://api.stripe.com/v1/charges'),
    'github' => fn () => $http->get('https://api.github.com/user'),
]);
```

- When an I/O operation begins, the current Fiber suspends (`Scheduler::suspend()`).
- The scheduler transfers control to other waiting tasks.
- Once cURL multi signals data readiness, the Fiber automatically resumes.
- Throughput exceeds **119,000 tasks/second** with zero external C extensions.
