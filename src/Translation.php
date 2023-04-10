<?php

namespace Dynart\Micro;

/**
 * Handles static text translations
 *
 * You can set the current locale, load the translation for the current locale and get text
 * for the current locale with the help of this class.
 *
 * Related configuration values:
 * * app.root_path - for the ~ symbol in the translations' folder path
 * * translation.all - the all known translation locales seperated with commas, for example: "hu, en"
 * * translation.default - if no locale was set this will be the default, for example: "en"
 *
 * @see Config
 * @package Dynart\Micro
 */
class Translation {

    /**
     * The configuration name of all of the known translation
     */
    const CONFIG_ALL = 'translation.all';

    /**
     * The configuration name of the default translation
     */
    const CONFIG_DEFAULT = 'translation.default';

    /**
     * The default locale
     */
    const DEFAULT_LOCALE = 'en';

    /**
     * The folders for all of the translations in [namespace => path] format
     * @var array
     */
    protected $folders = [];

    /**
     * The loaded translations in [namespace => [id => text]] format
     * @var array
     */
    protected $data = [];

    /**
     * All of the known translations
     * @var array
     */
    protected $allLocales = [];

    /**
     * Is it has a multi locale config?
     * @var bool
     */
    protected $hasMultiLocales = false;

    /**
     * The current locale
     * @var string
     */
    protected $locale = 'en';

    /**
     * The `app.root_path` configuration value
     * @var string
     */
    protected $rootPath;

    /**
     * Sets the `$rootPath`, the `$locale`, the `$allLocales` and `$hasMultiLocales` members via the `$config`
     * @param Config $config
     */
    public function __construct(Config $config) {
        $this->rootPath = $config->get('app.root_path');
        $this->locale = $config->get(self::CONFIG_DEFAULT, self::DEFAULT_LOCALE);
        $this->allLocales = $config->getCommaSeparatedValues(self::CONFIG_ALL);
        $this->hasMultiLocales = count($this->allLocales) > 1;
    }

    /**
     * Adds a folder path for a namespace
     * @param string $namespace The name of the namespace
     * @param string $folder The folder path for the namespace, it can contain a ~ symbol for the `app.root_path`
     */
    public function add(string $namespace, string $folder): void {
        $this->data[$namespace] = null;
        $this->folders[$namespace] = $folder;
    }

    /**
     * Returns with all of the known locales
     * @return array
     */
    public function allLocales(): array {
        return $this->allLocales;
    }

    /**
     * Does the application has multi locales?
     * @return bool True if the application has multiple known locales
     */
    public function hasMultiLocales(): bool {
        return $this->hasMultiLocales;
    }

    /**
     * Returns with the current locale
     * @return string The current locale
     */
    public function locale(): string {
        return $this->locale;
    }

    /**
     * Sets the current locale
     * @param string $locale The current locale
     */
    public function setLocale(string $locale): void {
        $this->locale = $locale;
    }

    /**
     * Returns with the text by namespace and text id for the current locale
     *
     * For translation you have to have multi locale config within your config.ini.php, for example:
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
     * If the translation doesn't exists, the result will be the `$id` between # symbols:
     *
     * <pre>
     * #test:welcome#
     * </pre>
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
            $path = str_replace('~', $this->rootPath, $this->folders[$namespace].'/'.$this->locale.'.ini');
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
