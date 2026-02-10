<?php

namespace Dynart\Micro;

/**
 * Handles CLI commands
 * @package Dynart\Micro
 */
class CliApp extends App {

    const EVENT_COMMAND_MATCHED = 'cliapp.command_matched';

    protected CliCommandsInterface $commands;

    protected CliOutputInterface $output;

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(CliCommandsInterface::class, CliCommands::class);
        Micro::add(CliOutputInterface::class, CliOutput::class);
    }

    public function init(): void {
        $this->commands = Micro::get(CliCommandsInterface::class);
    }

    public function process(): void {
        list($callable, $params) = $this->commands->matchCurrent();
        if ($callable) {
            $callable = Micro::getCallable($callable);
            Micro::get(EventServiceInterface::class)->emit(self::EVENT_COMMAND_MATCHED, [$callable, $params]);
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

    protected function handleException(\Exception $e): void {
        parent::handleException($e);
        $this->finish(1);
    }
}