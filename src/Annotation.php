<?php

namespace Dynart\Micro;

interface Annotation {
    public function name(): string;
    public function regex(): string;
    public function process(string $interface, \ReflectionMethod $method, string $comment, array $matches): void;
}