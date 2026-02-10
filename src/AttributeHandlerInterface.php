<?php

namespace Dynart\Micro;

interface AttributeHandlerInterface {

    const TARGET_CLASS    = 'class';
    const TARGET_PROPERTY = 'property';
    const TARGET_METHOD   = 'method';

    public function attributeClass(): string;
    public function targets(): array;
    public function handle(string $className, mixed $subject, object $attribute): void;
}
