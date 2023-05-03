<?php

namespace Dynart\Micro;

class CliCommands {

    /** @var array */
    protected $commands = [];

    public function add(string $name, $callable, array $paramNames = [], array $flagNames = []) {
        $this->commands[$name] = [$callable, $paramNames, $flagNames];
    }

    public function current() {
        return isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null;
    }

    public function matchCurrent() {
        if (!isset($this->commands[$this->current()])) {
            return null;
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