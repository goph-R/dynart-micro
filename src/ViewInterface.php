<?php

namespace Dynart\Micro;

interface ViewInterface {
    public function get(string $name, mixed $default = null): mixed;
    public function set(string $name, mixed $value): void;
    public function addScript(string $src, array $attributes = []): void;
    public function scripts(): array;
    public function addStyle(string $src, array $attributes = []): void;
    public function styles(): array;
    public function useLayout(string $path): void;
    public function layout(): string;
    public function block(string $name): string;
    public function startBlock(string $name): void;
    public function endBlock(): void;
    public function addFolder(string $namespace, string $path): void;
    public function folder(string $namespace): string;
    public function setTheme(string $path): void;
    public function theme(): string;
    public function fetch(string $__viewPath, array $__vars = []): string;
}
