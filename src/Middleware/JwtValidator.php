<?php

namespace Dynart\Micro\Middleware;

use Dynart\Micro\AuthorizationException;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\JwtAuthInterface;
use Dynart\Micro\MiddlewareInterface;
use Dynart\Micro\RequestInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtValidator implements MiddlewareInterface {

    public function __construct(
        private RequestInterface $request,
        private JwtAuthInterface $jwtAuth,
        private ConfigInterface $config
    ) {}

    public function run(): void {
        $header = $this->request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return;
        }
        $token = substr($header, 7);
        $secret = $this->config->get('jwt.secret', '');
        $algorithm = $this->config->get('jwt.algorithm', 'HS256');
        try {
            $payload = JWT::decode($token, new Key($secret, $algorithm));
            $user = $this->jwtAuth->resolveUser($payload->sub, $payload);
            $this->jwtAuth->setUser($user);
        } catch (\Exception $e) {
            throw new AuthorizationException(401);
        }
    }
}
