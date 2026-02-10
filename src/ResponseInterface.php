<?php

namespace Dynart\Micro;

interface ResponseInterface {
    public function clearHeaders(): void;
    public function setHeader(string $name, string $value): void;
    public function header(string $name, mixed $default = null): mixed;
    public function send(string $content = ''): void;
}
