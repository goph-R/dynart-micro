<?php

namespace Dynart\Micro;

interface EventServiceInterface {
    public function subscribeWithRef(string $event, &$callable): void;
    public function subscribe(string $event, mixed $callable): void;
    public function unsubscribe(string $event, &$callable): bool;
    public function emit(string $event, array $args = []): void;
}
