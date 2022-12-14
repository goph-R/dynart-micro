<?php
/**
 * Created by PhpStorm.
 * User: gopher
 * Date: 12/12/2022
 * Time: 7:10 PM
 */

namespace Dynart\Micro;


class Router
{
    const ROUTE_NOT_FOUND = [null, null];

    protected $routes = [];

    /** @var App */
    protected $app;

    public function __construct(App $app) {
        $this->app = $app;
    }

    public function getCurrentRoute() {
        $routeParameter = $this->app->config(App::CONFIG_ROUTE_PARAMETER);
        return $this->app->request($routeParameter, '/');
    }

    public function matchCurrentRoute() {
        $method = $this->app->requestMethod();
        $routes = array_key_exists($method, $this->routes) ? $this->routes[$method] : [];
        $currentParts = explode('/', $this->getCurrentRoute());
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
        $params = [$this->app]; // the first parameter is always the App
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

    public function getUrl($route, $params=[], $amp='&') {
        $result = $this->app->config(App::CONFIG_BASE_URL);
        $useRewrite = $this->app->config(App::CONFIG_USE_REWRITE);
        if ($useRewrite) {
            $result .= $route == null ? '' : $route;
        } else {
            $indexFile = $this->app->config(App::CONFIG_INDEX_FILE);
            $result .= '/'.$indexFile;
            if ($route && $route != '/') {
                $routeParameter = $this->app->config(App::CONFIG_ROUTE_PARAMETER);
                $params[$routeParameter] = $route;
            }
        }
        if ($params) {
            $result .= '?'.http_build_query($params, '', $amp);
        }
        return str_replace('%2F', '/', $result);
    }

    public function add(string $route, $callable, $method) {
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