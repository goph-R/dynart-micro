<?php

namespace Dynart\Micro;

use Exception;

/**
 * Micro PHP Application
 *
 * @package Dynart\Micro
 */
abstract class App {

    const CONFIG_BASE_URL = 'app.base_url';
    const CONFIG_ROOT_PATH = 'app.root_path';
    const CONFIG_ENVIRONMENT = 'app.environment';
    const PRODUCTION_ENVIRONMENT = 'prod';

    /**
     * Stores the middleware class names in a list
     * @var Middleware[]
     */
    protected $middlewares = [];

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var string[] */
    protected $configPaths;

    /** @var bool */
    protected $exitOnFinish = true;

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        Micro::add(Config::class);
        Micro::add(Logger::class);
    }

    /**
     * Abstract function for initialize the application
     * @return mixed
     */
    abstract public function init();

    /**
     * Abstract function for processing the application
     * @return mixed
     */
    abstract public function process();

    /**
     * Fully initializes the application
     *
     * Creates the `Config`, loads the configs, creates the `Logger`, calls the `init()` method
     * then runs all the middlewares. If an exception happens, handles it with the `handleException()` method.
     */
    public function fullInit() {
        try {
            $this->config = Micro::get(Config::class);
            $this->loadConfigs();
            $this->logger = Micro::get(Logger::class);
            $this->init();
            $this->runMiddlewares();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Calls the `process()` method within a try/catch, handles exception with the `handleException()` method
     */
    public function fullProcess() {
        try {
            $this->process();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Adds a middleware
     *
     * It adds only if not presents.
     *
     * @param string $interface
     */
    public function addMiddleware(string $interface) {
        if (!in_array($interface, $this->middlewares)) {
            Micro::add($interface);
            $this->middlewares[] = $interface;
        }
    }

    /**
     * Runs all the added middlewares
     */
    protected function runMiddlewares() {
        foreach ($this->middlewares as $m) {
            Micro::get($m)->run();
        }
    }

    /**
     * Finishes the application
     *
     * If the `$exitOnFinish` true (default) calls the exit, otherwise just prints out the content.
     *
     * @param string|int $content Content for the output. If it's an int, it is the return code of the process.
     */
    public function finish($content = 0): void {
        $this->exitOnFinish ? exit($content) : print($content);
    }

    /**
     * Loads all the configs by the `$configPaths`
     */
    protected function loadConfigs() {
        foreach ($this->configPaths as $path) {
            $this->config->load($path);
        }
    }

    /**
     * Handles the exception
     *
     * Sends the type, the line, the exception message and the stacktrace to the standard error output.
     * If the `Config` or the `Logger` wasn't initialised throws a `MicroException`.
     *
     * @param Exception $e The exception
     * @throws MicroException
     */
    protected function handleException(Exception $e) {
        $type = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();
        $message = $e->getMessage();
        $trace = $e->getTraceAsString();
        $text = "`$type` in $file on line $line with message: $message\n$trace";
        if (!$this->config) {
            throw new MicroException("Couldn't instantiate Config::class, original exception:\n".$text);
        }
        if (!$this->logger) {
            throw new MicroException("Couldn't instantiate Logger::class, original exception:\n".$text);
        }
        $this->logger->error($text);
    }

}