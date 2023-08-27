<?php

namespace Dynart\Micro;

/**
 * Represents the HTTP response
 * @package Dynart\Micro
 */
class Response {

    /**
     * Stores the headers for the response
     * @var array
     */
    protected $headers = [];

    /**
     * Clears the headers
     */
    public function clearHeaders(): void {
        $this->headers = [];
    }

    /**
     * Sets a header for the response
     *
     * @param string $name The name of the header
     * @param string $value The value of the header
     */
    public function setHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    /**
     * Returns with a header value by name
     *
     * @param string $name The header name
     * @param string|null $default The default value if the header doesn't present
     * @return string|null The header value or default
     */
    public function header(string $name, $default = null) {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }

    /**
     * Sends the headers then the given body content
     *
     * @param string $content The body content of the response
     */
    public function send($content = ''): void {
        $sendHeaderFunction = function_exists('header') ? function ($n, $v) { header($n.': '.$v); } : function($n, $v) {}; // because of CLI
        foreach ($this->headers as $name => $value) {
            $sendHeaderFunction($name, $value);
        }
        echo $content;
    }
}