<?php

namespace Dynart\Micro;

/**
 * Handles HTTP request/response
 * @package Dynart\Micro
 */
class WebApp extends App {

    const CONFIG_ERROR_PAGES_FOLDER = 'app.error_pages_folder';
    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_LOCATION = 'Location';
    const CONTENT_TYPE_HTML = 'text/html; charset=UTF-8';
    const CONTENT_TYPE_JSON = 'application/json';
    const ERROR_CONTENT_PLACEHOLDER = '<!-- content -->';

    /** @var Router */
    protected $router;

    /** @var Response */
    protected $response;

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(Request::class);
        $this->add(Response::class);
        $this->add(Router::class);
        $this->add(Session::class);
        $this->add(View::class);
    }

    public function init() {
        $this->router = $this->get(Router::class);
        $this->response = $this->get(Response::class);
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
            $this->sendError(404);
        }
    }

    public function redirect($location, $params = []) {
        $url = substr($location, 0, 4) == 'http' ? $location : $this->router->url($location, $params);
        $this->response->clearHeaders();
        $this->response->setHeader(self::HEADER_LOCATION, $url);
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

    /**
     * Returns true if the call happened in the command line interface
     * @return bool
     */
    protected function isCli() {
        return http_response_code() === false;
    }

    protected function handleException(\Exception $e) {
        parent::handleException($e);
        if ($this->isCli()) {
            $this->finish();
        }
        $type = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();
        $message = $e->getMessage();
        $trace = $e->getTraceAsString();
        $content = "<h2>$type</h2>\n<p>In <b>$file</b> on <b>line $line</b> with message: $message</p>\n";
        $content .= "<h3>Stacktrace:</h3>\n<p>".str_replace("\n", "<br>\n", $trace)."</p>";
        $env = $this->config->get(self::CONFIG_ENVIRONMENT, self::DEFAULT_ENVIRONMENT);
        $this->sendError(500, $env != self::DEFAULT_ENVIRONMENT ? $content : '');
    }

}