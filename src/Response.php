<?php

namespace Dynart\Micro;

class Response {

    protected $headers = [];

    public function clearHeaders() {
        return $this->headers;
    }

    public function setHeader(string $name, $value) {
        $this->headers[$name] = $value;
    }

    public function send($content = '') {
        foreach ($this->headers as $name => $value) {
            header($name.': '.$value);
        }
        echo $content;
    }
}