<?php

namespace Dynart\Micro\Middleware;

use Dynart\Micro\ConfigInterface;
use Dynart\Micro\MiddlewareInterface;
use Dynart\Micro\RequestInterface;

/**
 * Lifts a JWT out of a cookie into the Authorization header
 *
 * `JwtValidator` reads `Authorization: Bearer`, which is fine for an API client but there is
 * nowhere to put that header in a browser navigating to a server rendered page. This middleware
 * copies the token from a cookie into the header, so every existing `#[Authorize]` attribute
 * keeps working untouched and there is exactly one place that decodes a token.
 *
 * It has to run **before** `JwtValidator`, so add it with a lower priority number:
 *
 * <pre>
 * $app->addMiddleware(JwtCookieReader::class, 40);
 * $app->addMiddleware(JwtValidator::class, 50);
 * </pre>
 *
 * An Authorization header that is already present always wins, so an API client is never
 * overridden by a stale cookie the browser happened to send along.
 */
class JwtCookieReader implements MiddlewareInterface {

    const CONFIG_COOKIE_NAME = 'jwt.cookie_name';
    const DEFAULT_COOKIE_NAME = 'token';

    public function __construct(
        private RequestInterface $request,
        private ConfigInterface $config
    ) {}

    public function cookieName(): string {
        return (string)$this->config->get(self::CONFIG_COOKIE_NAME, self::DEFAULT_COOKIE_NAME);
    }

    public function run(): void {
        if ($this->request->header('Authorization')) {
            return;
        }
        $token = $this->request->cookie($this->cookieName());
        if (is_string($token) && $token !== '') {
            $this->request->setHeader('Authorization', 'Bearer '.$token);
        }
    }
}
