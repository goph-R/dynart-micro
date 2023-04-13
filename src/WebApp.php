<?php

namespace Dynart\Micro;

/**
 * Handles HTTP request/response
 * @package Dynart\Micro
 */
class WebApp extends App {

    const CONFIG_ERROR_PAGES_FOLDER = 'app.error_pages_folder';
    const CONFIG_ENVIRONMENT = 'app.environment';
    const DEFAULT_ENVIRONMENT = 'prod';
    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_LOCATION = 'Location';
    const CONTENT_TYPE_HTML = 'text/html; charset=UTF-8';
    const CONTENT_TYPE_JSON = 'application/json';
    const ERROR_CONTENT_PLACEHOLDER = '<!-- content -->';

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var Router */
    protected $router;

    /** @var Response */
    protected $response;

    /** @var string[] */
    protected $configPaths;

    /** @var Middleware[] */
    protected $middlewares = [];

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        $this->add(Config::class);
        $this->add(Logger::class);
        $this->add(Request::class);
        $this->add(Response::class);
        $this->add(Router::class);
        $this->add(Session::class);
        $this->add(View::class);
    }

    public function addMiddleware(string $interface) {
        $this->add($interface);
        $this->middlewares[] = $interface;
    }

    public function init() {
        try {
            $this->config = $this->get(Config::class);
            $this->loadConfigs();
            $this->logger = $this->get(Logger::class);
            $this->router = $this->get(Router::class);
            $this->response = $this->get(Response::class);
            $this->runMiddlewares();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    public function process() {
        try {
            list($callable, $params) = $this->router->matchCurrentRoute();
            if ($callable) {
                if (is_array($callable) && is_string($callable[0])) {
                    $callable = [$this->get($callable[0]), $callable[1]];
                }
                $content = call_user_func_array($callable, $params);
                $this->sendContent($content);
            } else {
                $this->sendError(404);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    public function redirect($url, $params = []) {
        $location = $this->router->url($url, $params);
        $this->response->clearHeaders();
        $this->response->setHeader(self::HEADER_LOCATION, $location);
        $this->response->send();
        $this->finish();
    }

    public function sendContent($content) {
        if (is_string($content)) {
            $this->response->setHeader(self::HEADER_CONTENT_TYPE, self::CONTENT_TYPE_HTML);
            $this->response->send($content);
        } else if (is_array($content)) {
            $this->response->setHeader(self::HEADER_CONTENT_TYPE, self::CONTENT_TYPE_JSON);
            $this->response->send(json_encode($content));
        }
    }

    /**
     * Sends an error as the response
     * @param int $code The error code
     * @param string $content The error content
     */
    public function sendError(int $code, $content = '') {
        http_response_code($code);
        $pageContent = str_replace(self::ERROR_CONTENT_PLACEHOLDER, $content, $this->loadErrorPageContent($code));
        $this->finish($pageContent);
    }

    protected function loadConfigs() {
        foreach ($this->configPaths as $path) {
            $this->config->load($path);
        }
    }

    protected function runMiddlewares() {
        foreach ($this->middlewares as $m) {
            $this->get($m)->run();
        }
    }

    protected function loadErrorPageContent(int $code) {
        $dir = $this->config->get(self::CONFIG_ERROR_PAGES_FOLDER);
        if ($dir) {
            $path = $this->config->getFullPath($dir.'/'.$code.'.html');
            if (file_exists($path)) {
                return file_get_contents($path);
            }
        }
        return self::ERROR_CONTENT_PLACEHOLDER;
    }

    protected function handleException(\Exception $e) {
        $type = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();
        $message = $e->getMessage();
        $trace = $e->getTraceAsString();
        $text = "`$type` in $file on line $line with message: $message\n$trace";
        $this->logger->error($text);
        if (http_response_code() === false) { // cli
            $this->finish();
        }
        if (!$this->config) {
            throw new AppException("Couldn't instantiate Config::class");
        }
        if (!$this->logger) {
            throw new AppException("Couldn't instantiate Logger::class");
        }
        $content = "<h2>$type</h2>\n<p>In <b>$file</b> on <b>line $line</b> with message: $message</p>\n";
        $content .= "<h3>Stacktrace:</h3>\n<p>".str_replace("\n", "<br>\n", $trace)."</p>";
        $env = $this->config->get(self::CONFIG_ENVIRONMENT, self::DEFAULT_ENVIRONMENT);
        $this->sendError(500, $env != self::DEFAULT_ENVIRONMENT ? $content : '');
    }

}