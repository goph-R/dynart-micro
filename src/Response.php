<?php

namespace Dynart\Micro;

/**
 * Represents the HTTP response
 */
class Response {

    /** Stores the headers for the response */
    protected array $headers = [];

    /**
     * Clears the headers
     */
    public function clearHeaders(): void {
        $this->headers = [];
    }

    /**
     * Sets a header for the response
     */
    public function setHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    /**
     * Returns with a header value by name
     */
    public function header(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }

    /**
     * Sends the headers then the given body content
     */
    public function send(string $content = ''): void {
        $sendHeaderFunction = function_exists('header') ? function ($n, $v) { header($n.': '.$v); } : function($n, $v) {}; // because of CLI
        foreach ($this->headers as $name => $value) {
            $sendHeaderFunction($name, $value);
        }
        echo $content;
    }
}
