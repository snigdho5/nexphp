# A Next-Generation PHP Framework

## Vision, Product Direction, Architecture, and Development Roadmap

## 1. Executive vision

Create a modern PHP framework that makes PHP a compelling choice
alongside Node.js, Python, Go, and other backend platforms.

The goal is not to imitate those languages or replace PHP itself. The
goal is to combine proven ideas from their ecosystems into a coherent
PHP framework that is:

-   Safe by default
-   Scalable and performant
-   Easy and enjoyable to develop with (exceptional human DX and AI "vibe coding" friendly)
-   Modular, with a lightweight core
-   Compatible with existing PHP libraries and Composer packages
-   Deployable on both traditional hosting and modern cloud
    infrastructure

**Product philosophy:** Less code. More power. Zero magic.

The framework should make common application development simple while
still exposing the controls that experienced engineers need.

## 2. Product principles

1.  **Developer experience first:** sensible defaults, clear
    conventions, helpful errors, and minimal boilerplate.
2.  **AI & "vibe coding" friendly:** strictly typed signatures, declarative
    attributes, self-describing DTOs, and deterministic conventions make it
    effortless for AI coding assistants and developers to prompt, generate,
    and self-heal working code without hallucinating magic methods or hidden configs.
3.  **Security by default:** secure defaults should be built into the
    framework rather than left entirely to individual developers.
4.  **Interoperability:** use Composer and PHP-FIG standards wherever
    practical.
5.  **Incremental adoption:** developers should be able to adopt the
    framework without rewriting every existing PHP library or
    application.
6.  **Progressive complexity:** simple projects should remain simple;
    advanced capabilities should be available as applications grow.
7.  **Runtime flexibility:** support traditional PHP-FPM deployments as
    well as persistent-worker runtimes.
8.  **Measured performance:** publish reproducible benchmarks rather
    than making unverified speed claims.

## 3. What to learn from other ecosystems

  -----------------------------------------------------------------------
  Ecosystem / project                 Ideas worth adopting
  ----------------------------------- -----------------------------------
  Node.js                             Event-driven I/O, async workflows,
                                      WebSockets

  NestJS                              Dependency injection, modules,
                                      decorators/attributes, application
                                      structure

  FastAPI                             Type-driven validation and
                                      automatic API documentation

  Django                              Batteries-included options, ORM,
                                      administration patterns

  Fastify                             Lightweight request handling and
                                      schema-based validation

  Laravel                             Productive conventions, migrations,
                                      queues, approachable APIs

  Go                                  Simplicity, predictable resource
                                      use, concurrency concepts
  -----------------------------------------------------------------------

These ideas should be adapted to PHP's runtime and language model, not
copied blindly.

## 4. Proposed feature set

### 4.1 Application core

-   Modular application architecture
-   Dependency injection container
-   Routing and middleware pipeline
-   Configuration management
-   Application lifecycle hooks
-   Event dispatcher
-   Plugin and extension system
-   Environment-specific configuration

### 4.2 Runtime and performance

-   Fiber-based concurrency abstractions
-   Event-loop integration
-   Persistent worker support
-   Non-blocking HTTP client
-   Async-capable database adapters
-   Connection pooling where supported
-   Streaming request and response support
-   Graceful worker shutdown and lifecycle management

**Important technical constraint:** PHP Fibers provide a mechanism for
cooperative concurrency; they do not automatically make blocking code
asynchronous. Genuine non-blocking behavior requires compatible
event-loop integrations and non-blocking drivers. Blocking libraries may
need adapters or isolation in separate workers.

### 4.3 Developer experience

-   Minimal route and controller definitions
-   CLI project scaffolding
-   Code generation for models, controllers, migrations, and tests
-   Automatic request validation
-   Consistent exception handling
-   Development server and reload workflow
-   Debugging and profiling tools
-   Clear documentation and actionable error messages

### 4.4 Data layer

-   ORM and query builder
-   Database migrations and seeders
-   Transactions
-   Multiple database connections
-   Relationship mapping
-   Redis integration
-   Cache abstraction
-   Pagination
-   Database connection management

### 4.5 Security

-   Secure authentication primitives
-   Authorization policies and roles/permissions
-   CSRF protection
-   Output escaping and XSS protections
-   Parameterized database queries
-   Request validation
-   Rate limiting
-   Secure password hashing
-   Session security
-   Secrets and configuration management
-   Secure file-upload handling
-   Security headers and safe cookie defaults

Security features should be enabled by default where possible, with
documented and deliberate opt-outs.

### 4.6 Application capabilities

-   REST APIs
-   OpenAPI documentation
-   WebSockets and real-time events
-   Background jobs and queues
-   Scheduled tasks
-   Notifications and email
-   File storage
-   GraphQL as an optional module
-   Service-to-service communication
-   AI and LLM integrations through optional packages

### 4.7 Production and observability

-   Structured logging
-   Metrics
-   Distributed tracing
-   Health and readiness checks
-   Graceful shutdown
-   Docker support
-   Horizontal scaling guidance
-   Worker supervision and restart policies
-   Centralized error reporting integrations

## 5. Asynchronous execution strategy

A major differentiator could be a consistent async programming model.

Illustrative target syntax:

``` php
$app->get('/dashboard', async function (Request $request) {
    $user = await User::find($request->userId);

    $orders = await Order::where('user_id', $user->id)->get();

    $notifications = await Notification::unread($user->id);

    return response()->json([
        'user' => $user,
        'orders' => $orders,
        'notifications' => $notifications,
    ]);
});
```

This is **illustrative pseudocode**, not valid native PHP syntax. PHP
does not currently provide JavaScript-style `async` and `await`
keywords.

A realistic first implementation should:

1.  Define a framework-level asynchronous task API.
2.  Use Fibers for cooperative task suspension and resumption.
3.  Integrate with a proven event loop.
4.  Provide non-blocking adapters for HTTP, database, cache, and other
    I/O.
5.  Clearly identify blocking operations.
6.  Offer worker isolation for libraries that cannot safely operate
    asynchronously.

Do not promise that every Composer package becomes non-blocking merely
because it is used inside an async route.

## 6. Runtime and deployment modes

The framework should support two deployment modes.

### Classic mode

-   PHP-FPM
-   Traditional hosting and VPS environments
-   Conventional request lifecycle
-   Broad compatibility with existing PHP deployment practices

### Modern mode

-   Persistent workers
-   Event-loop integration
-   Async-capable I/O
-   WebSockets and streaming
-   Long-running application processes
-   Container and cloud deployment

Investigate **FrankenPHP** as a runtime option rather than building a
new PHP execution engine. Also retain compatibility with conventional
PHP-FPM deployments.

Persistent workers introduce additional engineering requirements:
request-state isolation, memory-leak prevention, safe singleton
lifetimes, worker recycling, and careful cleanup between requests.

## 7. Composer and PHP ecosystem compatibility

Compatibility is a core product requirement.

The framework should support:

-   Composer package installation and autoloading
-   PSR-4 autoloading
-   Standard PHP classes and interfaces
-   Existing PHP extensions and database drivers
-   Framework-independent Composer libraries
-   Existing payment, email, storage, and authentication SDKs

Relevant PHP-FIG standards include:

  Standard   Purpose
  ---------- ---------------------------------------------
  PSR-3      Logger interface
  PSR-4      Autoloading
  PSR-7      HTTP message interfaces
  PSR-11     Container interface
  PSR-15     HTTP server request handlers and middleware
  PSR-16     Simple cache interface
  PSR-18     HTTP client interface
  PSR-20     Clock interface

Standards should be implemented where they provide real
interoperability. Avoid implementing standards merely to increase a
checklist.

### Compatibility boundaries

General Composer packages should work whenever their PHP and extension
requirements are satisfied.

Packages tightly coupled to another framework's internals---such as a
framework-specific service container, ORM, or lifecycle---may require
adapters. Full compatibility with every Laravel or Symfony package
should not be promised.

## 8. Example developer experience

The following examples show the intended ergonomics. They are proposed
API designs, not implemented functionality.

### 8.1 Routing

``` php
$app->get('/users/{id}', function (User $user) {
    return $user;
});
```

Potential framework behavior:

1.  Match the route and extract its parameter.
2.  Resolve the model through an explicitly configured binding.
3.  Return a consistent 404 response if the model is missing.
4.  Serialize the result as JSON when appropriate.
5.  Apply configured middleware and authorization rules.

Automatic model resolution must be explicit and predictable. It should
not silently infer unsafe database queries.

### 8.2 Validation

``` php
$app->post('/users', function (CreateUser $data) {
    return User::create($data->validated());
});
```

A request schema or DTO could define validation rules, error messages,
and API documentation metadata.

### 8.3 Database access

``` php
$user = User::create([
    'name' => 'John',
    'email' => 'john@example.com',
]);

$orders = $user->orders()->latest()->get();
```

The data layer should provide a familiar ORM experience while keeping
query behavior, eager loading, transactions, and performance
characteristics understandable.

## 9. Proposed architecture

The framework can be divided into five layers.

### Layer 1: Developer interface

-   Routing
-   Controllers
-   CLI
-   Attributes
-   Request validation
-   Application configuration

### Layer 2: Framework core

-   Dependency injection
-   Middleware
-   Events
-   Security
-   Application lifecycle
-   Exception handling

### Layer 3: Execution engine

-   Fiber integration
-   Async scheduler
-   Event-loop adapters
-   Worker management
-   HTTP execution

### Layer 4: Integration layer

-   Composer
-   PSR interfaces
-   Database drivers
-   Redis
-   Cache
-   External PHP libraries

### Layer 5: Runtime and deployment

-   PHP-FPM
-   FrankenPHP
-   Docker
-   VPS and cloud deployment
-   Traditional hosting

Keep the core independent of any one runtime so applications can use the
same application code in different deployment environments where
supported.

## 10. PHP version strategy

Use a currently supported PHP release as the minimum version and define
a tested compatibility matrix.

PHP 8.5 is a reasonable candidate for the initial development baseline,
subject to release timing, hosting availability, dependency support, and
the project's compatibility goals.

Do not depend on experimental language features or unmerged PHP
proposals. Keep the framework's public API stable and use native
language improvements when they are available in the supported version
range.

## 11. Development roadmap

  -----------------------------------------------------------------------
  Phase                   Scope                   Exit criteria
  ----------------------- ----------------------- -----------------------
  1\. Core                Bootstrap, routing, DI, Build and test a small
                          middleware,             working HTTP
                          configuration, errors   application

  2\. Application         Database, migrations,   Build a secure CRUD
  essentials              validation,             application
                          authentication,         
                          authorization           

  3\. Runtime             Fibers, scheduler,      Demonstrate concurrent
                          worker model, async     I/O with measurable
                          HTTP client             tests

  4\. Application         Queues, events,         Demonstrate background
  services                WebSockets, scheduler,  and real-time workflows
                          storage                 

  5\. Production          Security review, tests, Pass security and
  readiness               profiling,              reliability acceptance
                          observability           criteria

  6\. Ecosystem           CLI polish,             Publish a stable public
                          documentation,          release
                          examples, adapters,     
                          release process         
  -----------------------------------------------------------------------

### Phase 1 should remain deliberately small

Recommended first milestone:

-   Installable through Composer
-   Application bootstrap
-   Router with route parameters
-   Middleware pipeline
-   Dependency injection
-   Request and response abstractions
-   Configuration and environment handling
-   Exception handling
-   A basic CLI
-   Automated unit and integration tests
-   A minimal example application

Avoid starting with an ORM, async runtime, GraphQL, and dozens of
integrations simultaneously. Establish clean core interfaces first.

## 12. Quality and success criteria

The project should be judged on evidence, not marketing claims.

### Developer experience

-   Time to create a working API
-   Amount of boilerplate for common tasks
-   Clarity of errors and documentation
-   Ease of testing and debugging

### Compatibility

-   Composer installation works
-   PSR interoperability tests pass
-   Representative framework-independent packages work
-   Supported PHP and extension versions are documented

### Security

-   Security-focused automated tests
-   Dependency vulnerability scanning
-   Secure defaults reviewed by experienced PHP security engineers
-   Documented threat model and responsible disclosure process

### Performance and scalability

-   Reproducible benchmarks against relevant PHP frameworks
-   Throughput and latency under realistic workloads
-   Memory use per worker
-   Behavior under concurrent I/O
-   Worker stability over extended runs
-   Horizontal scaling tests

Never publish a performance comparison without documenting hardware,
runtime, workload, configuration, and test methodology.

## 13. Key technical risks

  -----------------------------------------------------------------------
  Risk                                Mitigation
  ----------------------------------- -----------------------------------
  Async syntax cannot be implemented  Design a valid PHP async API using
  as native PHP keywords              Fibers and event-loop abstractions

  Blocking third-party libraries      Provide adapters and worker
                                      isolation; document limitations

  Persistent-worker state leaks       Enforce lifecycle boundaries,
                                      cleanup, testing, and worker
                                      recycling

  Compatibility becomes too broad     Prioritize Composer and PSR
                                      compatibility; provide targeted
                                      adapters

  Framework becomes too large         Keep a small core and optional
                                      modules

  Performance claims are misleading   Use transparent, reproducible
                                      benchmarks

  Security defaults become            Provide documented configuration
  restrictive                         without unsafe silent fallbacks
  -----------------------------------------------------------------------

## 14. Product direction decisions

These decisions should be finalized before implementation.

### A. Framework architecture

Recommended: **Hybrid framework** --- a lightweight core with optional
full-stack modules.

Alternatives: - API-first framework - Full-stack framework with
everything included

### B. Backward compatibility

Recommended: **Maximum practical compatibility** --- prioritize Composer
and PSR compatibility, while using adapters for framework-specific
integrations.

### C. Developer experience

Recommended: **Both simple and structured** --- minimal syntax for
common use cases, with explicit classes, attributes, and dependency
injection available for complex applications.

### D. Runtime

Recommended: **Both traditional and modern** --- PHP-FPM for broad
hosting compatibility and persistent-worker runtimes for advanced
performance and concurrency.

## 15. Final product vision

The framework should not position itself as "Node.js or Python rewritten
in PHP."

It should offer a distinct, modern PHP experience:

-   Productive like Python
-   Event-driven like Node.js
-   Structured like modern enterprise frameworks
-   Interoperable with the PHP ecosystem
-   Secure by default
-   Scalable without unnecessary complexity

**The ultimate objective:** Give developers a compelling reason to
choose PHP for new applications, while making it easier for existing PHP
teams to build and maintain modern software.

------------------------------------------------------------------------

## Appendix: Suggested initial repository structure

``` text
framework/
├── packages/
│   ├── core/
│   ├── http/
│   ├── routing/
│   ├── container/
│   ├── middleware/
│   ├── config/
│   ├── console/
│   ├── validation/
│   ├── database/
│   ├── auth/
│   ├── cache/
│   ├── events/
│   ├── queue/
│   ├── async/
│   └── testing/
├── skeleton/
├── examples/
├── docs/
├── benchmarks/
├── tests/
├── composer.json
├── phpunit.xml
└── README.md
```

This is a starting proposal. Package boundaries should be adjusted after
the core interfaces and dependency graph are designed.
