<?php

namespace Dynart\Micro;

interface RouterInterface {
    public function currentRoute(): string;
    public function currentSegment(int $index, mixed $default = null): mixed;
    public function addPrefixVariable(callable|array $callable): int;
    public function matchCurrentRoute(): array;
    public function url(?string $route = null, array $params = [], string $amp = '&'): string;
    public function add(string $route, callable|array $callable, string $method = 'GET'): void;
    public function routes(): array;
    public function prefixVariables(): array;
}
