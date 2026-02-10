<?php

namespace Dynart\Micro;

/**
 * Handles the routing
 *
 * Every web application needs a router for handling routes. A route is a HTTP method and path for a specific action.
 *
 * Example routes:
 * * GET /books - returns a list of books
 * * GET /books/123 - returns the details of a book with ID 123
 * * POST /books/123/save - saves the details of a book with ID 123
 */
class Router implements RouterInterface
{
    /**
     * Constant used for the case when no route found
     */
    const ROUTE_NOT_FOUND = [null, null];

    const CONFIG_INDEX_FILE = 'router.index_file';
    const CONFIG_ROUTE_PARAMETER = 'router.route_parameter';
    const CONFIG_USE_REWRITE = 'router.use_rewrite';

    const DEFAULT_INDEX_FILE = 'index.php';
    const DEFAULT_ROUTE_PARAMETER = 'route';
    const DEFAULT_USE_REWRITE = false;

    /**
     * Stores all of the routes in ['HTTP method' => ['/route' => callable]] format
     *
     * Callables: https://www.php.net/manual/en/language.types.callable.php
     * But you can use the [ExampleClass::class, 'exampleMethod'] format too.
     */
    protected array $routes = [];

    /**
     * Stores callables for the start segments of all of the routes. For example the locale is a
     * prefix variable, because it is always in every route and the value depends on the incoming
     * request.
     *
     * In the route '/en/books' the '/en' is a prefix variable and the '/books' is the stored route
     */
    protected array $prefixVariables = [];

    /**
     * All segments of the current route
     *
     * For example if the route is '/en/book/123/save'
     * this will has the following value: ['en', 'book', '123', 'save']
     */
    protected array $segments = [];

    /**
     * It will fill up the `$segments` array
     */
    public function __construct(protected ConfigInterface $config, protected RequestInterface $request) {
        $this->segments = explode('/', $this->currentRoute());
        array_shift($this->segments);
    }

    /**
     * Returns with the current route
     *
     * The route HTTP query parameter name can be configured with the `router.route_parameter`.
     * If no parameter exists the default will be the home route '/'
     *
     * @see Config
     */
    public function currentRoute(): string {
        $routeParameter = $this->config->get(self::CONFIG_ROUTE_PARAMETER, self::DEFAULT_ROUTE_PARAMETER);
        return $this->request->get($routeParameter, '/');
    }

    /**
     * Returns with a segment value of the current route by index
     */
    public function currentSegment(int $index, mixed $default = null): mixed {
        return isset($this->segments[$index]) ? $this->segments[$index] : $default;
    }

    /**
     * Adds a prefix variable callable for all of the routes
     *
     * For example, if you have a prefix variable that calls the `Translation::locale()` method
     * and then you call the `url` method with '/something' and you configured the `app.base_url`
     * to 'https://example.com' it will return with
     *
     * <pre>
     * https://example.com/en/something
     * </pre>
     *
     * @link https://www.php.net/manual/en/language.types.callable.php
     *
     * @return int The segment index of the newly added prefix variable
     */
    public function addPrefixVariable(callable|array $callable): int {
        $this->prefixVariables[] = $callable;
        return count($this->prefixVariables) - 1;
    }

    /**
     * Matches the current route and returns with the associated callable and parameters
     *
     * For example, the current route is '/books/123/comments/45',
     * the stored route is '/books/?/comments/?' and it matches
     * then the result will be:
     *
     * <p>[the stored callable for the route, ['123', '45']]</p>
     *
     * If no match for the current route it will return with `ROUTE_NOT_FOUND` alias [null, null]
     *
     * @return array The associated callable and parameters with the current route in [callable, [parameters]] format
     */
    public function matchCurrentRoute(): array {
        $method = $this->request->httpMethod();
        $routes = array_key_exists($method, $this->routes) ? $this->routes[$method] : [];
        $segments = $this->segments;
        foreach ($this->prefixVariables as $prefixVariable) { // remove prefix variables from the segments
            array_shift($segments);
        }
        $segmentsCount = count($segments);
        if (!$segmentsCount && isset($this->routes[$method]['/'])) { // if no segments and having home route
            return [$this->routes[$method]['/'], []]; // return with that
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

    /**
     * Matches a route and returns with the given callable
     *
     * The `$callable` can be in a [Example::class, 'exampleMethod'] format as well,
     * so you don't have to create an instance only when this callable is used.
     */
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

    /**
     * Returns with a URL for the given route
     *
     * Heavily depends on the configuration of the application.
     *
     * For example the parameters are the following:
     * * `$route` = '/books/123'
     * * `$params` = ['name' => 'joe']
     * * `$amp` = '&'
     *
     * and the application config is the following:
     *
     * <pre>
     * app.base_url = "http://example.com"
     * router.use_rewrite = false
     * router.index_file = "index.php"
     * router.route_parameter = "route"
     * </pre>
     *
     * then the result will be:
     *
     * <pre>
     * http://example.com/index.php?route=/books/123&name=joe
     * </pre>
     *
     * If the `router.use_rewrite` set to true, the `router.index_file` and the `router.route_parameter` will not be
     * in the result, but for this you have to configure your webserver to redirect non existing
     * file &amp; directory HTTP queries to your /index.php?route={URI}
     *
     * <pre>
     * http://example.com/books/123?name=joe
     * </pre>
     *
     * If you have a prefix variable added (usually locale) and that has the value 'en', the result will be:
     *
     * <pre>
     * http://example.com/en/books/123?name=joe
     * </pre>
     */
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

    /**
     * Stores a route with a callable
     *
     * The `$callable` can use the [ExampleClass::class, 'exampleMethod'] format too.
     *
     * The route can have variables with question mark: '/route/?'
     * then the callable method in this case must have one parameter!
     *
     * The `$method` can be any of the HTTP methods or BOTH. BOTH will add the route to the GET and to the POST as well.
     *
     * For example, the route is GET /books/?:
     *
     * <pre>
     * $router->add('/books/?', [BooksController::class, 'view']);
     * </pre>
     *
     * then the callable should look like
     *
     * <pre>
     * class BooksController {
     *   function view($id) {
     *   }
     * }
     * </pre>
     */
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

    /**
     * Returns with all the stored routes
     *
     * The result format will be ['HTTP method' => ['/route' => callable]]
     */
    public function routes(): array {
        return $this->routes;
    }

    public function prefixVariables(): array {
        return $this->prefixVariables;
    }
}
