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

---

## CLI Commands

```bash
php bin/nex about             # System & framework info
php bin/nex route:list        # View registered routes
php bin/nex serve             # Start local dev server
php bin/nex make:migration    # Create timestamped migration file
php bin/nex migrate           # Run pending migrations
php bin/nex migrate:rollback  # Rollback last migration batch
php bin/nex make:model        # Scaffold an Active Record Model
php bin/nex make:request      # Scaffold a FormRequest validator
php bin/nex make:policy       # Scaffold an Authorization Policy
```

---

## Getting Started

```bash
composer install
./vendor/bin/phpunit
```
