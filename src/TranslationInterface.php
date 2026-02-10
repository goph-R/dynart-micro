<?php

namespace Dynart\Micro;

interface TranslationInterface {
    public function add(string $namespace, string $folder): void;
    public function allLocales(): array;
    public function hasMultiLocales(): bool;
    public function locale(): string;
    public function setLocale(string $locale): void;
    public function get(string $id, array $params = []): string;
}
