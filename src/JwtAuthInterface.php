<?php

namespace Dynart\Micro;

interface JwtAuthInterface {
    public function setUserResolver(callable $resolver): void;
    public function setUser(JwtUserInterface $user): void;
    public function user(): ?JwtUserInterface;
    public function resolveUser(string $sub, object $payload): JwtUserInterface;
    public function addClassAuthorization(string $className, string $permission): void;
    public function addMethodAuthorization(string $className, string $method, string $permission): void;
    public function addAllowAnonymous(string $className, string $method): void;
}
