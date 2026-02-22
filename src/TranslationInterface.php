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
interface TranslationInterface {
    /**
     * Adds a folder path for a namespace
     */
    public function add(string $namespace, string $folder): void;

    /**
     * Returns with all the known locales
     */
    public function allLocales(): array;

    /**
     * Does the application has multi locales?
     */
    public function hasMultiLocales(): bool;

    /**
     * Returns with the current locale
     */
    public function locale(): string;

    /**
     * Sets the current locale
     */
    public function setLocale(string $locale): void;

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
     * class App extends AbstractApp {
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
    public function get(string $id, array $params = []): string;
}
