<?php

namespace Dynart\Micro;

class Session {

    public function __construct() {
        session_start();
    }

    public function destroy() {
        $_SESSION = [];
        session_destroy();
    }

    public function get(string $name, $default = null) {
        return array_key_exists($name, $_SESSION) ? $_SESSION[$name] : $default;
    }

    public function set(string $name, $value) {
        $_SESSION[$name] = $value;
    }

}