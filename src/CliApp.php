<?php

namespace Dynart\Micro;

/**
 * Handles CLI commands
 * @package Dynart\Micro
 */
class CliApp extends App {

    /** @var CliCommands */
    protected $commands;

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(CliCommands::class);
    }

    public function init() {
        $this->commands = $this->get(CliCommands::class);
    }

    public function process() {
        list($callable, $params) = $this->commands->matchCurrent();
        if ($callable) {
            if (is_array($callable) && is_string($callable[0])) {
                $callable = [$this->get($callable[0]), $callable[1]];
            }
            if (empty($params)) {
                $content = call_user_func($callable);
            } else {
                $content = call_user_func_array($callable, [$params]);
            }
            $this->finish($content);
        } else {
            error_log("Unknown command: ".$this->commands->current());
            $this->finish(1);
        }
    }

    protected function handleException(\Exception $e) {
        parent::handleException($e);
        $this->finish(1);
    }
}