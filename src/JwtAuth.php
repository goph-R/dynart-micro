<?php

namespace Dynart\Micro;

class JwtAuth implements JwtAuthInterface {

    const EVENT_USER_SET = 'jwtauth:user_set';
    const EVENT_AUTHORIZATION_GRANTED = 'jwtauth:authorization_granted';
    const EVENT_AUTHORIZATION_DENIED = 'jwtauth:authorization_denied';

    private ?JwtUserInterface $currentUser = null;
    private mixed $userResolver = null;
    private array $classAuthorizations = [];
    private array $methodAuthorizations = [];
    private array $allowAnonymous = [];

    public function __construct(private EventServiceInterface $eventService) {
        $eventService->subscribe(WebApp::EVENT_ROUTE_MATCHED, [$this, 'onRouteMatched']);
    }

    public function setUserResolver(callable $resolver): void {
        $this->userResolver = $resolver;
    }

    public function setUser(JwtUserInterface $user): void {
        $this->currentUser = $user;
        $this->eventService->emit(self::EVENT_USER_SET, [$user]);
    }

    public function user(): ?JwtUserInterface {
        return $this->currentUser;
    }

    public function resolveUser(string $sub, object $payload): JwtUserInterface {
        if ($this->userResolver !== null) {
            return ($this->userResolver)($sub, $payload);
        }
        return new JwtUser($sub);
    }

    public function addClassAuthorization(string $className, string $permission): void {
        $this->classAuthorizations[$className] = $permission;
    }

    public function addMethodAuthorization(string $className, string $method, string $permission): void {
        $this->methodAuthorizations[$className . '::' . $method] = $permission;
    }

    public function addAllowAnonymous(string $className, string $method): void {
        $this->allowAnonymous[$className . '::' . $method] = true;
    }

    public function onRouteMatched(mixed $callable, array $params): void {
        if (is_array($callable)) {
            $this->checkAuthorization($callable);
        }
    }

    private function checkAuthorization(array $callable): void {
        $className = get_class($callable[0]);
        $key = $className . '::' . $callable[1];

        if (array_key_exists($key, $this->allowAnonymous)) {
            return;
        }

        if (array_key_exists($key, $this->methodAuthorizations)) {
            $permission = $this->methodAuthorizations[$key];
        } elseif (array_key_exists($className, $this->classAuthorizations)) {
            $permission = $this->classAuthorizations[$className];
        } else {
            return;
        }

        if ($this->currentUser === null) {
            $this->eventService->emit(self::EVENT_AUTHORIZATION_DENIED, [$callable, 401]);
            throw new AuthorizationException(401);
        }

        if ($permission !== '' && !$this->currentUser->hasPermission($permission)) {
            $this->eventService->emit(self::EVENT_AUTHORIZATION_DENIED, [$callable, 403]);
            throw new AuthorizationException(403);
        }

        $this->eventService->emit(self::EVENT_AUTHORIZATION_GRANTED, [$callable]);
    }
}
