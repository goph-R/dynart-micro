<?php

namespace Dynart\Micro;

use Exception;

/**
 * Micro PHP Application
 */
abstract class App {

    const CONFIG_BASE_URL = 'app.base_url';
    const CONFIG_ROOT_PATH = 'app.root_path';
    const CONFIG_ENVIRONMENT = 'app.environment';
    const PRODUCTION_ENVIRONMENT = 'prod';
    const EVENT_INIT_FINISHED = 'app.init_finished';

    /** Stores the middleware class names in a list */
    protected array $middlewares = [];
    protected ?ConfigInterface $config = null;
    protected ?LoggerInterface $logger = null;
    /** @var string[] */
    protected array $configPaths;
    protected bool $exitOnFinish = true;

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        Micro::add(ConfigInterface::class, Config::class);
        Micro::add(LoggerInterface::class, Logger::class);
        Micro::add(EventServiceInterface::class, EventService::class);
    }

    /**
     * Abstract function for initialize the application
     */
    abstract public function init(): void;

    /**
     * Abstract function for processing the application
     */
    abstract public function process(): void;

    /**
     * Fully initializes the application
     *
     * Creates the `Config`, loads the configs, creates the `Logger`, calls the `init()` method
     * then runs all the middlewares. If an exception happens, handles it with the `handleException()` method.
     */
    public function fullInit(): void {
        try {
            $this->config = Micro::get(ConfigInterface::class);
            $this->loadConfigs();
            $this->logger = Micro::get(LoggerInterface::class);
            $this->init();
            $this->runMiddlewares();
            Micro::get(EventServiceInterface::class)->emit(self::EVENT_INIT_FINISHED);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Calls the `process()` method within a try/catch, handles exception with the `handleException()` method
     */
    public function fullProcess(): void {
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
     */
    public function addMiddleware(string $interface): void {
        if (!in_array($interface, $this->middlewares)) {
            Micro::add($interface);
            $this->middlewares[] = $interface;
        }
    }

    /**
     * Runs all the added middlewares
     */
    protected function runMiddlewares(): void {
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
    public function finish(string|int $content = 0): void {
        $this->exitOnFinish ? exit($content) : print($content);
    }

    /**
     * Loads all the configs by the `$configPaths`
     */
    protected function loadConfigs(): void {
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
     * @throws MicroException
     */
    protected function handleException(Exception $e): void {
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
