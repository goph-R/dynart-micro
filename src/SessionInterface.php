<?php

namespace Dynart\Micro;

/**
 * Session handler
 */
interface SessionInterface {
    /**
     * Destroys the session
     */
    public function destroy(): void;

    /**
     * Returns the session ID
     */
    public function id(): string;

    /**
     * Returns with a session value by name or default
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Sets a session variable
     */
    public function set(string $name, mixed $value): void;
}
