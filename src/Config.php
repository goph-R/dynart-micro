<?php

namespace Dynart\Micro;

class Config {

    private $config = [];
    private $cached = [];

    public function __construct() {}

    public function load(string $path) {
        $this->config = array_merge($this->config, parse_ini_file($path, true));
    }

    public function get($name, $default = null, $useCache = true) {
        if ($useCache && array_key_exists($name, $this->cached)) {
            return $this->cached[$name.':'.$default];
        }
        $value = array_key_exists($name, $this->config) ? $this->config[$name] : $default;
        $matches = [];
        preg_match_all('/{{\s*(\w+)\s*}}/', $value, $matches);
        if ($matches) {
            $vars = array_unique($matches[1]);
            foreach ($vars as $var) {
                $value = str_replace('{{'.$var.'}}', getenv($var), $value);
            }
        }
        if ($useCache) {
            $this->cached[$name.':'.$default] = $value;
        }
        return $value;
    }

    public function getArray($prefix, $default = []) {
        $defaultsKey = join(':', $default);
        if (array_key_exists($prefix.':'.$defaultsKey, $this->cached)) {
            return $this->cached[$prefix];
        }
        $result = $default;
        $len = strlen($prefix);
        foreach ($this->config as $key => $value) {
            if (substr($key, 0, $len) == $prefix) {
                $resultKey = substr($key, $len + 1, strlen($key));
                $result[$resultKey] = $this->get($key, null, false);
            }
        }
        $this->cached[$prefix.':'.$defaultsKey] = $result;
        return $result;
    }

}
