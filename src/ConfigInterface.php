<?php

namespace Dynart\Micro;

interface ConfigInterface {
    public function load(string $path): void;
    public function clearCache(): void;
    public function get(string $name, mixed $default = null, bool $useCache = true): mixed;
    public function getCommaSeparatedValues(string $name, bool $useCache = true): array;
    public function getArray(string $prefix, array $default = [], bool $useCache = true): array;
    public function isCached(string $name): bool;
    public function getFullPath(string $path): string;
}
