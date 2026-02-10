<?php

namespace Dynart\Micro;

class Session implements SessionInterface {

    /**
     * Starts the session (only once)
     */
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function destroy(): void {
        $_SESSION = [];
        session_destroy();
    }

    public function id(): string {
        return session_id();
    }

    public function get(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_SESSION) ? $_SESSION[$name] : $default;
    }

    public function set(string $name, mixed $value): void {
        $_SESSION[$name] = $value;
    }
}
