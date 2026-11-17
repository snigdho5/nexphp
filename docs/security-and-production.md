# Production Deployment & Security Guide

This guide outlines security configurations and production deployment practices for NexPHP.

---

## 1. Production Security Checklist

- [ ] **Request Size Limiting:** Register `RequestSizeLimitMiddleware` with an appropriate maximum byte limit (e.g. 10MB) to prevent memory DoS attacks.
- [ ] **CORS Configuration:** Use `CorsMiddleware` specifying explicit allowed origins. Avoid wildcard `*` if `allowCredentials: true`.
- [ ] **CSRF Protection:** Protect web routes using `CsrfMiddleware` for all state-changing requests (`POST`, `PUT`, `PATCH`, `DELETE`).
- [ ] **Rate Limiting:** Attach `ThrottleRequestsMiddleware` to public authentication and resource endpoints to prevent brute-force attacks.
- [ ] **Security Headers:** Enforce `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and strict referrer policies via `SecurityHeadersMiddleware`.
- [ ] **Logging Redaction:** Use `StructuredLogger` with `Redactor` to prevent credentials, tokens, or card numbers from leaking into logs.

---

## 2. Health & Readiness Probes

Expose separate endpoints for orchestrators (Kubernetes, AWS ECS, Nomad, Docker Swarm):

```php
use Nex\Health\HealthManager;
use Nex\Health\DatabaseHealthCheck;
use Nex\Health\StorageHealthCheck;
use Nex\Http\Responses\JsonResponse;

$health = new HealthManager();
$health->register(new DatabaseHealthCheck($db));
$health->register(new StorageHealthCheck($filesystem));

// Liveness probe (is worker alive?)
$app->get('/health/live', function () use ($health) {
    return new JsonResponse($health->liveness(), 200);
});

// Readiness probe (can worker accept traffic?)
$app->get('/health/ready', function () use ($health) {
    $report = $health->readiness();
    $status = $report['status'] === 'unhealthy' ? 503 : 200;
    return new JsonResponse($report, $status);
});
```

---

## 3. Persistent Worker Deployment with Supervisor

For persistent worker mode, supervise `bin/nex worker:run`:

```ini
[program:nexphp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nexphp/bin/nex worker:run
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/nexphp/worker.log
```

---

## 4. Background Queue Worker Deployment

```ini
[program:nexphp-queue]
command=php /var/www/nexphp/bin/nex queue:work default
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/nexphp/queue.log
```
