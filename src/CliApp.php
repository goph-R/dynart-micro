<?php

namespace Dynart\Micro;

/**
 * Handles CLI commands
 * @package Dynart\Micro
 */
class CliApp extends App {

    /** @var CliCommands */
    protected $commands;

    /** @var CliOutput */
    protected $output;

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(CliCommands::class);
        Micro::add(CliOutput::class);
    }

    public function init() {
        $this->commands = Micro::get(CliCommands::class);
    }

    public function process() {
        list($callable, $params) = $this->commands->matchCurrent();
        if ($callable) {
            $callable = Micro::getCallable($callable);
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