<?php

declare(strict_types=1);

namespace Nex\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * @param string $path
     * @param list<string> $methods
     * @param string|null $name
     * @param list<class-string|object>|class-string|object $middleware
     */
    public function __construct(
        public string $path = '',
        public array $methods = ['GET'],
        public ?string $name = null,
        public array|string|object $middleware = []
    ) {
    }
}
