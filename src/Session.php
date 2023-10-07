<?php

namespace Dynart\Micro;

/**
 * Session handler
 * @package Dynart\Micro
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
    public function destroy() {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * @return mixed The session ID
     */
    public function id() {
        return session_id();
    }

    /**
     * Returns with a session value by name or default
     * @param string $name The name of the session variable
     * @param mixed|null $default The default value if the name does not exist
     * @return mixed|null The value of the session variable
     */
    public function get(string $name, $default = null) {
        return array_key_exists($name, $_SESSION) ? $_SESSION[$name] : $default;
    }

    /**
     * Sets a session variable
     * @param string $name The name of the session variable
     * @param mixed $value The value of the session variable
     */
    public function set(string $name, $value) {
        $_SESSION[$name] = $value;
    }
}