<?php

namespace Dynart\Micro;

/**
 * Handles static text translations
 *
 * @see Config
 */
class Translation {

    const CONFIG_ALL = 'translation.all';
    const CONFIG_DEFAULT = 'translation.default';
    const DEFAULT_LOCALE = 'en';

    protected array $folders = [];
    protected array $data = [];
    protected array $allLocales = [];
    protected bool $hasMultiLocales = false;
    protected string $locale = 'en';
    protected Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->locale = $config->get(self::CONFIG_DEFAULT, self::DEFAULT_LOCALE);
        $this->allLocales = $config->getCommaSeparatedValues(self::CONFIG_ALL);
        $this->hasMultiLocales = count($this->allLocales) > 1;
    }

    public function add(string $namespace, string $folder): void {
        $this->data[$namespace] = null;
        $this->folders[$namespace] = $folder;
    }

    public function allLocales(): array {
        return $this->allLocales;
    }

    public function hasMultiLocales(): bool {
        return $this->hasMultiLocales;
    }

    public function locale(): string {
        return $this->locale;
    }

    public function setLocale(string $locale): void {
        $this->locale = $locale;
    }

    /**
     * Returns with the text by namespace and text id for the current locale
     *
     * @param string $id The id of the translated text in 'namespace:text_id' format
     * @param array $params The parameters for the variables in the text in ['name' => 'value'] format
     * @return string The translated text with replaced variables
     */
    public function get(string $id, array $params = []): string {
        $dotPos = strpos($id, ':');
        $namespace = substr($id, 0, $dotPos);
        $name = substr($id, $dotPos + 1);
        $result = '#'.$id.'#';
        if (!isset($this->folders[$namespace])) {
            return $result;
        }
        if (!isset($this->data[$namespace])) {
            $path = $this->config->getFullPath($this->folders[$namespace].'/'.$this->locale.'.ini');
            $iniData = file_exists($path) ? parse_ini_file($path) : [];
            $this->data[$namespace] = $iniData;
        }
        if (isset($this->data[$namespace][$name])) {
            $result = $this->data[$namespace][$name];
        }
        foreach ($params as $name => $value) {
            $result = str_replace('{' . $name . '}', $value, $result);
        }
        return $result;
    }

}
