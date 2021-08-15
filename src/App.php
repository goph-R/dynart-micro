<?php

namespace Dynart\Micro;

class App {

    const CONFIG_BASE_URL = 'app.base_url';
    const CONFIG_SALT = 'app.salt';
    const CONFIG_ROUTE_PARAMETER = ['app.route_parameter', 'route'];
    const CONFIG_USE_SESSION = ['app.use_session', true];
    const CONFIG_USE_REWRITE = ['app.use_rewrite', true];
    const CONFIG_INDEX_FILE = ['app.index_file', 'index.php'];
    const CONFIG_VIEWS_FOLDER = ['app.views_folder', 'views'];

    const ROUTE_NOT_FOUND = [null, null];

    protected $config = [];
    protected $headers = [];
    protected $routes = [];

    protected $view;
    protected $database;

    public function __construct(array $configPaths) {
        $this->loadConfig($configPaths);
        if ($this->config(self::CONFIG_USE_SESSION)) {
            session_start();
        }
        $this->view = $this->createView();
        $this->database = $this->createDatabase();
    }

    protected function createView() {
        return new View($this);
    }

    protected function createDatabase() {
        return new Database($this);
    }

    public function view() {
        return $this->view;
    }

    public function database() {
        return $this->database;
    }

    protected function loadConfig(array &$paths) {
        foreach ($paths as $path) {
            $this->config = array_merge(
                $this->config, parse_ini_file($path)
            );
        }
    }

    public function run() {
        $found = $this->matchCurrentRoute();
        if ($found[0]) {
            list($callable, $params) = $found;
            $content = call_user_func_array($callable, $params);
            $this->sendContent($content);
        } else {
            $this->error(404);
        }
    }    

    public function getCurrentRoute() {
        $routeParameter = $this->config(self::CONFIG_ROUTE_PARAMETER);
        return $this->request($routeParameter, '/');
    }

    protected function matchCurrentRoute() {
        $currentParts = explode('/', $this->getCurrentRoute());
        $currentPartsCount = count($currentParts);
        $found = self::ROUTE_NOT_FOUND;
        foreach ($this->routes as $route => $callable) {
            $found = $this->findRoute($route, $callable, $currentParts, $currentPartsCount);
            if ($found[0]) {
                break;
            }
        }
        return $found;
    }

    protected function findRoute(string $route, &$callable, array &$currentParts, int $currentPartsCount) {
        $parts = explode('/', $route);
        if (count($parts) != $currentPartsCount) {
            return self::ROUTE_NOT_FOUND;
        }
        $found = true;
        $params = [$this];
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

    public function cookie(string $name, $default=null) {
        return array_key_exists($name, $_COOKIE) ? $_COOKIE[$cookie] : $default;
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

    public function requestPost() {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    public function header(string $name) {
        $headers = getallheaders();
        return isset($headers[$name]) ? $headers[$name] : null;
    }

    public function config($name, $default=null) {
        if (is_array($name)) {
            $default = $name[1];
            $name = $name[0];
        }
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    public function route(string $route, $callable) {
        $this->routes[$route] = $callable;
    }

    public function redirect($url) {
        $this->setHeader('Location', $url);
        $this->finish();
    }

    protected function sendContent(&$content) {
        if (is_string($content)) {
            $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
            $this->respond($content);
        } else if (is_array($content)) {
            $this->setHeader('Content-Type', 'application/json');
            $this->respond(json_encode($content));
        }
    }
    
    public function error(int $code, $content='') {
        http_response_code($code);
        $this->finish($content);
    }

    public function finish($content='') {
        exit($content);
    }

    public function setHeader(string $name, $value=null) {
        if ($value === null) {
            return isset($this->headers[$name]) ? $this->headers[$name] : null;
        }
        $this->headers[$name] = $value;
    }

    public function respond($content='') {
        foreach ($this->headers as $name => $value) {
            header($name, $value);
        }
        echo $content;        
    }
}

