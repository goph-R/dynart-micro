<?php

namespace Dynart\Micro;

class Response implements ResponseInterface {

    /**
     * The cookie options every cookie starts with
     *
     * `httponly` and `samesite` are on by default because the common reason to set a cookie from
     * the server is to hold something a script has no business reading. `secure` is deliberately
     * left off: turning it on by default would make cookies silently vanish on a plain HTTP
     * development site. Set it from the application config for production.
     */
    const DEFAULT_COOKIE_OPTIONS = [
        'expires'  => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    /** Stores the headers for the response */
    protected array $headers = [];

    /** Stores the cookies in [name => [value, options]] format */
    protected array $cookies = [];

    public function clearHeaders(): void {
        $this->headers = [];
    }

    public function setHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    public function header(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }

    public function setCookie(string $name, string $value, array $options = []): void {
        $this->cookies[$name] = [$value, array_merge(self::DEFAULT_COOKIE_OPTIONS, $options)];
    }

    /**
     * Expires a cookie
     *
     * The `path` and the `domain` have to match the ones it was set with, otherwise the browser
     * keeps the original cookie and clearing it silently does nothing.
     */
    public function clearCookie(string $name, array $options = []): void {
        $this->setCookie($name, '', array_merge($options, ['expires' => 1]));
    }

    public function cookie(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name][0] : $default;
    }

    public function cookies(): array {
        return $this->cookies;
    }

    public function clearCookies(): void {
        $this->cookies = [];
    }

    public function send(string $content = ''): void {
        $sendHeaderFunction = function_exists('header') ? function ($n, $v) { header($n.': '.$v); } : function($n, $v) {}; // because of CLI
        $sendCookieFunction = function_exists('setcookie')
            ? function ($n, $v, $o) { setcookie($n, $v, $o); }
            : function ($n, $v, $o) {};
        foreach ($this->cookies as $name => $cookie) {
            $sendCookieFunction($name, $cookie[0], $cookie[1]);
        }
        foreach ($this->headers as $name => $value) {
            $sendHeaderFunction($name, $value);
        }
        echo $content;
    }
}
