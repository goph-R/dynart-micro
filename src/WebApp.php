<?php

namespace Dynart\Micro;

require_once dirname(__FILE__) . '/../views/functions.php';

class WebApp extends App {

    /** @var Router */
    protected $router;

    /** @var Response */
    protected $response;

    protected $configPaths;

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        $this->add(Config::class);
        $this->add(Logger::class);
        $this->add(Request::class);
        $this->add(Response::class);
        $this->add(Router::class);
        $this->add(Database::class);
        $this->add(Session::class);
        $this->add(View::class);
        $this->add(Mailer::class);
    }

    public function init() {
        parent::init();
        $config = $this->get(Config::class);
        foreach ($this->configPaths as $path) {
            $config->load($path);
        }
        $this->router = $this->get(Router::class);
        $this->response = $this->get(Response::class);
        if ($config->get('app.use_annotations')) {
            $this->addRoutingByDocComments();
        }
    }

    protected function addRoutingByDocComments() {
        try {
            foreach ($this->classes as $interface => $class) {
                $reflectionClass = new \ReflectionClass($interface);
                foreach ($reflectionClass->getMethods() as $method) {
                    $this->addRoutingForMethod($interface, $method);
                }
            }
        } catch (\ReflectionException $ignore) {
            throw new AppException("Can't create reflection for: $interface");
        }
    }

    protected function addRoutingForMethod(string $interface, \ReflectionMethod $method) {
        $comments = $method->getDocComment();
        $hasRoute = strpos($comments, '* @route') !== false;
        if (!$hasRoute) {
            return;
        }
        $matches = [];
        preg_match('/\s\*\s@route\s(GET|POST|BOTH)\s(.*)\s/', $comments, $matches);
        if ($matches) {
            $route = str_replace(array("\r", "\n"), '', $matches[2]);
            $this->router->add($route, [$interface, $method->getName()], $matches[1]);
        } else {
            throw new AppException("Can't find valid route in: $comments\n\nA valid route example: @route GET /api/something");
        }
    }

    public function process() {
        list($callable, $params) = $this->router->matchCurrentRoute();
        if ($callable) {
            if (is_array($callable) && is_string($callable[0])) {
                $callable = [$this->get($callable[0]), $callable[1]];
            }
            $content = call_user_func_array($callable, $params);
            $this->sendContent($content);
        } else {
            $this->sendError(404, 'Not found.');
        }
    }

    public function redirect($url, $params = []) {
        $location = $this->router->url($url, $params);
        $this->response->clearHeaders();
        $this->response->setHeader('Location', $location);
        $this->response->send();
        $this->finish();
    }

    public function sendContent($content) {
        if (is_string($content)) {
            $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8');
            $this->response->send($content);
        } else if (is_array($content)) {
            $this->response->setHeader('Content-Type', 'application/json');
            $this->response->send(json_encode($content));
        }
    }

    public function sendError(int $code, $content='') {
        http_response_code($code);
        $this->finish($content);
    }
}