<?php

namespace Dynart\Micro;

class Config {

    private $config = [];
    private $cached = [];

    public function __construct() {}

    public function load(string $path) {
        $this->config = array_merge($this->config, parse_ini_file($path, false, INI_SCANNER_TYPED));
    }

    public function get($name, $default = null, $useCache = true) {
        if ($useCache && array_key_exists($name, $this->cached)) {
            return $this->cached[$name];
        }
        if (getenv($name) !== false) {
            $value = getenv($name);
        } else {
            $value = array_key_exists($name, $this->config) ? $this->config[$name] : $default;
            if ($value !== null) {
                $matches = [];
                preg_match_all('/{{\s*(\w+)\s*}}/', $value, $matches);
                if ($matches) {
                    $vars = array_unique($matches[1]);
                    foreach ($vars as $var) {
                        $value = str_replace('{{' . $var . '}}', getenv($var), $value);
                    }
                }
            }
        }
        if ($useCache) {
            $this->cached[$name] = $value;
        }
        return $value;
    }

    public function getCommaSeparatedValues($name) {
        $values = explode(',', $this->get($name));
        return array_map(function ($e) { return trim($e); }, $values);
    }

    public function getArray($prefix, $default = []) {
        global $_ENV;
        if (array_key_exists($prefix, $this->cached)) {
            return $this->cached[$prefix];
        }
        $result = $default;
        $len = strlen($prefix);
        $keys = array_merge(array_keys($this->config), array_keys($_ENV));
        foreach ($keys as $key) {
            if (substr($key, 0, $len) == $prefix) {
                $resultKey = substr($key, $len + 1, strlen($key));
                $result[$resultKey] = $this->get($key, null, false);
            }
        }
        $this->cached[$prefix] = $result;
        return $result;
    }

    public function isCached(string $name) {
        return array_key_exists($name, $this->cached);
    }

}
