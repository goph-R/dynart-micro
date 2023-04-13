<?php

namespace Dynart\Micro;

use Katzgrau\KLogger\Logger as KLogger;

class Logger extends KLogger {

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

    /** @var string */
    private $level;

    public function __construct(Config $config) {
        parent::__construct(
            $config->get(self::CONFIG_DIR, self::DEFAULT_DIR),
            $config->get(self::CONFIG_LEVEL, self::DEFAULT_LEVEL),
            $config->getArray(self::CONFIG_OPTIONS, self::DEFAULT_OPTIONS)
        );
        $this->level = $config->get(self::CONFIG_LEVEL, self::DEFAULT_LEVEL);
    }

    public function level(): string {
        return $this->level;
    }

}