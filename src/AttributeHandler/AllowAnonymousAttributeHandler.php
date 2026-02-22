<?php

namespace Dynart\Micro\AttributeHandler;

use Dynart\Micro\Attribute\AllowAnonymous;
use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\JwtAuthInterface;

class AllowAnonymousAttributeHandler implements AttributeHandlerInterface {

    public function __construct(private JwtAuthInterface $jwtAuth) {}

    public function attributeClass(): string {
        return AllowAnonymous::class;
    }

    public function targets(): array {
        return [AttributeHandlerInterface::TARGET_METHOD];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        if ($subject instanceof \ReflectionMethod) {
            $this->jwtAuth->addAllowAnonymous($className, $subject->getName());
        }
    }
}
