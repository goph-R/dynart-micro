<?php

use Dynart\Micro\App;
use Dynart\Micro\Config;
use Dynart\Micro\I18n\Translation;
use Dynart\Micro\Router;

function base_url() {
    return App::instance()->get(Config::class)->get('app.base_url');
}

function url(string $uri, bool $withMTime = true) {
    $result = base_url().$uri;
    if ($withMTime) {
        $result .= '?'.filemtime(App::instance()->get(Config::class)->get('app.path_root').$uri);
    }
    return $result;
}

function route_url(string $route, array $params = []) {
    return App::instance()->get(Router::class)->url($route, $params, '&amp;');
}

function esc_html($text) {
    return htmlspecialchars($text);
}

function esc_attr($value) {
    return htmlspecialchars($value, ENT_QUOTES);
}

function esc_attrs(array $attributes, $startWithSpace = true) {
    $pairs = [];
    foreach ($attributes as $name => $value) {
        if (is_int($name)) {
            $pairs[] = $value;
        } else if ($value === null) {
            $pairs[] = $name;
        } else {
            $pairs[] = $name.'="'.esc_attr($value).'"';
        }
    }
    $prefix = !empty($pairs) && $startWithSpace ? ' ' : '';
    return $prefix.join(' ', $pairs);
}

function tr($id) {
    App::instance()->get(Translation::class)->get($id);
}