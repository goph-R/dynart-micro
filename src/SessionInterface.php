<?php

namespace Dynart\Micro;

interface SessionInterface {
    public function destroy(): void;
    public function id(): string;
    public function get(string $name, mixed $default = null): mixed;
    public function set(string $name, mixed $value): void;
}
