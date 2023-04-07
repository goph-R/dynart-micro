<?php

namespace Dynart\Micro;

class Translation {

    const CONFIG_ALL = 'translation.all';
    const CONFIG_DEFAULT = 'translation.default';

    const DEFAULT_LOCALE = 'en';

    protected $folders;
    protected $data;
    protected $allLocales;
    protected $hasMultiLocales;
    protected $locale;
    protected $rootPath;

    public function __construct(Config $config) {
        $this->rootPath = $config->get('app.root_path');
        $this->locale = $config->get(self::CONFIG_DEFAULT, self::DEFAULT_LOCALE);
        $this->allLocales = $config->getCommaSeparatedValues(self::CONFIG_ALL);
        $this->hasMultiLocales = count($this->allLocales) > 1;
    }

    public function add(string $namespace, string $folder) {
        $this->data[$namespace] = null;
        $this->folders[$namespace] = $folder;
    }

    public function allLocales() {
        return $this->allLocales;
    }

    public function hasMultiLocales() {
        return $this->hasMultiLocales;
    }

    public function locale() {
        return $this->locale;
    }

    public function setLocale(string $locale) {
        $this->locale = $locale;
    }

    public function get($id, array $params = []) {
        $dotPos = strpos($id, ':');
        $namespace = substr($id, 0, $dotPos);
        $name = substr($id, $dotPos + 1);
        $result = '#'.$id.'#';
        if (!isset($this->folders[$namespace])) {
            return $result;
        }
        if (!isset($this->data[$namespace])) {
            $path = $this->rootPath.$this->folders[$namespace].'/'.$this->locale.'.ini';
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
