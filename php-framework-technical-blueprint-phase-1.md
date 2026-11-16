# Next-Generation PHP Framework
## Technical Blueprint — Hybrid Architecture, Maximum Compatibility, Minimal DX, Dual Runtime

**Status:** Architecture proposal  
**Decision profile:** Hybrid · Maximum practical compatibility · Minimal developer experience · PHP-FPM and persistent-worker runtimes  
**Working name:** Unnamed — choose the product name separately before publishing  
**Document purpose:** Define the technical direction, module boundaries, core contracts, package strategy, and Phase 1 delivery plan.

---

# 1. Executive summary

Build a modern PHP application framework with a small, stable core and a batteries-included set of optional first-party modules.

The framework should feel exceptionally simple for common tasks while remaining interoperable with the existing PHP ecosystem. Applications should run in both:

1. **Classic mode:** PHP-FPM and conventional request-per-process hosting.
2. **Modern mode:** persistent workers, with runtime-specific capabilities such as long-lived processes, event-loop integration, and WebSockets.

The framework must not require developers to adopt a proprietary package manager, rewrite Composer libraries, or use a custom PHP runtime.

## 1.1 Finalized product decisions

| Decision | Selected direction | Architectural consequence |
|---|---|---|
| Architecture | Hybrid | Small core plus optional full-stack modules |
| Compatibility | Maximum practical compatibility | Composer-first, PSR-friendly, adapters for framework-specific integrations |
| Developer experience | Minimal & AI-First ("Vibe Coding") | Convention over configuration, strict types, declarative attributes, generated boilerplate, self-healing errors |
| Runtime | Both | Runtime-neutral application core and separate runtime adapters |

## 1.2 Product principles

1. **Composer-first:** every package is a normal Composer package.
2. **PSR-friendly:** use PHP-FIG interfaces where they fit the problem.
3. **Small core:** routing, HTTP abstractions, container, middleware, configuration, and lifecycle belong in the foundation; ORM, auth, queues, and other capabilities are modules.
4. **Minimal syntax, explicit behavior:** concise APIs must not hide expensive database access, security decisions, or blocking I/O.
5. **AI and "vibe coding" optimized:** strict typing, declarative PHP 8.3+ attributes, self-describing DTOs, and clear single-responsibility patterns make code generation by AI assistants accurate, hallucination-free, and instantly testable.
6. **Runtime neutrality:** application code should not depend directly on PHP-FPM or FrankenPHP APIs.
7. **Secure defaults:** unsafe behavior should require explicit configuration.
8. **No magical async claims:** Fibers are a concurrency primitive, not automatic non-blocking I/O.
9. **Incremental adoption:** developers can use individual packages or the full application skeleton.
10. **Stable contracts:** public interfaces should be small, documented, and versioned carefully.
11. **Evidence-led performance:** publish reproducible benchmarks and compatibility tests.

---

# 2. Scope and non-goals

## 2.1 Initial target applications

- REST APIs and backend services
- Admin panels and internal business applications
- SaaS products
- E-commerce backends
- Traditional server-rendered applications
- Real-time applications when deployed on a compatible runtime
- Modular monoliths that may later be split into services

## 2.2 Explicit non-goals for Phase 1

Phase 1 will not attempt to deliver:

- A new PHP language or syntax
- A new PHP execution engine
- A custom package manager
- A proprietary ORM
- A complete async database ecosystem
- A GraphQL implementation
- A full admin-panel generator
- A microservices platform
- Automatic compatibility with Laravel- or Symfony-internal APIs
- A performance claim unsupported by benchmarks

The first milestone is a dependable HTTP application foundation.

---

# 3. Technical baseline and compatibility policy

## 3.1 PHP version

**Proposed initial baseline: PHP 8.3 or newer**, subject to a release-time support review.

Reasons:

- Avoid depending on features introduced only in the newest PHP release.
- Support a broad range of currently deployed environments.
- Allow modern typing, readonly properties, enums, attributes, and Fibers.
- Keep the framework compatible with the PHP 8.3–8.5 generation during initial development, provided dependencies support those versions.

Before the first stable release, re-evaluate the minimum version against PHP's official support calendar. Do not promise support for an end-of-life PHP version merely to maximize theoretical compatibility.

Use CI against every PHP version in the declared support matrix.

## 3.2 Composer policy

- Composer 2 is the supported package manager.
- Use PSR-4 autoloading.
- Use semantic versioning for public packages.
- Keep runtime dependencies low.
- Avoid requiring optional modules from the core package.
- Support Composer plugins only when necessary; do not require a custom installer.
- Document required PHP extensions and optional extensions separately.

## 3.3 PHP-FIG standards

Adopt standards where they improve interoperability:

| Standard | Use |
|---|---|
| PSR-3 | Logging contracts |
| PSR-4 | Autoloading |
| PSR-7 | HTTP message interoperability at adapter boundaries |
| PSR-11 | Container interoperability |
| PSR-15 | Middleware and request handlers |
| PSR-16 | Simple cache interoperability |
| PSR-17 | HTTP message factories |
| PSR-18 | HTTP client interoperability |
| PSR-20 | Clock abstraction |

Do not force every internal class to implement every PSR. Use adapters where the framework's internal model is intentionally different.

## 3.4 Compatibility tiers

**Tier 1 — Native compatibility**
- Composer packages that use ordinary PHP classes and supported extensions.
- PSR-compatible logging, cache, HTTP, and container integrations.
- Standard PDO drivers and PHP extensions.

**Tier 2 — Adapter compatibility**
- Libraries that expect a particular PSR implementation.
- Third-party routers, HTTP clients, cache stores, and loggers.
- Framework-independent packages that need a small bridge.

**Tier 3 — Framework-specific compatibility**
- Laravel service providers, Eloquent-specific packages, Symfony bundles, or packages coupled to framework internals.
- These require dedicated adapters and are not guaranteed by the core compatibility promise.

The project should publish a compatibility guide and test representative popular packages. Never claim that every Laravel or Symfony package works unchanged.

---

# 4. High-level architecture

The architecture has five logical layers.

```text
┌────────────────────────────────────────────────────────────┐
│                    Developer Experience                    │
│ CLI · Route API · Controllers · Attributes · Validation    │
├────────────────────────────────────────────────────────────┤
│                    Application Modules                     │
│ Database · Auth · Cache · Queue · Events · Mail · Storage   │
├────────────────────────────────────────────────────────────┤
│                        Framework Core                      │
│ Container · HTTP · Routing · Middleware · Config · Errors  │
├────────────────────────────────────────────────────────────┤
│                    Runtime Abstraction                     │
│ Request Runner · Worker Lifecycle · Server Adapters         │
├────────────────────────────────────────────────────────────┤
│                     PHP Execution Host                     │
│ PHP-FPM · FrankenPHP · CLI · Test Runtime                   │
└────────────────────────────────────────────────────────────┘
```

## 4.1 Dependency direction

Dependencies point inward toward stable contracts.

- Application modules may depend on core contracts.
- Core must not depend on ORM, authentication, queues, or a specific runtime.
- Runtime adapters implement runtime contracts and call the application kernel.
- Integrations adapt third-party packages to framework contracts.
- The application layer composes modules; it should not need to know internal runtime details.

## 4.2 Core versus modules

**Core must contain:**
- Application bootstrap and lifecycle
- Dependency injection contract and default container
- HTTP request/response abstractions
- Router
- Middleware pipeline
- Configuration
- Exception handling
- Event and clock contracts where needed
- Runtime-neutral kernel

**Optional first-party modules:**
- Database and ORM
- Authentication and authorization
- Cache
- Queue and scheduler
- Events and broadcasting
- Mail and notifications
- Filesystem and storage
- Validation
- Serialization
- OpenAPI
- WebSockets
- Observability
- Testing utilities

Validation may be included in the standard application skeleton, but it should remain a separately versioned package so minimal applications can omit it.

---

# 5. Framework module structure

Use a monorepo during initial development to coordinate contracts and integration tests. Publish each package independently to Packagist when it is stable.

## 5.1 Proposed repository tree

```text
framework/
├── packages/
│   ├── contracts/
│   ├── container/
│   ├── config/
│   ├── http/
│   ├── routing/
│   ├── middleware/
│   ├── foundation/
│   ├── console/
│   ├── runtime-contracts/
│   ├── runtime-fpm/
│   ├── runtime-frankenphp/
│   ├── validation/
│   ├── database/
│   ├── auth/
│   ├── cache/
│   ├── events/
│   ├── queue/
│   ├── filesystem/
│   ├── mail/
│   ├── observability/
│   └── testing/
├── skeleton/
│   ├── app/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Requests/
│   │   ├── Providers/
│   │   └── Services/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── routes/
│   ├── storage/
│   ├── tests/
│   ├── composer.json
│   └── artisan
├── examples/
│   ├── minimal-api/
│   └── classic-crud/
├── docs/
├── benchmarks/
├── tools/
├── .github/
│   └── workflows/
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── rector.php
├── infection.json
├── LICENSE
└── README.md
```

The exact package count can change. Do not create empty packages just to match the diagram. Extract a module when it has a stable contract and a real independent use case.

## 5.2 Package responsibility matrix

| Package | Responsibility | Depends on |
|---|---|---|
| `contracts` | Shared interfaces and value contracts | PHP only |
| `container` | Dependency injection and resolution | contracts |
| `config` | Configuration repository and environment loading | contracts |
| `http` | Request, response, uploaded file, stream and emitter abstractions | contracts, PSR adapters |
| `routing` | Route definitions, matching, parameters, route collection | contracts, http |
| `middleware` | Middleware pipeline and dispatch | contracts, http |
| `foundation` | Application builder, kernel, providers, lifecycle | core packages |
| `console` | CLI application and command contracts | contracts, foundation |
| `runtime-contracts` | Runtime and worker lifecycle interfaces | contracts, http |
| `runtime-fpm` | Conventional PHP-FPM entry point and response emission | runtime-contracts |
| `runtime-frankenphp` | FrankenPHP integration and worker-mode adapter | runtime-contracts |
| `validation` | Input validation and DTO mapping | contracts, http |
| `database` | Database manager, query builder, migrations, ORM integration | contracts |
| `auth` | Authentication guards, identities, authorization | contracts, http |
| `cache` | Cache abstraction and adapters | contracts, PSR-16 bridge |
| `events` | Event dispatcher and listeners | contracts |
| `queue` | Queue contracts, workers, retry and failure handling | contracts, events |
| `filesystem` | Storage abstraction and adapters | contracts |
| `mail` | Mail transport and message API | contracts |
| `observability` | Logging, metrics, tracing integrations | contracts, PSR-3 |
| `testing` | Test kernel, HTTP test client, fakes and assertions | foundation, http |

## 5.3 Package naming

Choose a vendor namespace and package prefix only after a name and trademark review.

For examples below, `Acme` is a placeholder:

- `acme/contracts`
- `acme/container`
- `acme/http`
- `acme/routing`
- `acme/foundation`
- `acme/console`

Use a consistent PHP namespace such as `Acme\Framework\Routing`.

---

# 6. Core contracts and public APIs

The contracts package is the foundation of interoperability. Interfaces should be small, stable, and free of runtime-specific details.

The snippets below are proposed PHP API designs. They are not implemented code and should be validated with tests and static analysis before being treated as final.

## 6.1 Application kernel

The kernel accepts a request and returns a response. It does not send the response directly; emission belongs to the runtime adapter.

```php
namespace Acme\Framework\Contracts\Http;

interface RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

The framework may use PSR-7 request and response types internally or its own lightweight types with PSR-7 adapters. Decide this once, document the trade-off, and avoid maintaining two competing HTTP object models.

Recommended initial choice: use PSR-7 at interoperability boundaries and provide a first-party implementation or adapter. Avoid unnecessary conversion copies on the hot path.

## 6.2 Middleware contracts

Prefer PSR-15-compatible middleware:

```php
namespace Acme\Framework\Contracts\Http;

interface MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}
```

Middleware responsibilities include authentication, request IDs, rate limits, CORS, security headers, and exception mapping.

Middleware ordering must be deterministic and documented.

## 6.3 Router contracts

```php
namespace Acme\Framework\Contracts\Routing;

interface RouterInterface
{
    public function get(string $path, mixed $handler): RouteDefinitionInterface;
    public function post(string $path, mixed $handler): RouteDefinitionInterface;
    public function put(string $path, mixed $handler): RouteDefinitionInterface;
    public function patch(string $path, mixed $handler): RouteDefinitionInterface;
    public function delete(string $path, mixed $handler): RouteDefinitionInterface;

    public function match(
        string $method,
        string $path
    ): RouteMatchInterface;
}
```

The concrete API may also expose `map()` for multiple methods and route groups. Route definitions should support names, middleware, constraints, prefixes, and controller actions.

Do not use `mixed` as a permanent public contract if a stable callable type or handler interface can be defined without making the API awkward.

## 6.4 Dependency injection

Use PSR-11 compatibility:

```php
namespace Acme\Framework\Contracts\Container;

use Psr\Container\ContainerInterface;

interface ContainerBuilderInterface extends ContainerInterface
{
    public function bind(string $id, mixed $concrete): void;

    public function singleton(string $id, mixed $concrete): void;

    public function instance(string $id, object $instance): void;

    public function has(string $id): bool;
}
```

The exact interface should avoid extending PSR-11 with methods that conflict with its signatures. A cleaner implementation may expose a separate framework-specific registration interface and have the runtime container implement PSR-11's `ContainerInterface`.

Resolution rules:
- Constructor injection is the default.
- Scalar parameters require explicit configuration or attributes.
- Circular dependencies produce clear errors.
- Container scopes distinguish application singletons from request-scoped services.
- In persistent-worker mode, request-scoped instances must be discarded after every request.

## 6.5 Configuration

```php
namespace Acme\Framework\Contracts\Config;

interface ConfigRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value): void;

    public function all(): array;
}
```

Configuration is loaded at bootstrap. Environment variables should be read and validated at the boundary, not scattered throughout application code.

## 6.6 Clock

```php
namespace Acme\Framework\Contracts\Time;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

Provide a system clock and a frozen clock for tests. This supports deterministic scheduling, token expiry, and time-sensitive business logic.

## 6.7 Logging

Use PSR-3 `LoggerInterface`. Provide a null logger for minimal installations and adapters for common logging libraries.

## 6.8 Events

```php
namespace Acme\Framework\Contracts\Events;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
}
```

Synchronous in-process events belong in the core event module. Durable asynchronous delivery belongs in the queue/broadcasting modules and must not be implied by this interface alone.

## 6.9 Runtime contracts

```php
namespace Acme\Framework\Contracts\Runtime;

interface RuntimeInterface
{
    public function run(
        RequestHandlerInterface $handler
    ): void;
}
```

Worker lifecycle should be separate:

```php
interface WorkerLifecycleInterface
{
    public function boot(): void;

    public function beforeRequest(): void;

    public function afterRequest(): void;

    public function shutdown(): void;
}
```

The final API may use more focused interfaces for server startup, response emission, worker lifecycle, and capability discovery. Avoid one giant runtime interface.

## 6.10 Validation

Validation should distinguish:
- Raw input
- Validated data
- Validation errors
- DTO construction

Never treat unvalidated request input as a trusted typed object. The validation package should expose a stable contract that can be implemented by different validators.

---

# 7. Developer experience and application API

The user selected **minimal developer experience**. This means concise conventions and generated structure, not hidden side effects.

## 7.1 Target application bootstrap

Illustrative target:

```php
use Acme\Framework\Foundation\Application;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRoutes(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
    )
    ->create();

return $app;
```

The actual bootstrap should be small, deterministic, and inspectable.

## 7.2 Target route syntax

```php
use Acme\Framework\Http\Request;
use Acme\Framework\Http\Response;

$app->get('/health', function () {
    return ['status' => 'ok'];
});

$app->get('/users/{id}', [UserController::class, 'show']);

$app->post('/users', [UserController::class, 'store'])
    ->middleware('auth')
    ->name('users.store');
```

A handler may return:
- A response object
- A supported scalar or array that the response factory can serialize
- A framework-supported view response

The framework should reject unsupported return values with a useful exception.

## 7.3 Automatic model binding

A future ergonomic API may allow:

```php
$app->get('/users/{user}', function (User $user) {
    return $user;
});
```

This requires explicit model-binding registration or a documented convention. It must:
- Scope queries safely.
- Respect tenant boundaries.
- Return a consistent 404.
- Avoid accidental N+1 queries.
- Never infer authorization from successful model resolution.

Do not include automatic model binding in Phase 1 unless it can be implemented and tested without compromising clarity.

## 7.4 Validation API

Illustrative future API:

```php
final class CreateUserRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
        ];
    }
}
```

The framework can resolve the request object, validate input, and expose validated data. Validation errors should produce a consistent JSON response for API requests and a suitable response for browser requests.

## 7.5 CLI

The application should have a predictable command name, provisionally `framework` or `app`.

Initial commands:
- `serve`
- `make:controller`
- `make:middleware`
- `make:provider`
- `route:list`
- `about`
- `test`

Database commands such as `make:migration` and `migrate` belong to the database module and should be added later.

---

# 8. Dual-runtime design

## 8.1 Runtime-neutral kernel

The application kernel should:
1. Receive a request.
2. Create or enter the request scope.
3. Resolve the route.
4. Execute middleware and the route handler.
5. Convert exceptions into responses.
6. Return a response.
7. Release request-scoped services and resources.

It should not:
- Read directly from PHP superglobals throughout the application.
- Echo response content.
- Exit the process.
- Start its own server.
- Assume one request per process.
- Depend on a specific worker implementation.

## 8.2 PHP-FPM runtime

Responsibilities:
- Build a request from PHP globals.
- Invoke the kernel.
- Emit status, headers, and body.
- Respect output buffering and headers-sent conditions.
- Avoid leaking state between requests.
- Provide a conventional public entry point.

## 8.3 FrankenPHP runtime

Responsibilities:
- Integrate with FrankenPHP's supported worker model.
- Reuse the application kernel across requests.
- Invoke lifecycle hooks.
- Ensure request scope cleanup.
- Support worker recycling and graceful shutdown.
- Expose runtime capabilities without coupling application code to FrankenPHP.

FrankenPHP should be an optional runtime package, not a mandatory dependency of the framework core.

## 8.4 Persistent-worker safety

Persistent workers are a major source of subtle bugs. The framework must define:

- **Application scope:** safe long-lived immutable services and shared pools.
- **Request scope:** request, authenticated identity, request context, mutable per-request services.
- **Transient scope:** new instance per resolution where appropriate.

At the end of each request:
- Clear request-scoped container entries.
- Clear request-local context and correlation IDs.
- Reset mutable state in framework-managed services.
- Close or release request-bound resources.
- Ensure exceptions cannot skip cleanup.

Tests must run multiple requests through the same worker instance to detect state leakage.

## 8.5 Async strategy

Do not design a fake `async`/`await` syntax that PHP cannot parse.

Initial strategy:
- Define task and scheduler abstractions.
- Use Fibers as a cooperative execution mechanism.
- Integrate with a mature event-loop library through an adapter.
- Provide non-blocking adapters for supported I/O clients.
- Mark blocking operations clearly.
- Allow blocking libraries to run in separate worker processes.

Async database support should be added only when reliable drivers and cancellation, timeout, and connection lifecycle behavior are available.

---

# 9. Security architecture

Security is a framework-wide responsibility.

## 9.1 Default protections

- Parameterized database operations.
- Output escaping in any first-party templating module.
- CSRF protection for browser-session state-changing routes.
- Secure cookie defaults.
- Strict request validation.
- Safe file-upload validation and storage names.
- Rate-limiting hooks.
- Security headers middleware.
- Password hashing through PHP's password API.
- Constant-time comparison where appropriate.
- Safe exception responses that do not expose stack traces in production.

## 9.2 Authentication and authorization

Keep authentication separate from authorization.

Authentication module responsibilities:
- Guards and authenticators
- Identity providers
- Session and token adapters
- Password verification
- Authentication events

Authorization module responsibilities:
- Policies
- Abilities
- Role and permission adapters
- Resource-level checks

Never assume that authentication means a user is authorized to access a resource.

## 9.3 Security engineering process

- Threat model the HTTP kernel, session handling, file uploads, and persistent workers.
- Add security regression tests.
- Run dependency audits in CI.
- Publish a security policy and vulnerability reporting process.
- Review cryptographic decisions rather than inventing algorithms.
- Keep production error output safe by default.

---

# 10. Testing, quality, and release engineering

## 10.1 Testing layers

1. Unit tests for individual packages.
2. Contract tests for interfaces and adapters.
3. Integration tests for the full HTTP kernel.
4. Runtime tests for PHP-FPM and persistent workers.
5. Compatibility tests for supported PHP versions.
6. Security regression tests.
7. Performance tests with published workloads.

## 10.2 Tooling proposal

- PHPUnit for tests
- PHPStan or Psalm for static analysis
- PHP-CS-Fixer or PHP_CodeSniffer for coding standards
- Composer audit for dependency advisories
- Infection for mutation testing on critical packages
- Rector for controlled code modernization
- GitHub Actions or equivalent CI

Select one primary static-analysis tool and one primary style tool for the repository to avoid duplicated configuration.

## 10.3 Quality gates

Every pull request should pass:
- Formatting and style checks
- Static analysis
- Unit tests
- Integration tests
- Supported PHP version matrix
- Dependency audit
- Package dependency-boundary checks

No public API should be merged without tests and documentation.

---

# 11. Phase 1 implementation plan

## 11.1 Phase 1 objective

Deliver a small but complete HTTP application foundation that can:
- Be installed with Composer.
- Boot from a project skeleton.
- Register routes.
- Resolve dependencies.
- Execute middleware.
- Produce predictable HTTP responses.
- Handle errors safely.
- Run under PHP-FPM.
- Be tested without a real web server.

Phase 1 does not include ORM, authentication, async scheduling, queues, or WebSockets.

## 11.2 Phase 1 package scope

Build only these packages initially:

1. `contracts`
2. `container`
3. `config`
4. `http`
5. `routing`
6. `middleware`
7. `foundation`
8. `runtime-contracts`
9. `runtime-fpm`
10. `console` (minimal)
11. `testing` (minimal)

Do not create the remaining package directories until their implementation begins.

## 11.3 Work breakdown structure

### Milestone 0 — Repository and engineering standards

Tasks:
- Create monorepo.
- Decide namespace and package naming.
- Configure Composer workspaces/path repositories.
- Add coding standard and static analysis.
- Add PHPUnit.
- Add CI for supported PHP versions.
- Add contribution guide and architecture decision record template.
- Define semantic versioning and release policy.

Acceptance criteria:
- Clean checkout installs dependencies.
- CI runs on pull requests.
- A sample package can be tested independently.

### Milestone 1 — Contracts and container

Tasks:
- Define core interfaces.
- Implement PSR-11-compatible container.
- Support constructor injection.
- Support bindings, singletons, and instances.
- Detect circular dependencies.
- Handle scalar parameters explicitly.
- Define application and request scopes.
- Add container exception types.

Acceptance criteria:
- Constructor injection works.
- Interface-to-implementation bindings work.
- Circular dependency errors are readable.
- Request-scoped services can be cleared.
- PSR-11 interoperability tests pass.

### Milestone 2 — Configuration

Tasks:
- Implement configuration repository.
- Load PHP configuration files.
- Add environment variable adapter.
- Validate required configuration at bootstrap.
- Define configuration precedence.
- Add immutable/frozen configuration option after boot.

Acceptance criteria:
- Environment-specific configuration works.
- Missing required configuration produces a useful error.
- Application code uses the repository rather than reading environment variables directly.

### Milestone 3 — HTTP foundation

Tasks:
- Select the internal HTTP model and PSR-7 interoperability approach.
- Implement request creation from globals.
- Implement response factories.
- Implement JSON, text, redirect, and empty responses.
- Implement response emission.
- Handle headers and status codes.
- Add uploaded-file handling boundaries.

Acceptance criteria:
- Request and response tests pass.
- JSON responses have correct content type and status.
- Response emission works under PHP-FPM.
- Application handlers do not echo or exit.

### Milestone 4 — Routing

Tasks:
- Implement HTTP method and path matching.
- Support route parameters.
- Support named routes.
- Support route groups and prefixes.
- Support route-level middleware metadata.
- Support 404 and 405 behavior.
- Add route listing output.

Acceptance criteria:
- Static and parameterized routes match correctly.
- Method mismatch returns 405 where appropriate.
- Unknown route returns 404.
- Route order and conflict behavior are documented.
- Route matching has benchmark coverage.

### Milestone 5 — Middleware pipeline

Tasks:
- Implement PSR-15-compatible middleware dispatch.
- Add global and route-specific middleware.
- Define middleware ordering.
- Add exception-handling middleware.
- Add request ID middleware.
- Add security headers middleware.

Acceptance criteria:
- Middleware executes in documented order.
- Middleware can short-circuit a request.
- Exceptions become safe responses.
- Request IDs appear in logs and response headers when enabled.

### Milestone 6 — Application foundation and bootstrap

Tasks:
- Implement application builder.
- Register providers.
- Compose router, container, config, and middleware.
- Implement runtime-neutral request handler.
- Implement request-scope cleanup.
- Add environment-based error display policy.
- Create project skeleton.

Acceptance criteria:
- A new project can boot from the skeleton.
- A route can return JSON.
- Dependency injection works in route handlers.
- Exceptions are converted to consistent responses.
- Two requests handled by the same kernel do not share request state.

### Milestone 7 — PHP-FPM runtime and CLI

Tasks:
- Add public front controller.
- Build request from PHP globals.
- Emit response.
- Add minimal `serve` development command if appropriate.
- Add `route:list` and `about`.
- Document PHP-FPM deployment.

Acceptance criteria:
- The example application runs under PHP-FPM.
- CLI commands work.
- No framework code depends on FrankenPHP.
- A conventional deployment guide is complete.

### Milestone 8 — Testing utilities and Phase 1 release candidate

Tasks:
- Add HTTP test client.
- Add fake clock and test container helpers as appropriate.
- Add kernel integration tests.
- Add package dependency checks.
- Add security regression tests.
- Add quick-start documentation.
- Run a review of public API stability.

Acceptance criteria:
- CRUD-style in-memory example can be tested end to end.
- CI passes across the declared PHP matrix.
- Documentation covers install, bootstrap, routing, middleware, DI, errors, and deployment.
- No unresolved critical security issues.
- Phase 1 release candidate is tagged.

## 11.4 Suggested implementation order

```text
Repository and CI
       |
       v
Contracts
       |
       v
Container ---- Configuration
       |             |
       +------v------+
              |
             HTTP
              |
           Routing
              |
          Middleware
              |
          Foundation
              |
       Runtime Contracts
              |
          PHP-FPM
              |
       CLI + Test Tools
              |
         Phase 1 RC
```

## 11.5 Phase 1 sample application

The first example should be intentionally small.

```php
use Acme\Framework\Foundation\Application;

$app = Application::configure(dirname(__DIR__))
    ->withRoutes(__DIR__ . '/../routes/api.php')
    ->create();

return $app;
```

`routes/api.php`:

```php
$app->get('/health', function () {
    return [
        'status' => 'ok',
        'service' => 'example-api',
    ];
});
```

Expected behavior:

```http
GET /health

HTTP/1.1 200 OK
Content-Type: application/json

{
  "status": "ok",
  "service": "example-api"
}
```

This example is a target API. The exact bootstrap and route registration mechanics should be settled by the Phase 1 implementation and tests.

---

# 12. Phase 1 definition of done

Phase 1 is complete only when all of the following are true:

- [ ] A developer can create a project from the skeleton.
- [ ] Composer installs the framework packages.
- [ ] The application boots without a framework-specific server.
- [ ] Routes support common HTTP methods and path parameters.
- [ ] Middleware is composable and ordered.
- [ ] Constructor dependency injection works.
- [ ] Configuration is centralized.
- [ ] Request and response handling is testable.
- [ ] Errors return safe, consistent responses.
- [ ] PHP-FPM deployment works.
- [ ] Request-scoped services are cleaned up.
- [ ] A multi-request kernel test detects state leakage.
- [ ] CI passes on all declared PHP versions.
- [ ] Public APIs have tests and documentation.
- [ ] A minimal example API works end to end.

---

# 13. Risks and architectural decisions to record

Create an Architecture Decision Record (ADR) for each of these:

1. Internal HTTP model: PSR-7 directly or framework-native types with adapters.
2. Container: build a small container or adopt an existing maintained implementation.
3. Router: build a focused router or adopt an existing compatible package.
4. Monorepo tooling: Composer path repositories or a dedicated monorepo tool.
5. Minimum PHP version and support window.
6. Runtime adapter contract and response emission.
7. Request-scope cleanup model.
8. Attribute/decorator strategy.
9. Async event-loop dependency.
10. Package release and backward-compatibility policy.

For maximum ecosystem compatibility, prefer adopting proven components when they meet requirements. The framework's value should come from coherent integration, developer experience, lifecycle safety, and excellent defaults—not from rewriting every low-level component.

---

# 14. Recommended next steps

1. Select and verify the framework name, namespace, and Composer vendor.
2. Create ADRs for the HTTP model, container, router, and minimum PHP version.
3. Establish the monorepo and CI.
4. Implement contracts before convenience APIs.
5. Build a working PHP-FPM vertical slice.
6. Validate the skeleton with a small real application.
7. Only then expand into database, validation, authentication, and modern runtime work.

## Final positioning

This framework should not market itself as Node.js or Python rewritten in PHP.

It should be a modern PHP framework with:
- Python-like approachability
- Node-inspired event-driven capabilities where the runtime supports them
- Strong modular architecture
- Composer and PSR interoperability
- Secure defaults
- A minimal developer experience
- Deployment flexibility

**The product promise: Build modern PHP applications with less boilerplate, fewer integration headaches, and a clear path from a simple API to a scalable production system.**
