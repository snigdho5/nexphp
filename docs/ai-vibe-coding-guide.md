# AI "Vibe Coding" Guide with NexPHP

NexPHP was purpose-built from the ground up to be the most ergonomic framework for AI-assisted coding (Claude, Cursor, Copilot, ChatGPT, Antigravity).

---

## Why NexPHP is Built for AI Vibe-Coding

Traditional frameworks often suffer from "magic metaprogramming" — hidden facades, dynamic `$this->load()` methods, untyped array bags, and implicit global scopes that confuse AI models and lead to hallucinated APIs.

NexPHP solves this with 4 core pillars:
1. **Zero Magic, Strict Typing**: Everything has explicit PHP 8.3+ types (`readonly`, enums, strict union types). AI models can read the signature and write 100% correct code on the first try.
2. **Declarative Attributes**: Routes, middleware, and validation rules are declared right above the method.
3. **Automatic FormRequest Injection**: Any subclass of `FormRequest` typehinted on a controller method is automatically resolved, validated, and injected by the container.
4. **Self-Healing Diagnostics**: If an AI creates a typo or invalid configuration, NexPHP's exception messages provide exact suggestions on how to resolve it.

---

## Pattern 1: Declarative Controller with Auto-Validation

When prompting an AI to generate a CRUD endpoint, instruct it to use declarative attributes:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Requests\CreateProductRequest;
use Nex\Database\Connection;
use Nex\Http\Responses\JsonResponse;
use Nex\Routing\Attributes\Get;
use Nex\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;

final class ProductController
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    #[Get('/products')]
    public function index(): ResponseInterface
    {
        $products = $this->db->table('products')->orderBy('id', 'desc')->get();
        return new JsonResponse(['data' => $products]);
    }

    #[Post('/products')]
    public function store(CreateProductRequest $request): ResponseInterface
    {
        // $request is ALREADY validated! If validation fails, 
        // NexPHP automatically returns HTTP 422 JSON before this method runs.
        $data = $request->validated();

        $id = $this->db->table('products')->insertGetId([
            'name'       => $data['name'],
            'price'      => $data['price'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return new JsonResponse(['id' => $id, 'message' => 'Product created'], 201);
    }
}
```

---

## Pattern 2: Self-Describing FormRequest

No complicated setup — just declare the validation rules:

```php
<?php

declare(strict_types=1);

namespace App\Requests;

use Nex\Validation\FormRequest;

final class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => 'required|min:3|max:255',
            'price' => 'required|numeric|min:0',
        ];
    }
}
```

---

## Pattern 3: Cooperative Fiber Concurrency

When an AI needs to fan out multiple I/O requests concurrently, use `Scheduler::all`:

```php
use Nex\Concurrency\Scheduler;

$scheduler = new Scheduler();

$results = $scheduler->all([
    'user'  => fn () => $httpClient->get('https://api.example.com/user'),
    'order' => fn () => $httpClient->get('https://api.example.com/order'),
]);
```

No Promise callbacks or complex event loops needed.

---

## Recommended System Prompt for AI Coding Assistants

When using tools like Cursor, Windsurf, or Copilot on a NexPHP project, include this prompt in `.cursorrules` or instructions:

```markdown
You are building an application with NexPHP.
- Always use declare(strict_types=1);
- Use declarative attributes (#[Get], #[Post], #[Route]) on controller methods.
- Typehint FormRequest subclasses for automatic request validation.
- Use Nex\Http\Responses\JsonResponse for JSON responses.
- Prefer constructor injection for database connections, mailers, and loggers.
- Keep background jobs serializable (scalar parameters and IDs only, never pass PDO or Closures).
```
