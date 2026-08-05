<?php

namespace Dynart\Micro;

use Katzgrau\KLogger\Logger as KLogger;

class Logger extends KLogger implements LoggerInterface {

    const CONFIG_DIR = 'log.dir';
    const DEFAULT_DIR = 'logs';

    const CONFIG_LEVEL = 'log.level';
    const DEFAULT_LEVEL = 'error';

    const CONFIG_OPTIONS = 'log.options';
    const DEFAULT_OPTIONS = [];

    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';

    private string $level;

    /**
     * @param ConfigInterface $config `log.dir`, `log.level`, `log.options`
     *
     * The directory goes through `getFullPath()`, so `~/logs` means the application's root and
     * not a folder called `~`. Without that the only way to write outside the working directory
     * was an absolute path, and the working directory of a web request is wherever the entry
     * script lives - which is inside the document root, where a log file is a URL.
     *
     * `static::` rather than `self::` for the defaults, so a subclass can choose safer ones.
     */
    public function __construct(ConfigInterface $config) {
        parent::__construct(
            $config->getFullPath($config->get(static::CONFIG_DIR, static::DEFAULT_DIR)),
            $config->get(static::CONFIG_LEVEL, static::DEFAULT_LEVEL),
            $config->getArray(static::CONFIG_OPTIONS, static::DEFAULT_OPTIONS)
        );
        $this->level = $config->get(static::CONFIG_LEVEL, static::DEFAULT_LEVEL);
    }

    public function level(): string {
        return $this->level;
    }

    public function error($message, array $context = array()) {
        parent::error($message, $context);
        error_log($message); // always log errors
    }
}