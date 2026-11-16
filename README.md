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

## Monorepo Packages (Phase 1)

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

---

## Getting Started

```bash
composer install
./vendor/bin/phpunit
```
