<?php

namespace Dynart\Micro;

class CliCommands implements CliCommandsInterface {

    /** Returned by `matchCurrent()` when there is no command with the current name */
    const COMMAND_NOT_FOUND = [null, null];

    protected array $commands = [];

    public function add(string $name, callable|array $callable, array $paramNames = [], array $flagNames = []): void {
        $this->commands[$name] = [$callable, $paramNames, $flagNames];
    }

    public function current(): ?string {
        return $_SERVER['argv'][1] ?? null;
    }

    public function has(string $name): bool {
        return isset($this->commands[$name]);
    }

    /**
     * @return array The [callable, params] pair, or `COMMAND_NOT_FOUND` when there is no such command
     */
    public function matchCurrent(): array {
        if (!isset($this->commands[$this->current()])) {
            return self::COMMAND_NOT_FOUND;
        }
        list($callable, $paramNames, $flagNames) = $this->commands[$this->current()];

        // reset parameters
        $params = [];
        foreach ($paramNames as $name) {
            $params[$name] = '';
        }
        foreach ($flagNames as $name) {
            $params[$name] = false;
        }

        // get parameters
        $currentName = '';
        $currentIndex = 0;
        for ($i = 2; $i < $_SERVER['argc']; $i++) {
            $argument = $_SERVER['argv'][$i];
            if ($argument[0] == '-' && !$currentName) {
                $name = substr($argument, 1);
                if (in_array($name, $paramNames)) {
                    $currentName = $name;
                } else if (in_array($name, $flagNames)) {
                    $params[$name] = true;
                }
            } else if ($currentName) {
                $params[$currentName] = $argument;
                $currentName = '';
            } else {
                $params[$currentIndex] = $argument;
                $currentIndex++;
            }
        }

        return [$callable, $params];
    }

}