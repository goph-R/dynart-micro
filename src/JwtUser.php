<?php

namespace Dynart\Micro;

class JwtUser implements JwtUserInterface {

    public function __construct(
        private string $sub,
        private array $permissions = []
    ) {}

    public function sub(): string {
        return $this->sub;
    }

    public function permissions(): array {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool {
        return in_array($permission, $this->permissions);
    }
}
