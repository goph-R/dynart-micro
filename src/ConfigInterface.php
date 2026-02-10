<?php

namespace Dynart\Micro;

/**
 * Config handler
 *
 * Loads INI files, caches the retrieved values
 */
interface ConfigInterface {
    /**
     * Loads an INI file and merges with current config
     *
     * It does NOT process sections! The "true", "false", "no", "yes", "on", "off" values
     * will be replaced with true and false values.
     *
     * @param string $path The path of the INI file
     */
    public function load(string $path): void;

    /**
     * Clears the in-memory cache
     */
    public function clearCache(): void;

    /**
     * Returns with value from the configuration
     *
     * @param string $name The config name
     * @param mixed $default The return value if the name does not exist in the configuration
     * @param bool $useCache Use the cache for retrieving the value?
     */
    public function get(string $name, mixed $default = null, bool $useCache = true): mixed;

    /**
     * Returns with an array from a comma separated string config value
     *
     * For example: "1, 2, 3" will result in ['1', '2', '3']
     *
     * @param string $name The config name
     * @param bool $useCache Use the cache for retrieving the value?
     */
    public function getCommaSeparatedValues(string $name, bool $useCache = true): array;

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
     */
    public function getArray(string $prefix, array $default = [], bool $useCache = true): array;

    /**
     * Returns true if the config value is cached
     *
     * @param string $name Name of the config
     * @return bool Is the config value cached?
     */
    public function isCached(string $name): bool;

    /**
     * Replaces the ~ symbol at the beginning of the path
     * with the `app.root_path` config value
     */
    public function getFullPath(string $path): string;
}
