<?php

namespace Dynart\Micro;

/**
 * Represents the HTTP response
 */
interface ResponseInterface {
    /**
     * Clears the headers
     */
    public function clearHeaders(): void;

    /**
     * Sets a header for the response
     */
    public function setHeader(string $name, string $value): void;

    /**
     * Returns with a header value by name
     */
    public function header(string $name, mixed $default = null): mixed;

    /**
     * Sends the headers then the given body content
     */
    public function send(string $content = ''): void;
}
