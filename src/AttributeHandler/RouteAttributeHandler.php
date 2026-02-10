<?php

namespace Dynart\Micro\AttributeHandler;

use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\Attribute\Route;
use Dynart\Micro\RouterInterface;

/**
 * Handles #[Route] attributes
 *
 * @see AttributeHandler
 * @package Dynart\Micro
 */
class RouteAttributeHandler implements AttributeHandlerInterface {

    public function __construct(private RouterInterface $router) {}

    public function attributeClass(): string {
        return Route::class;
    }

    public function targets(): array {
        return [AttributeHandlerInterface::TARGET_METHOD];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->router->add($attribute->path, [$className, $subject->getName()], $attribute->method);
    }
}
