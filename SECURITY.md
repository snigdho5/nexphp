# Security Policy

## Supported Versions

Only the latest active release branch receives security patches.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

---

## Reporting a Vulnerability

We take the security of NexPHP and its users very seriously. If you believe you have found a security vulnerability, please follow responsible disclosure guidelines.

**Do NOT report security vulnerabilities via public GitHub issues.**

Instead, please report security vulnerabilities via private security advisory on GitHub or email:
- **Email:** `mail@nexphp.com`
- **Website:** [https://nexphp.com](https://nexphp.com)

### What to include in your report:
1. Detailed description of the vulnerability and attack vector.
2. Step-by-step reproduction instructions or a minimal Proof of Concept (PoC).
3. The affected packages, classes, and versions.
4. Proposed mitigation or patch if available.

### Response SLA:
- **Initial Acknowledgement:** Within 48 hours.
- **Triage & Severity Assessment:** Within 5 business days.
- **Remediation & Patch Release:** Coordinated disclosure target within 30 days.

---

## Threat Model & Security Architecture

NexPHP enforces "Secure by Default" across all framework boundaries:

### 1. HTTP Layer
- **Request Size Limiting:** `RequestSizeLimitMiddleware` enforces body caps to prevent memory DOS.
- **Cross-Origin Resource Sharing:** `CorsMiddleware` prohibits wildcard credentials and restricts allowed origins.
- **CSRF Protection:** `CsrfMiddleware` strictly validates tokens on state-changing methods (`POST`, `PUT`, `PATCH`, `DELETE`).
- **Security Headers:** `SecurityHeadersMiddleware` sets `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and strict referrer policies.
- **Rate Limiting:** `ThrottleRequestsMiddleware` provides sliding-window throttling with IP / signature hashing.

### 2. Database & SQL
- All queries constructed by `QueryBuilder` use parameterized prepared statements (`?` bindings).
- Identifiers (table names, column names) are sanitized and wrapped.
- Connection strings and credentials are never stored in plain text or rendered in exception outputs.

### 3. Queue & Background Jobs
- Arbitrary PHP object serialization (`serialize()` / `unserialize()`) is **strictly forbidden**.
- Jobs serialize strictly as JSON DTOs containing scalar values, arrays, and entity IDs.
- Closures and open resources in job payloads are rejected at push time.

### 4. Storage & Filesystem
- `LocalFilesystem` prevents path traversal attacks by resolving and asserting canonical paths against the storage root.
- User-supplied filenames are never executed directly from storage directories.

### 5. Observability & Logging
- `StructuredLogger` and `Redactor` automatically scrub sensitive keys (`password`, `token`, `secret`, `authorization`, `credit_card`) from structured contexts before writing to logs.
- Health endpoints (`/health/live`, `/health/ready`) separate liveness from readiness and expose zero internal credentials or topology.
