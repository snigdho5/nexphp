# Next-Generation PHP Framework
## Phase 2 — Application Essentials: Database, Validation, Authentication & Authorization

**Status:** Implementation plan  
**Prerequisite:** Phase 1 framework core is complete and its public contracts are stable  
**Architecture decisions:** Hybrid · Maximum practical compatibility · Minimal & AI-first DX ("vibe coding") · PHP-FPM and persistent-worker runtimes  
**Primary outcome:** Enable developers and AI assistants to build secure, database-backed CRUD applications and REST APIs without requiring third-party framework integrations.

---

# 1. Executive summary

Phase 2 turns the Phase 1 HTTP foundation into a practical application framework.

It introduces four essential capabilities:

1. **Database:** connection management, query builder, transactions, migrations, seeders, and an initial model layer.
2. **Validation:** reusable validation rules, validated input objects, DTO mapping, and consistent error responses.
3. **Authentication:** authentication contracts, session-based authentication, password hashing, and token authentication through a clearly scoped adapter.
4. **Authorization:** policies, abilities, gates, and middleware for resource-level access control.

The objective is not to build every database feature or authentication protocol at once. The objective is to provide a secure, testable, predictable baseline that can support real business applications.

## 1.1 Phase 2 outcomes

At the end of this phase, a developer should be able to:

- Configure a MySQL or PostgreSQL database.
- Run and roll back migrations.
- Create and retrieve records using a query builder.
- Use transactions safely.
- Validate incoming API and browser requests.
- Register and authenticate users.
- Hash and verify passwords using PHP's password APIs.
- Protect routes with authentication middleware.
- Enforce authorization through policies and abilities.
- Write integration tests without depending on a live external service for every test.
- Run the same application under PHP-FPM and the Phase 1-compatible runtime model.

## 1.2 Out of scope

Do not include these in Phase 2 unless a specific use case requires them:

- Full async database drivers
- Distributed transactions
- Multi-region database orchestration
- GraphQL
- OAuth authorization server implementation
- Social login provider integrations
- Multi-factor authentication UI
- Full admin panel
- Advanced search engine integration
- Queue-based authentication workflows
- A complete replacement for mature database engines or drivers

These can be added later as optional modules.

---

# 2. Prerequisites and Phase 1 contract freeze

Before starting Phase 2:

- Phase 1 CI must pass on every supported PHP version.
- The application kernel, container, configuration, HTTP, routing, and middleware contracts must be documented.
- Request-scope cleanup must be tested with multiple requests through one kernel.
- The framework must have a stable exception and response model.
- Package dependency rules must be enforced in CI.
- A basic application skeleton must be available.

Phase 2 may add new interfaces, but it should not casually break Phase 1 public contracts. Any breaking change requires an architecture decision record (ADR), migration notes, and a versioning decision.

---

# 3. Phase 2 package architecture

Add or complete the following packages:

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
├── runtime-contracts/
├── runtime-fpm/
├── runtime-frankenphp/
├── database-contracts/
├── database/
├── database-migrations/
├── database-seeding/
├── validation/
├── auth-contracts/
├── auth/
├── auth-session/
├── auth-token/
├── authorization/
└── testing/
```

The final package boundaries may be consolidated if independent packages would create unnecessary complexity. Keep contracts separate when they are consumed by multiple adapters or implementations.

## 3.1 Package responsibilities

| Package | Responsibility |
|---|---|
| `database-contracts` | Connection, transaction, query, and migration-facing interfaces |
| `database` | Connection manager, PDO adapter, query builder, result mapping |
| `database-migrations` | Migration discovery, execution, rollback, status |
| `database-seeding` | Seeder execution and development/test data |
| `validation` | Rule engine, input validation, errors, DTO mapping |
| `auth-contracts` | User identity, guard, authenticator, credential contracts |
| `auth` | Authentication manager, guard resolution, password services |
| `auth-session` | Session guard and browser login lifecycle |
| `auth-token` | Token authentication adapter and token persistence abstraction |
| `authorization` | Policies, abilities, gates, and authorization middleware |
| `testing` | Database test helpers, fakes, authenticated test requests |

## 3.2 Dependency direction

```text
                    Application
                         |
          +--------------+---------------+
          |              |               |
       Database       Validation       Auth / Access
          |              |               |
   Database Contracts  Contracts    Auth Contracts
          |              |               |
          +--------------+---------------+
                         |
                    Framework Core
                         |
                    Runtime Layer
```

Rules:

- `database` must not depend on `auth`.
- `validation` must not depend on `database`.
- `auth` may depend on core HTTP, container, configuration, and contracts.
- `authorization` should depend on identity and authorization contracts, not on a specific database implementation.
- `auth-session` and `auth-token` are adapters around the authentication contracts.
- The core application kernel must not require any Phase 2 package.

---

# 4. Database subsystem

## 4.1 Design goals

- PDO-based initial implementation.
- MySQL and PostgreSQL as initial supported database engines.
- SQLite support for tests and lightweight applications where practical.
- Prepared statements by default.
- Explicit transaction boundaries.
- Predictable query behavior.
- No hidden lazy-loading surprises in the initial model layer.
- Connection lifecycle compatible with persistent workers.
- Clear extension points for other database drivers.

## 4.2 Initial database capabilities

### Connection management

- Named connections.
- Environment-based configuration.
- PDO connection creation.
- Connection health checks.
- Connection reconnection policy where safe.
- Connection options and driver-specific configuration.
- Explicit read/write configuration as a later enhancement.

Example configuration:

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
        ],
    ],
];
```

The `env()` helper is illustrative. Its exact behavior should be defined in the configuration package. Secrets must not be committed to source control.

### Query builder

Initial operations:

- `select`
- `insert`
- `update`
- `delete`
- `where`
- `whereIn`
- `whereNull`
- `orderBy`
- `limit`
- `offset`
- `join` (only after safe SQL compilation and tests)
- `count`
- `exists`
- pagination helpers

Illustrative API:

```php
$users = DB::table('users')
    ->select(['id', 'name', 'email'])
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();
```

The query builder must use parameter binding for values. Identifiers such as table and column names cannot generally be parameter-bound; validate or quote identifiers using driver-aware rules and never accept arbitrary user input as an identifier.

### Transactions

```php
DB::transaction(function () use ($input) {
    $userId = DB::table('users')->insertGetId([
        'name' => $input['name'],
        'email' => $input['email'],
    ]);

    DB::table('profiles')->insert([
        'user_id' => $userId,
    ]);
});
```

Requirements:

- Commit on successful completion.
- Roll back when an exception escapes.
- Preserve the original exception if rollback also fails, while recording rollback failure.
- Define nested transaction behavior.
- Use savepoints where supported, or explicitly reject unsupported nesting.
- Do not silently swallow database exceptions.

## 4.3 Core database contracts

Proposed interfaces:

```php
namespace Acme\Framework\Database\Contracts;

interface ConnectionInterface
{
    public function select(string $sql, array $bindings = []): array;

    public function statement(string $sql, array $bindings = []): bool;

    public function insert(string $sql, array $bindings = []): bool;

    public function lastInsertId(): string|false;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    public function driverName(): string;
}
```

A higher-level manager:

```php
interface DatabaseManagerInterface
{
    public function connection(?string $name = null): ConnectionInterface;

    public function transaction(callable $callback, ?string $connection = null): mixed;
}
```

A query builder contract may expose a fluent builder interface, but avoid forcing every driver to implement a large interface if a smaller compiler/connection strategy is cleaner.

## 4.4 PDO adapter

The initial production adapter should use PDO.

Responsibilities:

- Configure error mode to exceptions.
- Configure fetch behavior explicitly.
- Disable emulated prepares where supported and appropriate.
- Bind values with correct PDO parameter types.
- Handle driver-specific capabilities.
- Avoid logging raw secrets or sensitive parameter values.
- Ensure connection state is safe across persistent requests.

PDO is a synchronous API. It must not be advertised as non-blocking. Async database support belongs to a later runtime/driver integration.

## 4.5 Migrations

Migration requirements:

- Timestamped migration files.
- `up()` and `down()` methods.
- Migration repository/table.
- Batch tracking.
- Status command.
- Run pending migrations.
- Roll back the last batch.
- Roll back a specified number of migrations.
- Driver-aware schema operations.
- Locking or another mechanism to prevent concurrent migration runners.

Illustrative migration:

```php
return new class {
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255)->unique();
            $table->string('password_hash');
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('users');
    }
};
```

This is proposed syntax. The schema builder must compile to supported SQL for each driver.

Migration safety:

- Never run destructive migrations automatically during application startup.
- Require an explicit CLI command.
- Clearly warn before destructive operations.
- Make migration execution observable.
- Document that rollback code may not perfectly restore data.
- Test migration behavior separately for each supported database.

## 4.6 Seeders

Provide:
- Seeder base contract.
- Seeder discovery.
- Ordered execution.
- Environment-aware safeguards.
- Test database seeding.
- Optional factory integration in a later increment.

Never run production seeders implicitly.

## 4.7 Model layer: deliberately limited first version

The framework may introduce a small active-record-style model API after the query builder is stable.

Illustrative target:

```php
$user = User::find(42);

$user->name = 'Alex';
$user->save();

$users = User::where('status', 'active')->get();
```

Model requirements:

- Explicit table and primary-key configuration.
- Mass-assignment protection.
- Casts for common scalar and date types.
- Timestamp support.
- Relationship definitions only after query behavior is well specified.
- No implicit lazy loading by default.
- No hidden writes during serialization.
- Clear distinction between persisted and unpersisted models.

Do not attempt to clone every Eloquent feature in Phase 2. Prioritize query correctness, safety, and understandable behavior.

---

# 5. Validation subsystem

## 5.1 Design goals

- Validate at the HTTP boundary.
- Separate untrusted input from validated data.
- Support reusable rule definitions.
- Produce consistent JSON and browser error responses.
- Support nested fields and arrays.
- Provide custom rules.
- Avoid coupling validation to the database package.
- Allow DTO mapping after successful validation.

## 5.2 Initial rule set

| Rule | Purpose |
|---|---|
| `required` | Field must be present and non-empty |
| `nullable` | Null is accepted |
| `string` | String type |
| `integer` | Integer validation |
| `numeric` | Numeric validation |
| `boolean` | Boolean validation |
| `email` | Email format |
| `url` | URL format |
| `min` / `max` | Length or numeric boundaries |
| `between` | Range validation |
| `in` | Allowed values |
| `not_in` | Disallowed values |
| `confirmed` | Confirmation field comparison |
| `same` / `different` | Cross-field comparison |
| `array` | Array type |
| `date` | Date validation |
| `before` / `after` | Date comparison |
| `regex` | Pattern validation |
| `unique` | Database-backed uniqueness adapter |
| `exists` | Database-backed existence adapter |
| `file` | Uploaded-file validation |
| `mimes` | File type validation |
| `max_size` | File size limit |

Database-dependent rules such as `unique` and `exists` should be implemented through an optional database validation adapter. The base validator should remain usable without a database.

## 5.3 Validation contracts

```php
namespace Acme\Framework\Validation\Contracts;

interface ValidatorInterface
{
    public function validate(array $data, array $rules): ValidationResultInterface;
}
```

```php
interface ValidationResultInterface
{
    public function passes(): bool;

    public function validated(): array;

    public function errors(): array;
}
```

The result should be immutable after validation. Avoid exposing the original untrusted input as if it were validated data.

## 5.4 Request validation API

Illustrative target:

```php
final class CreateUserRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12'],
        ];
    }
}
```

Controller:

```php
public function store(CreateUserRequest $request): array
{
    $data = $request->validated();

    // Only validated fields reach this point.
    return [
        'created' => true,
    ];
}
```

The framework should resolve and validate request classes before invoking the controller. If validation fails, it should return a structured validation exception that the HTTP exception handler maps to the correct response.

## 5.5 Error response format

Recommended API response:

```json
{
  "type": "validation_error",
  "message": "The submitted data is invalid.",
  "errors": {
    "email": [
      "The email field must be a valid email address."
    ]
  }
}
```

Requirements:

- Stable error keys.
- Configurable localization.
- No sensitive submitted values in error output.
- Appropriate HTTP status, normally 422 for validation failures.
- Browser form behavior can be added through a separate response adapter.

---

# 6. Authentication subsystem

## 6.1 Design principles

- Authentication and authorization are separate.
- Identity resolution is independent of the user storage implementation.
- Passwords are never stored in plaintext.
- Use PHP's `password_hash()` and `password_verify()` APIs.
- Session and token authentication are separate adapters.
- Tokens must be stored securely and revocable.
- Authentication state must be request-scoped in persistent-worker mode.

## 6.2 Core concepts

- **Authenticatable:** an identity that can be authenticated.
- **Guard:** determines how the current request is authenticated.
- **Authenticator:** verifies a credential or authentication proof.
- **User provider:** retrieves identities from a configured source.
- **Authentication manager:** resolves the configured guard.
- **Credential verifier:** verifies passwords or other credentials.

## 6.3 Authentication contracts

Illustrative contracts:

```php
namespace Acme\Framework\Auth\Contracts;

interface AuthenticatableInterface
{
    public function getAuthIdentifier(): string|int;
}
```

```php
interface GuardInterface
{
    public function check(): bool;

    public function user(): ?AuthenticatableInterface;

    public function id(): string|int|null;

    public function validate(array $credentials): bool;
}
```

```php
interface UserProviderInterface
{
    public function retrieveById(string|int $identifier): ?AuthenticatableInterface;

    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;

    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials
    ): bool;
}
```

The exact method signatures should be refined to avoid leaking password fields or arbitrary credentials into logs and generic data structures.

## 6.4 Password security

Requirements:

- Use `password_hash()` with `PASSWORD_DEFAULT` unless a reviewed configuration requires otherwise.
- Verify with `password_verify()`.
- Rehash with `password_needs_rehash()` when appropriate.
- Never log passwords or raw authentication credentials.
- Apply login rate limiting.
- Use generic failure messages to reduce account enumeration.
- Enforce password policy at registration and password change boundaries.
- Avoid custom cryptographic algorithms.

## 6.5 Session authentication

The session adapter should support:
- Login and logout.
- Session regeneration after successful authentication.
- Session invalidation on logout.
- Secure, HttpOnly, and SameSite cookie configuration.
- CSRF protection for state-changing browser requests.
- Idle and absolute expiration policies.
- Session storage abstraction.
- Request-scoped current identity.

Session handling must be compatible with the framework's runtime model. Do not keep a current user in a process-wide singleton.

## 6.6 Token authentication

Token authentication should be optional and separate from browser sessions.

Initial token requirements:
- Cryptographically secure random token generation.
- Store only a token hash or digest, not the raw token.
- Show the raw token only once at creation.
- Support expiry and revocation.
- Support abilities/scopes if needed.
- Use constant-time hash comparison where applicable.
- Never put tokens in URLs.
- Avoid logging Authorization headers.
- Provide token rotation/revocation strategy.

Do not build a full OAuth 2.0 authorization server in this phase. Support simple personal access tokens only if the security review approves the storage and lifecycle design.

## 6.7 Authentication middleware

Illustrative usage:

```php
$app->get('/account', [AccountController::class, 'show'])
    ->middleware('auth');
```

Expected behavior:
- Resolve the configured guard.
- Authenticate the request.
- Attach the identity to request context.
- Return 401 for unauthenticated API requests.
- Use a redirect response only for browser routes with an explicit browser-auth policy.

---

# 7. Authorization subsystem

## 7.1 Design goals

Authorization should be explicit, composable, and resource-aware.

Support:
- Abilities
- Policies
- Role and permission adapters
- Route middleware
- Controller-level checks
- Resource-level authorization

Do not make roles the only authorization model. Policies and abilities should support decisions based on the current user and the resource.

## 7.2 Core authorization contracts

```php
namespace Acme\Framework\Authorization\Contracts;

interface GateInterface
{
    public function allows(string $ability, mixed ...$arguments): bool;

    public function denies(string $ability, mixed ...$arguments): bool;

    public function authorize(string $ability, mixed ...$arguments): void;
}
```

```php
interface PolicyInterface
{
    // Concrete policies define methods for supported abilities.
}
```

The policy interface may remain a marker or be replaced with a resolver contract. Avoid forcing every policy into a generic method if typed methods provide better clarity.

## 7.3 Example policy

```php
final class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->is_admin
            || $user->id === $post->user_id;
    }
}
```

Illustrative controller use:

```php
$this->authorize('update', $post);
```

## 7.4 Authorization middleware

Example:

```php
$app->delete('/posts/{post}', [PostController::class, 'destroy'])
    ->middleware(['auth', 'can:delete,post']);
```

Requirements:
- Denials return 403 for authenticated users.
- Missing authentication returns 401 or the configured browser redirect.
- Policy resolution is deterministic.
- Authorization checks are testable.
- Resource ownership and tenant boundaries are explicitly checked.

---

# 8. CLI and developer workflow

Add Phase 2 commands through module-owned command providers.

## 8.1 Database commands

- `db:connect` or `db:check`
- `make:migration`
- `migrate`
- `migrate:status`
- `migrate:rollback`
- `db:seed`

## 8.2 Application generation commands

- `make:model`
- `make:request`
- `make:policy`
- `make:seeder`

Avoid generating code that developers cannot understand or maintain. Generated files should be plain PHP with minimal hidden conventions.

## 8.3 Example workflow

```bash
composer create-project acme/framework-skeleton my-app
cd my-app

php framework make:migration create_users_table
php framework migrate

php framework make:model User
php framework make:request CreateUserRequest
php framework make:policy PostPolicy

php framework test
```

These commands are proposed names and should be finalized with the CLI package.

---

# 9. Testing strategy

## 9.1 Database tests

- Query builder compilation tests for every supported driver.
- Prepared-statement binding tests.
- Transaction commit and rollback tests.
- Nested transaction/savepoint tests.
- Migration up/down tests.
- Connection failure and timeout tests.
- Persistent-worker connection lifecycle tests.
- SQL injection regression tests.
- Query identifier validation tests.

Use SQLite for fast tests where behavior is equivalent, but run integration tests against real MySQL and PostgreSQL instances. SQLite cannot prove that driver-specific SQL behaves correctly.

## 9.2 Validation tests

- Every built-in rule.
- Nested arrays and wildcard paths.
- Missing versus null values.
- Type coercion policy.
- Unicode and multibyte strings.
- Custom rules.
- Localization.
- File upload edge cases.
- Stable JSON error format.

## 9.3 Authentication tests

- Password hash and verification.
- Login success and failure.
- Generic error responses.
- Session regeneration.
- Logout invalidation.
- Session expiry.
- Token creation, hashing, expiry, revocation.
- Rate limiting.
- Request-scope identity cleanup across repeated worker requests.

## 9.4 Authorization tests

- Allowed and denied abilities.
- Policy resolution.
- Missing policy behavior.
- Unauthenticated versus forbidden response.
- Tenant boundary enforcement.
- Resource ownership checks.
- Route middleware integration.

## 9.5 Testing utilities

Provide:
- Database transaction test helper.
- Database refresh helper for isolated test databases.
- Fake authentication guard.
- Authenticated HTTP request helper.
- Policy and gate assertions.
- Validation assertions.
- Time-freezing helper.

A test helper must not silently point at a production database. Require explicit test environment safeguards.

---

# 10. Security requirements and threat model

Phase 2 introduces sensitive data and therefore requires a focused security review.

## 10.1 Database threats

- SQL injection
- Unsafe identifier interpolation
- Credential leakage
- Excessive database privileges
- Transaction misuse
- Connection state leakage in long-running workers

Mitigations:
- Prepared statements.
- Driver-aware identifier quoting/validation.
- Least-privilege database accounts.
- Secret redaction.
- Explicit transaction handling.
- Request cleanup and connection lifecycle tests.

## 10.2 Validation threats

- Trusting unvalidated input
- Mass assignment
- Type confusion
- Oversized request payloads
- Unsafe file uploads
- Validation bypass through alternate input sources

Mitigations:
- Validated-data object.
- Explicit fillable/guarded model properties.
- Request size limits at server and application layers.
- Strict file handling.
- One defined input normalization pipeline.

## 10.3 Authentication threats

- Password disclosure
- Credential stuffing
- Brute-force attempts
- Session fixation
- Session hijacking
- Token theft
- Account enumeration
- Insecure logout

Mitigations:
- PHP password APIs.
- Rate limiting.
- Session ID rotation.
- Secure cookie flags.
- Token hashing and revocation.
- Generic authentication failures.
- Security event logging without credentials.

## 10.4 Authorization threats

- Insecure direct object references
- Missing ownership checks
- Tenant data leakage
- Confusing authentication with authorization
- Fail-open policy resolution

Mitigations:
- Explicit policy checks.
- Deny by default for unknown protected abilities.
- Tenant-aware query constraints.
- Integration tests for cross-user and cross-tenant access.

---

# 11. Phase 2 milestones and work breakdown

## Milestone 0 — Design and contract review

Tasks:
- Review Phase 1 public contracts.
- Record ADRs for database abstraction, validation result model, identity model, and session storage.
- Decide supported database versions.
- Decide whether the first ORM is active-record, data-mapper, or a limited hybrid.
- Define authentication threat model.
- Freeze Phase 2 package dependency rules.

Exit criteria:
- Contracts reviewed.
- Database and auth data flows documented.
- No unresolved architectural blocker.

## Milestone 1 — Database connections and query execution

Tasks:
- Implement PDO connection factory.
- Implement named connection manager.
- Add configuration validation.
- Implement prepared-statement execution.
- Implement typed parameter binding.
- Add transaction support.
- Add connection lifecycle hooks.
- Add MySQL and PostgreSQL CI services.

Exit criteria:
- Can connect to supported databases.
- Safe parameter binding works.
- Transactions commit and roll back.
- Connection failures produce useful exceptions.
- Persistent-worker lifecycle tests pass.

## Milestone 2 — Query builder

Tasks:
- Implement select, insert, update, delete.
- Implement where, whereIn, whereNull.
- Implement orderBy, limit, offset.
- Implement count and exists.
- Implement pagination primitives.
- Add driver-specific SQL compilation.
- Add identifier validation and quoting.

Exit criteria:
- Query builder tests pass for MySQL and PostgreSQL.
- Values are parameter-bound.
- Invalid identifiers are rejected.
- Query behavior is documented.

## Milestone 3 — Migrations and seeders

Tasks:
- Define migration contract.
- Build migration repository.
- Implement migration discovery and execution.
- Implement rollback and status commands.
- Add schema builder for common column types and indexes.
- Add migration locking.
- Implement seeder runner.
- Add destructive-operation safeguards.

Exit criteria:
- Fresh database can be migrated.
- Rollback works for supported operations.
- Migration status is accurate.
- Concurrent migration execution is safely handled.
- Production safeguards are tested.

## Milestone 4 — Validation engine

Tasks:
- Define validation contracts.
- Implement built-in rules.
- Implement nested field paths.
- Implement custom rules.
- Add localization hooks.
- Add request validation integration.
- Add structured validation exceptions.
- Add JSON response mapping.
- Add optional database validation adapter.

Exit criteria:
- Common API request validation works.
- Only validated data is exposed to application handlers.
- Error format is stable.
- Validation works without a database package.

## Milestone 5 — Authentication

Tasks:
- Define authenticatable and guard contracts.
- Implement authentication manager.
- Implement user provider abstraction.
- Implement password hashing and verification.
- Implement session guard.
- Add session regeneration and invalidation.
- Add login rate-limit integration.
- Optionally implement personal access tokens after review.
- Add request identity context.

Exit criteria:
- User can register and log in in a sample application.
- Passwords are hashed.
- Session fixation protections work.
- Logout invalidates the session.
- Authentication state does not leak between worker requests.

## Milestone 6 — Authorization

Tasks:
- Define gate and policy resolution.
- Implement abilities.
- Implement policy registration.
- Implement authorization middleware.
- Add controller authorization helper.
- Add deny-by-default behavior for unresolved protected abilities.
- Add tenant/ownership examples.

Exit criteria:
- Protected routes enforce authorization.
- Policies can evaluate resource ownership.
- 401 and 403 behavior is correct.
- Cross-user access regression tests pass.

## Milestone 7 — CLI, testing tools, and documentation

Tasks:
- Add migration/model/request/policy/seeder generators.
- Add database testing helpers.
- Add fake authentication and authenticated request helpers.
- Add end-to-end CRUD example.
- Write database, validation, auth, and authorization documentation.
- Run dependency and security audits.
- Prepare release notes and upgrade notes.

Exit criteria:
- A developer can build and test a secure database-backed CRUD API from the skeleton.
- CI passes across the declared PHP matrix and database services.
- Security review has no unresolved critical/high findings.
- Phase 2 release candidate is tagged.

---

# 12. Suggested implementation order

```text
Phase 1 stable core
        |
        v
Database contracts
        |
        v
PDO connections and transactions
        |
        v
Query builder
        |
        v
Migrations and seeders
        |
        +----------------------+
        |                      |
        v                      v
Validation engine       Auth contracts
        |                      |
        v                      v
Request validation      Session authentication
        |                      |
        |                 Token adapter (optional)
        |                      |
        +-----------+----------+
                    |
                    v
               Authorization
                    |
                    v
             CLI and test tools
                    |
                    v
             End-to-end example
                    |
                    v
                Phase 2 RC
```

Validation and authentication can proceed in parallel after their contracts are agreed. Authorization should follow the identity model so it does not need to invent its own user abstraction.

---

# 13. Phase 2 definition of done

## Database
- [ ] MySQL and PostgreSQL connection adapters work.
- [ ] SQLite test support is available if included in the support matrix.
- [ ] Prepared statements are used for values.
- [ ] Transactions commit and roll back correctly.
- [ ] Query builder covers common CRUD operations.
- [ ] Migrations can run, report status, and roll back.
- [ ] Seeders run only when explicitly requested.
- [ ] Database connections behave correctly in persistent workers.

## Validation
- [ ] Built-in rules are documented and tested.
- [ ] Nested input is supported.
- [ ] Validated data is distinct from raw input.
- [ ] Custom rules are supported.
- [ ] API errors use a stable format.
- [ ] Validation can run without the database module.

## Authentication
- [ ] Passwords use PHP's password APIs.
- [ ] Session login and logout work.
- [ ] Session IDs are regenerated after authentication.
- [ ] Secure cookie settings are available.
- [ ] Login rate limiting can be applied.
- [ ] Authentication state is request-scoped.
- [ ] Token authentication, if shipped, stores only token hashes and supports revocation.

## Authorization
- [ ] Policies and abilities work.
- [ ] Route middleware enforces authorization.
- [ ] Unauthenticated and unauthorized responses are distinct.
- [ ] Resource ownership checks are testable.
- [ ] Unknown protected abilities fail closed.

## Quality
- [ ] Unit and integration tests pass.
- [ ] MySQL and PostgreSQL integration tests pass.
- [ ] Supported PHP version matrix passes.
- [ ] Static analysis and dependency audit pass.
- [ ] Documentation and migration notes are complete.
- [ ] No unresolved critical or high security issues remain.

---

# 14. Example application after Phase 2

The target experience is a compact, secure CRUD API.

Illustrative route:

```php
$app->post('/api/users', [UserController::class, 'store'])
    ->middleware('auth');
```

Illustrative controller:

```php
final class UserController
{
    public function store(CreateUserRequest $request): array
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => password_hash(
                    $data['password'],
                    PASSWORD_DEFAULT
                ),
            ]);
        });

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
```

This example illustrates the intended developer experience. The final implementation should use a dedicated password service, avoid returning sensitive model fields, and define whether this endpoint is public registration or an authenticated administrative action.

A complete sample application should additionally demonstrate:
- Login and logout.
- Authenticated profile retrieval.
- Policy-protected resource update.
- Validation error responses.
- Migration and rollback.
- Database integration tests.
- Persistent-worker request isolation tests.

---

# 15. Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| ORM scope grows too large | Delays core features | Start with query builder and limited model layer |
| Driver behavior differs | Portability bugs | Real MySQL/PostgreSQL CI and driver-specific compilers |
| SQL injection through identifiers | Critical security issue | Identifier validation and driver-aware quoting |
| Persistent PDO state leaks | Cross-request data exposure | Request cleanup and connection lifecycle tests |
| Validation coercion is surprising | Data integrity bugs | Document strictness and avoid implicit coercion |
| Session identity leaks between requests | Account compromise | Request-scoped identity and multi-request worker tests |
| Token storage is unsafe | Credential compromise | Store token hashes, expiry, revocation, security review |
| Authorization defaults fail open | Data exposure | Deny by default and test ownership/tenant boundaries |
| Too many packages too early | Maintenance burden | Extract packages only where independent contracts are useful |

---

# 16. Deliverables

At the end of Phase 2, publish:

1. Database package and supported-driver matrix.
2. Query builder and transaction documentation.
3. Migration and seeder CLI.
4. Validation package and rule reference.
5. Authentication contracts and session adapter.
6. Optional personal access token adapter, if approved.
7. Authorization policy and gate system.
8. Testing utilities for database and authenticated requests.
9. A secure CRUD API example application.
10. Phase 2 release notes, upgrade notes, and security review summary.

---

# 17. Recommended next steps after Phase 2

Phase 3 should focus on runtime and performance:

- Fiber-based task abstraction.
- Event-loop integration.
- Async HTTP client adapters.
- Worker lifecycle and supervision.
- Persistent-worker memory and state safety.
- Runtime capability discovery.
- Performance benchmarks and profiling.

Do not make the database package appear asynchronous by wrapping synchronous PDO calls in an `await`-like API. Async database support should be a separately tested driver integration.

---

# Final Phase 2 objective

**Turn the framework core into a secure application platform.**

A developer should be able to create a database-backed API, validate input, authenticate users, enforce authorization, run migrations, and test the complete application—without adopting another framework's ecosystem or sacrificing Composer compatibility.
