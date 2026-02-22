<?php

namespace Dynart\Micro;

interface JwtUserInterface {
    public function sub(): string;
    public function permissions(): array;
    public function hasPermission(string $permission): bool;
}
