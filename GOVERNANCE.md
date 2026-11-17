# Project Governance & RFC Process

## Overview

NexPHP is an open-source project designed for modern PHP runtimes and AI "vibe coding". The governance model ensures technical excellence, backwards compatibility, and transparent decision-making.

---

## Decision-Making & RFC Process

For major architectural additions or breaking public API changes, contributors and maintainers must submit an **RFC (Request for Comments)**:

1. **RFC Document**: Propose the change via a GitHub Discussion or Markdown PR under `docs/rfcs/`.
2. **Review Period**: At least 14 days of open discussion covering:
   - Motivation and developer ergonomics.
   - Dual-runtime impact (PHP-FPM vs persistent workers).
   - Strict typing and AI readability.
   - Performance and benchmark impact.
3. **Consensus**: Core maintainers review and approve or reject based on alignment with the core philosophy: *"Less code. More power. Zero magic."*

---

## Semantic Versioning (SemVer)

NexPHP adheres strictly to Semantic Versioning 2.0.0:

- **Major (X.0.0):** Breaking API changes, major architectural shifts.
- **Minor (1.X.0):** Backwards-compatible new features, new packages, new CLI commands.
- **Patch (1.0.X):** Backwards-compatible bug fixes and security patches.
