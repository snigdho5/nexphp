# NexPHP
## Phases 3–6: Runtime, Application Services, Production Readiness, and Ecosystem

**Project:** NexPHP  
**Document type:** Technical implementation blueprint  
**Prerequisites:** NexPHP Phase 1 (core foundation) and Phase 2 (database, validation, authentication, authorization) are complete  
**Architecture decisions:** Hybrid · Maximum practical Composer/PSR compatibility · Minimal developer experience · PHP-FPM and persistent-worker runtimes  
**Status:** Proposed roadmap — validate package contracts and scope through ADRs before implementation.

---

# 0. Project direction and roadmap overview

NexPHP is a modern PHP application framework built around a small core, optional first-party modules, Composer interoperability, secure defaults, and runtime flexibility.

The finalized architectural decisions remain:

| Decision | NexPHP direction |
|---|---|
| Architecture | Hybrid: lightweight core with optional full-stack modules |
| Compatibility | Maximum practical PHP, Composer, and PSR compatibility |
| Developer experience | Minimal syntax, sensible conventions, and AI "vibe coding" optimized |
| Runtime | Traditional PHP-FPM and modern persistent-worker runtimes |

## 0.1 What the four phases deliver

| Phase | Theme | Main outcome |
|---|---|---|
| 3 | Runtime, concurrency, and performance | A reliable modern runtime with worker lifecycle management and optional async I/O |
| 4 | Application services | Queues, events, scheduling, WebSockets, storage, mail, notifications, and API tooling |
| 5 | Production readiness | Security hardening, observability, resilience, compatibility, and performance validation |
| 6 | Ecosystem and stable release | Excellent CLI, documentation, integrations, migration guidance, community, and NexPHP 1.0 |

## 0.2 Important engineering rule

NexPHP must not disguise synchronous work as asynchronous.

PHP Fibers provide cooperative suspension and resumption. They do not make PDO, filesystem calls, or arbitrary Composer libraries non-blocking. Every async-capable integration must use a genuinely non-blocking implementation or explicitly run blocking work in an isolated worker/process.

Likewise, persistent workers require explicit request-scope cleanup. A process that handles many requests must never accidentally carry one user's identity, request data, locale, tenant, or mutable state into another request.

---

# Phase 3 — Runtime, Concurrency, and Performance

## 1. Phase 3 objective

Build a robust execution layer that allows NexPHP applications to run efficiently in both conventional PHP-FPM environments and modern persistent-worker environments.

Phase 3 should make the runtime more capable without forcing application developers to write runtime-specific code.

At the end of Phase 3, NexPHP should have:

- A documented runtime abstraction.
- A reliable persistent-worker lifecycle.
- Request-state isolation.
- Worker supervision and graceful shutdown behavior.
- Fiber-based task coordination where useful.
- Event-loop integration through adapters.
- A non-blocking HTTP client integration.
- Timeouts, cancellation semantics, and error propagation.
- Benchmarks demonstrating where the modern runtime helps.
- A clear capability model for runtimes and integrations.

## 2. Scope

### In scope

- Runtime contracts and capability discovery.
- Worker lifecycle and request scope.
- Fiber task abstraction.
- Event-loop adapter.
- Async HTTP client adapter.
- Concurrency limits and backpressure primitives.
- Timeouts and cancellation.
- Persistent-worker diagnostics.
- Runtime health and graceful shutdown.
- Benchmark and profiling harness.

### Out of scope

- Rewriting the PHP execution engine.
- Claiming all PHP libraries are asynchronous.
- A proprietary language-level `async`/`await` syntax.
- A complete distributed task system (Phase 4).
- Async support for every database driver.
- A new server implementation if established runtimes meet requirements.

## 3. Proposed package structure

```text
packages/
├── runtime-contracts/
├── runtime-fpm/
├── runtime-frankenphp/
├── runtime-cli/
├── runtime-worker/
├── concurrency-contracts/
├── concurrency/
├── event-loop-adapter/
├── http-client-contracts/
├── http-client/
├── http-client-async/
├── process/
├── worker-health/
└── testing/
```

Keep package boundaries practical. Do not create a package for every class. Extract packages when there is a stable contract, an independent adapter, or a meaningful optional dependency.

## 4. Runtime architecture

### 4.1 Runtime-neutral kernel

The NexPHP kernel remains responsible for application behavior:

1. Receive a request.
2. Establish a request scope.
3. Resolve routing and middleware.
4. Invoke the handler.
5. Convert exceptions to responses.
6. Return a response to the runtime.
7. Clean up request-scoped resources.

The kernel must not:
- Emit headers or echo response bodies directly.
- Call `exit()` as part of normal request handling.
- Assume one request per process.
- Store the current user in a process-wide singleton.
- Depend on FrankenPHP-specific APIs.

### 4.2 Runtime adapters

**PHP-FPM adapter**
- Build requests from PHP globals.
- Invoke the kernel.
- Emit status, headers, and body.
- Respect PHP output and header constraints.
- Maintain compatibility with conventional hosting.

**FrankenPHP adapter**
- Integrate with supported worker mode.
- Reuse the application kernel safely.
- Invoke lifecycle hooks.
- Clean request state after every request.
- Support graceful shutdown and worker recycling.
- Expose runtime capabilities without leaking implementation details into application code.

**CLI adapter**
- Boot the application for commands, migrations, workers, and scheduled tasks.
- Avoid assuming HTTP request context exists.

### 4.3 Capability discovery

A runtime may support different features. Do not infer support from a runtime name alone.

Proposed capability contract:

```php
namespace NexPHP\Contracts\Runtime;

interface RuntimeCapabilitiesInterface
{
    public function supportsPersistentWorkers(): bool;

    public function supportsStreamingResponses(): bool;

    public function supportsWebSockets(): bool;

    public function supportsAsyncIO(): bool;

    public function supportsGracefulShutdown(): bool;
}
```

The actual capability model may use named capability objects or a registry. Capabilities must describe verified behavior, not marketing labels.

## 5. Persistent-worker lifecycle

### 5.1 Lifecycle stages

```text
Process starts
     |
     v
Load configuration and application
     |
     v
Boot application providers
     |
     v
Worker ready
     |
     v
Receive request
     |
     v
Create request scope and context
     |
     v
Run kernel and middleware
     |
     v
Emit response
     |
     v
Release request-scoped services
     |
     v
Reset request-local state
     |
     +------> Receive next request
     |
     v
Graceful shutdown / worker recycle
```

### 5.2 Scope model

- **Application scope:** immutable configuration, stateless services, safe connection pools, compiled route metadata.
- **Request scope:** request object, authenticated identity, tenant context, locale, correlation ID, request-specific services.
- **Transient scope:** newly constructed objects where requested.

A singleton must not retain request-specific mutable state.

### 5.3 Cleanup requirements

After every request:
- Clear request-scoped container instances.
- Clear current identity and authorization context.
- Clear tenant and locale context.
- Reset request correlation data.
- Release request-bound database transactions and resources.
- Remove request-specific event listeners if they were registered dynamically.
- Close temporary streams and files.
- Ensure cleanup runs even if middleware or handlers throw.
- Record cleanup failures without hiding the original application exception.

### 5.4 Worker recycling

Support configurable limits such as:
- Maximum requests per worker.
- Maximum worker age.
- Memory threshold.
- Maximum request duration.
- Graceful shutdown deadline.

These should be runtime configuration, not hard-coded assumptions. Worker recycling must not interrupt in-flight work without a defined shutdown policy.

## 6. Concurrency and async model

### 6.1 Design principles

- Use Fibers as a cooperative concurrency primitive.
- Keep scheduler implementation behind contracts.
- Integrate with a maintained event loop.
- Do not require async execution for ordinary applications.
- Make blocking boundaries visible.
- Define cancellation and timeout behavior.
- Bound concurrency to avoid exhausting sockets, memory, or database connections.

### 6.2 Proposed API

The API below is illustrative. It must be validated against the selected event-loop implementation and PHP syntax.

```php
$results = await_all([
    fn () => $http->getAsync('/users/1'),
    fn () => $http->getAsync('/users/2'),
    fn () => $http->getAsync('/users/3'),
]);
```

If a valid PHP API cannot use the shown syntax, use ordinary methods and task objects:

```php
$tasks = [
    $http->getAsync('/users/1'),
    $http->getAsync('/users/2'),
];

$results = $scheduler->awaitAll($tasks);
```

Do not use pseudo-keywords such as `await` or `async` in documentation as if they were native PHP.

### 6.3 Core concurrency contracts

```php
namespace NexPHP\Contracts\Concurrency;

interface SchedulerInterface
{
    public function run(callable $callback): mixed;

    public function all(iterable $tasks): array;
}
```

```php
interface TaskInterface
{
    public function isComplete(): bool;

    public function result(): mixed;

    public function cancel(): void;
}
```

These are starting points, not mandatory final signatures. The implementation must define:
- Whether `result()` blocks, throws, or requires completion.
- How task failures are represented.
- Whether cancellation is cooperative.
- How cancellation propagates to underlying I/O.
- How timeouts are applied.
- How task context is propagated safely.

### 6.4 Non-blocking HTTP client

Provide:
- Request and response abstractions compatible with PSR-18 where practical.
- Sync and async APIs with clear differences.
- Connection reuse.
- TLS verification enabled by default.
- Connect, read, and total timeouts.
- Redirect policy.
- Response size limits.
- Cancellation where supported.
- Structured transport exceptions.
- No automatic retry for non-idempotent requests unless explicitly configured.

### 6.5 Backpressure and resource limits

Async does not mean unlimited concurrency.

Support:
- Maximum in-flight task count.
- Per-host connection limits.
- Bounded queues.
- Timeout budgets.
- Response body limits.
- Circuit-breaker integration points.
- Metrics for pending and active work.

## 7. Database and blocking I/O policy

Phase 2's PDO adapter remains synchronous.

In Phase 3:
- Document that PDO queries block the executing worker.
- Do not wrap PDO in a fake async method.
- Provide a database-driver extension point for genuinely async adapters.
- Consider process/thread isolation only where operationally justified.
- Ensure transaction and connection ownership is explicit.
- Do not share one unsafe connection concurrently across tasks.

Async database drivers should be added only after a concrete implementation passes correctness, cancellation, transaction, and load tests.

## 8. Observability and diagnostics for the runtime

Add:
- Worker start and stop logs.
- Request count per worker.
- Worker memory usage.
- Request duration.
- Active task count.
- Pending task count.
- Event-loop lag where measurable.
- Timeout and cancellation counts.
- Unhandled task failure reporting.
- Worker recycle reason.

Never log credentials, session identifiers, access tokens, or sensitive request bodies.

## 9. Phase 3 testing

### Unit and contract tests
- Scheduler task completion.
- Task exception propagation.
- Cancellation semantics.
- Timeout behavior.
- Capability reporting.
- Runtime lifecycle hooks.

### Persistent-worker tests
- Multiple requests in one process.
- Identity does not leak.
- Request context does not leak.
- Exception does not poison next request.
- Memory growth is measured over repeated requests.
- Cleanup runs on every exit path.
- Worker shutdown handles active requests predictably.

### Async integration tests
- Concurrent HTTP requests.
- DNS and connection failures.
- TLS verification.
- Timeouts.
- Cancellation.
- Response size limits.
- Backpressure under load.
- Blocking task behavior is documented.

### Performance tests
Compare:
- PHP-FPM baseline.
- Persistent-worker runtime.
- Sync I/O workload.
- Concurrent I/O workload.
- Cold and warm application startup.
- Memory per request and per worker.

Publish exact hardware, PHP version, extensions, runtime settings, workload, and benchmark methodology.

## 10. Phase 3 milestones

| Milestone | Work | Exit criteria |
|---|---|---|
| 0. Runtime ADRs | Runtime contracts, lifecycle, capabilities | Decisions reviewed and documented |
| 1. Worker safety | Scopes, cleanup, lifecycle | Repeated-request isolation tests pass |
| 2. Modern runtime | FrankenPHP adapter | Example app runs in worker mode |
| 3. Concurrency core | Task and scheduler contracts | Task, failure, timeout semantics tested |
| 4. Async HTTP | Non-blocking HTTP adapter | Concurrency, TLS, timeout and cancellation tests pass |
| 5. Diagnostics | Worker and event-loop metrics | Runtime health is observable |
| 6. Benchmarking | Reproducible workloads | Baseline and results published |

## 11. Phase 3 definition of done

- [ ] PHP-FPM behavior remains supported.
- [ ] Persistent-worker runtime works with the same application kernel.
- [ ] Request-scoped services are reliably reset.
- [ ] Multi-request isolation tests pass.
- [ ] Worker shutdown and recycling are documented.
- [ ] Runtime capabilities are discoverable.
- [ ] Async behavior uses a real event-loop integration.
- [ ] Blocking I/O is clearly identified.
- [ ] HTTP client has timeout and TLS tests.
- [ ] Concurrency limits and backpressure are implemented.
- [ ] Runtime benchmarks are reproducible.
- [ ] No unresolved critical or high security issue remains.

---

# Phase 4 — Application Services and Full-Stack Capabilities

## 12. Phase 4 objective

Provide the common application services expected from a productive hybrid framework.

At the end of Phase 4, NexPHP should support:
- In-process events.
- Durable background jobs and queues.
- Scheduled tasks.
- WebSockets and broadcasting through optional runtime adapters.
- Filesystem and object storage.
- Mail and notifications.
- API serialization and OpenAPI generation.
- Health checks and application integration hooks.

The core remains small. Every service is optional and can be installed through Composer.

## 13. Proposed package structure

```text
packages/
├── events/
├── event-contracts/
├── queue-contracts/
├── queue/
├── queue-database/
├── queue-redis/
├── scheduler/
├── broadcasting-contracts/
├── broadcasting/
├── websocket/
├── filesystem-contracts/
├── filesystem/
├── filesystem-local/
├── filesystem-s3/
├── mail-contracts/
├── mail/
├── mail-smtp/
├── notifications/
├── serializer/
├── api-resources/
├── openapi/
├── health/
└── testing/
```

Adapters should be separate when they introduce optional dependencies or external service requirements.

## 14. Event system

### 14.1 Event capabilities

- Synchronous event dispatch.
- Listener registration.
- Subscriber registration.
- Wildcard listeners if useful.
- Event discovery as an optional convenience.
- Queued listener adapter.
- Error reporting.
- Listener ordering rules.

### 14.2 Contracts

```php
namespace NexPHP\Contracts\Events;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
}
```

```php
interface ListenerInterface
{
    public function handle(object $event): void;
}
```

Synchronous dispatch should be the default. A listener must not silently become durable or asynchronous simply because it is registered with the event dispatcher.

### 14.3 Event guarantees

Document:
- Whether listener order is guaranteed.
- Whether listener exceptions stop dispatch.
- How retries work for queued listeners.
- Whether events are serializable.
- Whether events are safe to replay.

For durable event delivery, use a queue or transactional outbox design rather than pretending an in-memory event dispatcher is durable.

## 15. Queue and background jobs

### 15.1 Queue goals

- Driver-independent queue contract.
- Database queue adapter.
- Redis queue adapter.
- Worker command.
- Retry and backoff.
- Failed-job storage.
- Delayed jobs.
- Job timeouts.
- Job uniqueness or deduplication where supported.
- Graceful worker shutdown.
- Job middleware.
- Metrics and operational visibility.

### 15.2 Queue contract

```php
namespace NexPHP\Contracts\Queue;

interface QueueInterface
{
    public function push(object $job, array $options = []): string;

    public function pop(?string $queue = null): ?QueuedJobInterface;

    public function acknowledge(QueuedJobInterface $job): void;

    public function release(
        QueuedJobInterface $job,
        int $delaySeconds = 0
    ): void;
}
```

A production interface must also define reservation leases, visibility timeout, attempts, and failure semantics.

### 15.3 Job design

Illustrative job:

```php
final class SendWelcomeEmail
{
    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(MailerInterface $mailer): void
    {
        // Load the user and send the message.
    }
}
```

Jobs should contain serializable identifiers and small payloads, not entire request objects, database connections, closures, or live service instances.

### 15.4 Reliability requirements

- At-least-once delivery should be the baseline expectation for typical queue adapters.
- Jobs must be idempotent where possible.
- Acknowledgment happens only after successful handling.
- Worker crashes must not silently lose reserved jobs.
- Retries must use bounded backoff.
- Poison jobs must move to a failed-job store.
- Operators must be able to inspect, retry, and discard failed jobs.
- Queue payloads must be version-aware and safe to deserialize.
- Never unserialize untrusted arbitrary PHP objects from a queue.

### 15.5 Transactional dispatch

Document the race where a job is dispatched before the database transaction that creates its referenced record commits.

Offer an after-commit dispatch option. A transactional outbox can be added for stronger guarantees in systems that need atomic database-write-plus-event publication.

## 16. Scheduler

### 16.1 Capabilities

- Cron-like schedules.
- Timezone-aware scheduling.
- Overlap prevention.
- Distributed lock adapter.
- Background execution.
- Missed-run policy.
- Run history and status.
- Graceful shutdown.

### 16.2 Example API

```php
$schedule->command('reports:daily')
    ->dailyAt('02:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();
```

The scheduler should use an injected clock so schedule behavior can be tested deterministically.

### 16.3 Reliability

- A scheduler tick should not execute the same protected task concurrently.
- Lock expiry and crash recovery must be documented.
- Timezone and daylight-saving behavior must be tested.
- The scheduler should not assume that every minute tick is delivered.
- Long-running work should be delegated to queues.

## 17. WebSockets and broadcasting

### 17.1 Architecture

WebSocket support belongs in optional packages and requires a compatible server/runtime.

Provide:
- Connection lifecycle.
- Message routing.
- Channel abstraction.
- Authentication handshake.
- Authorization for channel subscriptions.
- Heartbeats and idle timeouts.
- Message size limits.
- Rate limiting.
- Broadcasting adapter.
- Graceful disconnect handling.

### 17.2 Security requirements

- Authenticate connections.
- Authorize every private or presence-channel subscription.
- Validate message payloads.
- Enforce maximum frame and message sizes.
- Apply per-connection and per-user rate limits.
- Avoid exposing internal channel names or sensitive metadata.
- Revalidate expiring credentials where appropriate.
- Define origin policy for browser clients.

### 17.3 Runtime compatibility

PHP-FPM alone is not a general-purpose long-lived WebSocket server. NexPHP should use a dedicated compatible runtime or server adapter. The framework's broadcasting API should remain independent of the transport.

## 18. Filesystem and object storage

### 18.1 Capabilities

- Local filesystem adapter.
- S3-compatible object storage adapter.
- Stream-based reads and writes.
- Existence and metadata operations.
- Directory/list operations where supported.
- Temporary URLs where supported.
- Visibility/permissions abstraction.
- Safe file naming.
- Configurable disks/stores.

### 18.2 Contract

```php
namespace NexPHP\Contracts\Filesystem;

interface FilesystemInterface
{
    public function read(string $path): string;

    public function write(string $path, string $contents): void;

    public function delete(string $path): void;

    public function exists(string $path): bool;
}
```

A production interface should prefer streams for large files and include explicit error behavior. Avoid loading large objects into memory unnecessarily.

### 18.3 Security

- Normalize and validate paths.
- Prevent path traversal.
- Never trust user-provided storage paths.
- Generate storage names independently of original filenames.
- Validate uploaded file size and content.
- Keep private storage private by default.
- Use short-lived signed URLs where needed.
- Avoid exposing local filesystem paths in API responses.

## 19. Mail and notifications

### 19.1 Mail

- Message object.
- Transport interface.
- SMTP adapter.
- Optional API-provider adapters.
- HTML and text bodies.
- Attachments and inline images.
- Template integration.
- Queue integration.
- Delivery failure reporting.

### 19.2 Notifications

Support channels through adapters:
- Mail
- Database
- Broadcast
- Optional SMS/push integrations

Notification payloads should be explicit and versionable. Sensitive notification data must not be exposed through public channels.

## 20. Serialization, API resources, and OpenAPI

### 20.1 Serialization

- JSON serialization.
- Explicit field inclusion/exclusion.
- Date/time normalization.
- Circular reference handling.
- Sensitive-field exclusion.
- Stable error object format.

Never serialize every model property automatically. Use explicit resource/serializer definitions for public API responses.

### 20.2 OpenAPI

Generate OpenAPI documents from explicit route metadata, request schemas, response schemas, and validation definitions.

Do not promise complete documentation inference from arbitrary PHP code. If metadata is missing, mark the schema as incomplete or require explicit definitions.

## 21. Health and readiness

Provide:
- Liveness endpoint.
- Readiness endpoint.
- Dependency health checks.
- Queue health adapter.
- Database health adapter.
- Storage health adapter.
- Runtime health metadata.

Health endpoints must not expose credentials, internal topology, stack traces, or detailed dependency secrets. Separate liveness from readiness so a temporary downstream dependency failure does not cause inappropriate process restarts.

## 22. Phase 4 milestones

| Milestone | Work | Exit criteria |
|---|---|---|
| 0. Service contracts | Event, queue, storage, mail contracts | Interfaces and failure semantics documented |
| 1. Events | Dispatcher, listeners, subscribers | Deterministic tests and error behavior |
| 2. Queues | Database and Redis adapters, workers, retries | Crash/retry/failed-job tests pass |
| 3. Scheduler | Cron, locks, timezone, history | Overlap and missed-run behavior tested |
| 4. Filesystem | Local and S3-compatible adapters | Stream, path safety, permission tests pass |
| 5. Mail/notifications | SMTP and notification channels | Delivery errors and queue integration tested |
| 6. Broadcasting | WebSocket and channel abstractions | Authenticated channel tests pass |
| 7. API tooling | Serializer, resources, OpenAPI | Stable API schemas generated |
| 8. Health | Liveness/readiness and checks | Safe operational endpoints available |

## 23. Phase 4 definition of done

- [ ] Event dispatch is deterministic and documented.
- [ ] Queue delivery, retries, reservation, and failure behavior are tested.
- [ ] Jobs are safe to retry and payloads are versioned.
- [ ] Database after-commit dispatch is available or explicitly deferred.
- [ ] Scheduler supports timezone-aware schedules and overlap protection.
- [ ] Local storage works.
- [ ] Object storage adapter is independently installable if shipped.
- [ ] Mail transport is configurable and testable.
- [ ] Notifications can use queue adapters.
- [ ] WebSocket authentication and authorization are enforced.
- [ ] API serialization avoids accidental sensitive-field exposure.
- [ ] OpenAPI output is valid and tested.
- [ ] Health endpoints expose no secrets.
- [ ] All external service adapters have failure and timeout tests.

---

# Phase 5 — Production Readiness, Security, Reliability, and Performance

## 24. Phase 5 objective

Turn NexPHP from a feature-complete framework into a framework that teams can responsibly operate in production.

This phase is not primarily about adding features. It is about proving the existing features are secure, observable, reliable, compatible, and maintainable.

At the end of Phase 5, NexPHP should have:
- A documented threat model.
- Security review and remediation process.
- Production-ready logging, metrics, and tracing.
- Resilience patterns for external dependencies.
- Performance baselines and regression gates.
- Runtime and worker reliability tests.
- Supported-version and dependency policies.
- Deployment and operations documentation.

## 25. Security program

### 25.1 Threat model

Threat-model at minimum:
- HTTP parsing and routing.
- Middleware ordering.
- Authentication and sessions.
- Authorization and tenant boundaries.
- SQL construction and migrations.
- File uploads and storage.
- Queue payloads and workers.
- WebSocket connections and channels.
- Configuration and secret handling.
- Persistent-worker state.
- Deserialization and serialization.
- CLI commands and deployment operations.

### 25.2 Security controls

**HTTP**
- Request size limits.
- Header validation.
- Secure cookie defaults.
- CSRF protection for browser-session requests.
- CORS configuration that does not default to permissive wildcard credentials.
- Security headers.
- Rate limiting.
- Safe error responses.

**Database**
- Prepared statements.
- Safe identifier handling.
- Least-privilege credentials.
- Secret redaction.
- Migration safeguards.
- Transaction correctness.

**Authentication**
- Password API usage.
- Login throttling.
- Session rotation and invalidation.
- Token hashing and revocation.
- Secure reset-token lifecycle.
- Credential redaction.

**Authorization**
- Deny-by-default for protected abilities.
- Tenant-aware access checks.
- Object-level authorization.
- Regression tests for cross-user access.

**Files**
- Path traversal prevention.
- Upload size/type validation.
- Safe generated filenames.
- Private-by-default storage.
- No executable uploads in public directories.

**Queues and events**
- Avoid arbitrary object deserialization.
- Validate job payloads.
- Restrict worker privileges.
- Protect failed-job payloads.
- Avoid logging sensitive data.

### 25.3 Security release process

- Publish `SECURITY.md`.
- Define supported security branches.
- Provide a private vulnerability reporting channel.
- Define severity and response targets.
- Publish security advisories.
- Add dependency scanning.
- Add static analysis and security regression tests.
- Require review for cryptographic, authentication, authorization, and deserialization changes.

## 26. Observability

### 26.1 Logging

- PSR-3 compatible.
- Structured context.
- Request/correlation ID.
- Worker ID.
- Job ID.
- Route name.
- Exception class and safe details.
- Configurable redaction.
- JSON output for production.
- Human-readable output for development.

Do not log passwords, access tokens, session cookies, authorization headers, private keys, or raw sensitive request bodies.

### 26.2 Metrics

Expose metrics through an adapter, with common measurements such as:
- Request count.
- Request latency histogram.
- Response status counts.
- Error rate.
- Database query duration.
- Queue depth.
- Job processing duration.
- Job retry and failure count.
- Worker memory and restart count.
- Active WebSocket connections.
- Event-loop lag where supported.

Metric labels must avoid unbounded cardinality. Never use raw user IDs, emails, request paths with arbitrary identifiers, or tokens as labels.

### 26.3 Distributed tracing

- Trace context propagation.
- HTTP client span integration.
- Database span integration.
- Queue producer/consumer trace propagation.
- Error and status recording.
- Sampling configuration.
- Sensitive attribute filtering.

Use OpenTelemetry-compatible adapters where practical. Tracing should be optional and should not impose a mandatory heavyweight runtime dependency on the core.

## 27. Resilience patterns

Add optional integrations or guidance for:

- Timeouts.
- Bounded retries.
- Exponential backoff with jitter.
- Circuit breakers.
- Bulkheads/concurrency limits.
- Idempotency keys.
- Graceful degradation.
- Queue dead-letter handling.
- Health/readiness checks.
- Graceful worker shutdown.

Rules:
- Do not retry non-idempotent operations blindly.
- Every retry policy must have a maximum attempt or time budget.
- Timeouts should be explicit for outbound network operations.
- Circuit breakers must not hide persistent failures.
- Resilience behavior must be observable.

## 28. Performance engineering

### 28.1 Benchmark suite

Create reproducible benchmarks for:
- Router throughput.
- Middleware overhead.
- Container resolution.
- Request bootstrap.
- JSON response serialization.
- Database query overhead.
- Cache operations.
- Persistent-worker throughput.
- Async HTTP fan-out.
- Queue throughput.
- Memory growth over long worker runs.

### 28.2 Benchmark methodology

Every published result must specify:
- Hardware and operating system.
- PHP version and SAPI.
- Enabled extensions.
- Runtime and server configuration.
- Database/cache versions.
- Dataset and workload.
- Concurrency level.
- Warm-up procedure.
- Number of repetitions.
- Median and tail latency.
- Error rate.
- Memory consumption.

Compare equivalent application behavior. Do not compare a minimal route in one framework with a database-heavy route in another.

### 28.3 Performance budgets

Set budgets after obtaining a reliable baseline. Candidate budgets:
- No unexplained regression in router or middleware microbenchmarks.
- Bounded memory growth in persistent workers.
- No unbounded task or queue accumulation.
- Predictable response latency under configured limits.
- No material performance penalty from disabled optional modules.

Avoid arbitrary numerical promises before measurement.

## 29. Reliability and failure testing

Add:
- Repeated worker request soak tests.
- Memory leak tests.
- Forced worker termination tests.
- Database disconnect tests.
- Redis outage tests.
- Queue reservation expiry tests.
- Mail provider timeout tests.
- Storage failure tests.
- Slow HTTP dependency tests.
- Graceful shutdown tests.
- Disk-full and log-write failure tests where feasible.

The framework should fail predictably and preserve the original cause of failures.

## 30. Compatibility and support policy

Define:
- Supported PHP version range.
- Supported database versions.
- Supported runtime versions.
- Required and optional extensions.
- Composer version policy.
- PSR compatibility guarantees.
- Deprecation policy.
- Security support window.
- Upgrade policy for major versions.

Run a compatibility matrix in CI. Test both minimum supported dependency versions and current compatible versions where practical.

## 31. Documentation for operations

Write operational guides for:
- PHP-FPM deployment.
- FrankenPHP worker deployment.
- Docker deployment.
- Reverse proxy configuration.
- TLS termination.
- Environment and secrets management.
- Database migrations.
- Queue workers and supervisors.
- Scheduler deployment.
- WebSocket deployment.
- Health checks.
- Log and metric collection.
- Backup and restore considerations.
- Graceful deployment and rollback.
- Incident troubleshooting.

## 32. Phase 5 milestones

| Milestone | Work | Exit criteria |
|---|---|---|
| 0. Threat model | Review attack surfaces | Threat model approved |
| 1. Security hardening | Fix defaults and vulnerabilities | No unresolved critical/high findings |
| 2. Observability | Logs, metrics, traces | Core operational signals available |
| 3. Resilience | Timeouts, retries, circuit breakers | Failure behavior tested |
| 4. Performance | Benchmarks and budgets | Baselines published |
| 5. Reliability | Soak, fault, worker tests | No known critical leaks or failure loops |
| 6. Compatibility | PHP/runtime/database matrix | Support matrix passes |
| 7. Operations docs | Deployment and incident guides | Production runbooks reviewed |

## 33. Phase 5 definition of done

- [ ] Threat model is documented and reviewed.
- [ ] No unresolved critical or high security findings remain.
- [ ] Security policy and vulnerability process are published.
- [ ] Sensitive data redaction is tested.
- [ ] Structured logs are available.
- [ ] Metrics are available through an optional adapter.
- [ ] Trace propagation is supported through adapters.
- [ ] Outbound I/O has explicit timeout guidance and defaults.
- [ ] Retry policies are bounded and safe.
- [ ] Worker soak tests pass.
- [ ] Memory and request-state behavior are measured.
- [ ] Performance baselines are reproducible.
- [ ] Supported version matrix is documented and tested.
- [ ] Deployment and operations runbooks are complete.

---

# Phase 6 — Ecosystem, Developer Experience, and Stable Release

## 34. Phase 6 objective

Make NexPHP easy to discover, install, learn, extend, upgrade, and operate.

The framework may be technically capable after Phases 1–5, but adoption requires more than code. Phase 6 builds the developer-facing product and release ecosystem.

At the end of Phase 6, NexPHP should be ready for a stable 1.0 release with:
- A polished CLI.
- A reliable project skeleton.
- Complete documentation.
- Tutorials and real-world examples.
- Official integration adapters.
- Upgrade and migration tools.
- Contribution and governance processes.
- Release automation.
- A clear compatibility and support policy.

## 35. CLI and project creation

### 35.1 CLI goals

The CLI should be consistent, discoverable, and safe.

Core commands:
- `nexphp new`
- `nexphp serve`
- `nexphp route:list`
- `nexphp about`
- `nexphp make:controller`
- `nexphp make:middleware`
- `nexphp make:provider`
- `nexphp test`

Module commands:
- `nexphp make:model`
- `nexphp make:migration`
- `nexphp migrate`
- `nexphp migrate:status`
- `nexphp migrate:rollback`
- `nexphp make:request`
- `nexphp make:policy`
- `nexphp queue:work`
- `nexphp queue:failed`
- `nexphp schedule:run`

Commands should only appear when the relevant module is installed. Unknown commands should offer helpful suggestions.

### 35.2 Project skeleton

The official skeleton should include:
- Sensible directory layout.
- Minimal bootstrap.
- Example routes.
- Environment example file.
- Configuration files.
- Tests.
- Public entry point.
- CLI entry point.
- Git ignore rules.
- Basic security headers.
- Deployment examples.

Keep the default skeleton lean. Full-stack modules should be installable without requiring every project to use them.

### 35.3 Code generation

Generated files must:
- Be ordinary readable PHP.
- Follow documented conventions.
- Include only necessary imports and methods.
- Avoid unnecessary base classes.
- Include tests where useful.
- Be safe to regenerate only when the command explicitly supports it.
- Never overwrite user code without confirmation or a backup strategy.

## 36. Documentation system

### 36.1 Documentation structure

```text
docs/
├── getting-started/
├── installation/
├── fundamentals/
│   ├── application/
│   ├── configuration/
│   ├── routing/
│   ├── middleware/
│   ├── dependency-injection/
│   └── errors/
├── database/
├── validation/
├── authentication/
├── authorization/
├── runtime/
├── concurrency/
├── queues/
├── events/
├── scheduling/
├── websockets/
├── filesystem/
├── mail/
├── testing/
├── deployment/
├── security/
├── performance/
├── packages/
├── upgrades/
└── contributing/
```

### 36.2 Required documentation quality

Every feature guide should include:
- What the feature does.
- Installation requirements.
- Minimal example.
- Configuration.
- Common use cases.
- Error behavior.
- Security considerations.
- Testing guidance.
- Runtime compatibility.
- Links to relevant API references.

### 36.3 Learning paths

Create:
1. Five-minute first API.
2. Build a CRUD API.
3. Build authentication and authorization.
4. Build a database-backed SaaS module.
5. Use background jobs.
6. Build a real-time feature.
7. Deploy to PHP-FPM.
8. Deploy with persistent workers.
9. Write a NexPHP package.
10. Upgrade an application between major versions.

## 37. Official integrations and adapters

Prioritize integrations based on demand and maintenance capacity.

Potential official adapters:
- MySQL and PostgreSQL through PDO.
- Redis cache and queue.
- S3-compatible object storage.
- SMTP mail.
- OpenTelemetry.
- PSR-3 logging adapters.
- PSR-18 HTTP client adapters.
- FrankenPHP runtime.
- PHPUnit testing tools.
- Common authentication providers as separate packages.

Rules:
- Do not make every integration a core dependency.
- Maintain a compatibility matrix.
- Test adapters against real services in CI where possible.
- Define package ownership and maintenance expectations.
- Mark community-maintained adapters clearly.

## 38. Ecosystem compatibility program

Create a compatibility suite that tests representative:
- PSR loggers.
- PSR cache implementations.
- PSR HTTP clients.
- PSR containers.
- Composer libraries with no framework dependency.
- PDO drivers and supported database versions.

Publish a compatibility registry or documentation page with:
- Package name.
- Supported NexPHP versions.
- Tested PHP versions.
- Adapter required or not.
- Last tested date.
- Maintenance status.

Do not label a package "compatible" solely because it installs with Composer.

## 39. Upgrade and deprecation system

Before 1.0:
- Define semantic versioning.
- Define public versus internal APIs.
- Add deprecation notices.
- Provide upgrade guides.
- Test supported upgrade paths.
- Document configuration changes.
- Avoid breaking changes in patch and minor releases.

For major upgrades:
- Publish migration guides.
- Provide codemods or Rector rules where safe.
- Include automated configuration checks.
- Keep upgrade tooling optional and transparent.

## 40. Governance and community

Publish:
- `CONTRIBUTING.md`
- `CODE_OF_CONDUCT.md`
- `SECURITY.md`
- `SUPPORT.md`
- `GOVERNANCE.md`
- Maintainer responsibilities.
- RFC/ADR process.
- Issue and pull request templates.
- Release schedule.
- Version support policy.

Use an RFC process for major public API changes. Keep architecture decisions in the repository so decisions are not lost in chat or informal discussions.

## 41. Release engineering

### 41.1 Automated release pipeline

- Run tests and static analysis.
- Run dependency and security audits.
- Verify package metadata.
- Verify changelog.
- Build release artifacts where needed.
- Tag versions.
- Publish packages to Packagist.
- Publish documentation.
- Generate release notes.
- Announce security fixes separately.

### 41.2 Stable release criteria

NexPHP 1.0 should not be declared stable merely because all planned features exist.

Require:
- Public API review.
- Supported PHP/runtime/database matrix.
- Security review.
- Compatibility suite.
- Documentation completeness.
- Upgrade policy.
- Production deployment examples.
- Maintainer coverage for critical packages.
- No known critical/high defects.
- A release candidate period with real application feedback.

## 42. Real-world reference applications

Build and maintain at least three example applications:

### A. Minimal API
- Routing
- Validation
- Database
- Authentication
- Authorization
- Testing

### B. Business application
- Admin-style CRUD
- Roles and policies
- File uploads
- Mail notifications
- Background jobs
- Audit-friendly logging

### C. Real-time application
- Persistent runtime
- WebSockets
- Broadcasting
- Queue workers
- Presence or channel authorization
- Observability

Examples should be maintained against the current stable framework version. They are integration tests for the developer experience.

## 43. Phase 6 milestones

| Milestone | Work | Exit criteria |
|---|---|---|
| 0. CLI polish | Command UX and project generator | New project workflow is reliable |
| 1. Skeleton | Official minimal and optional full-stack templates | Example projects boot and test |
| 2. Documentation | Guides, API reference, tutorials | Required learning paths complete |
| 3. Integrations | Official adapters and compatibility matrix | Key integrations tested |
| 4. Upgrade tooling | Deprecations and migration guides | Supported upgrade path verified |
| 5. Governance | Contribution and security processes | Public policies published |
| 6. Release candidate | API freeze and real-world testing | RC feedback triaged |
| 7. NexPHP 1.0 | Stable release and packages | Release checklist complete |

## 44. Phase 6 definition of done

- [ ] `nexphp new` creates a working application.
- [ ] CLI commands are documented and tested.
- [ ] Official skeleton is lean and secure.
- [ ] Getting-started guide is complete.
- [ ] Core and module documentation is complete.
- [ ] API reference is generated and published.
- [ ] At least three reference applications work.
- [ ] Official adapters have compatibility tests.
- [ ] Upgrade and deprecation policies are published.
- [ ] Contribution and governance documents exist.
- [ ] Security reporting process is public.
- [ ] Release automation works.
- [ ] Release candidate feedback is resolved or documented.
- [ ] NexPHP 1.0 support matrix is published.

---

# 45. Cross-phase package map

This is the consolidated target package map across Phases 1–6. It is a destination, not a requirement to create every package immediately.

```text
packages/
├── contracts/
├── container/
├── config/
├── http/
├── routing/
├── middleware/
├── foundation/
├── console/
│
├── runtime-contracts/
├── runtime-fpm/
├── runtime-frankenphp/
├── runtime-cli/
├── runtime-worker/
│
├── concurrency-contracts/
├── concurrency/
├── event-loop-adapter/
│
├── database-contracts/
├── database/
├── database-migrations/
├── database-seeding/
├── database-validation/
│
├── validation/
├── auth-contracts/
├── auth/
├── auth-session/
├── auth-token/
├── authorization/
│
├── events/
├── queue-contracts/
├── queue/
├── queue-database/
├── queue-redis/
├── scheduler/
│
├── broadcasting-contracts/
├── broadcasting/
├── websocket/
│
├── filesystem-contracts/
├── filesystem/
├── filesystem-local/
├── filesystem-s3/
├── mail-contracts/
├── mail/
├── mail-smtp/
├── notifications/
│
├── serializer/
├── api-resources/
├── openapi/
├── health/
├── observability/
├── process/
├── worker-health/
└── testing/
```

## 45.1 Package rules

- Core packages must not depend on optional adapters.
- Adapter packages declare the third-party dependency they require.
- Public contracts should be small and stable.
- Avoid circular dependencies.
- Avoid service locator access in application code.
- Prefer constructor injection.
- Use package-level tests and cross-package integration tests.
- Do not split packages without a meaningful independent lifecycle.

---

# 46. Consolidated delivery roadmap

| Phase | Primary deliverable | Release gate |
|---|---|---|
| 1 | Core foundation | A working PHP-FPM HTTP application |
| 2 | Database and application essentials | Secure database-backed CRUD application |
| 3 | Runtime and concurrency | Safe persistent workers and tested async I/O |
| 4 | Application services | Queues, scheduler, storage, mail, events, real-time adapters |
| 5 | Production readiness | Security, observability, resilience, compatibility, benchmarks |
| 6 | Ecosystem and stable release | NexPHP 1.0 with docs, CLI, integrations, and support policy |

## 46.1 Recommended execution discipline

- Finish each phase's definition of done before declaring it complete.
- Keep an ADR for major architectural decisions.
- Maintain a working reference application throughout development.
- Prefer small vertical slices over large disconnected subsystems.
- Avoid adding features without tests and documentation.
- Reassess scope when package maintenance cost becomes excessive.
- Keep the framework usable in classic PHP-FPM mode even as modern runtime features mature.

---

# 47. Final NexPHP product promise

NexPHP should give PHP developers a modern, cohesive application platform without forcing them to abandon the PHP ecosystem.

**NexPHP: Build modern PHP applications with less boilerplate, strong defaults, runtime flexibility, and the freedom to use the Composer ecosystem.**

The goal is not to make PHP pretend to be another language. The goal is to make choosing PHP an excellent engineering decision.
