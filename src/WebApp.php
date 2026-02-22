<?php

namespace Dynart\Micro;
use Dynart\Micro\AttributeHandler\AllowAnonymousAttributeHandler;
use Dynart\Micro\AttributeHandler\AuthorizeAttributeHandler;
use Dynart\Micro\AttributeHandler\RouteAttributeHandler;
use Dynart\Micro\Middleware\AttributeProcessor;
use Dynart\Micro\Middleware\JwtValidator;

/**
 * Handles HTTP request/response
 * @package Dynart\Micro
 */
class WebApp extends AbstractApp {

    const CONFIG_ERROR_PAGES_FOLDER = 'app.error_pages_folder';
    const CONFIG_USE_ROUTE_ATTRIBUTES = 'app.use_route_attributes';
    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_LOCATION = 'Location';
    const CONTENT_TYPE_HTML = 'text/html; charset=UTF-8';
    const CONTENT_TYPE_JSON = 'application/json';
    const ERROR_CONTENT_PLACEHOLDER = '<!-- content -->';
    const EVENT_ROUTE_MATCHED = 'webapp.route_matched';

    protected RouterInterface $router;
    protected ResponseInterface $response;

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(RequestInterface::class, Request::class);
        Micro::add(ResponseInterface::class, Response::class);
        Micro::add(RouterInterface::class, Router::class);
        Micro::add(SessionInterface::class, Session::class);
        Micro::add(ViewInterface::class, View::class);
    }

    public function init(): void {
        $this->router = Micro::get(RouterInterface::class);
        $this->response = Micro::get(ResponseInterface::class);
        if ($this->config?->get(self::CONFIG_USE_ROUTE_ATTRIBUTES, true) ?? true) {
            $this->useRouteAttributes();
        }
    }

    public function process(): void {
        list($callable, $params) = $this->router->matchCurrentRoute();
        if ($callable) {
            $callable = Micro::getCallable($callable);
            Micro::get(EventServiceInterface::class)->emit(self::EVENT_ROUTE_MATCHED, [$callable, $params]);
            $content = call_user_func_array($callable, $params);
            $this->sendContent($content);
        } else {
            $this->sendError(404);
        }
    }

    public function redirect(string $location, array $params = []): void {
        $url = str_starts_with($location, 'http') ? $location : $this->router->url($location, $params);
        $this->response->clearHeaders();
        $this->response->setHeader(self::HEADER_LOCATION, $url);
        $this->response->send();
        $this->finish();
    }

    public function sendContent(mixed $content): void {
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
    public function sendError(int $code, string $content = ''): void {
        if ($this->isWeb()) { // because of testing in cli
            http_response_code($code);
        }
        $pageContent = str_replace(self::ERROR_CONTENT_PLACEHOLDER, $content, $this->loadErrorPageContent($code));
        $this->finish($pageContent);
    }

    public function useRouteAttributes(): void {
        $this->addMiddleware(AttributeProcessor::class);
        Micro::add(RouteAttributeHandler::class);
        $processor = Micro::get(AttributeProcessor::class);
        $processor->add(RouteAttributeHandler::class);
    }

    public function useJwtAuth(): void {
        $this->addMiddleware(AttributeProcessor::class);
        $this->addMiddleware(JwtValidator::class);
        Micro::add(JwtAuthInterface::class, JwtAuth::class);
        Micro::add(AuthorizeAttributeHandler::class);
        Micro::add(AllowAnonymousAttributeHandler::class);
        $processor = Micro::get(AttributeProcessor::class);
        $processor->add(AuthorizeAttributeHandler::class);
        $processor->add(AllowAnonymousAttributeHandler::class);
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
    protected function isWeb(): bool {
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
        if ($e instanceof AuthorizationException) {
            $this->sendError($e->getCode());
            return;
        }
        parent::handleException($e);
        $env = $this->config->get(AbstractApp::CONFIG_ENVIRONMENT, AbstractApp::PRODUCTION_ENVIRONMENT);
        if ($env != AbstractApp::PRODUCTION_ENVIRONMENT) {
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