<?php

namespace Dynart\Micro;

interface CliCommandsInterface {
    public function add(string $name, callable|array $callable, array $paramNames = [], array $flagNames = []): void;
    public function current(): ?string;
    public function has(string $name): bool;

    /**
     * @return array The [callable, params] pair, or `CliCommands::COMMAND_NOT_FOUND` when there is no such command
     */
    public function matchCurrent(): array;
}
