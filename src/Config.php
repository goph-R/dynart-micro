<?php

namespace Dynart\Micro;

/**
 * Config handler
 *
 * Loads INI files, caches the retrieved values
 *
 * @package Dynart\Micro
 */
class Config {

    private $config = [];
    private $cached = [];

    public function __construct() {}

    /**
     * Loads an INI file and merges with current config
     *
     * It does NOT process sections! The "true", "false", "no", "yes", "on", "off" values
     * will be replaced with true and false values.
     *
     * @param string $path The path of the INI file
     */
    public function load(string $path) {
        $this->config = array_merge($this->config, parse_ini_file($path, false, INI_SCANNER_TYPED));
    }

    public function get($name, $default = null, $useCache = true) {
        if ($useCache && array_key_exists($name, $this->cached)) {
            return $this->cached[$name];
        }
        if (getenv($name) !== false) {
            return $this->cacheAndReturn($name, getenv($name), $useCache);
        }
        $value = array_key_exists($name, $this->config) ? $this->config[$name] : $default;
        return $this->cacheAndReturn($name, $this->replaceEnvValue($value), $useCache);
    }

    /**
     * Returns with an array from a comma separated string config value
     *
     * For example: "1, 2, 3" will result in ['1', '2', '3']
     *
     * @param string $name The config name
     * @param bool $useCache Use the cache for retrieving the value?
     * @return array The result in array
     */
    public function getCommaSeparatedValues(string $name, bool $useCache = true): array {
        $values = explode(',', $this->get($name));
        $result = array_map([$this, 'getArrayItemValue'], $values);
        return $this->cacheAndReturn($name, $result, $useCache);
    }

    /**
     * Returns with an array from the config
     *
     * For example: with the following config:
     *
     * <pre>
     * persons.0.name = "name1"
     * persons.0.age = "32"
     * persons.1.name = "name2"
     * persons.1.age = "42"
     * </pre>
     *
     * the result will be for `$config->getArray('persons')`:
     *
     * <pre>
     * [
     *   0 => [
     *      "name" => "name1",
     *      "age" => "32"
     *   ],
     *   1 => [
     *      "name" => "name2",
     *      "age" => "42"
     *   ]
     * ]
     * </pre>
     *
     * @param string $prefix
     * @param array $default
     * @param bool $useCache
     * @return array
     */
    public function getArray(string $prefix, array $default = [], bool $useCache = true): array {
        global $_ENV;
        if ($useCache && array_key_exists($prefix, $this->cached)) {
            return $this->cached[$prefix];
        }
        $result = $default;
        $len = strlen($prefix);
        $keys = array_merge(array_keys($this->config), array_keys($_ENV));
        foreach ($keys as $key) {
            if (substr($key, 0, $len) != $prefix) {
                continue;
            }
            $configKey = substr($key, $len + 1, strlen($key));
            $parts = explode('.', $configKey);
            $current = &$result;
            foreach ($parts as $part) {
                if (ctype_digit($part)) {
                    $part = (int)$part;
                }
                if (!array_key_exists($part, $current)) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
            $current = $this->get($key, null, false);
        }
        return $this->cacheAndReturn($prefix, $result, $useCache);
    }

    /**
     * Returns true if the config value is cached
     * @param string $name Name of the config
     * @return bool Is the config value cached?
     */
    public function isCached(string $name): bool {
        return array_key_exists($name, $this->cached);
    }

    /**
     * Replaces the ~ symbol at the beginning of the path
     * with the `app.root_path` config value
     * @param string $path
     * @return string
     */
    public function getFullPath(string $path): string {
        if (strpos($path, '~') === 0) {
            return $this->get(App::CONFIG_ROOT_PATH) . substr($path, 1);
        }
        return $path;
    }

    /**
     * Trims and replaces variables to environment variable values in a string
     * @param string $value The value for trim and replace
     * @return string The trimmed and replaced value
     */
    protected function getArrayItemValue(string $value): string {
        $result = trim($value);
        $this->replaceEnvValue($result);
        return $result;
    }

    /**
     * Caches a value if the `$useCache` is true and returns with it
     * @param string|null $name The config name
     * @param mixed|null $value The value
     * @param bool $useCache Use the cache?
     * @return mixed|null
     */
    protected function cacheAndReturn(?string $name, $value, bool $useCache = true) {
        if ($useCache) {
            $this->cached[$name] = $value;
        }
        return $value;
    }

    /**
     * Replaces the {{name}} formatted variables in a string with environment variable values
     * @param mixed|null $value The value
     * @return mixed|null The replaced string
     */
    protected function replaceEnvValue($value) {
        if (!is_string($value)) {
            return $value; // don’t touch non-strings
        }
        $matches = [];
        if (!preg_match_all('/{{\s*(\w+)\s*}}/', $value, $matches)) {
            return $value;
        }
        $vars = array_unique($matches[1]);
        foreach ($vars as $var) {
            $value = str_replace('{{' . $var . '}}', getenv($var), $value);
        }
        return $value;
    }

}