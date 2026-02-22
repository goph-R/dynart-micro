<?php

namespace Dynart\Micro\AttributeHandler;

use Dynart\Micro\Attribute\Authorize;
use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\JwtAuthInterface;

class AuthorizeAttributeHandler implements AttributeHandlerInterface {

    public function __construct(private JwtAuthInterface $jwtAuth) {}

    public function attributeClass(): string {
        return Authorize::class;
    }

    public function targets(): array {
        return [AttributeHandlerInterface::TARGET_CLASS, AttributeHandlerInterface::TARGET_METHOD];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        if ($subject instanceof \ReflectionClass) {
            $this->jwtAuth->addClassAuthorization($className, $attribute->permission);
        } elseif ($subject instanceof \ReflectionMethod) {
            $this->jwtAuth->addMethodAuthorization($className, $subject->getName(), $attribute->permission);
        }
    }
}
