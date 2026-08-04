<?php

namespace Dynart\Micro;

/**
 * Represents the HTTP response
 */
interface ResponseInterface {
    /**
     * Clears the headers
     */
    public function clearHeaders(): void;

    /**
     * Sets a header for the response
     */
    public function setHeader(string $name, string $value): void;

    /**
     * Returns with a header value by name
     */
    public function header(string $name, mixed $default = null): mixed;

    /**
     * Sets a cookie for the response
     *
     * The options are merged into `Response::DEFAULT_COOKIE_OPTIONS`, which sets `httponly` and
     * `samesite` but leaves `secure` off - turn that on from the application config in
     * production.
     *
     * @param array $options The `setcookie()` options: expires, path, domain, secure, httponly, samesite
     */
    public function setCookie(string $name, string $value, array $options = []): void;

    /**
     * Expires a cookie
     *
     * The `path` and the `domain` have to match the ones it was set with.
     */
    public function clearCookie(string $name, array $options = []): void;

    /**
     * Returns with a cookie value that was set on this response
     */
    public function cookie(string $name, mixed $default = null): mixed;

    /**
     * Returns with every cookie of this response in [name => [value, options]] format
     */
    public function cookies(): array;

    /**
     * Clears the cookies of this response
     */
    public function clearCookies(): void;

    /**
     * Sends the cookies, then the headers, then the given body content
     */
    public function send(string $content = ''): void;
}
