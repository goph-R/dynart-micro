<?php

namespace Dynart\Micro;

class Config implements ConfigInterface {

    private array $config = [];
    private array $cached = [];

    /** Comma separated settings, as lists. Kept apart from `cached`: same name, different type. */
    private array $cachedValues = [];

    public function __construct() {}

    public function load(string $path): void {
        $this->config = array_merge($this->config, parse_ini_file($path, false, INI_SCANNER_TYPED));
    }

    public function clearCache(): void {
        $this->cached = [];
        $this->cachedValues = [];
    }

    public function get(string $name, mixed $default = null, bool $useCache = true): mixed {
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
     * A comma separated setting, as a list
     *
     * **Cached apart from the raw values.** It used to write its list into the same cache that
     * `get()` reads, under the same name: the first call left an array where the string had
     * been, so the second call exploded an array and fataled, and any `get()` of that name in
     * between silently returned a list. It took a caller asking for the same setting twice in
     * one request to find it.
     *
     * A missing or empty setting is an empty list rather than a list holding one empty string,
     * which was never anything a caller wanted to iterate.
     */
    public function getCommaSeparatedValues(string $name, bool $useCache = true): array {
        if ($useCache && array_key_exists($name, $this->cachedValues)) {
            return $this->cachedValues[$name];
        }
        $value = trim((string)$this->get($name, '', $useCache));
        $result = $value === '' ? [] : array_map([$this, 'getArrayItemValue'], explode(',', $value));
        if ($useCache) {
            $this->cachedValues[$name] = $result;
        }
        return $result;
    }

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

    public function isCached(string $name): bool {
        return array_key_exists($name, $this->cached);
    }

    public function getFullPath(string $path): string {
        if (str_starts_with($path, '~')) {
            return $this->get(AbstractApp::CONFIG_ROOT_PATH) . substr($path, 1);
        }
        return $path;
    }

    public function rootPath(): string {
        return $this->get(AbstractApp::CONFIG_ROOT_PATH, '');
    }

    /**
     * Trims and replaces variables to environment variable values in a string
     *
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
     *
     * @param string|null $name The config name
     * @param mixed $value The value
     * @param bool $useCache Use the cache?
     * @return mixed
     */
    protected function cacheAndReturn(?string $name, mixed $value, bool $useCache = true): mixed {
        if ($useCache) {
            $this->cached[$name] = $value;
        }
        return $value;
    }

    /**
     * Replaces the {{name}} formatted variables in a string with environment variable values
     *
     * @param mixed $value The value
     * @return mixed The value or the replaced string
     */
    protected function replaceEnvValue(mixed $value): mixed {
        if (!is_string($value)) {
            return $value; // don't touch non-strings
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
