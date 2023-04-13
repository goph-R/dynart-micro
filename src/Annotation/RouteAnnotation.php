<?php

namespace Dynart\Micro\Annotation;

use Dynart\Micro\Annotation;
use Dynart\Micro\Router;
use Dynart\Micro\AppException;

/**
 * The @route annotation
 *
 * @see Annotation
 * @package Dynart\Micro
 */
class RouteAnnotation implements Annotation {

    /** @var Router */
    private $router;

    public function __construct(Router $router) {
        $this->router = $router;
    }

    public function name(): string {
        return 'route';
    }

    public function regex(): string {
        return '(GET|POST|OPTIONS|PUT|DELETE|PATCH|BOTH)\s(.*)';
    }

    public function process(string $interface, \ReflectionMethod $method, string $comment, array $matches): void {
        if ($matches) {
            $route = str_replace(' ', '', $matches[2]); // remove spaces
            $this->router->add($route, [$interface, $method->getName()], $matches[1]);
        } else {
            throw new AppException("Can't find valid route in: $comment\nA valid route example: @route GET /api/something");
        }
    }
}