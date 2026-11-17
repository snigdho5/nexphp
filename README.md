# NexPHP

> **Less code. More power. Zero magic.**  
> A next-generation PHP framework designed for modern runtimes (PHP-FPM & persistent workers), developer ergonomics, and AI "vibe coding".

---

## Architecture Overview

NexPHP is built with a hybrid architecture:
- **Small, stable core**: Contracts, Container, Config, HTTP, Routing, Middleware, and Foundation.
- **Dual-runtime readiness**: Neutral core designed for both PHP-FPM (classic) and persistent workers (modern).
- **Standards first**: Built on PSR-3, PSR-4, PSR-7, PSR-11, PSR-15, PSR-17, and Composer.
- **AI & Vibe-Coding Optimized**: Strict types, declarative attributes (`#[Route]`, `#[Middleware]`), self-describing DTOs, and self-healing error diagnostics.

## Monorepo Packages

### Phase 1: Core Foundation
| Package | Namespace | Purpose |
| :--- | :--- | :--- |
| `packages/contracts` | `Nex\Contracts` | Shared interfaces, PSR compliance, value contracts |
| `packages/container` | `Nex\Container` | PSR-11 autowiring container, scopes, circular dependency detection |
| `packages/config` | `Nex\Config` | Immutable config repository, environment adapters |
| `packages/http` | `Nex\Http` | PSR-7 request & response models, response factories, emitters |
| `packages/routing` | `Nex\Routing` | High-speed router, parameter constraints, attributes (`#[Route]`) |
| `packages/middleware` | `Nex\Middleware` | PSR-15 middleware pipeline and core middlewares |
| `packages/foundation` | `Nex\Foundation` | Application kernel, bootstrap, request-scope lifecycle |
| `packages/runtime-contracts` | `Nex\Runtime\Contracts` | Worker and request runner interfaces |
| `packages/runtime-fpm` | `Nex\Runtime\Fpm` | Standard PHP-FPM entry runner |
| `packages/console` | `Nex\Console` | Lightweight CLI commands (`serve`, `route:list`, `about`) |
| `packages/testing` | `Nex\Testing` | In-memory HTTP test client, test assertions |

### Phase 2: Application Essentials
| Package | Namespace | Purpose |
| :--- | :--- | :--- |
| `packages/database-contracts` | `Nex\Database\Contracts` | Connection, query builder, and manager interfaces |
| `packages/database` | `Nex\Database` | PDO connection manager, query builder, transactions, Active Record Model |
| `packages/database-migrations` | `Nex\Database\Migrations` | Schema builder (`Blueprint`), migration runner, rollback |
| `packages/validation` | `Nex\Validation` | Validation rules engine, `FormRequest` validation, 422 JSON errors |
| `packages/auth-contracts` | `Nex\Auth\Contracts` | Authenticatable, guard, and user provider contracts |
| `packages/auth` | `Nex\Auth` | Password hashing (Bcrypt), Database provider, Token guard, Authenticate middleware |
| `packages/authorization` | `Nex\Authorization` | Gate, policy resolution, abilities, Authorize middleware |

### Phase 3: Runtime, Concurrency & Performance
| Package | Namespace | Purpose |
| :--- | :--- | :--- |
| `packages/runtime-worker` | `Nex\Runtime\Worker` | Persistent worker runner, request provider, lifecycle supervision & auto-recycling |
| `packages/runtime-frankenphp` | `Nex\Runtime\FrankenPhp` | Native FrankenPHP worker mode adapter & capability discovery |
| `packages/concurrency-contracts` | `Nex\Concurrency\Contracts` | Task and Scheduler interfaces for cooperative concurrency |
| `packages/concurrency` | `Nex\Concurrency` | Fiber-based cooperative concurrency scheduler (`all`, `race`, `withTimeout`, `suspend`) |
| `packages/http-client-contracts` | `Nex\HttpClient\Contracts` | Async and sync HTTP client contracts |
| `packages/http-client` | `Nex\HttpClient` | Non-blocking HTTP client using cURL multi with Fiber suspension |
| `packages/worker-health` | `Nex\WorkerHealth` | Worker diagnostics, memory tracking, and uptime metrics |

### Phase 4: Application Services
| Package | Namespace | Purpose |
| :--- | :--- | :--- |
| `packages/event-contracts` | `Nex\Events\Contracts` | Event dispatcher, listener, and subscriber contracts |
| `packages/events` | `Nex\Events` | In-process synchronous event dispatcher with priority ordering |
| `packages/queue-contracts` | `Nex\Queue\Contracts` | Queue adapter and queued job interfaces |
| `packages/queue` | `Nex\Queue` | Database queue adapter, retry backoff, failed-job store, worker loop |
| `packages/scheduler` | `Nex\Scheduler` | Cron scheduler DSL, overlap prevention, clock abstraction |
| `packages/filesystem-contracts` | `Nex\Filesystem\Contracts` | File & object storage contracts |
| `packages/filesystem` | `Nex\Filesystem` | Local filesystem driver with path-traversal prevention & MIME detection |
| `packages/mail-contracts` | `Nex\Mail\Contracts` | Mail transport interface |
| `packages/mail` | `Nex\Mail` | Mailable message builder, SmtpMailer (raw socket), LogMailer (dev/test) |
| `packages/notifications` | `Nex\Notifications` | Multi-channel notifications (`mail`, `database`), Notifiable trait |

### Phase 5: Production Readiness, Security & Observability
| Package | Namespace | Purpose |
| :--- | :--- | :--- |
| `packages/security` | `Nex\Security` | CORS, CSRF verification, RateLimiter sliding window, Throttling, Request size caps |
| `packages/logging` | `Nex\Logging` | PSR-3 structured JSON logger, automatic sensitive data redaction, HTTP access logging |
| `packages/metrics` | `Nex\Metrics` | Counters, Gauges, Histograms, Prometheus text exposition exporter |
| `packages/health` | `Nex\Health` | Liveness & Readiness checks, Database, Storage, and Queue operational probes |
| `packages/resilience` | `Nex\Resilience` | Circuit Breaker (CLOSED/OPEN/HALF_OPEN), bounded exponential retries with jitter |

---

## CLI Commands

```bash
php bin/nex about                # System & framework info
php bin/nex route:list           # View registered routes
php bin/nex serve                # Start local dev server
php bin/nex worker:run           # Run in persistent worker mode
php bin/nex runtime:diagnostics  # Runtime capabilities & memory diagnostics
php bin/nex make:migration       # Create timestamped migration file
php bin/nex migrate              # Run pending migrations
php bin/nex migrate:rollback     # Rollback last migration batch
php bin/nex make:model           # Scaffold an Active Record Model
php bin/nex make:request         # Scaffold a FormRequest validator
php bin/nex make:policy          # Scaffold an Authorization Policy
php bin/nex queue:work           # Process jobs from the queue
php bin/nex queue:failed         # List failed jobs
php bin/nex queue:retry          # Retry a failed job by ID
php bin/nex schedule:run         # Run due scheduled tasks
php bin/nex make:job             # Scaffold a background Job class
php bin/nex make:event           # Scaffold an Event class
php bin/nex make:listener        # Scaffold an Event Listener class
php bin/nex make:mail            # Scaffold a Mailable class
php bin/nex make:notification    # Scaffold a Notification class
```

---

## Performance & Reliability Benchmarks

- **Application Boot Time:** ~2.8 ms
- **Throughput:** ~218,000 req/sec (in-memory persistent worker)
- **Fiber Concurrency:** ~119,000 tasks/sec cooperative task interleaving
- **Worker Soak Stability (`WorkerSoakTest.php`):** 10,000 consecutive worker requests in 0.058s (171,450 req/sec) with **0.00 MB memory growth** (stable 4.0 MB base)
- **Security Policy & Threat Model:** See [SECURITY.md](file:///Users/snigdho/dev/php-projects/nexphp/SECURITY.md)

---

## Getting Started

```bash
composer install
./vendor/bin/phpunit
```
