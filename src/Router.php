<?php

namespace Dynart\Micro;

class Router
{
    const ROUTE_NOT_FOUND = [null, null];

    protected $routes = [];

    /** @var Config */
    protected $config;

    /** @var Request */
    protected $request;

    public function __construct(Config $config, Request $request) {
        $this->config = $config;
        $this->request = $request;
    }

    public function currentRoute() {
        $routeParameter = $this->config->get('app.route_parameter');
        return $this->request->get($routeParameter, '/');
    }

    public function matchCurrentRoute() {
        $method = $this->request->method();
        $routes = array_key_exists($method, $this->routes) ? $this->routes[$method] : [];
        $currentParts = explode('/', $this->currentRoute());
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

    public function url($route, $params=[], $amp='&') {
        $result = $this->config->get('app.base_url');
        $useRewrite = $this->config->get('app.use_rewrite');
        if ($useRewrite) {
            $result .= $route == null ? '' : $route;
        } else {
            $indexFile = $this->config->get('app.index_file');
            $result .= '/'.$indexFile;
            if ($route && $route != '/') {
                $routeParameter = $this->config->get('app.route_parameter');
                $params[$routeParameter] = $route;
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
}