<?php

declare(strict_types=1);

namespace Nex\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * @param list<class-string|object>|class-string|object $middleware
     */
    public function __construct(public array|string|object $middleware)
    {
    }
}
