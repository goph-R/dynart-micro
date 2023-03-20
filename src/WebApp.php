<?php

namespace Dynart\Micro;

abstract class WebApp extends App {

    /** @var Router */
    protected $router;

    /** @var Response */
    protected $response;

    protected $configPaths;

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        $this->add(Config::class);
        $this->add(Request::class);
        $this->add(Response::class);
        $this->add(Router::class);
        $this->add(Database::class);
        $this->add(Session::class);
        $this->add(View::class);
    }

    public function init() {
        parent::init();
        $config = $this->get(Config::class);
        foreach ($this->configPaths as $path) {
            $config->load($path);
        }
        $this->router = $this->get(Router::class);
        $this->response = $this->get(Response::class);
    }

    public function process() {
        list($callable, $params) = $this->router->matchCurrentRoute();
        if ($callable) {
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

    protected function sendContent($content) {
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