<?php

namespace Dynart\Micro;

class Logger extends \Katzgrau\KLogger\Logger {

    public function __construct(Config $config) {// string $logDirectory, string $logLevelThreshold = \Psr\Log\LogLevel::DEBUG, array $options = array()) {
        parent::__construct($config->get('log.dir'), $config->get('log.level'));
        $logDir = $config->get('log.dir');
        if (!file_exists($logDir)) {
            mkdir($logDir, 0x755);
        }
    }

}