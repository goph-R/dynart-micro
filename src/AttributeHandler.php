<?php

namespace Dynart\Micro;

interface AttributeHandler {

    const TARGET_CLASS    = 'class';
    const TARGET_PROPERTY = 'property';
    const TARGET_METHOD   = 'method';

    /**
     * Returns the attribute class name this handler processes
     * @return string
     */
    public function attributeClass(): string;

    /**
     * Returns the targets this handler processes
     *
     * Can be: TARGET_CLASS, TARGET_PROPERTY, TARGET_METHOD
     *
     * @return array
     */
    public function targets(): array;

    /**
     * Handles an attribute found on a class, property or method
     *
     * @param string $className The name of the class
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $subject The reflected class, property or method
     * @param object $attribute The attribute instance
     */
    public function handle(string $className, mixed $subject, object $attribute): void;
}
