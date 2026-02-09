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

    protected array $middlewares = [];
    protected ?Config $config = null;
    protected ?Logger $logger = null;
    /** @var string[] */
    protected array $configPaths;
    protected bool $exitOnFinish = true;

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        Micro::add(Config::class);
        Micro::add(Logger::class);
    }

    abstract public function init(): void;

    abstract public function process(): void;

    public function fullInit(): void {
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

    public function fullProcess(): void {
        try {
            $this->process();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function addMiddleware(string $interface): void {
        if (!in_array($interface, $this->middlewares)) {
            Micro::add($interface);
            $this->middlewares[] = $interface;
        }
    }

    protected function runMiddlewares(): void {
        foreach ($this->middlewares as $m) {
            Micro::get($m)->run();
        }
    }

    public function finish(string|int $content = 0): void {
        $this->exitOnFinish ? exit($content) : print($content);
    }

    protected function loadConfigs(): void {
        foreach ($this->configPaths as $path) {
            $this->config->load($path);
        }
    }

    /**
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
