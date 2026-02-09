<?php

namespace Dynart\Micro;

/**
 * Handles static text translations
 *
 * You can set the current locale, load the translation for the current locale and get text
 * for the current locale with the help of this class.
 *
 * Related configuration values:
 * * app.root_path - used for the `~` symbol in the translations' folder path
 * * translation.all - the all known translation locales seperated with commas, for example: `hu, en`
 * * translation.default - if no locale was set this will be the default, for example: `en`
 *
 * @see Config
 */
class Translation {

    /** The configuration name of all the known translation */
    const CONFIG_ALL = 'translation.all';

    /** The configuration name of the default translation */
    const CONFIG_DEFAULT = 'translation.default';

    /** The default locale */
    const DEFAULT_LOCALE = 'en';

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

    protected Config $config;

    /**
     * Sets the `$locale`, the `$allLocales` and `$hasMultiLocales` members via the `$config`
     */
    public function __construct(Config $config) {
        $this->config = $config;
        $this->locale = $config->get(self::CONFIG_DEFAULT, self::DEFAULT_LOCALE);
        $this->allLocales = $config->getCommaSeparatedValues(self::CONFIG_ALL);
        $this->hasMultiLocales = count($this->allLocales) > 1;
    }

    /**
     * Adds a folder path for a namespace
     */
    public function add(string $namespace, string $folder): void {
        $this->data[$namespace] = null;
        $this->folders[$namespace] = $folder;
    }

    /**
     * Returns with all the known locales
     */
    public function allLocales(): array {
        return $this->allLocales;
    }

    /**
     * Does the application has multi locales?
     */
    public function hasMultiLocales(): bool {
        return $this->hasMultiLocales;
    }

    /**
     * Returns with the current locale
     */
    public function locale(): string {
        return $this->locale;
    }

    /**
     * Sets the current locale
     */
    public function setLocale(string $locale): void {
        $this->locale = $locale;
    }

    /**
     * Returns with the text by namespace and text id for the current locale
     *
     * You have to have multi locale config within your config.ini.php, for example:
     *
     * <pre>
     * translation.all = en, hu
     * translation.default = en
     * </pre>
     *
     * then you have to add at least one namespace with a folder path for example in your App::init() method:
     *
     * <pre>
     * class MyApp extends App {
     *   // ...
     *   public function init() {
     *     $translation = $this->get(Translation::class);
     *     $translation->addFolder('test', '~/folder/within/the/app/root/folder');
     *   }
     *   // ...
     * }
     * </pre>
     *
     * In the given folder you have to have the files `en.ini` and `hu.ini`. Both of the files have to have the
     * text IDs and the translations. The `en.ini` could look like:
     *
     * <pre>
     * welcome = "Hello {name}!"
     * </pre>
     *
     * and then you can use it in your code:
     *
     * <pre>
     * echo $translation->get('test:welcome', ['name' => 'Joe']);
     * </pre>
     *
     * or in your view with the `tr` helper function:
     *
     * <pre>
     * &lt;?= tr('test:welcome', ['name' => 'Joe']); ?&gt;
     * </pre>
     *
     * the result will be with 'en' current locale:
     *
     * <pre>
     * Hello Joe!
     * </pre>
     *
     * If the translation doesn't exist, the result will be the `$id` between # symbols:
     *
     * <pre>
     * #test:welcome#
     * </pre>
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
