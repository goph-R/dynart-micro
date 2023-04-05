<?php

namespace Dynart\Micro;

class Router
{
    const ROUTE_NOT_FOUND = [null, null];

    protected $routes = [];
    protected $prefixVariables = [];
    protected $segments = [];

    /** @var Config */
    protected $config;

    /** @var Request */
    protected $request;

    public function __construct(Config $config, Request $request) {
        $this->config = $config;
        $this->request = $request;
        $this->segments = explode('/', $this->currentRoute());
    }

    public function currentRoute() {
        $routeParameter = $this->config->get('app.route_parameter');
        return $this->request->get($routeParameter, '/');
    }

    public function addPrefixVariable($callable) {
        $this->prefixVariables[] = $callable;
        return count($this->prefixVariables) - 1;
    }

    public function matchCurrentRoute() {
        $method = $this->request->method();
        $routes = array_key_exists($method, $this->routes) ? $this->routes[$method] : [];
        $currentParts = $this->segments;
        foreach ($this->prefixVariables as $prefixVariable) { // remove prefix variables before match
            array_shift($currentParts);
        }
        $currentPartsCount = count($currentParts);
        $found = self::ROUTE_NOT_FOUND;
        foreach ($routes as $route => $callable) {
            $found = $this->match($route, $callable, $currentParts, $currentPartsCount);
            if ($found[0]) {
                break;
            }
        }
        return $found;
    }

    public function currentSegment(int $index, $default = null) {
        return isset($this->segments[$index]) ? $this->segments[$index] : $default;
    }

    protected function match(string $route, $callable, array $currentParts, int $currentPartsCount) {
        $parts = explode('/', $route);
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

    public function url($route, $params = [], $amp = '&') {
        $prefix = '';
        foreach ($this->prefixVariables as $prefixVariable) {
            $prefix .= '/'.call_user_func($prefixVariable);
        }
        $result = $this->config->get('app.base_url');
        $useRewrite = $this->config->get('app.use_rewrite');
        if ($useRewrite) {
            $result .= $route == null ? '' : $prefix.$route;
        } else {
            $indexFile = $this->config->get('app.index_file');
            $result .= '/'.$indexFile;
            if ($route && $route != '/') {
                $routeParameter = $this->config->get('app.route_parameter');
                $params[$routeParameter] = $prefix.$route;
            }
        }
        if ($params) {
            $result .= '?'.http_build_query($params, '', $amp);
        }
        return str_replace('%2F', '/', $result);
    }

    public function add(string $route, $callable, $method = 'GET') {
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

    public function printRoutes() {
        print_r($this->routes);
    }
}