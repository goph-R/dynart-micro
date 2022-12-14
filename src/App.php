<?php

namespace Dynart\Micro;

class App {

    const CONFIG_BASE_URL = ['app.base_url', 'http://localhost'];
    const CONFIG_SALT = ['app.salt', ''];
    const CONFIG_ROUTE_PARAMETER = ['app.route_parameter', 'route'];
    const CONFIG_USE_SESSION = ['app.use_session', true];
    const CONFIG_USE_REWRITE = ['app.use_rewrite', true];
    const CONFIG_INDEX_FILE = ['app.index_file', 'index.php'];
    const CONFIG_VIEWS_FOLDER = ['app.views_folder', 'views'];
    const CONFIG_USE_DATABASE = ['app.use_database', false];
    const CONFIG_USE_VIEW = ['app.use_view', false];

    protected $config = [];
    protected $requestHeaders = [];
    protected $responseHeaders = [];

    /** @var Router */
    protected $router;
    /** @var Database */
    protected $database;
    /** @var View */
    protected $view;

    public function __construct(array $configPaths) {
        $this->loadConfig($configPaths);
        $this->requestHeaders = getallheaders();
        $this->router = $this->createRouter();
        if ($this->config(self::CONFIG_USE_SESSION)) {
            session_start();
        }
        if ($this->config(self::CONFIG_USE_DATABASE)) {
            $this->database = $this->createDatabase();
        }
        if ($this->config(self::CONFIG_USE_VIEW)) {
            $this->view = $this->createView();
        }
    }

    protected function createRouter() {
        return new Router($this);
    }

    protected function createDatabase() {
        return new Database($this);
    }

    protected function createView() {
        return new View($this);
    }

    public function router() {
        return $this->router;
    }

    public function database() {
        return $this->database;
    }

    public function view() {
        return $this->view;
    }

    protected function loadConfig(array &$paths) {
        foreach ($paths as $path) {
            $this->config = array_merge(
                $this->config, parse_ini_file($path)
            );
        }
    }

    public function run() {
        $found = $this->router->matchCurrentRoute();
        if ($found[0]) {
            list($callable, $params) = $found;
            $content = call_user_func_array($callable, $params);
            $this->sendContent($content);
        } else {
            $this->sendError(404, 'Not found.');
        }
    }

    public function cookie(string $name, $default=null) {
        return array_key_exists($name, $_COOKIE) ? $_COOKIE[$name] : $default;
    }

    public function session(string $name, $default=null) {
        return array_key_exists($name, $_SESSION) ? $_SESSION[$name] : $default;
    }

    public function setSession(string $name, $value) {
        $_SESSION[$name] = $value;
    }

    public function request(string $name, $default=null) {
        return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
    }

    public function requestIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function requestMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function requestHeader(string $name) {
        return isset($this->requestHeaders[$name]) ? $this->requestHeaders[$name] : null;
    }

    public function responseHeader(string $name) {
        return isset($this->responseHeaders[$name]) ? $this->responseHeaders[$name] : null;
    }

    public function config(array $params) {
        list($name, $default) = $params;
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    public function route(string $route, $callable, $method='GET') {
        $this->router->add($route, $callable, $method);
    }

    public function redirect($url, $params=[]) {
        $location = $this->router->getUrl($url, $params);
        $this->clearHeaders();
        $this->setHeader('Location', $location);
        $this->send();
        $this->finish();
    }

    protected function sendContent($content) {
        if (is_string($content)) {
            $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
            $this->send($content);
        } else if (is_array($content)) {
            $this->setHeader('Content-Type', 'application/json');
            $this->send(json_encode($content));
        }
    }
    
    public function sendError(int $code, $content='') {
        http_response_code($code);
        $this->finish($content);
    }

    public function finish($content='') {
        exit($content);
    }

    public function clearHeaders() {
        return $this->responseHeaders;
    }

    public function setHeader(string $name, $value) {
        $this->responseHeaders[$name] = $value;
    }

    public function send($content='') {
        foreach ($this->responseHeaders as $name => $value) {
            header($name.': '.$value);
        }
        echo $content;        
    }

}

