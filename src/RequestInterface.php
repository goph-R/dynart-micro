<?php

namespace Dynart\Micro;

interface RequestInterface {
    public function get(string $name, mixed $default = null): mixed;
    public function server(string $name, mixed $default = null): mixed;
    public function httpMethod(): string;
    public function ip(): ?string;
    public function setHeader(string $name, string $value): void;
    public function header(string $name, mixed $default = null): mixed;
    public function cookie(string $name, mixed $default = null): mixed;
    public function body(): string;
    public function setBody(string $content): void;
    public function bodyAsJson(): ?array;
    public function uploadedFile(string $name): UploadedFile|array|null;
}
