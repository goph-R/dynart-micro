<?php

namespace Dynart\Micro;

class Config {

    private $config = [];

    public function __construct() {}

    public function load(string $path) {
        $this->config = array_merge($this->config, parse_ini_file($path, true));
    }

    public function get($name, $default=null) {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

}
