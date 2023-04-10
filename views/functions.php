<?php

use Dynart\Micro\App;
use Dynart\Micro\Config;
use Dynart\Micro\Translation;
use Dynart\Micro\Router;

if (!function_exists('base_url')) {
    /**
     * Returns with the `app.base_url` config value
     *
     * @return string The base URL
     */
    function base_url() {
        return App::instance()->get(Config::class)->get('app.base_url');
    }
}

if (!function_exists('url')) {
    /**
     * Returns with a URL for the given URI
     *
     * For example, if your `app.base_url` is http://example.com and the given $uri is "/static/script.js",
     * the result will be: http://example.com/static/script.js?123123123
     *
     * The number at the end is the modification timestamp of the file.
     * If the `$withMTime` parameter is false the result will not contain the question mark and the timestamp.
     *
     * @param string $uri The URI
     * @param bool $withMTime Are you need a modification time at the end?
     * @return string The full URL
     */
    function url(string $uri, bool $withMTime = true) {
        $result = base_url().$uri;
        if ($withMTime) {
            $result .= '?'.filemtime(App::instance()->get(Config::class)->get('app.root_path').$uri);
        }
        return $result;
    }
}

if (!function_exists('route_url')) {
    /**
     * Returns with a URL for the given route
     *
     * Heavily depends on the configuration of the application.
     *
     * For example, if the given `$route` is '/example-route', the `$params` is an associative array ['name' => 'joe'],
     * you have a multi locale config, the `app.use_rewrite` set to true and `app.base_url` is 'http://example.com'
     * then the result will be: 'http://example.com/en/example-route?name=joe'
     *
     * @see \Dynart\Micro\Router::url()
     * @param string $route The route
     * @param array $params The HTTP query parameters for the route
     * @param string $amp The ampersand symbol. The default is '\&amp;' but you can change it to '&' if needed.
     * @return string The full URL for the route
     */
    function route_url(string $route, array $params = [], string $amp = '&amp;') {
        return App::instance()->get(Router::class)->url($route, $params, $amp);
    }
}

if (!function_exists('esc_html')) {
    /**
     * Returns with a safe HTML string
     *
     * For example: if the `$text` is '<script>Evil script</script>'
     * the result will be '\&lt;script\&gt;Evil script\&lt;/script\&gt;'
     *
     * @param string $text The text for escaping
     * @return string The HTML escaped string
     */
    function esc_html($text) {
        return htmlspecialchars($text);
    }
}

if (!function_exists('esc_attr')) {
    /**
     * Returns with a safe HTML attribute value
     *
     * For example: if the `$value` is '"something"' with the double quotes
     * the result will be '\&quot;something\&quot;'
     *
     * @param string $value The value for escaping
     * @return string The safe HTML attribute value
     */
    function esc_attr($value) {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}

if (!function_exists('esc_attrs')) {
    /**
     * Returns with a safe HTML attributes string
     *
     * For example, the following call:
     *
     * <pre>
     * esc_attrs(['name1' => 'value1', 'name2' = '"', 'name3']);
     * </pre>
     *
     * will return with 'name1="value1" name2="\&quot;" name3'
     *
     * @param array $attributes
     * @param bool $startWithSpace should the result start with a space?
     * @return string The HTML attributes string
     */
    function esc_attrs(array $attributes, $startWithSpace = true) {
        $pairs = [];
        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $pairs[] = $value;
            } else {
                $pairs[] = $name.'="'.esc_attr($value).'"';
            }
        }
        $prefix = !empty($pairs) && $startWithSpace ? ' ' : '';
        return $prefix.join(' ', $pairs);
    }
}

if (!function_exists('tr')) {
    /**
     * Returns with a translated text
     *
     * For example: if the application is configured with multi locale in the config.ini.php:
     *
     * <pre>
     * translation.all = en, hu
     * translation.default = en
     * </pre>
     *
     * The current locale is "en" and you added a translation directory with namespace "example"
     * and the directory contains an en.ini with the following content:
     *
     * <pre>
     * hello = "Welcome {name}!"
     * </pre>
     *
     * Calling `tr('example:hello', ['name' => 'Joe'])` will return 'Welcome Joe!'
     *
     * @see \Dynart\Micro\Translation
     * @see \Dynart\Micro\LocaleResolver
     * @param string $id The ID of the text "namespace:text_id"
     * @return string The translated text
     */
    function tr($id) {
        return App::instance()->get(Translation::class)->get($id);
    }
}