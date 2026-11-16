<?php

declare(strict_types=1);

namespace Nex\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Patch extends Route
{
    public function __construct(string $path = '', ?string $name = null, array|string|object $middleware = [])
    {
        parent::__construct(path: $path, methods: ['PATCH'], name: $name, middleware: $middleware);
    }
}
