<?php

namespace Dynart\Micro;

class Request {

    protected $headers = [];

    public function __construct() {
        $this->headers = getallheaders();
    }

    public function get(string $name, $default = null) {
        return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
    }

    public function server(string $name, $default = null) {
        return array_key_exists($name, $_SERVER) ? $_SERVER[$name] : $default;
    }

    public function method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function header(string $name) {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

}