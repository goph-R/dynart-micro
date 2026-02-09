<?php

namespace Dynart\Micro;

/**
 * Handles the routing
 */
class Router
{
    const ROUTE_NOT_FOUND = [null, null];

    const CONFIG_INDEX_FILE = 'router.index_file';
    const CONFIG_ROUTE_PARAMETER = 'router.route_parameter';
    const CONFIG_USE_REWRITE = 'router.use_rewrite';

    const DEFAULT_INDEX_FILE = 'index.php';
    const DEFAULT_ROUTE_PARAMETER = 'route';
    const DEFAULT_USE_REWRITE = false;

    protected array $routes = [];
    protected array $prefixVariables = [];
    protected array $segments = [];

    public function __construct(protected Config $config, protected Request $request) {
        $this->segments = explode('/', $this->currentRoute());
        array_shift($this->segments);
    }

    public function currentRoute(): string {
        $routeParameter = $this->config->get(self::CONFIG_ROUTE_PARAMETER, self::DEFAULT_ROUTE_PARAMETER);
        return $this->request->get($routeParameter, '/');
    }

    public function currentSegment(int $index, mixed $default = null): mixed {
        return isset($this->segments[$index]) ? $this->segments[$index] : $default;
    }

    /**
     * Adds a prefix variable callable for all of the routes
     *
     * @return int The segment index of the newly added prefix variable
     */
    public function addPrefixVariable(callable|array $callable): int {
        $this->prefixVariables[] = $callable;
        return count($this->prefixVariables) - 1;
    }

    public function matchCurrentRoute(): array {
        $method = $this->request->httpMethod();
        $routes = array_key_exists($method, $this->routes) ? $this->routes[$method] : [];
        $segments = $this->segments;
        foreach ($this->prefixVariables as $prefixVariable) {
            array_shift($segments);
        }
        $segmentsCount = count($segments);
        if (!$segmentsCount && isset($this->routes[$method]['/'])) {
            return [$this->routes[$method]['/'], []];
        }
        $found = self::ROUTE_NOT_FOUND;
        foreach ($routes as $route => $callable) {
            $found = $this->match($route, $callable, $segments, $segmentsCount);
            if ($found[0]) {
                break;
            }
        }
        return $found;
    }

    protected function match(string $route, callable|array $callable, array $currentParts, int $currentPartsCount): array {
        $parts = explode('/', $route);
        array_shift($parts);
        if (count($parts) != $currentPartsCount) {
            return self::ROUTE_NOT_FOUND;
        }
        $found = true;
        $params = [];
        foreach ($parts as $i => $part) {
            if ($part == $currentParts[$i]) {
                continue;
            }
            if ($part == '?') {
                $params[] = $currentParts[$i];
                continue;
            }
            $found = false;
            break;
        }
        if ($found) {
            return [$callable, $params];
        }
        return self::ROUTE_NOT_FOUND;
    }

    public function url(?string $route = null, array $params = [], string $amp = '&'): string {
        $prefix = '';
        foreach ($this->prefixVariables as $callable) {
            $prefix .= '/'.call_user_func($callable);
        }
        $result = $this->config->get(App::CONFIG_BASE_URL);
        $useRewrite = $this->config->get(self::CONFIG_USE_REWRITE, self::DEFAULT_USE_REWRITE);
        if ($useRewrite) {
            $result .= $route == null ? '' : $prefix.$route;
        } else {
            $indexFile = $this->config->get(self::CONFIG_INDEX_FILE, self::DEFAULT_INDEX_FILE);
            $result .= '/'.$indexFile;
            if ($route && $route != '/') {
                $routeParameter = $this->config->get(self::CONFIG_ROUTE_PARAMETER, self::DEFAULT_ROUTE_PARAMETER);
                $params[$routeParameter] = $prefix.$route;
            }
        }
        if ($params) {
            $result .= '?'.http_build_query($params, '', $amp);
        }
        return str_replace('%2F', '/', $result);
    }

    public function add(string $route, callable|array $callable, string $method = 'GET'): void {
        if ($method == 'BOTH') {
            $this->add($route, $callable, 'GET');
            $this->add($route, $callable, 'POST');
        } else {
            if (!array_key_exists($method, $this->routes)) {
                $this->routes[$method] = [];
            }
            $this->routes[$method][$route] = $callable;
        }
    }

    public function routes(): array {
        return $this->routes;
    }

    public function prefixVariables(): array {
        return $this->prefixVariables;
    }
}
