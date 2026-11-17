# Contributing to NexPHP

Thank you for your interest in contributing to NexPHP! As a modern, AI-ergonomic PHP framework, our design philosophy is:
> **"Less code. More power. Zero magic."**

Every contribution should honor strict typing (PHP 8.3+), zero magic metaprogramming, self-healing error diagnostics, and dual-runtime safety (PHP-FPM and persistent workers).

---

## Code of Conduct

All contributors must adhere to our [Code of Conduct](file:///Users/snigdho/dev/php-projects/nexphp/CODE_OF_CONDUCT.md).

---

## Development Workflow

### 1. Requirements
- PHP 8.3 or higher (PHP 8.4+ and 8.5 tested)
- Composer 2.x
- SQLite3 PDO extension for running tests

### 2. Setup
```bash
git clone git@github.com:snigdho5/nexphp.git
cd nexphp
composer install
```

### 3. Running the Test Suite
NexPHP requires 100% green tests with 0 deprecations:
```bash
./vendor/bin/phpunit
```

To run individual package tests:
```bash
./vendor/bin/phpunit tests/Routing
./vendor/bin/phpunit tests/Queue
./vendor/bin/phpunit tests/Security
```

### 4. Running Benchmarks & Soak Tests
```bash
php benchmarks/RuntimeBenchmark.php
php benchmarks/WorkerSoakTest.php
```

---

## Architecture Principles for Contributions

1. **AI & Vibe-Coding First**:
   - Use declarative PHP 8 attributes (`#[Route]`, `#[Get]`, `#[Post]`, `#[Middleware]`).
   - Prefer explicit typed parameters over magic arrays.
   - When throwing exceptions, provide self-healing suggestions explaining what went wrong and how to fix it.

2. **Persistent Worker Safety**:
   - Never store request-specific state in static properties or long-lived singletons.
   - Scope request-bound dependencies in the container using `scoped()` and clean them up after each request lifecycle via `forgetScoped()`.

3. **Strict Zero-Magic**:
   - No hidden global state.
   - No untyped magic getters (`__get`, `__set`) in core abstractions.
   - PSR compliance where applicable (PSR-7, PSR-11, PSR-15, PSR-3).

---

## Pull Request Guidelines

1. Create a feature branch from `main`:
   ```bash
   git checkout -b feat/my-improvement
   ```
2. Write unit tests covering your changes in `tests/`.
3. Verify all tests pass without deprecation warnings.
4. Keep commits clean and descriptive (Conventional Commits: `feat:`, `fix:`, `docs:`, `test:`, `perf:`).
5. Open a Pull Request on GitHub with a clear description of the problem solved.
