<?php

namespace Dynart\Micro\Attribute;

use Attribute;

/**
 * Registers a route for a controller method
 *
 * Repeatable, because one action commonly answers more than one method - a form is `GET` to
 * render it and `POST` to process it, and both land in the same place:
 *
 * <pre>
 * #[Route('GET', '/login')]
 * #[Route('POST', '/login')]
 * public function login(): string { ... }
 * </pre>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $method,
        public string $path
    ) {}
}