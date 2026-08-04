<?php

namespace Dynart\Micro;

class Translation implements TranslationInterface {

    /** The configuration name of all the known translation */
    const CONFIG_ALL = 'translation.all';

    /** The configuration name of the default translation */
    const CONFIG_DEFAULT = 'translation.default';

    /** The default locale */
    const DEFAULT_LOCALE = 'en';

    /**
     * The namespace of the texts the framework ships
     *
     * Registered automatically, so `Form`'s built in messages can actually be translated. An
     * application overrides them by calling `add('micro', $itsOwnFolder)`, which replaces the
     * folder for the namespace.
     */
    const NAMESPACE_MICRO = 'micro';

    /** The folders for all the translations in [namespace => path] format */
    protected array $folders = [];

    /** The loaded translations in [namespace => [id => text]] format */
    protected array $data = [];

    /** All the known translations */
    protected array $allLocales = [];

    /** Has a multi locale config? */
    protected bool $hasMultiLocales = false;

    /** The current locale */
    protected string $locale = 'en';

    protected ConfigInterface $config;

    /**
     * Sets the `$locale`, the `$allLocales` and `$hasMultiLocales` members via the `$config`
     */
    public function __construct(ConfigInterface $config) {
        $this->config = $config;
        $this->locale = $config->get(self::CONFIG_DEFAULT, self::DEFAULT_LOCALE);
        $this->allLocales = $config->getCommaSeparatedValues(self::CONFIG_ALL);
        $this->hasMultiLocales = count($this->allLocales) > 1;
        $this->add(self::NAMESPACE_MICRO, dirname(__FILE__).'/../translations');
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
