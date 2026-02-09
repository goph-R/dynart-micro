<?php

namespace Dynart\Micro;

/**
 * Session handler
 */
class Session {

    /**
     * Starts the session (only once)
     */
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Destroys the session
     */
    public function destroy(): void {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Returns the session ID
     */
    public function id(): string {
        return session_id();
    }

    /**
     * Returns with a session value by name or default
     */
    public function get(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_SESSION) ? $_SESSION[$name] : $default;
    }

    /**
     * Sets a session variable
     */
    public function set(string $name, mixed $value): void {
        $_SESSION[$name] = $value;
    }
}
