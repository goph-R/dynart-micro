<?php

namespace Dynart\Micro;

class Response {

    protected $headers = [];

    public function clearHeaders() {
        $this->headers = [];
    }

    public function setHeader(string $name, string $value) {
        $this->headers[$name] = $value;
    }

    public function header(string $name, string $default = null) {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }

    public function send($content = '') {
        if (function_exists('header')) { // because of command line purposes
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $content;
    }
}