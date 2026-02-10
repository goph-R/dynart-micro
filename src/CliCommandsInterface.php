<?php

namespace Dynart\Micro;

interface CliCommandsInterface {
    public function add(string $name, callable|array $callable, array $paramNames = [], array $flagNames = []): void;
    public function current(): ?string;
    public function matchCurrent(): ?array;
}
