<?php

declare(strict_types=1);

namespace Nex\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Post extends Route
{
    public function __construct(string $path = '', ?string $name = null, array|string|object $middleware = [])
    {
        parent::__construct(path: $path, methods: ['POST'], name: $name, middleware: $middleware);
    }
}
