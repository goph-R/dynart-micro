<?php

namespace Dynart\Micro\AttributeHandler;

use Dynart\Micro\AttributeHandler;
use Dynart\Micro\Attribute\Route;
use Dynart\Micro\Router;

/**
 * Handles #[Route] attributes
 *
 * @see AttributeHandler
 * @package Dynart\Micro
 */
class RouteAttributeHandler implements AttributeHandler {

    public function __construct(private Router $router) {}

    public function attributeClass(): string {
        return Route::class;
    }

    public function targets(): array {
        return [AttributeHandler::TARGET_METHOD];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->router->add($attribute->path, [$className, $subject->getName()], $attribute->method);
    }
}
