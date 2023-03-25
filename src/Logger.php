<?php

namespace Dynart\Micro;

use Katzgrau\KLogger\Logger as KLogger;

class Logger extends KLogger {

    public function __construct(Config $config) {
        parent::__construct($config->get('log.dir'), $config->get('log.level'), $config->getArray('log.options'));
        $logDir = $config->get('log.dir');
        if (!file_exists($logDir)) {
            mkdir($logDir, 0x755);
        }
    }

}