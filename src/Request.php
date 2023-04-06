<?php

namespace Dynart\Micro;

class Request {

    protected $headers = ['empty' => '']; // default value for testing purposes

    public function __construct() {
        if (function_exists('getallheaders')) { // because of command line purposes
            $this->headers = getallheaders();
        }
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
            return $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return null;
    }

    public function header(string $name) {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

}