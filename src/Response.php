<?php

namespace Dynart\Micro;

class Response implements ResponseInterface {

    /** Stores the headers for the response */
    protected array $headers = [];

    public function clearHeaders(): void {
        $this->headers = [];
    }

    public function setHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    public function header(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }

    public function send(string $content = ''): void {
        $sendHeaderFunction = function_exists('header') ? function ($n, $v) { header($n.': '.$v); } : function($n, $v) {}; // because of CLI
        foreach ($this->headers as $name => $value) {
            $sendHeaderFunction($name, $value);
        }
        echo $content;
    }
}
