# NexPHP — AI & "Vibe Coding" Design Guidelines

## Core Objective
NexPHP is architected from day one to be **exceptionally ergonomic for AI code generation ("vibe coding") and human developers alike**.

When a developer prompts an AI coding assistant (Cursor, Antigravity, Copilot, Claude, Gemini) to build a feature, an endpoint, or a full app in NexPHP, the framework must ensure the AI generates working, bug-free, and secure code on the first attempt without getting trapped in boilerplate, configuration sprawl, or runtime hallucinations.

---

## The 7 Tenets of Vibe-Code-Friendly Architecture

### 1. Strict Typing Over Magic Metaprogramming
- **Rule:** Use native PHP 8.3+ types everywhere (`typed properties`, `enums`, `readonly classes`, explicit return types).
- **Why for AI:** AI models hallucinate when frameworks rely on dynamic `__call`, magic getters/setters, or untyped property bags. Strict typing provides clear LSP introspection, allowing AI to infer exact types and autocompletes without guesswork.

### 2. Declarative Attributes Over Fragmented Configs
- **Rule:** Colocate routing, middleware, validation, and documentation directly on classes and methods using native PHP attributes (`#[Get('/api/v1/users')]`, `#[Middleware(AuthMiddleware::class)]`).
- **Why for AI:** AI writes best when context is local. An AI can output an entire working endpoint in a single, coherent block of code without having to edit 4 different XML/YAML/PHP config files.

### 3. Self-Describing DTOs & Schema Validation
- **Rule:** Every request payload and response contract should be easily expressed via typed DTOs with validation attributes (inspired by FastAPI / NestJS).
- **Why for AI:** When an AI is told *"create an endpoint to register a user with email, password, and optional phone"*, it can declare one strongly typed DTO class that handles validation, sanitization, and OpenAPI documentation automatically.

### 4. Zero "Spooky Action at a Distance"
- **Rule:** Conventions must be predictable and deterministic. If a model is bound or injected, it is explicit. No hidden globals, implicit facades with unknown methods, or invisible life-cycle hooks.
- **Why for AI:** Eliminates silent bugs where code looks correct to the LLM but fails at runtime due to framework-internal magic state.

### 5. Actionable, Self-Healing Error Messages
- **Rule:** Exception messages and debug output must clearly explain **what went wrong**, **which file/line was involved**, and **how to fix it** (e.g., *"Route parameter {id} has no matching argument in UserController::show(int $userId). Rename parameter to {userId} or update the controller parameter."*).
- **Why for AI:** Enables AI agents in automated edit-test-fix loops to instantly read the error output and self-heal in a single pass.

### 6. Single-File / Micro-App Prototyping
- **Rule:** NexPHP must support clean single-file applications for rapid prototyping, experiments, and tests:
  ```php
  use Nex\Application;
  
  $app = Application::create();
  $app->get('/ping', fn() => ['status' => 'pong']);
  $app->run();
  ```
- **Why for AI:** Allows AI agents to quickly generate standalone reproduction scripts, test harnesses, and minimal viable APIs in one prompt.

### 7. AI Context Readiness (`llms.txt` & Prompt Presets)
- **Rule:** The framework ships with clear, machine-readable specifications, comprehensive docblocks, and a curated `llms.txt` summary file.
- **Why for AI:** Any LLM tool can ingest the framework’s core contracts and conventions in seconds with minimal token footprint.
