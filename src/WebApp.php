<?php

namespace Dynart\Micro;
use Dynart\Micro\Annotation\RouteAnnotation;
use Dynart\Micro\Middleware\AnnotationProcessor;

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
        Micro::add(Request::class);
        Micro::add(Response::class);
        Micro::add(Router::class);
        Micro::add(Session::class);
        Micro::add(View::class);
    }

    public function init() {
        $this->router = Micro::get(Router::class);
        $this->response = Micro::get(Response::class);
    }

    public function process() {
        list($callable, $params) = $this->router->matchCurrentRoute();
        if ($callable) {
            $callable = Micro::getCallable($callable);
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
     * Sends an error response
     * @param int $code The error code
     * @param string $content The error content
     */
    public function sendError(int $code, $content = '') {
        if ($this->isWeb()) { // because of testing in cli
            http_response_code($code);
        }
        $pageContent = str_replace(self::ERROR_CONTENT_PLACEHOLDER, $content, $this->loadErrorPageContent($code));
        $this->finish($pageContent);
    }

    /**
     * Call this if you want to use &#64;route annotations
     */
    public function useRouteAnnotations() {
        $this->addMiddleware(AnnotationProcessor::class);
        Micro::add(RouteAnnotation::class);
        $annotations = Micro::get(AnnotationProcessor::class);
        $annotations->add(RouteAnnotation::class);
    }

    /**
     * If it exists, loads the content of an error HTML page otherwise
     * returns the HTML comment for the error placeholder
     *
     * @param int $code The HTTP status code for the error
     * @return string The content of the HTML file or the HTML comment for the error placeholder
     */
    protected function loadErrorPageContent(int $code): string {
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
     * Returns true if the call is from the web
     * @return bool
     */
    protected function isWeb() {
        return http_response_code() !== false;
    }

    /**
     * Handles the exception
     *
     * Calls the parent exception handler, then calls the sendError with HTTP error 500.
     * Sets the content for the error placeholder if the environment is not production.
     *
     * @param \Exception $e The exception for handling
     */
    protected function handleException(\Exception $e): void {
        parent::handleException($e);
        $env = $this->config->get(App::CONFIG_ENVIRONMENT, App::PRODUCTION_ENVIRONMENT);
        if ($env != App::PRODUCTION_ENVIRONMENT) {
            $type = get_class($e);
            $file = $e->getFile();
            $line = $e->getLine();
            $message = $e->getMessage();
            $trace = $e->getTraceAsString();
            $content = "<h2>$type</h2>\n<p>In <b>$file</b> on <b>line $line</b> with message: $message</p>\n";
            $content .= "<h3>Stacktrace:</h3>\n<p>".str_replace("\n", "<br>\n", $trace)."</p>";
        } else {
            $content = '';
        }
        $this->sendError(500, $content);
    }

}